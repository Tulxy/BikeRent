<?php
// config.php

// Databázové konstanty
const DB_HOST = 'localhost';
const DB_NAME = 'bike_rent';
const DB_USER = 'root';
const DB_PASS = 'root';  // Změň podle svého MySQL hesla

// Vytvoření PDO připojení
function getDB() {
  try {
    return new PDO(
      "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
      DB_USER,
      DB_PASS,
      [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
      ]
    );
  } catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
  }
}

// Další globální nastavení
const SITE_NAME = 'BikeRent';
const SITE_URL = 'http://localhost/bikerent';

// Timezone
date_default_timezone_set('Europe/Prague');

