<?php

// This file should be in your document root folder!

require_once __DIR__ . '/vendor/autoload.php';

use Dynart\Micro\Micro;
use Dynart\Micro\WebApp;
use Dynart\Micro\View;

class MyController {

    /** @var View */
    private $view;

    public function __construct(View $view) { // the `$view` parameter will be automatically injected
        $this->view = $view;
    }

    /**
     * Renders the vendor/dynart/micro/views/index.phtml
     * Example call: http://localhost/my-app/index.php
     *
     * @route GET /
     * @return string
     */
    public function index() {
        return $this->view->fetch('index');
    }

    /**
     * Returns with the path variables in JSON format
     * Example call: http://localhost/my-app/index.php?route=/example/value1/value2
     *
     * @route GET /example/?/?
     * @param string $param1 The first path variable
     * @param string $param2 The second path variable
     * @return array
     */
    public function example($param1, $param2) {
        return [
            'param1' => $param1,
            'param2' => $param2
        ];
    }
}

class MyApp extends WebApp { // inherit from WebApp for an MVC/REST web application

    public function __construct(array $configPaths) {
        parent::__construct($configPaths);

        // register the controller
        Micro::add(MyController::class);

        // use the route annotations on the registered classes
        $this->useRouteAnnotations();
    }
}

Micro::run(new MyApp(['config.ini.php']));



