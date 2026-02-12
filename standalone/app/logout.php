<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/auth.php';
require_installation();
app_logout();
header('Location: /app/login.php');
