<?php
namespace App\Core;

class Autoloader
{
    public static function register(): void
    {
        spl_autoload_register(function ($class) {
            // Only handle App\ namespace
            $prefix = 'App\\';
            if (str_starts_with($class, $prefix)) {
                $rel = substr($class, strlen($prefix));
                $path = __DIR__ . '/../../' . str_replace('\\', '/', $rel) . '.php';
                if (is_file($path)) {
                    require_once $path;
                }
            }
        });
    }
}
