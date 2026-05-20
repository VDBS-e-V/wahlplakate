<?php
require_once __DIR__ . '/../app/inc/auth.php';
require_once __DIR__ . '/../app/inc/util.php';

\App\Inc\logout();
\App\Inc\flash_set('success', 'Logged out successfully!');
\App\Inc\redirect(\App\Inc\base_url() ? \App\Inc\base_url() . '/login.php' : '/login.php');

