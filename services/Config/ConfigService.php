<?php
declare(strict_types=1);

namespace VantixDash\Config;
    
/**
 * ConfigService - Typsicherer Key-Value Zugriff
 * 
 * Verantwortlichkeit: Type-Safe Getter/Setter für generische Config
 */
class ConfigService {
    private ConfigRepository $repository;
    private array $data = [];
public function getVersion(): string {
        return (string)($this->versionData['version'] ?? '0.0.0');
    }
    public function __construct(?ConfigRepository $repository = null) {
        $this->repository = $repository ?? new ConfigRepository();
        $this->data = $this->repository->load();
    }

    // ==================== GETTER ====================

    public function getString(string $key, string $default = ''): string {
        return (string)($this->data[$key] ?? $default);
    }

    public function getInt(string $key, int $default = 0): int {
        return (int)($this->data[$key] ?? $default);
    }

    public function getBool(string $key, bool $default = false): bool {
        if (!isset($this->data[$key])) {
            return $default;
        }
        return filter_var($this->data[$key], FILTER_VALIDATE_BOOLEAN);
    }

    public function getArray(string $key, array $default = []): array {
        return (array)($this->data[$key] ?? $default);
    }

    public function has(string $key): bool {
        return isset($this->data[$key]);
    }

    public function getAll(): array {
        return $this->data;
    }

    // ==================== SETTER ====================

    public function set(string $key, mixed $value): void {
        $this->data[$key] = $value;
    }

    public function remove(string $key): void {
        unset($this->data[$key]);
    }

    public function save(): bool {
        return $this->repository->save($this->data);
    }

    // ==================== BATCH OPERATIONS ====================

    public function merge(array $values): void {
        $this->data = array_merge($this->data, $values);
    }

    public function replace(array $data): void {
        $this->data = $data;
    }
}
