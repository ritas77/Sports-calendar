<?php
// Force PHP to tell us what's wrong
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Debug Info</h1>";
echo "POSTGRES_USER: " . (getenv('POSTGRES_USER') ?: "NOT FOUND") . "<br>";
echo "POSTGRES_DB: " . (getenv('POSTGRES_DB') ?: "NOT FOUND") . "<br>";

echo "<h3>Full Environment:</h3>";
echo "<pre>";
print_r($_ENV);
echo "</pre>";