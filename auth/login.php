<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport"
        content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <script src="https://cdn.tailwindcss.com"></script>
  <title>Login - BikeRent</title>
</head>
<body class="bg-black text-white min-h-screen flex items-center justify-center">

<div class="w-full max-w-md px-8">
  <!-- Logo/Header -->
  <div class="text-center mb-10">
    <a href="../index.php" class="text-3xl font-bold hover:text-cyan-500 transition">BikeRent</a>
    <p class="text-gray-400 mt-2">Welcome back</p>
  </div>

  <!-- Login Form -->
  <div class="bg-gray-900 border border-gray-800 rounded-xl p-8">
    <h2 class="text-2xl font-bold mb-6">Login</h2>

    <form method="POST" action="process_login.php" class="space-y-6">
      <!-- Email -->
      <div>
        <label for="email" class="block text-sm text-gray-400 mb-2">Email</label>
        <input
          type="email"
          id="email"
          name="email"
          required
          class="w-full px-4 py-3 bg-black border border-gray-800 rounded-lg focus:border-cyan-600 focus:outline-none transition"
          placeholder="your@email.com"
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
          class="w-full px-4 py-3 bg-black border border-gray-800 rounded-lg focus:border-cyan-600 focus:outline-none transition"
          placeholder="••••••••"
        >
      </div>

      <!-- Remember Me -->
      <div class="flex items-center justify-between text-sm">
        <label class="flex items-center text-gray-400 cursor-pointer">
          <input type="checkbox" name="remember" class="mr-2 accent-cyan-600">
          Remember me
        </label>
      </div>

      <!-- Submit Button -->
      <button
        type="submit"
        class="w-full py-3 bg-cyan-600 hover:bg-cyan-700 rounded-lg font-semibold transition"
      >
        Login
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

    <!-- Register Link -->
    <div class="text-center text-gray-400">
      Don't have an account?
      <a href="register.php" class="text-cyan-500 hover:text-cyan-400 transition font-semibold ml-1">
        Register
      </a>
    </div>
  </div>

  <!-- Back to Home -->
  <div class="text-center mt-6">
    <a href="../index.php" class="text-gray-500 hover:text-gray-400 transition text-sm">
      ← Back to home
    </a>
  </div>
</div>

</body>
</html>
