<?php

define('XDEBUG_PATH_INCLUDE', 1);

xdebug_set_filter(
    XDEBUG_FILTER_CODE_COVERAGE,
    XDEBUG_PATH_INCLUDE,
    ["c:/xampp/htdocs/dynart-micro/src"]
);
