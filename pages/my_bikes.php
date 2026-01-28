<?php
session_start();

// Kontrola přihlášení
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
  header("Location: ../auth/login.php");
  exit;
}

// Připojení k databázi
require_once '../config.php';
$pdo = getDB();

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Zpracování smazání kola
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
  $bike_id = $_GET['delete'];

  try {
    // Zkontrolovat, že kolo patří uživateli
    $stmt = $pdo->prepare("SELECT id FROM bikes WHERE id = ? AND owner_id = ?");
    $stmt->execute([$bike_id, $user_id]);

    if ($stmt->fetch()) {
      // Zkontrolovat zda má aktivní nebo budoucí rezervace
      $stmt = $pdo->prepare("
                SELECT COUNT(*) as booking_count 
                FROM bookings 
                WHERE bike_id = ? 
                AND status IN ('pending', 'confirmed')
            ");
      $stmt->execute([$bike_id]);
      $result = $stmt->fetch();

      if ($result['booking_count'] > 0) {
        $_SESSION['error'] = "Cannot delete bike: It has active or pending bookings. Cancel them first.";
      } else {
        // Smazat kolo (i s completed/cancelled bookings)
        // Nejdřív smazat všechny bookings
        $stmt = $pdo->prepare("DELETE FROM bookings WHERE bike_id = ?");
        $stmt->execute([$bike_id]);

        // Pak smazat kolo
        $stmt = $pdo->prepare("DELETE FROM bikes WHERE id = ?");
        $stmt->execute([$bike_id]);

        $_SESSION['success'] = "Bike deleted successfully!";
      }
    } else {
      $_SESSION['error'] = "Bike not found or you don't have permission to delete it.";
    }
  } catch(PDOException $e) {
    $_SESSION['error'] = "Error deleting bike: " . $e->getMessage();
  }

  header("Location: my_bikes.php");
  exit;
}

// Zpracování toggle dostupnosti
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
  $bike_id = $_GET['toggle'];

  try {
    $stmt = $pdo->prepare("SELECT available FROM bikes WHERE id = ? AND owner_id = ?");
    $stmt->execute([$bike_id, $user_id]);
    $bike = $stmt->fetch();

    if ($bike) {
      $new_status = $bike['available'] ? 0 : 1;
      $stmt = $pdo->prepare("UPDATE bikes SET available = ? WHERE id = ?");
      $stmt->execute([$new_status, $bike_id]);
      $_SESSION['success'] = "Bike availability updated!";
    }
  } catch(PDOException $e) {
    $_SESSION['error'] = "Error updating bike: " . $e->getMessage();
  }

  header("Location: my_bikes.php");
  exit;
}

