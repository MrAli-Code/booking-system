<?php
namespace BBS\Core;

class Session
{
    private static ?Session $instance = null;
    private bool $started = false;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function start(): void
    {
        if ($this->started) return;
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->started = true;
    }

    public function get(string $key, $default = null): mixed
    {
        $this->start();
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        $this->start();
        return isset($_SESSION[$key]);
    }

    public function remove(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function destroy(): void
    {
        $this->start();
        session_destroy();
        $_SESSION = [];
        $this->started = false;
    }

    public function flash(string $key, $value): void
    {
        $this->start();
        $_SESSION['_flash'][$key] = $value;
    }

    public function getFlash(string $key, $default = null): mixed
    {
        $this->start();
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    public function hasFlash(string $key): bool
    {
        $this->start();
        return isset($_SESSION['_flash'][$key]);
    }

    public function all(): array
    {
        $this->start();
        return $_SESSION;
    }

    public function regenerate(): void
    {
        $this->start();
        session_regenerate_id(true);
    }

    public function getId(): string
    {
        $this->start();
        return session_id();
    }

    public function setId(string $id): void
    {
        session_id($id);
    }

    public function setUser(\stdClass $user): void
    {
        $this->set('_user', $user);
    }

    public function getUser(): ?\stdClass
    {
        return $this->get('_user');
    }

    public function isAuthenticated(): bool
    {
        return $this->has('_user');
    }

    public function logout(): void
    {
        $this->remove('_user');
        $this->regenerate();
    }
}
