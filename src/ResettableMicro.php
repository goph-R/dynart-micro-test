<?php

namespace Dynart\Micro\Test;

use Dynart\Micro\Micro;

class ResettableMicro extends Micro {
    public static function reset(): void {
        Micro::$instances = [];
        Micro::$classes = [];
        Micro::$app = null;
    }

    /**
     * Puts a ready made instance in the container
     *
     * `add()` only registers the class, and the container builds it - which is no use to a test
     * that needs the singleton to be an object it has already configured.
     */
    public static function set(string $interface, object $instance): void {
        Micro::$classes[$interface] = get_class($instance);
        Micro::$instances[$interface] = $instance;
    }
}