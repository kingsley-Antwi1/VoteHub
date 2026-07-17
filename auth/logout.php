<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../auth/session.php';
logout();
redirect(BASE_URL . '/');
