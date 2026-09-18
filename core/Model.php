<?php
namespace BBS\Core;

abstract class Model
{
    protected static string $table = '';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;
    protected static bool $softDeletes = false;
    protected static string $uuidColumn = 'uuid';
    protected static array $fillable = [];
    protected static array $hidden = ['password', 'remember_token'];
    protected static array $casts = [];
    protected static ?string $connectionName = null;

    protected ?\stdClass $attributes = null;
    protected array $original = [];
    protected array $changes = [];
    protected bool $exists = false;

    public function __construct(array $data = [])
    {
        $this->attributes = new \stdClass();
        foreach ($data as $key => $value) {
            $this->attributes->$key = $this->castValue($key, $value);
        }
    }

    public static function table(): string
    {
        if (static::$table) return static::$table;
        $class = (new \ReflectionClass(static::class))->getShortName();
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $class)) . 's';
    }

    public static function db(): Database
    {
        $app = App::getInstance();
        if (static::$connectionName) {
            return $app->getDatabase()->connection(static::$connectionName);
        }
        return $app->getDatabase();
    }

    public static function find(int|string $id): ?static
    {
        $pk = static::$primaryKey;
        $data = static::db()->fetch(
            "SELECT * FROM {prefix}" . static::table() . " WHERE {$pk} = ?",
            [$id]
        );
        return $data ? static::hydrate($data) : null;
    }

    public static function findBy(string $column, $value): ?static
    {
        $data = static::db()->fetch(
            "SELECT * FROM {prefix}" . static::table() . " WHERE {$column} = ?",
            [$value]
        );
        return $data ? static::hydrate($data) : null;
    }

    public static function where(string $column, string $operator = '=', $value = null): QueryBuilder
    {
        return static::db()->table(static::table())->where($column, $operator, $value);
    }

    public static function all(): array
    {
        $rows = static::db()->fetchAll("SELECT * FROM {prefix}" . static::table());
        return array_map(fn($r) => static::hydrate($r), $rows);
    }

    public static function count(): int
    {
        return static::db()->fetch(
            "SELECT COUNT(*) as count FROM {prefix}" . static::table()
        )->count ?? 0;
    }

    public static function hydrate(\stdClass $data): static
    {
        $instance = new static();
        $instance->attributes = $data;
        $instance->original = (array) $data;
        $instance->exists = true;
        return $instance;
    }

    public function __get(string $name)
    {
        if (isset($this->attributes->$name)) {
            return $this->castValue($name, $this->attributes->$name);
        }
        return null;
    }

    public function __set(string $name, $value): void
    {
        $this->attributes->$name = $value;
        $this->changes[$name] = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->attributes->$name);
    }

    public function toArray(): array
    {
        $data = (array) $this->attributes;
        foreach (static::$hidden as $key) {
            unset($data[$key]);
        }
        return $data;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    public function save(): bool
    {
        $db = static::db();
        $table = static::table();

        if (static::$timestamps) {
            $this->attributes->updated_at = date('Y-m-d H:i:s');
        }

        if ($this->exists) {
            $pk = static::$primaryKey;
            $id = $this->attributes->$pk;
            $data = [];
            foreach ($this->changes as $key => $value) {
                if (in_array($key, static::$fillable) || empty(static::$fillable)) {
                    $data[$key] = $value;
                }
            }
            if (empty($data)) return true;
            if (static::$timestamps) {
                $data['updated_at'] = $this->attributes->updated_at;
            }
            $db->update($table, $data, "{$pk} = ?", [$id]);
            return true;
        }

        $data = (array) $this->attributes;
        if (static::$uuidColumn && !isset($data[static::$uuidColumn])) {
            $data[static::$uuidColumn] = self::generateUuid();
            $this->attributes->{static::$uuidColumn} = $data[static::$uuidColumn];
        }
        unset($data[static::$primaryKey]);

        if (static::$timestamps) {
            $data['created_at'] = $data['updated_at'] = date('Y-m-d H:i:s');
        }

        $id = $db->insert($table, $data);
        $pk = static::$primaryKey;
        $this->attributes->$pk = $id;
        $this->exists = true;
        return true;
    }

    public function delete(): bool
    {
        if (!$this->exists) return false;
        $pk = static::$primaryKey;
        if (static::$softDeletes) {
            static::db()->update(static::table(), ['deleted_at' => date('Y-m-d H:i:s')], "{$pk} = ?", [$this->attributes->$pk]);
        } else {
            static::db()->delete(static::table(), "{$pk} = ?", [$this->attributes->$pk]);
        }
        return true;
    }

    public function forceDelete(): bool
    {
        if (!$this->exists) return false;
        $pk = static::$primaryKey;
        static::db()->delete(static::table(), "{$pk} = ?", [$this->attributes->$pk]);
        return true;
    }

    public function fresh(): static
    {
        if (!$this->exists) return $this;
        $pk = static::$primaryKey;
        return static::find($this->attributes->$pk);
    }

    public function isDirty(): bool
    {
        return !empty($this->changes);
    }

    public function getOriginal(string $key = null): mixed
    {
        if ($key) return $this->original[$key] ?? null;
        return $this->original;
    }

    private function castValue(string $key, $value): mixed
    {
        if (!isset(static::$casts[$key])) return $value;
        return match (static::$casts[$key]) {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'bool', 'boolean' => (bool) $value,
            'json', 'array' => is_string($value) ? json_decode($value, true) : $value,
            'object' => is_string($value) ? json_decode($value) : $value,
            'date' => $value,
            default => $value,
        };
    }

    public static function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public static function query(): QueryBuilder
    {
        return static::db()->table(static::table());
    }

    public static function create(array $data): static
    {
        $instance = new static($data);
        $instance->save();
        return $instance;
    }

    public static function updateOrCreate(array $conditions, array $data): static
    {
        $query = static::db()->table(static::table());
        foreach ($conditions as $key => $value) {
            $query->where($key, '=', $value);
        }
        $existing = $query->first();
        if ($existing) {
            $instance = static::hydrate($existing);
            foreach ($data as $key => $value) {
                $instance->$key = $value;
            }
            $instance->save();
            return $instance;
        }
        return static::create(array_merge($conditions, $data));
    }

    public function hasMany(string $relatedClass, string $foreignKey, string $localKey = null): array
    {
        $localKey = $localKey ?? static::$primaryKey;
        $relatedTable = $relatedClass::table();
        $localValue = $this->attributes->$localKey;
        $rows = static::db()->fetchAll(
            "SELECT * FROM {prefix}{$relatedTable} WHERE {$foreignKey} = ?",
            [$localValue]
        );
        return array_map(fn($r) => $relatedClass::hydrate($r), $rows);
    }

    public function belongsTo(string $relatedClass, string $foreignKey, string $ownerKey = null): ?static
    {
        $ownerKey = $ownerKey ?? static::$primaryKey;
        $foreignValue = $this->attributes->$foreignKey ?? null;
        if (!$foreignValue) return null;
        return $relatedClass::findBy($ownerKey, $foreignValue);
    }

    public static function paginate(int $page = 1, int $perPage = 15): array
    {
        $total = static::count();
        $offset = ($page - 1) * $perPage;
        $rows = static::db()->fetchAll(
            "SELECT * FROM {prefix}" . static::table() . " LIMIT ? OFFSET ?",
            [$perPage, $offset]
        );
        return [
            'data' => array_map(fn($r) => static::hydrate($r), $rows),
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => ceil($total / $perPage),
        ];
    }
}