// Získat moje kola
try {
  $stmt = $pdo->prepare("
        SELECT 
            b.*,
            COUNT(DISTINCT bk.id) as total_bookings,
            COUNT(DISTINCT CASE WHEN bk.status = 'pending' THEN bk.id END) as pending_bookings,
            COALESCE(SUM(CASE WHEN bk.status = 'completed' THEN bk.total_price ELSE 0 END), 0) as total_earned
        FROM bikes b
        LEFT JOIN bookings bk ON b.id = bk.bike_id
        WHERE b.owner_id = ?
        GROUP BY b.id
        ORDER BY b.created_at DESC
    ");
  $stmt->execute([$user_id]);
  $my_bikes = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Celková statistika
  $total_bikes = count($my_bikes);
  $available_bikes = count(array_filter($my_bikes, fn($b) => $b['available'] == 1));
  $total_earnings = array_sum(array_column($my_bikes, 'total_earned'));

} catch(PDOException $e) {
  $error = "Database error: " . $e->getMessage();
  $my_bikes = [];
}

// Zprávy
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <script src="https://cdn.tailwindcss.com"></script>
  <title>My Bikes - BikeRent</title>
</head>
<body class="bg-black text-white min-h-screen">

<!-- Header -->
<?php include '../components/header.php'; ?>

<!-- Main Content -->
<main class="container mx-auto px-6 py-12">

  <!-- Page Header -->
  <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
    <div>
      <h1 class="text-4xl font-bold mb-2">My Bikes</h1>
      <p class="text-gray-400">Manage your bike listings and earnings</p>
    </div>
    <a href="add_bike.php" class="px-6 py-3 bg-green-600 hover:bg-green-700 rounded-lg transition font-semibold">
      + Add New Bike
    </a>
  </div>

  <!-- Success/Error Messages -->
  <?php if (!empty($success)): ?>
    <div class="bg-green-900/50 border border-green-700 rounded-lg p-4 mb-6">
      <p class="text-sm"><?php echo htmlspecialchars($success); ?></p>
    </div>
  <?php endif; ?>

  <?php if (!empty($error)): ?>
    <div class="bg-red-900/50 border border-red-700 rounded-lg p-4 mb-6">
      <p class="text-sm"><?php echo htmlspecialchars($error); ?></p>
    </div>
  <?php endif; ?>

  <!-- Stats Cards -->
  <div class="grid md:grid-cols-2 gap-6 mb-12">
    <div class="bg-gray-900 border border-gray-700 rounded-xl p-6">
      <div class="flex items-center justify-between mb-2">
        <span class="text-gray-400 text-sm">Total Bikes</span>
        <span class="text-2xl">🚴</span>
      </div>
      <div class="text-3xl font-bold text-cyan-500"><?php echo $total_bikes; ?></div>
    </div>

    <div class="bg-gray-900 border border-gray-700 rounded-xl p-6">
      <div class="flex items-center justify-between mb-2">
        <span class="text-gray-400 text-sm">Available</span>
        <span class="text-2xl">✅</span>
      </div>
      <div class="text-3xl font-bold text-green-500"><?php echo $available_bikes; ?></div>
    </div>
  </div>

  <!-- Bikes List -->
  <?php if (empty($my_bikes)): ?>
    <div class="bg-gray-900 border border-gray-700 rounded-xl p-12 text-center">
      <div class="text-6xl mb-4">🚴</div>
      <h3 class="text-2xl font-bold mb-2">No bikes yet</h3>
      <p class="text-gray-400 mb-6">Start earning by listing your first bike!</p>
      <a href="add_bike.php" class="inline-block px-6 py-3 bg-green-600 hover:bg-green-700 rounded-lg transition font-semibold">
        + Add Your First Bike
      </a>
    </div>
  <?php else: ?>

    <div class="space-y-4">
      <?php foreach ($my_bikes as $bike): ?>
        <div class="bg-gray-900 border border-gray-700 rounded-xl overflow-hidden hover:border-gray-700 transition">
          <div class="flex flex-col md:flex-row">

            <!-- Bike Image -->
            <div class="flex items-center justify-center w-full md:w-48 h-48  text-6xl flex-shrink-0">
              <?php
              $emoji = '🚴';
              if (strpos(strtolower($bike['bike_type']), 'mountain') !== false) $emoji = '🚵';
              if (strpos(strtolower($bike['bike_type']), 'electric') !== false) $emoji = '⚡';
              if (strpos(strtolower($bike['bike_type']), 'road') !== false) $emoji = '🚴';
              echo $emoji;
              ?>
            </div>

            <!-- Bike Details -->
            <div class="flex-grow p-6">
              <div class="flex flex-col md:flex-row justify-between items-start gap-4 mb-4">
                <div>
                  <div class="flex items-center gap-3 mb-2">
                    <h3 class="text-2xl font-bold"><?php echo htmlspecialchars($bike['bike_type']); ?></h3>
                    <?php if ($bike['available']): ?>
                      <span class="bg-green-900/50 text-green-500 text-xs px-3 py-1 rounded">Available</span>
                    <?php else: ?>
                      <span class="bg-gray-700 text-gray-400 text-xs px-3 py-1 rounded">Unavailable</span>
                    <?php endif; ?>
                  </div>
                  <p class="text-gray-400"><?php echo htmlspecialchars($bike['description'] ?? 'No description'); ?></p>
                </div>

                <div class="text-right">
                  <div class="text-3xl font-bold text-cyan-500 mb-1">
                    <?php echo number_format($bike['price_per_day'], 0); ?> Kč
                    <span class="text-sm text-gray-500">/day</span>
                  </div>
                </div>
              </div>

              <!-- Stats & Info -->
              <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-4 text-sm">
                <div>
                  <div class="text-gray-400">Location</div>
                  <div class="font-semibold">📍 <?php echo htmlspecialchars($bike['city'] ?? 'Not set'); ?></div>
                </div>
                <div>
                  <div class="text-gray-400">Frame Size</div>
                  <div class="font-semibold">📏 <?php echo htmlspecialchars($bike['frame_size'] ?? 'N/A'); ?></div>
                </div>
                <div>
                  <div class="text-gray-400">Total Bookings</div>
                  <div class="font-semibold">📊 <?php echo $bike['total_bookings']; ?></div>
                </div>
              </div>

              <?php if ($bike['pending_bookings'] > 0): ?>
                <div class="bg-yellow-900/30 border border-yellow-700 rounded-lg p-3 mb-4">
                  <div class="flex items-center gap-2 text-sm text-yellow-500">
                    <span>⚠️</span>
                    <span><strong><?php echo $bike['pending_bookings']; ?></strong> pending booking<?php echo $bike['pending_bookings'] > 1 ? 's' : ''; ?> waiting for your approval</span>
                  </div>
                </div>
              <?php endif; ?>

              <!-- Action Buttons -->
              <div class="flex flex-wrap gap-3">
                <a href="edit_bike.php?id=<?php echo $bike['id']; ?>"
                   class="px-4 py-2 bg-cyan-600 hover:bg-cyan-800 rounded-lg transition text-sm font-semibold">
                  ✏️ Edit
                </a>

                <a href="?toggle=<?php echo $bike['id']; ?>"
                   class="px-4 py-2 <?php echo $bike['available'] ? 'bg-cyan-600 hover:bg-cyan-800' : 'bg-gray-900 hover:bg-gray-800'; ?> rounded-lg transition text-sm font-semibold"
                   onclick="return confirm('Are you sure you want to <?php echo $bike['available'] ? 'hide' : 'show'; ?> this bike?')">
                  <?php echo $bike['available'] ? '👁️ Hide' : '✅ Show'; ?>
                </a>

                <a href="bike_bookings.php?id=<?php echo $bike['id']; ?>"
                   class="px-4 py-2 border border-cyan-600 hover:bg-cyan-800 rounded-lg transition text-sm font-semibold">
                  📅 View Bookings (<?php echo $bike['total_bookings']; ?>)
                </a>

                <a href="?delete=<?php echo $bike['id']; ?>"
                   class="px-4 py-2 border border-red-600 text-red-600 hover:bg-red-600 hover:text-white rounded-lg transition text-sm font-semibold"
                   onclick="return confirm('Are you sure you want to delete this bike? This action cannot be undone.')">
                  🗑️ Delete
                </a>
              </div>
            </div>

          </div>
        </div>
      <?php endforeach; ?>
    </div>

  <?php endif; ?>

</main>

<!-- Footer -->
<?php include '../components/footer.php'; ?>

</body>
</html>
