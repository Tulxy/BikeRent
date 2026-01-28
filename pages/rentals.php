<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../auth/login.php");
    exit;
}

require_once '../config.php';
$pdo = getDB();

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Získat layout preference (grid nebo list)
$layout = isset($_GET['layout']) ? $_GET['layout'] : 'grid';
if (!in_array($layout, ['grid', 'list'])) {
  $layout = 'grid';
}

// Filtry
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$bike_type = isset($_GET['bike_type']) ? $_GET['bike_type'] : '';
$city = isset($_GET['city']) ? $_GET['city'] : '';

// Získat všechna dostupná kola (kromě mých vlastních)
try {
  $sql = "
        SELECT 
            b.*,
            u.name as owner_name,
            u.phone as owner_phone,
            u.city as owner_city
        FROM bikes b
        JOIN users u ON b.owner_id = u.id
        WHERE b.owner_id != ?
        AND b.available = 1
    ";

  $params = [$user_id];

  // Přidat filtry
  if (!empty($search)) {
    $sql .= " AND (b.bike_type LIKE ? OR b.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
  }

  if (!empty($bike_type)) {
    $sql .= " AND b.bike_type = ?";
    $params[] = $bike_type;
  }

  if (!empty($city)) {
    $sql .= " AND b.city = ?";
    $params[] = $city;
  }

  $sql .= " ORDER BY b.created_at DESC";

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $bikes = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Získat unikátní typy kol pro filter
  $stmt = $pdo->query("SELECT DISTINCT bike_type FROM bikes WHERE available = 1 ORDER BY bike_type");
  $bike_types = $stmt->fetchAll(PDO::FETCH_COLUMN);

  // Získat unikátní města pro filter
  $stmt = $pdo->query("SELECT DISTINCT city FROM bikes WHERE available = 1 AND city IS NOT NULL ORDER BY city");
  $cities = $stmt->fetchAll(PDO::FETCH_COLUMN);

} catch(PDOException $e) {
  $error = "Database error: " . $e->getMessage();
  $bikes = [];
  $bike_types = [];
  $cities = [];
}
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <script src="https://cdn.tailwindcss.com"></script>
  <title>Browse Bikes - BikeRent</title>
</head>
<body class="bg-black text-white min-h-screen">

<?php include '../components/header.php'; ?>

