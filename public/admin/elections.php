<?php
require_once __DIR__ . '/../../app/Core/Autoloader.php';
\App\Core\Autoloader::register();

require_once __DIR__ . '/../../app/Controllers/Admin/ElectionsController.php';

$controller = new \App\Controllers\Admin\ElectionsController();
$controller->handle();
