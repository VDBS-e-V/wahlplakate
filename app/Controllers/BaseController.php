<?php
namespace App\Controllers;

abstract class BaseController
{
    protected function view(string $name, array $data = []): void
    {
        // make data available to view
        extract($data, EXTR_SKIP);
        $viewPath = __DIR__ . '/../Views/' . $name . '.php';
        if (! is_file($viewPath)) {
            throw new \RuntimeException('View not found: ' . $viewPath);
        }
        require $viewPath;
    }
}
