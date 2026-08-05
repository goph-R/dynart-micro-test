<?php

namespace Dynart\Micro\Test;

use Dynart\Micro\ConfigInterface;

/**
 * A config that returns whatever it was handed
 *
 * `Config` reads an INI file and keeps its values private, so a test that needs one key set to
 * three different things would need three files. This is the same interface with an array
 * behind it.
 */
class StubConfig implements ConfigInterface {

    public function __construct(private array $values = []) {}

    public function load(string $path): void {}

    public function clearCache(): void {}

    public function get(string $name, mixed $default = null, bool $useCache = true): mixed {
        return $this->values[$name] ?? $default;
    }

    public function getCommaSeparatedValues(string $name, bool $useCache = true): array {
        $value = (string)($this->values[$name] ?? '');
        return $value === '' ? [] : array_map('trim', explode(',', $value));
    }

    public function getArray(string $prefix, array $default = [], bool $useCache = true): array {
        return $this->values[$prefix] ?? $default;
    }

    public function isCached(string $name): bool {
        return false;
    }

    public function getFullPath(string $path): string {
        return $path;
    }

    public function rootPath(): string {
        return '';
    }

    public function set(string $name, mixed $value): void {
        $this->values[$name] = $value;
    }
}