<!-- Main Content -->
<main class="container mx-auto px-6 py-12">

  <!-- Page Header -->
  <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
    <div>
      <h1 class="text-4xl font-bold mb-2">Browse Bikes</h1>
      <p class="text-gray-400">Find the perfect bike for your next adventure</p>
    </div>
    <a href="add_bike.php" class="px-6 py-3 bg-green-600 hover:bg-green-700 rounded-lg transition font-semibold">
      + List Your Bike
    </a>
  </div>

  <!-- Filters & Layout Switcher -->
  <div class="bg-gray-900 border border-gray-700 rounded-xl p-6 mb-8">
    <form method="GET" action="rentals.php" class="grid md:grid-cols-4 gap-4">

      <!-- Search -->
      <div>
        <label class="block text-sm text-gray-400 mb-2">Search</label>
        <input
          type="text"
          name="search"
          value="<?php echo htmlspecialchars($search); ?>"
          placeholder="Search bikes..."
          class="w-full px-4 py-2 bg-black border border-gray-700 rounded-lg focus:border-cyan-600 focus:outline-none transition text-sm"
        >
      </div>

      <!-- Bike Type Filter -->
      <div>
        <label class="block text-sm text-gray-400 mb-2">Bike Type</label>
        <select
          name="bike_type"
          class="w-full px-4 py-2 bg-black border border-gray-700 rounded-lg focus:border-cyan-600 focus:outline-none transition text-sm"
        >
          <option value="">All Types</option>
          <?php foreach ($bike_types as $type): ?>
            <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $bike_type === $type ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($type); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- City Filter -->
      <div>
        <label class="block text-sm text-gray-400 mb-2">City</label>
        <select
          name="city"
          class="w-full px-4 py-2 bg-black border border-gray-700 rounded-lg focus:border-cyan-600 focus:outline-none transition text-sm"
        >
          <option value="">All Cities</option>
          <?php foreach ($cities as $c): ?>
            <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $city === $c ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($c); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Submit -->
      <div class="flex items-end">
        <button type="submit" class="w-full px-4 py-2 bg-cyan-600 hover:bg-cyan-700 rounded-lg transition text-sm font-semibold">
          Apply Filters
        </button>
      </div>

      <!-- Hidden layout field -->
      <input type="hidden" name="layout" value="<?php echo $layout; ?>">
    </form>

    <!-- Layout Switcher -->
    <div class="flex items-center gap-2 mt-4 pt-4 border-t border-gray-800">
      <span class="text-sm text-gray-400">View:</span>
      <a href="?layout=grid<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($bike_type) ? '&bike_type=' . urlencode($bike_type) : ''; ?><?php echo !empty($city) ? '&city=' . urlencode($city) : ''; ?>"
         class="px-3 py-1 rounded <?php echo $layout === 'grid' ? 'bg-cyan-600 text-white' : 'bg-gray-800 text-gray-400 hover:bg-gray-700'; ?> transition text-sm">
        Grid
      </a>
      <a href="?layout=list<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($bike_type) ? '&bike_type=' . urlencode($bike_type) : ''; ?><?php echo !empty($city) ? '&city=' . urlencode($city) : ''; ?>"
         class="px-3 py-1 rounded <?php echo $layout === 'list' ? 'bg-cyan-600 text-white' : 'bg-gray-800 text-gray-400 hover:bg-gray-700'; ?> transition text-sm">
        List
      </a>
    </div>
  </div>

  <!-- Results Count -->
  <div class="mb-6">
    <p class="text-gray-400">
      <?php echo count($bikes); ?> bike<?php echo count($bikes) !== 1 ? 's' : ''; ?> available
    </p>
  </div>

  <!-- Bikes Display -->
  <?php if (empty($bikes)): ?>
    <div class="bg-gray-900 border border-gray-800 rounded-xl p-12 text-center">
      <div class="text-6xl mb-4">🚴</div>
      <h3 class="text-2xl font-bold mb-2">No bikes found</h3>
      <p class="text-gray-400 mb-6">Try adjusting your filters or check back later</p>
      <a href="../pages/rentals.php" class="inline-block px-6 py-3 bg-cyan-600 hover:bg-cyan-700 rounded-lg transition">
        Clear Filters
      </a>
    </div>
  <?php else: ?>

    <!-- Grid Layout -->
    <?php if ($layout === 'grid'): ?>
      <div class="grid md:grid-cols-3 gap-6">
        <?php foreach ($bikes as $bike): ?>
          <div class="bg-gray-900 border border-gray-700 rounded-xl overflow-hidden hover:border-cyan-600 transition group">
            <!-- Bike Image Placeholder -->
            <div class="bg-gradient-to-br from-gray-800 to-gray-900 h-48 flex items-center justify-center text-6xl">
              <?php
              $emoji = '🚴';
              if (strpos(strtolower($bike['bike_type']), 'mountain') !== false) $emoji = '🚵';
              if (strpos(strtolower($bike['bike_type']), 'electric') !== false) $emoji = '⚡';
              if (strpos(strtolower($bike['bike_type']), 'road') !== false) $emoji = '🚴';
              echo $emoji;
              ?>
            </div>

            <!-- Bike Info -->
            <div class="p-6">
              <div class="flex items-start justify-between mb-2">
                <h3 class="text-xl font-bold"><?php echo htmlspecialchars($bike['bike_type']); ?></h3>
                <span class="bg-green-900/50 text-green-500 text-xs px-2 py-1 rounded">Available</span>
              </div>

              <p class="text-gray-400 text-sm mb-4 line-clamp-2">
                <?php echo htmlspecialchars($bike['description'] ?? 'No description'); ?>
              </p>

              <div class="space-y-2 mb-4 text-sm text-gray-400">
                <div class="flex items-center gap-2">
                  <span>👤</span>
                  <span><?php echo htmlspecialchars($bike['owner_name']); ?></span>
                </div>
                <div class="flex items-center gap-2">
                  <span>📍</span>
                  <span><?php echo htmlspecialchars($bike['city'] ?? 'Unknown'); ?></span>
                </div>
                <?php if (!empty($bike['frame_size'])): ?>
                  <div class="flex items-center gap-2">
                    <span>📏</span>
                    <span>Size: <?php echo htmlspecialchars($bike['frame_size']); ?></span>
                  </div>
                <?php endif; ?>
              </div>

              <div class="border-t border-gray-800 pt-4 flex items-center justify-between">
                <div class="text-2xl font-bold text-cyan-500">
                  <?php echo number_format($bike['price_per_day'], 0); ?> Kč
                  <span class="text-sm text-gray-500">/day</span>
                </div>
                <a href="book_bike.php?id=<?php echo $bike['id']; ?>"
                   class="px-4 py-2 bg-cyan-600 hover:bg-cyan-700 rounded-lg transition text-sm font-semibold">
                  Book Now
                </a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- List Layout -->
    <?php else: ?>
      <div class="space-y-4">
        <?php foreach ($bikes as $bike): ?>
          <div class="bg-gray-900 border border-gray-700 rounded-xl p-6 hover:border-cyan-600 transition">
            <div class="flex gap-6">
              <!-- Bike Image Placeholder -->
              <div class="bg-gradient-to-br from-gray-800 to-gray-900 w-32 h-32 rounded-lg flex items-center justify-center text-5xl flex-shrink-0">
                <?php
                $emoji = '🚴';
                if (strpos(strtolower($bike['bike_type']), 'mountain') !== false) $emoji = '🚵';
                if (strpos(strtolower($bike['bike_type']), 'electric') !== false) $emoji = '⚡';
                if (strpos(strtolower($bike['bike_type']), 'road') !== false) $emoji = '🚴';
                echo $emoji;
                ?>
              </div>

              <!-- Bike Details -->
              <div class="flex-grow">
                <div class="flex items-start justify-between mb-2">
                  <div>
                    <h3 class="text-2xl font-bold mb-1"><?php echo htmlspecialchars($bike['bike_type']); ?></h3>
                    <p class="text-gray-400 text-sm">by <?php echo htmlspecialchars($bike['owner_name']); ?></p>
                  </div>
                  <span class="bg-green-900/50 text-green-500 text-xs px-3 py-1 rounded">Available</span>
                </div>

                <p class="text-gray-400 mb-4">
                  <?php echo htmlspecialchars($bike['description'] ?? 'No description'); ?>
                </p>

                <div class="flex gap-6 text-sm text-gray-400 mb-4">
                  <div class="flex items-center gap-2">
                    <span>📍</span>
                    <span><?php echo htmlspecialchars($bike['city'] ?? 'Unknown'); ?></span>
                  </div>
                  <?php if (!empty($bike['frame_size'])): ?>
                    <div class="flex items-center gap-2">
                      <span>📏</span>
                      <span>Size: <?php echo htmlspecialchars($bike['frame_size']); ?></span>
                    </div>
                  <?php endif; ?>
                </div>

                <div class="flex items-center justify-between">
                  <div class="text-3xl font-bold text-cyan-500">
                    <?php echo number_format($bike['price_per_day'], 0); ?> Kč
                    <span class="text-sm text-gray-500">/day</span>
                  </div>
                  <a href="book_bike.php?id=<?php echo $bike['id']; ?>"
                     class="px-6 py-3 bg-cyan-600 hover:bg-cyan-700 rounded-lg transition font-semibold">
                    Book Now
                  </a>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  <?php endif; ?>

</main>

<!-- Footer -->
<footer class="border-t border-gray-800 mt-20 py-8 text-center text-gray-500 text-sm">
  <p>© 2026 BikeRent. Share the ride.</p>
</footer>

</body>
</html>
