<?php

use Dynart\Micro\Micro;

class ResettableMicro extends Micro {
    public static function reset() {
        Micro::$instances = [];
        Micro::$classes = [];
        Micro::$instance = null;
    }
}