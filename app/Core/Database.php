<?php
namespace App\Core;

/**
 * Small bridge to the existing App\Inc db() function.
 */
class Database
{
    public static function pdo(): \PDO
    {
        // lazy load the existing procedural wrapper
        if (! function_exists('\App\Inc\db')) {
            require_once __DIR__ . '/../inc/db.php';
        }
        return \App\Inc\db();
    }
}
