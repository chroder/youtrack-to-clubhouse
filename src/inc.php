<?php

spl_autoload_register(function ($name) {
    if (preg_match('/^App\\\\/', $name)) {
        $file = str_replace('\\', DIRECTORY_SEPARATOR, $name) . '.php';
        require __DIR__ . '/' . $file;
        return true;
    }

    return false;
});

function getApp()
{
    static $app;

    if (!$app) {
        $app = new App\Env(require(__DIR__ . '/../config/config.php'));
    }

    return $app;
}
