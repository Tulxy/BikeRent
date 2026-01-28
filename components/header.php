<?php
?>

<header class="border-b border-gray-800 sticky top-0 bg-black z-50">
  <div class="container mx-auto px-6 py-4 flex justify-between items-center">
    <div class="flex items-center gap-8">
      <a href="../index.php" class="text-2xl font-bold hover:text-cyan-500 transition">BikeRent</a>
      <nav class="hidden md:flex gap-6 text-sm">
        <a href="../pages/dashboard.php" class="text-cyan-500 font-semibold">Dashboard</a>
        <a href="../pages/rentals.php" class="text-gray-400 hover:text-white transition">Browse Bikes</a>
        <a href="../pages/my_bikes.php" class="text-gray-400 hover:text-white transition">My Bikes</a>
        <a href="../pages/my_bookings.php" class="text-gray-400 hover:text-white transition">My Bookings</a>
      </nav>
    </div>

    <div class="flex items-center gap-4">
      <span class="text-gray-400 text-sm">Hello, <span class="text-white font-semibold"><?php echo htmlspecialchars($user_name); ?></span></span>
      <a href="../auth/logout.php" class="px-4 py-2 text-sm border border-red-600 text-red-600 hover:bg-red-600 hover:text-white rounded-lg transition">
        Logout
      </a>
    </div>
  </div>
</header>
