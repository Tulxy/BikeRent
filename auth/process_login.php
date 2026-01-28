<?php

session_start();

// Konfigurační soubor databáze
require_once '../config.php';
$pdo = getDB();

// Zpracování formuláře
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

  // Získání dat z formuláře
  $email = trim($_POST['email']);
  $password = $_POST['password'];
  $remember = isset($_POST['remember']);

  // Validace
  $errors = [];

  if (empty($email)) {
    $errors[] = "Email is required";
  }

  if (empty($password)) {
    $errors[] = "Password is required";
  }

  // Pokud jsou chyby, vrátit zpět
  if (!empty($errors)) {
    $_SESSION['errors'] = $errors;
    $_SESSION['old_email'] = $email;
    header("Location: login.php");
    exit;
  }

  // Najít uživatele v databázi
  try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Kontrola zda uživatel existuje a heslo je správné
    if ($user && password_verify($password, $user['password'])) {

      // Úspěšné přihlášení - uložit data do session
      $_SESSION['user_id'] = $user['id'];
      $_SESSION['user_name'] = $user['name'];
      $_SESSION['user_email'] = $user['email'];
      $_SESSION['logged_in'] = true;

      if ($remember) {
        setcookie('user_email', $email, time() + (86400 * 30), "/");
      }

      header("Location: ../pages/dashboard.php");

    } else {
      $_SESSION['errors'] = ["Invalid email or password"];
      $_SESSION['old_email'] = $email;
      header("Location: login.php");
    }
    exit;

  } catch(PDOException $e) {
    $_SESSION['errors'] = ["Database error: " . $e->getMessage()];
    header("Location: login.php");
    exit;
  }

} else {
  header("Location: login.php");
  exit;
}

