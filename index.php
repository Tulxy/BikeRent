<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport"
        content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <script src="https://cdn.tailwindcss.com"></script>
  <title>BikeRent</title>
</head>
<body class="bg-black text-white">

<!-- Header -->
<header class="flex justify-between items-center px-8 py-6 border-b border-gray-800">
  <h1 class="text-2xl font-bold">BikeRent</h1>

  <div class="flex gap-4">
    <a href="auth/login.php" class="px-6 py-2 bg-cyan-600 hover:bg-cyan-700 rounded-lg transition">
      Login
    </a>
    <a href="auth/register.php" class="px-6 py-2 border border-cyan-600 hover:bg-cyan-600 rounded-lg transition">
      Register
    </a>
  </div>
</header>

<!-- Main Content -->
<main class="min-h-screen flex flex-col justify-center items-center px-8">
  <div class="max-w-4xl w-full text-center">
    <!-- Hero -->
    <div class="mb-16">
      <h1 class="text-6xl font-bold mb-6">
        Rent a bike.<br/>
        <span class="text-cyan-500">Explore the city.</span>
      </h1>
      <p class="text-xl text-gray-xc400 mb-8">
        Simple bike rentals in Zlín. Choose your bike and ride.
      </p>

      <a href="auth/login.php" class="inline-block px-8 py-4 bg-cyan-600 hover:bg-cyan-700 rounded-lg text-lg font-semibold transition">
        Book now →
      </a>
    </div>

    <!-- Bikes Grid -->
    <div class="grid md:grid-cols-3 gap-6 mt-20">
      <!-- Mountain Bike -->
      <div class="bg-gray-900 border border-gray-800 rounded-xl p-8 hover:border-cyan-600 transition">
        <div class="text-5xl mb-4">🚵</div>
        <h3 class="text-xl font-bold mb-2">Mountain</h3>
        <p class="text-gray-400 text-sm mb-4">For rough terrain</p>
        <div class="text-3xl font-bold text-cyan-500">200 Kč<span class="text-sm text-gray-500">/day</span></div>
      </div>

      <!-- Road Bike -->
      <div class="bg-gray-900 border border-gray-800 rounded-xl p-8 hover:border-cyan-600 transition">
        <div class="text-5xl mb-4">🚴</div>
        <h3 class="text-xl font-bold mb-2">Road</h3>
        <p class="text-gray-400 text-sm mb-4">For speed</p>
        <div class="text-3xl font-bold text-cyan-500">250 Kč<span class="text-sm text-gray-500">/day</span></div>
      </div>

      <!-- E-Bike -->
      <div class="bg-gray-900 border border-gray-800 rounded-xl p-8 hover:border-cyan-600 transition">
        <div class="text-5xl mb-4">⚡</div>
        <h3 class="text-xl font-bold mb-2">Electric</h3>
        <p class="text-gray-400 text-sm mb-4">For comfort</p>
        <div class="text-3xl font-bold text-cyan-500">400 Kč<span class="text-sm text-gray-500">/day</span></div>
      </div>
    </div>

    <!-- Features -->
    <div class="grid md:grid-cols-3 gap-8 mt-20 text-sm">
      <div>
        <div class="text-2xl mb-2">✓</div>
        <p class="text-gray-400">Free helmet & lock</p>
      </div>
      <div>
        <div class="text-2xl mb-2">✓</div>
        <p class="text-gray-400">3 locations in Zlín</p>
      </div>
      <div>
        <div class="text-2xl mb-2">✓</div>
        <p class="text-gray-400">24/7 booking</p>
      </div>
    </div>
  </div>
</main>

<!-- Footer -->
<footer class="border-t border-gray-800 py-8 text-center text-gray-500 text-sm">
  <p>© 2026 BikeRent. All rights reserved.</p>
</footer>

</body>
</html>
