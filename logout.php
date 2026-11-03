<?php
require __DIR__ . '/lib.php';
$_SESSION = []; session_destroy();
header('Location: login.php');
