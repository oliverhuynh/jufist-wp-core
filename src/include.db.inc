<?php

/**
 * @file
 */

use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;

$request = Request::createFromGlobals();
$postData = (array) $request->request->all();


// Define the path to the wp-config.php file
$wp_config_path = WP_ROOT . '/wp-config.php';

// Check if the file exists
if (file_exists($wp_config_path)) {
  // Read the wp-config.php file to extract database credentials
  $wp_config_content = file_get_contents($wp_config_path);

  // Extract database credentials from wp-config.php
  preg_match("/define\s*\(\s*'DB_NAME'\s*,\s*'([^']+)'\s*\);/", $wp_config_content, $matches);
  $dbname = isset($matches[1]) ? $matches[1] : '';

  preg_match("/define\s*\(\s*'DB_USER'\s*,\s*'([^']+)'\s*\);/", $wp_config_content, $matches);
  $user = isset($matches[1]) ? $matches[1] : '';

  preg_match("/define\s*\(\s*'DB_PASSWORD'\s*,\s*'([^']+)'\s*\);/", $wp_config_content, $matches);
  $password = isset($matches[1]) ? $matches[1] : '';

  preg_match("/define\s*\(\s*'DB_HOST'\s*,\s*'([^']+)'\s*\);/", $wp_config_content, $matches);
  $host = isset($matches[1]) ? $matches[1] : '';

  preg_match("/define\s*\(\s*'DB_DRIVER'\s*,\s*'([^']+)'\s*\);/", $wp_config_content, $matches);
  $db_driver = isset($matches[1]) ? $matches[1] : '';

  $driver = $db_driver ? $db_driver : 'mysqli';

  // Create database credentials array
  $database_credentials = [
        'dbname'   => $dbname,
        'user'     => $user,
        'password' => $password,
        'host'     => $host,
        'driver'   => $driver
  ];

  if ($driver == 'pdo_sqlite') {
    $db_rel = getcwd() == OL_ROOT ? '' : '../';
    $database_credentials = [
        'path'   => $dbname,// For pdo_sqlite
        'driver'   => $driver
    ];
  }

  // Create a Doctrine\DBAL\Configuration object
  $config = new Configuration();

  // Create the database connection
  $database = DriverManager::getConnection($database_credentials, $config);
}
else {
  // Handle the case when the wp-config.php file is not found
  echo "Error: wp-config.php file not found.";
  exit;
}
