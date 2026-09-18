<?php
namespace BBS\Core;

class Database
{
    private ?\PDO $pdo = null;
    private string $driver;
    private string $host;
    private string $dbname;
    private string $user;
    private string $pass;
    private string $prefix = '';
    private array $options = [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_OBJ,
        \PDO::ATTR_EMULATE_PREPARES => false,
    ];

    private static array $connections = [];
    private string $connectionName = 'default';

    public function __construct(
        string $host = 'localhost',
        string $dbname = 'booking',
        string $user = 'root',
        string $pass = '',
        string $driver = 'mysql',
        string $prefix = ''
    ) {
        $this->host = $host;
        $this->dbname = $dbname;
        $this->user = $user;
        $this->pass = $pass;
        $this->driver = $driver;
        $this->prefix = $prefix;
    }

    public function connect(): void
    {
        if ($this->pdo !== null) return;

        try {
            $dsn = match ($this->driver) {
                'mysql' => "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4",
                'sqlite' => "sqlite:{$this->dbname}",
                'pgsql' => "pgsql:host={$this->host};dbname={$this->dbname}",
                default => "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4",
            };

            if ($this->driver === 'sqlite') {
                $dir = dirname($this->dbname);
                if (!is_dir($dir)) mkdir($dir, 0755, true);
            }

            $this->pdo = new \PDO($dsn, $this->user, $this->pass, $this->options);
        } catch (\PDOException $e) {
            throw new \RuntimeException("Database connection failed: " . $e->getMessage());
        }
    }

    public function disconnect(): void
    {
        $this->pdo = null;
    }

    public function pdo(): \PDO
    {
        if ($this->pdo === null) $this->connect();
        return $this->pdo;
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $sql = $this->applyPrefix($sql);
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetch(string $sql, array $params = []): ?\stdClass
    {
        $result = $this->query($sql, $params)->fetch();
        return $result ?: null;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function insert(string $table, array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO {$this->prefix}{$table} ({$columns}) VALUES ({$placeholders})";
        $this->query($sql, array_values($data));
        return (int) $this->pdo()->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $sets = implode(', ', array_map(fn($k) => "$k = ?", array_keys($data)));
        $sql = "UPDATE {$this->prefix}{$table} SET {$sets} WHERE {$where}";
        $stmt = $this->query($sql, array_merge(array_values($data), $whereParams));
        return $stmt->rowCount();
    }

    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = "DELETE FROM {$this->prefix}{$table} WHERE {$where}";
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function exists(string $table, string $where, array $params = []): bool
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->prefix}{$table} WHERE {$where}";
        return (bool) $this->fetch($sql, $params)->count;
    }

    public function beginTransaction(): bool
    {
        return $this->pdo()->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo()->commit();
    }

    public function rollback(): bool
    {
        return $this->pdo()->rollBack();
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function setPrefix(string $prefix): void
    {
        $this->prefix = $prefix;
    }

    public function applyPrefix(string $sql): string
    {
        if ($this->prefix) {
            $sql = preg_replace('/\{prefix\}/', $this->prefix, $sql);
        } else {
            $sql = str_replace('{prefix}', '', $sql);
        }
        return $sql;
    }

    public function table(string $table): QueryBuilder
    {
        return new QueryBuilder($this, $this->prefix . $table);
    }

    public function getDriver(): string
    {
        return $this->driver;
    }

    public function createConnection(string $name, array $config): void
    {
        $db = new self(
            $config['host'] ?? 'localhost',
            $config['name'] ?? $config['dbname'] ?? 'booking',
            $config['user'] ?? $config['username'] ?? 'root',
            $config['pass'] ?? $config['password'] ?? '',
            $config['driver'] ?? 'mysql',
            $config['prefix'] ?? ''
        );
        self::$connections[$name] = $db;
    }

    public function connection(string $name = null): ?self
    {
        $name = $name ?? $this->connectionName;
        return self::$connections[$name] ?? $this;
    }

    public function getSchemaBuilder(): SchemaBuilder
    {
        return new SchemaBuilder($this);
    }

    public function quote(string $value): string
    {
        return $this->pdo()->quote($value);
    }
}

class QueryBuilder
{
    private Database $db;
    private string $table;
    private array $selects = ['*'];
    private array $wheres = [];
    private array $params = [];
    private array $orders = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private array $joins = [];
    private ?string $groupBy = null;
    private ?string $having = null;

    public function __construct(Database $db, string $table)
    {
        $this->db = $db;
        $this->table = $table;
    }

    public function select(array $columns): self
    {
        $this->selects = $columns;
        return $this;
    }

