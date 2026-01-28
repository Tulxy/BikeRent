<?php
session_start();

// Pokud už není přihlášen
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
  header("Location: ../index.php");
  exit;
}

// Pokud klikne na confirm
if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
  session_unset();
  session_destroy();

  if (isset($_COOKIE['user_email'])) {
    setcookie('user_email', '', time() - 3600, "/");
  }

  header("Location: ../index.php");
  exit;
}

$user_name = $_SESSION['user_name'];
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <script src="https://cdn.tailwindcss.com"></script>
  <title>Logout - BikeRent</title>
</head>
<body class="bg-black text-white min-h-screen flex items-center justify-center">

<div class="w-full max-w-md px-8">
  <div class="text-center mb-10">
    <h1 class="text-3xl font-bold">BikeRent</h1>
  </div>

  <div class="bg-gray-900 border border-gray-800 rounded-xl p-8 text-center">
    <div class="text-5xl mb-4">👋</div>
    <h2 class="text-2xl font-bold mb-4">Logout</h2>
    <p class="text-gray-400 mb-8">
      Are you sure you want to logout, <?php echo htmlspecialchars($user_name); ?>?
    </p>

    <div class="flex gap-4">
      <a href="?confirm=yes" class="flex-1 py-3 bg-red-600 hover:bg-red-700 rounded-lg transition font-semibold">
        Yes, Logout
      </a>
      <a href="../pages/dashboard.php" class="flex-1 py-3 border border-gray-700 hover:bg-gray-800 rounded-lg transition font-semibold">
        Cancel
      </a>
    </div>
  </div>
</div>

</body>
</html>
