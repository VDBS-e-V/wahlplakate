<?php
require_once __DIR__ . '/../app/inc/auth.php';
require_once __DIR__ . '/../app/inc/util.php';

\App\Inc\logout();
// redirect to login
header('Location: ' . (\App\Inc\base_url() ? \App\Inc\base_url() . '/login.php' : '/login.php'));
exit;

