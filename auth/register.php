<?php
session_start();

$errors = $_SESSION['errors'] ?? [];
$old_data = $_SESSION['old_data'] ?? [];

unset($_SESSION['errors']);
unset($_SESSION['old_data']);
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <script src="https://cdn.tailwindcss.com"></script>
  <title>Register - BikeRent</title>
</head>
<body class="bg-black text-white min-h-screen flex items-center justify-center py-12">

<div class="w-full max-w-md px-8">
  <!-- Logo/Header -->
  <div class="text-center mb-10">
    <a href="/index.php" class="text-3xl font-bold hover:text-cyan-500 transition">BikeRent</a>
    <p class="text-gray-400 mt-2">Create your account</p>
  </div>

  <!-- Register Form -->
  <div class="bg-gray-900 border border-gray-800 rounded-xl p-8">
    <h2 class="text-2xl font-bold mb-6">Register</h2>

    <!-- Error Messages -->
    <?php if (!empty($errors)): ?>
      <div class="bg-red-900/50 border border-red-700 rounded-lg p-4 mb-6">
        <ul class="text-sm space-y-1">
          <?php foreach ($errors as $error): ?>
            <li>• <?php echo htmlspecialchars($error); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="POST" action="process_registration.php" class="space-y-5">
      <!-- Name -->
      <div>
        <label for="name" class="block text-sm text-gray-400 mb-2">Full Name</label>
        <input
          type="text"
          id="name"
          name="name"
          required
          value="<?php echo htmlspecialchars($old_data['name'] ?? ''); ?>"
          class="w-full px-4 py-3 bg-black border border-gray-800 rounded-lg focus:border-cyan-600 focus:outline-none transition"
          placeholder="Jan Novák"
        >
      </div>

      <!-- Email -->
      <div>
        <label for="email" class="block text-sm text-gray-400 mb-2">Email</label>
        <input
          type="email"
          id="email"
          name="email"
          required
          value="<?php echo htmlspecialchars($old_data['email'] ?? ''); ?>"
          class="w-full px-4 py-3 bg-black border border-gray-800 rounded-lg focus:border-cyan-600 focus:outline-none transition"
          placeholder="your@email.com"
        >
      </div>

      <!-- Phone -->
      <div>
        <label for="phone" class="block text-sm text-gray-400 mb-2">Phone</label>
        <input
          type="tel"
          id="phone"
          name="phone"
          required
          value="<?php echo htmlspecialchars($old_data['phone'] ?? ''); ?>"
          class="w-full px-4 py-3 bg-black border border-gray-800 rounded-lg focus:border-cyan-600 focus:outline-none transition"
          placeholder="+420 777 123 456"
        >
      </div>

      <!-- Password -->
      <div>
        <label for="password" class="block text-sm text-gray-400 mb-2">Password</label>
        <input
          type="password"
          id="password"
          name="password"
          required
          minlength="6"
          class="w-full px-4 py-3 bg-black border border-gray-800 rounded-lg focus:border-cyan-600 focus:outline-none transition"
          placeholder="••••••••"
        >
        <p class="text-xs text-gray-500 mt-1">Minimum 6 characters</p>
      </div>

      <!-- Confirm Password -->
      <div>
        <label for="confirm_password" class="block text-sm text-gray-400 mb-2">Confirm Password</label>
        <input
          type="password"
          id="confirm_password"
          name="confirm_password"
          required
          minlength="6"
          class="w-full px-4 py-3 bg-black border border-gray-800 rounded-lg focus:border-cyan-600 focus:outline-none transition"
          placeholder="••••••••"
        >
      </div>

      <!-- Submit Button -->
      <button
        type="submit"
        class="w-full py-3 bg-cyan-600 hover:bg-cyan-700 rounded-lg font-semibold transition"
      >
        Create Account
      </button>
    </form>

    <!-- Divider -->
    <div class="relative my-8">
      <div class="absolute inset-0 flex items-center">
        <div class="w-full border-t border-gray-800"></div>
      </div>
      <div class="relative flex justify-center text-sm">
        <span class="px-4 bg-gray-900 text-gray-500">or</span>
      </div>
    </div>

    <!-- Login Link -->
    <div class="text-center text-gray-400">
      Already have an account?
      <a href="login.php" class="text-cyan-500 hover:text-cyan-400 transition font-semibold ml-1">
        Login
      </a>
    </div>
  </div>

  <!-- Back to Home -->
  <div class="text-center mt-6">
    <a href="/index.php" class="text-gray-500 hover:text-gray-400 transition text-sm">
      ← Back to home
    </a>
  </div>
</div>

</body>
</html>
