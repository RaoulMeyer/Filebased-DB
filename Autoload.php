<?php

function __autoload($class) {
    $paths = array('./', './core/', './entities/');
    foreach($paths as $path) {
        if(file_exists($path . $class . '.php')) {
            include($path . $class . '.php');
            return;
        }
    }
}