    public function where(string $column, string $operator = '=', $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        $this->wheres[] = [$column, $operator, 'AND'];
        $this->params[] = $value;
        return $this;
    }

    public function orWhere(string $column, string $operator = '=', $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        $this->wheres[] = [$column, $operator, 'OR'];
        $this->params[] = $value;
        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->wheres[] = ["{$column} IN ({$placeholders})", '', 'AND'];
        $this->params = array_merge($this->params, $values);
        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->wheres[] = ["{$column} IS NULL", '', 'AND'];
        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $this->wheres[] = ["{$column} IS NOT NULL", '', 'AND'];
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orders[] = "{$column} {$direction}";
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $this->joins[] = "{$type} JOIN {$table} ON {$first} {$operator} {$second}";
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function groupBy(string $columns): self
    {
        $this->groupBy = $columns;
        return $this;
    }

    private function buildSelect(): string
    {
        $sql = "SELECT " . implode(', ', $this->selects) . " FROM {$this->table}";

        foreach ($this->joins as $join) {
            $sql .= " {$join}";
        }

        if (!empty($this->wheres)) {
            $parts = [];
            foreach ($this->wheres as $i => $where) {
                [$column, $operator, $boolean] = $where;
                if ($i === 0) {
                    if ($operator === '') {
                        $parts[] = "WHERE {$column}";
                    } else {
                        $parts[] = "WHERE {$column} {$operator} ?";
                    }
                } else {
                    if ($operator === '') {
                        $parts[] = "{$boolean} {$column}";
                    } else {
                        $parts[] = "{$boolean} {$column} {$operator} ?";
                    }
                }
            }
            $sql .= ' ' . implode(' ', $parts);
        }

        if ($this->groupBy) {
            $sql .= " GROUP BY {$this->groupBy}";
        }

        if (!empty($this->orders)) {
            $sql .= " ORDER BY " . implode(', ', $this->orders);
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }

        if ($this->offset !== null) {
            $sql .= " OFFSET {$this->offset}";
        }

        return $sql;
    }

    public function get(): array
    {
        return $this->db->fetchAll($this->buildSelect(), $this->params);
    }

    public function first(): ?\stdClass
    {
        $this->limit(1);
        return $this->db->fetch($this->buildSelect(), $this->params);
    }

    public function count(): int
    {
        $this->selects = ['COUNT(*) as count'];
        $result = $this->first();
        return $result ? (int) $result->count : 0;
    }

    public function pluck(string $column): array
    {
        $rows = $this->select([$column])->get();
        return array_map(fn($r) => $r->$column, $rows);
    }

    public function insert(array $data): int
    {
        return $this->db->insert($this->table, $data);
    }

    public function update(array $data): int
    {
        $sql = "UPDATE {$this->table} SET ";
        $sets = [];
        $params = [];
        foreach ($data as $key => $value) {
            $sets[] = "{$key} = ?";
            $params[] = $value;
        }
        $sql .= implode(', ', $sets);

        if (!empty($this->wheres)) {
            $sql .= " WHERE ";
            $parts = [];
            foreach ($this->wheres as $i => $where) {
                [$column, $operator, $boolean] = $where;
                if ($i === 0) {
                    if ($operator === '') {
                        $parts[] = "{$column}";
                    } else {
                        $parts[] = "{$column} {$operator} ?";
                        $params[] = $this->params[count($parts) - 1] ?? null;
                    }
                } else {
                    if ($operator === '') {
                        $parts[] = "{$boolean} {$column}";
                    } else {
                        $parts[] = "{$boolean} {$column} {$operator} ?";
                    }
                }
            }
            $sql .= implode(' ', $parts);
        }

        $stmt = $this->db->query($sql, $params);
        // Fix params
        $stmt = $this->db->query($sql, array_merge(array_values($data), $this->params));
        return $stmt->rowCount();
    }

    public function delete(): int
    {
        $sql = "DELETE FROM {$this->table}";
        if (!empty($this->wheres)) {
            $sql .= " WHERE ";
            $parts = [];
            foreach ($this->wheres as $i => $where) {
                [$column, $operator, $boolean] = $where;
                if ($i === 0) {
                    if ($operator === '') {
                        $parts[] = "{$column}";
                    } else {
                        $parts[] = "{$column} {$operator} ?";
                    }
                } else {
                    if ($operator === '') {
                        $parts[] = "{$boolean} {$column}";
                    } else {
                        $parts[] = "{$boolean} {$column} {$operator} ?";
                    }
                }
            }
            $sql .= implode(' ', $parts);
        }
        return $this->db->query($sql, $this->params)->rowCount();
    }
}

class SchemaBuilder
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function hasTable(string $table): bool
    {
        $driver = $this->db->getDriver();
        $sql = match ($driver) {
            'mysql' => "SHOW TABLES LIKE '{$table}'",
            'sqlite' => "SELECT name FROM sqlite_master WHERE type='table' AND name='{$table}'",
            default => "SELECT * FROM information_schema.tables WHERE table_name='{$table}'",
        };
        return (bool) $this->db->fetch($sql);
    }

