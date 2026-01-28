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

// Kontrola ID kola
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
  $_SESSION['error'] = "Invalid bike ID";
  header("Location: my_bikes.php");
  exit;
}

$bike_id = $_GET['id'];

try {
  $stmt = $pdo->prepare("
        SELECT * FROM bikes 
        WHERE id = ? AND owner_id = ?
    ");
  $stmt->execute([$bike_id, $user_id]);
  $bike = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$bike) {
    $_SESSION['error'] = "Bike not found or you don't have permission to view its bookings";
    header("Location: my_bikes.php");
    exit;
  }

} catch(PDOException $e) {
  $_SESSION['error'] = "Database error";
  header("Location: my_bikes.php");
  exit;
}

try {
  $stmt = $pdo->prepare("
        SELECT 
            b.*,
            u.name as renter_name,
            u.email as renter_email,
            u.phone as renter_phone
        FROM bookings b
        JOIN users u ON b.renter_id = u.id
        WHERE b.bike_id = ?
        ORDER BY 
            CASE 
                WHEN b.status = 'pending' THEN 1
                WHEN b.status = 'confirmed' THEN 2
                WHEN b.status = 'completed' THEN 3
                WHEN b.status = 'cancelled' THEN 4
            END,
            b.rental_date DESC
    ");
  $stmt->execute([$bike_id]);
  $all_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $pending = array_filter($all_bookings, fn($b) => $b['status'] === 'pending');
  $confirmed = array_filter($all_bookings, fn($b) => $b['status'] === 'confirmed');
  $past = array_filter($all_bookings, fn($b) => in_array($b['status'], ['completed', 'cancelled']));

  $total_bookings = count($all_bookings);
  $active_bookings = count($pending) + count($confirmed);

} catch(PDOException $e) {
  $error = "Database error: " . $e->getMessage();
  $all_bookings = [];
}

// Helper funkce pro status badge
function getStatusBadge($status) {
  switch($status) {
    case 'pending':
      return '<span class="bg-yellow-900/50 text-yellow-500 text-xs px-3 py-1 rounded">Pending</span>';
    case 'confirmed':
      return '<span class="bg-green-900/50 text-green-500 text-xs px-3 py-1 rounded">Confirmed</span>';
    case 'completed':
      return '<span class="bg-blue-900/50 text-blue-500 text-xs px-3 py-1 rounded">Completed</span>';
    case 'cancelled':
      return '<span class="bg-gray-700 text-gray-400 text-xs px-3 py-1 rounded">Cancelled</span>';
    default:
      return '<span class="bg-gray-700 text-gray-400 text-xs px-3 py-1 rounded">' . ucfirst($status) . '</span>';
  }
}
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <script src="https://cdn.tailwindcss.com"></script>
  <title>Bike Bookings - BikeRent</title>
</head>
<body class="bg-black text-white min-h-screen">

<!-- Header -->
<?php include '../components/header.php'; ?>

<!-- Main Content -->
<main class="container mx-auto px-6 py-12">

  <!-- Page Header -->
  <div class="mb-8">
    <a href="my_bikes.php" class="text-cyan-500 hover:text-cyan-400 transition text-sm mb-2 inline-block">
      ← Back to My Bikes
    </a>
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
      <div>
        <h1 class="text-4xl font-bold mb-2">Bookings for <?php echo htmlspecialchars($bike['bike_type']); ?></h1>
        <p class="text-gray-400">Manage all bookings for this bike</p>
      </div>
    </div>
  </div>

  <!-- Bike Info Card -->
  <div class="bg-gray-900 border border-gray-700 rounded-xl p-6 mb-8">
    <div class="flex items-center gap-6">
      <div class="bg-gradient-to-br from-gray-800 to-gray-900 w-24 h-24 rounded-lg flex items-center justify-center text-5xl">
        <?php
        $emoji = '🚴';
        if (strpos(strtolower($bike['bike_type']), 'mountain') !== false) $emoji = '🚵';
        if (strpos(strtolower($bike['bike_type']), 'electric') !== false) $emoji = '⚡';
        echo $emoji;
        ?>
      </div>
      <div class="flex-grow">
        <h2 class="text-2xl font-bold mb-2"><?php echo htmlspecialchars($bike['bike_type']); ?></h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
          <div>
            <span class="text-gray-400">Frame Size:</span>
            <span class="font-semibold ml-2"><?php echo htmlspecialchars($bike['frame_size']); ?></span>
          </div>
          <div>
            <span class="text-gray-400">Price:</span>
            <span class="font-semibold ml-2"><?php echo number_format($bike['price_per_day'], 0); ?> Kč/day</span>
          </div>
          <div>
            <span class="text-gray-400">Location:</span>
            <span class="font-semibold ml-2"><?php echo htmlspecialchars($bike['city']); ?></span>
          </div>
          <div>
            <span class="text-gray-400">Status:</span>
            <span class="font-semibold ml-2"><?php echo $bike['available'] ? '✅ Available' : '❌ Unavailable'; ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Stats Cards -->
  <div class="grid md:grid-cols-2 gap-6 mb-12">
    <div class="bg-gray-900 border border-gray-700 rounded-xl p-6">
      <div class="flex items-center justify-between mb-2">
        <span class="text-gray-400 text-sm">Total Bookings</span>
        <span class="text-2xl">📊</span>
      </div>
      <div class="text-3xl font-bold text-cyan-500"><?php echo $total_bookings; ?></div>
    </div>

    <div class="bg-gray-900 border border-gray-800 rounded-xl p-6">
      <div class="flex items-center justify-between mb-2">
        <span class="text-gray-400 text-sm">Active Bookings</span>
        <span class="text-2xl">🚴</span>
      </div>
      <div class="text-3xl font-bold text-green-500"><?php echo $active_bookings; ?></div>
    </div>
  </div>

  <!-- No Bookings State -->
  <?php if (empty($all_bookings)): ?>
    <div class="bg-gray-900 border border-gray-700 rounded-xl p-12 text-center">
      <div class="text-6xl mb-4">📭</div>
      <h3 class="text-2xl font-bold mb-2">No bookings yet</h3>
      <p class="text-gray-400 mb-6">This bike hasn't received any booking requests yet</p>
    </div>
  <?php else: ?>

    <!-- Pending Bookings -->
    <?php if (!empty($pending)): ?>
      <div class="mb-12">
        <h2 class="text-2xl font-bold mb-6 flex items-center gap-3">
          <span>⏳</span>
          <span>Pending Requests</span>
          <span class="bg-yellow-900/50 text-yellow-500 text-sm px-3 py-1 rounded"><?php echo count($pending); ?></span>
        </h2>

        <div class="space-y-4">
          <?php foreach ($pending as $booking): ?>
            <div class="bg-gray-900 border border-yellow-800 rounded-xl p-6 hover:border-yellow-700 transition">
              <div class="flex flex-col md:flex-row justify-between items-start gap-4 mb-4">
                <div>
                  <div class="flex items-center gap-3 mb-2">
                    <h3 class="text-xl font-bold"><?php echo htmlspecialchars($booking['renter_name']); ?></h3>
                    <?php echo getStatusBadge($booking['status']); ?>
                  </div>
                  <div class="text-sm text-gray-400 space-y-1">
                    <div>📧 <?php echo htmlspecialchars($booking['renter_email']); ?></div>
                    <div>📞 <?php echo htmlspecialchars($booking['renter_phone']); ?></div>
                  </div>
                </div>
                <div class="text-2xl font-bold text-cyan-500">
                  <?php echo number_format($booking['total_price'], 0); ?> Kč
                </div>
              </div>

              <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-4 text-sm">
                <div>
                  <div class="text-gray-400">Start Date</div>
                  <div class="font-semibold">📅 <?php echo date('M d, Y', strtotime($booking['rental_date'])); ?></div>
                </div>
                <div>
                  <div class="text-gray-400">Duration</div>
                  <div class="font-semibold">⏱️ <?php echo $booking['rental_days']; ?> days</div>
                </div>
                <div>
                  <div class="text-gray-400">Requested</div>
                  <div class="font-semibold">📝 <?php echo date('M d', strtotime($booking['created_at'])); ?></div>
                </div>
              </div>

              <?php if (!empty($booking['special_requirements'])): ?>
                <div class="bg-black border border-gray-800 rounded-lg p-3 mb-4">
                  <div class="text-xs text-gray-400 mb-1">Special Requirements:</div>
                  <div class="text-sm"><?php echo nl2br(htmlspecialchars($booking['special_requirements'])); ?></div>
                </div>
              <?php endif; ?>

              <div class="flex gap-3">
                <a href="manage_booking.php?id=<?php echo $booking['id']; ?>"
                   class="flex-1 text-center py-2 bg-cyan-600 hover:bg-cyan-700 rounded-lg transition text-sm font-semibold">
                  Review Details
                </a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- Confirmed Bookings -->
    <?php if (!empty($confirmed)): ?>
      <div class="mb-12">
        <h2 class="text-2xl font-bold mb-6 flex items-center gap-3">
          <span>✅</span>
          <span>Confirmed Bookings</span>
          <span class="bg-green-900/50 text-green-500 text-sm px-3 py-1 rounded"><?php echo count($confirmed); ?></span>
        </h2>

        <div class="space-y-4">
          <?php foreach ($confirmed as $booking): ?>
            <div class="bg-gray-900 border border-green-800 rounded-xl p-6 hover:border-green-700 transition">
              <div class="flex flex-col md:flex-row justify-between items-start gap-4 mb-4">
                <div>
                  <div class="flex items-center gap-3 mb-2">
                    <h3 class="text-xl font-bold"><?php echo htmlspecialchars($booking['renter_name']); ?></h3>
                    <?php echo getStatusBadge($booking['status']); ?>
                  </div>
                  <div class="text-sm text-gray-400 space-y-1">
                    <div>📧 <?php echo htmlspecialchars($booking['renter_email']); ?></div>
                    <div>📞 <?php echo htmlspecialchars($booking['renter_phone']); ?></div>
                  </div>
                </div>
                <div class="text-2xl font-bold text-cyan-500">
                  <?php echo number_format($booking['total_price'], 0); ?> Kč
                </div>
              </div>

              <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                <div>
                  <div class="text-gray-400">Start Date</div>
                  <div class="font-semibold">📅 <?php echo date('M d, Y', strtotime($booking['rental_date'])); ?></div>
                </div>
                <div>
                  <div class="text-gray-400">Duration</div>
                  <div class="font-semibold">⏱️ <?php echo $booking['rental_days']; ?> days</div>
                </div>
                <div>
                  <div class="text-gray-400">End Date</div>
                  <div class="font-semibold">
                    <?php
                    $end_date = date('M d, Y', strtotime($booking['rental_date'] . ' + ' . ($booking['rental_days']-1) . ' days'));
                    echo $end_date;
                    ?>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- Past Bookings -->
    <?php if (!empty($past)): ?>
      <div>
        <h2 class="text-2xl font-bold mb-6 flex items-center gap-3">
          <span>📜</span>
          <span>Past Bookings</span>
        </h2>

        <div class="space-y-4">
          <?php foreach ($past as $booking): ?>
            <div class="bg-gray-900 border border-gray-800 rounded-xl p-6 opacity-75 hover:opacity-100 transition">
              <div class="flex flex-col md:flex-row justify-between items-start gap-4">
                <div>
                  <div class="flex items-center gap-3 mb-2">
                    <h3 class="text-lg font-bold"><?php echo htmlspecialchars($booking['renter_name']); ?></h3>
                    <?php echo getStatusBadge($booking['status']); ?>
                  </div>
                  <div class="text-sm text-gray-400">
                    📅 <?php echo date('M d, Y', strtotime($booking['rental_date'])); ?> • <?php echo $booking['rental_days']; ?> days
                  </div>
                </div>
                <div class="text-xl font-bold text-gray-500">
                  <?php echo number_format($booking['total_price'], 0); ?> Kč
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
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
