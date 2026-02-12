<?php
require_once __DIR__ . '/../lib/auth.php';
admin_logout();
header('Location: /admin/index.php');