    public function createTable(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table, $this->db->getDriver());
        $callback($blueprint);
        $sql = $blueprint->toSql();
        $this->db->query($sql);
    }

    public function dropTable(string $table): void
    {
        $this->db->query("DROP TABLE IF EXISTS {$table}");
    }

    public function runMigrations(string $path): void
    {
        $files = glob($path . '/*.sql');
        sort($files);
        foreach ($files as $file) {
            $sql = file_get_contents($file);
            $this->db->query($sql);
        }
    }
}

class Blueprint
{
    private string $table;
    private string $driver;
    private array $columns = [];

    public function __construct(string $table, string $driver = 'mysql')
    {
        $this->table = $table;
        $this->driver = $driver;
    }

    public function id(string $name = 'id'): void
    {
        $this->columns[] = "{$name} BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY";
    }

    public function string(string $name, int $length = 255): void
    {
        $this->columns[] = "{$name} VARCHAR({$length}) NOT NULL";
    }

    public function integer(string $name, bool $unsigned = false): void
    {
        $type = $unsigned ? 'INT UNSIGNED' : 'INT';
        $this->columns[] = "{$name} {$type} NOT NULL DEFAULT 0";
    }

    public function bigInteger(string $name, bool $unsigned = false): void
    {
        $type = $unsigned ? 'BIGINT UNSIGNED' : 'BIGINT';
        $this->columns[] = "{$name} {$type} NOT NULL DEFAULT 0";
    }

    public function decimal(string $name, int $total = 20, int $places = 2): void
    {
        $this->columns[] = "{$name} DECIMAL({$total},{$places}) NOT NULL DEFAULT 0.00";
    }

    public function text(string $name): void
    {
        $this->columns[] = "{$name} TEXT";
    }

    public function json(string $name): void
    {
        $this->columns[] = "{$name} JSON";
    }

    public function boolean(string $name): void
    {
        $this->columns[] = "{$name} TINYINT(1) NOT NULL DEFAULT 0";
    }

    public function date(string $name): void
    {
        $this->columns[] = "{$name} DATE";
    }

    public function datetime(string $name): void
    {
        $this->columns[] = "{$name} DATETIME";
    }

    public function timestamp(string $name, bool $useCurrent = false): void
    {
        $default = $useCurrent ? 'DEFAULT CURRENT_TIMESTAMP' : '';
        $this->columns[] = "{$name} TIMESTAMP {$default}";
    }

    public function enum(string $name, array $values): void
    {
        $vals = implode("','", $values);
        $this->columns[] = "{$name} ENUM('{$vals}') NOT NULL";
    }

    public function nullable(): void
    {
        $last = array_pop($this->columns);
        $this->columns[] = str_replace('NOT NULL', '', $last) . ' DEFAULT NULL';
    }

    public function default($value): void
    {
        $last = array_pop($this->columns);
        $v = is_string($value) ? "'{$value}'" : $value;
        $this->columns[] = "{$last} DEFAULT {$v}";
    }

    public function unique(array $columns, string $name = null): void
    {
        $cols = implode('_', $columns);
        $name = $name ?? "idx_{$cols}_unique";
        $cols = implode(', ', $columns);
        $this->columns[] = "UNIQUE INDEX `{$name}` ({$cols})";
    }

    public function index(string $column, string $name = null): void
    {
        $name = $name ?? "idx_{$column}";
        $this->columns[] = "INDEX `{$name}` (`{$column}`)";
    }

    public function foreignId(string $column, string $references, string $on = 'id', string $onDelete = 'CASCADE'): void
    {
        $this->columns[] = "{$column} BIGINT UNSIGNED NOT NULL";
        $this->columns[] = "FOREIGN KEY (`{$column}`) REFERENCES `{$references}`(`{$on}`) ON DELETE {$onDelete}";
    }

    public function timestamps(): void
    {
        $this->timestamp('created_at', true);
        $this->timestamp('updated_at', false, true);
    }

    public function softDeletes(): void
    {
        $this->columns[] = "deleted_at TIMESTAMP NULL DEFAULT NULL";
    }

    public function toSql(): string
    {
        $engine = $this->driver === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        return "CREATE TABLE IF NOT EXISTS `{$this->table}` (\n  " . implode(",\n  ", $this->columns) . "\n){$engine};";
    }
}
