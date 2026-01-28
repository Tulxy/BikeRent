<?php
session_start();

// Konfigurační soubor databáze
require_once '../config.php';
$pdo = getDB();

// Zpracování formuláře
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

  // Získání dat z formuláře
  $name = trim($_POST['name']);
  $email = trim($_POST['email']);
  $phone = trim($_POST['phone']);
  $password = $_POST['password'];
  $confirm_password = $_POST['confirm_password'];

  // Validace
  $errors = [];

  // Kontrola povinných polí
  if (empty($name)) {
    $errors[] = "Name is required";
  }

  if (empty($email)) {
    $errors[] = "Email is required";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Invalid email format";
  }

  if (empty($phone)) {
    $errors[] = "Phone is required";
  }

  if (empty($password)) {
    $errors[] = "Password is required";
  } elseif (strlen($password) < 6) {
    $errors[] = "Password must be at least 6 characters";
  }

  if ($password !== $confirm_password) {
    $errors[] = "Passwords do not match";
  }


  // Kontrola, zda email již existuje
  $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
  $stmt->execute([$email]);
  if ($stmt->fetch()) {
    $errors[] = "Email already exists";
  }

  // Pokud jsou chyby, vrátit zpět
  if (!empty($errors)) {
    $_SESSION['errors'] = $errors;
    $_SESSION['old_data'] = $_POST;
    header("Location: register.php");
    exit;
  }

  // Hashování hesla
  $hashed_password = password_hash($password, PASSWORD_DEFAULT);

  // Uložení do databáze
  try {
    $stmt = $pdo->prepare("
            INSERT INTO users (name, email, phone, password, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");

    $stmt->execute([$name, $email, $phone, $hashed_password]);

    $_SESSION['success'] = "Registration successful! You can now login.";
    header("Location: login.php");
    exit;

  } catch(PDOException $e) {
    $_SESSION['errors'] = ["Database error: " . $e->getMessage()];
    header("Location: register.php");
    exit;
  }

} else {
  header("Location: register.php");
  exit;
}
?>
