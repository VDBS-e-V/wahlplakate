<?php
// MVC dispatcher for upload
require_once __DIR__ . '/../app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Controllers\UploadController;

$c = new UploadController();
$c->handle();

