<?php
require_once __DIR__ . '/config/config.php';
if (!empty($_SESSION['user'])) { header('Location: ' . url('dashboard.php')); } else { header('Location: ' . url('login.php')); }
exit;
