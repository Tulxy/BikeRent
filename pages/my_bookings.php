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

// Zpracování zrušení rezervace
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
  $booking_id = $_GET['cancel'];

  try {
    // Zkontrolovat, že rezervace patří uživateli a lze ji zrušit
    $stmt = $pdo->prepare("
            SELECT id, status FROM bookings 
            WHERE id = ? AND renter_id = ? 
            AND status IN ('pending', 'confirmed')
        ");
    $stmt->execute([$booking_id, $user_id]);

    if ($stmt->fetch()) {
      // Zrušit rezervaci
      $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
      $stmt->execute([$booking_id]);
      $_SESSION['success'] = "Booking cancelled successfully!";
    } else {
      $_SESSION['error'] = "Booking not found or cannot be cancelled.";
    }
  } catch(PDOException $e) {
    $_SESSION['error'] = "Error cancelling booking: " . $e->getMessage();
  }

  header("Location: my_bookings.php");
  exit;
}

// Získat všechny moje rezervace
try {
  $stmt = $pdo->prepare("
        SELECT 
            b.id,
            b.rental_date,
            b.rental_days,
            b.total_price,
            b.status,
            b.created_at,
            bk.bike_type,
            bk.frame_size,
            bk.city as bike_city,
            bk.address as bike_address,
            u.name as owner_name,
            u.phone as owner_phone,
            u.email as owner_email
        FROM bookings b
        JOIN bikes bk ON b.bike_id = bk.id
        JOIN users u ON bk.owner_id = u.id
        WHERE b.renter_id = ?
        ORDER BY 
            CASE 
                WHEN b.status = 'pending' THEN 1
                WHEN b.status = 'confirmed' THEN 2
                WHEN b.status = 'completed' THEN 3
                WHEN b.status = 'cancelled' THEN 4
            END,
            b.rental_date DESC
    ");
  $stmt->execute([$user_id]);
  $all_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Rozdělit rezervace podle statusu
  $pending_bookings = array_filter($all_bookings, fn($b) => $b['status'] === 'pending');
  $confirmed_bookings = array_filter($all_bookings, fn($b) => $b['status'] === 'confirmed');
  $cancelled_bookings = array_filter($all_bookings, fn($b) => $b['status'] === 'cancelled');
  $completed_bookings = array_filter($all_bookings, fn($b) => $b['status'] === 'completed');

  // Statistiky
  $total_bookings = count($all_bookings);
  $active_bookings = count($pending_bookings) + count($confirmed_bookings);

} catch(PDOException $e) {
  $error = "Database error: " . $e->getMessage();
  $all_bookings = [];
}

// Zprávy
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

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
  <title>My Bookings - BikeRent</title>
</head>
<body class="bg-black text-white min-h-screen">

<!-- Header -->
<?php include '../components/header.php'; ?>

<!-- Main Content -->
<main class="container mx-auto px-6 py-12">

  <!-- Page Header -->
  <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
    <div>
      <h1 class="text-4xl font-bold mb-2">My Bookings</h1>
      <p class="text-gray-400">Track your bike rental bookings</p>
    </div>
    <a href="rentals.php" class="px-6 py-3 bg-cyan-600 hover:bg-cyan-700 rounded-lg transition font-semibold">
      Book Another Bike
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
        <span class="text-gray-400 text-sm">Total Bookings</span>
        <span class="text-2xl">📊</span>
      </div>
      <div class="text-3xl font-bold text-cyan-500"><?php echo $total_bookings; ?></div>
    </div>

    <div class="bg-gray-900 border border-gray-700 rounded-xl p-6">
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
      <div class="text-6xl mb-4">📅</div>
      <h3 class="text-2xl font-bold mb-2">No bookings yet</h3>
      <p class="text-gray-400 mb-6">Start your adventure by booking a bike!</p>
      <a href="../pages/rentals.php" class="inline-block px-6 py-3 bg-cyan-600 hover:bg-cyan-700 rounded-lg transition font-semibold">
        Browse Bikes
      </a>
    </div>
  <?php else: ?>

    <!-- Pending Bookings -->
    <?php if (!empty($pending_bookings)): ?>
      <div class="mb-12">
        <h2 class="text-2xl font-bold mb-6 flex items-center gap-3">
          <span>⏳</span>
          <span>Pending Approval</span>
          <span class="bg-yellow-900/50 text-yellow-500 text-sm px-3 py-1 rounded"><?php echo count($pending_bookings); ?></span>
        </h2>

        <div class="space-y-4">
          <?php foreach ($pending_bookings as $booking): ?>
            <div class="bg-gray-900 border border-yellow-800 rounded-xl p-6 hover:border-yellow-700 transition">
              <div class="flex flex-col md:flex-row gap-6">

                <!-- Bike Image -->
                <div class="bg-gradient-to-br from-gray-800 to-gray-900 w-full md:w-32 h-32 rounded-lg flex items-center justify-center text-5xl flex-shrink-0">
                  <?php
                  $emoji = '🚴';
                  if (strpos(strtolower($booking['bike_type']), 'mountain') !== false) $emoji = '🚵';
                  if (strpos(strtolower($booking['bike_type']), 'electric') !== false) $emoji = '⚡';
                  echo $emoji;
                  ?>
                </div>

                <!-- Booking Details -->
                <div class="flex-grow">
                  <div class="flex flex-col md:flex-row justify-between items-start gap-4 mb-4">
                    <div>
                      <div class="flex items-center gap-3 mb-2">
                        <h3 class="text-xl font-bold"><?php echo htmlspecialchars($booking['bike_type']); ?></h3>
                        <?php echo getStatusBadge($booking['status']); ?>
                      </div>
                      <p class="text-gray-400 text-sm">by <?php echo htmlspecialchars($booking['owner_name']); ?></p>
                    </div>
                    <div class="text-2xl font-bold text-cyan-500">
                      <?php echo number_format($booking['total_price'], 0); ?> Kč
                    </div>
                  </div>

                  <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4 text-sm">
                    <div>
                      <div class="text-gray-400">Start Date</div>
                      <div class="font-semibold">📅 <?php echo date('M d, Y', strtotime($booking['rental_date'])); ?></div>
                    </div>
                    <div>
                      <div class="text-gray-400">Duration</div>
                      <div class="font-semibold">⏱️ <?php echo $booking['rental_days']; ?> days</div>
                    </div>
                    <div>
                      <div class="text-gray-400">Location</div>
                      <div class="font-semibold">📍 <?php echo htmlspecialchars($booking['bike_city']); ?></div>
                    </div>
                    <div>
                      <div class="text-gray-400">Frame Size</div>
                      <div class="font-semibold">📏 <?php echo htmlspecialchars($booking['frame_size']); ?></div>
                    </div>
                  </div>

                  <div class="bg-yellow-900/30 border border-yellow-700 rounded-lg p-3 mb-4">
                    <div class="text-sm text-yellow-500">
                      ⏳ Waiting for owner's approval. You'll be notified once they respond.
                    </div>
                  </div>

                  <div class="flex gap-3">
                    <a href="?cancel=<?php echo $booking['id']; ?>"
                       class="px-4 py-2 border border-red-600 text-red-600 hover:bg-red-600 hover:text-white rounded-lg transition text-sm font-semibold"
                       onclick="return confirm('Are you sure you want to cancel this booking?')">
                      Cancel Booking
                    </a>
                  </div>
                </div>

              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- Confirmed Bookings -->
    <?php if (!empty($confirmed_bookings)): ?>
      <div class="mb-12">
        <h2 class="text-2xl font-bold mb-6 flex items-center gap-3">
          <span>✅</span>
          <span>Confirmed Trips</span>
          <span class="bg-green-900/50 text-green-500 text-sm px-3 py-1 rounded"><?php echo count($confirmed_bookings); ?></span>
        </h2>

        <div class="space-y-4">
          <?php foreach ($confirmed_bookings as $booking): ?>
            <div class="bg-gray-900 border border-green-800 rounded-xl p-6 hover:border-green-700 transition">
              <div class="flex flex-col md:flex-row gap-6">

                <!-- Bike Image -->
                <div class="bg-gradient-to-br from-gray-800 to-gray-900 w-full md:w-32 h-32 rounded-lg flex items-center justify-center text-5xl flex-shrink-0">
                  <?php
                  $emoji = '🚴';
                  if (strpos(strtolower($booking['bike_type']), 'mountain') !== false) $emoji = '🚵';
                  if (strpos(strtolower($booking['bike_type']), 'electric') !== false) $emoji = '⚡';
                  echo $emoji;
                  ?>
                </div>

                <!-- Booking Details -->
                <div class="flex-grow">
                  <div class="flex flex-col md:flex-row justify-between items-start gap-4 mb-4">
                    <div>
                      <div class="flex items-center gap-3 mb-2">
                        <h3 class="text-xl font-bold"><?php echo htmlspecialchars($booking['bike_type']); ?></h3>
                        <?php echo getStatusBadge($booking['status']); ?>
                      </div>
                      <p class="text-gray-400 text-sm">by <?php echo htmlspecialchars($booking['owner_name']); ?></p>
                    </div>
                    <div class="text-2xl font-bold text-cyan-500">
                      <?php echo number_format($booking['total_price'], 0); ?> Kč
                    </div>
                  </div>

                  <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4 text-sm">
                    <div>
                      <div class="text-gray-400">Start Date</div>
                      <div class="font-semibold">📅 <?php echo date('M d, Y', strtotime($booking['rental_date'])); ?></div>
                    </div>
                    <div>
                      <div class="text-gray-400">Duration</div>
                      <div class="font-semibold">⏱️ <?php echo $booking['rental_days']; ?> days</div>
                    </div>
                    <div>
                      <div class="text-gray-400">Location</div>
                      <div class="font-semibold">📍 <?php echo htmlspecialchars($booking['bike_city']); ?></div>
                    </div>
                    <div>
                      <div class="text-gray-400">Frame Size</div>
                      <div class="font-semibold">📏 <?php echo htmlspecialchars($booking['frame_size']); ?></div>
                    </div>
                  </div>

                  <div class="bg-green-900/30 border border-green-700 rounded-lg p-4 mb-4">
                    <div class="font-semibold mb-2 text-green-500">✅ Booking Confirmed!</div>
                    <div class="text-sm text-gray-300 space-y-1">
                      <div><strong>Owner:</strong> <?php echo htmlspecialchars($booking['owner_name']); ?></div>
                      <div><strong>Phone:</strong> <?php echo htmlspecialchars($booking['owner_phone']); ?></div>
                      <div><strong>Email:</strong> <?php echo htmlspecialchars($booking['owner_email']); ?></div>
                      <?php if (!empty($booking['bike_address'])): ?>
                        <div><strong>Pickup:</strong> <?php echo htmlspecialchars($booking['bike_address']); ?></div>
                      <?php endif; ?>
                    </div>
                  </div>

                  <div class="flex gap-3">
                    <a href="?cancel=<?php echo $booking['id']; ?>"
                       class="px-4 py-2 border border-red-600 text-red-600 hover:bg-red-600 hover:text-white rounded-lg transition text-sm font-semibold"
                       onclick="return confirm('Are you sure you want to cancel this confirmed booking?')">
                      Cancel Booking
                    </a>
                  </div>
                </div>

              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- Cancelled Bookings (NOVÁ SEKCE) -->
    <?php if (!empty($cancelled_bookings)): ?>
      <div class="mb-12">
        <h2 class="text-2xl font-bold mb-6 flex items-center gap-3">
          <span>❌</span>
          <span>Cancelled Bookings</span>
          <span class="bg-red-900/50 text-red-500 text-sm px-3 py-1 rounded"><?php echo count($cancelled_bookings); ?></span>
        </h2>

        <div class="space-y-4">
          <?php foreach ($cancelled_bookings as $booking): ?>
            <div class="bg-gray-900 border border-red-800 rounded-xl p-6 hover:border-red-700 transition">
              <div class="flex flex-col md:flex-row gap-6">

                <!-- Bike Image -->
                <div class="bg-gradient-to-br from-gray-800 to-gray-900 w-full md:w-32 h-32 rounded-lg flex items-center justify-center text-5xl flex-shrink-0">
                  <?php
                  $emoji = '🚴';
                  if (strpos(strtolower($booking['bike_type']), 'mountain') !== false) $emoji = '🚵';
                  if (strpos(strtolower($booking['bike_type']), 'electric') !== false) $emoji = '⚡';
                  echo $emoji;
                  ?>
                </div>

                <!-- Booking Details -->
                <div class="flex-grow">
                  <div class="flex flex-col md:flex-row justify-between items-start gap-4 mb-4">
                    <div>
                      <div class="flex items-center gap-3 mb-2">
                        <h3 class="text-xl font-bold"><?php echo htmlspecialchars($booking['bike_type']); ?></h3>
                        <?php echo getStatusBadge($booking['status']); ?>
                      </div>
                      <p class="text-gray-400 text-sm">by <?php echo htmlspecialchars($booking['owner_name']); ?></p>
                    </div>
                    <div class="text-2xl font-bold text-gray-500 line-through">
                      <?php echo number_format($booking['total_price'], 0); ?> Kč
                    </div>
                  </div>

                  <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4 text-sm">
                    <div>
                      <div class="text-gray-400">Start Date</div>
                      <div class="font-semibold">📅 <?php echo date('M d, Y', strtotime($booking['rental_date'])); ?></div>
                    </div>
                    <div>
                      <div class="text-gray-400">Duration</div>
                      <div class="font-semibold">⏱️ <?php echo $booking['rental_days']; ?> days</div>
                    </div>
                    <div>
                      <div class="text-gray-400">Location</div>
                      <div class="font-semibold">📍 <?php echo htmlspecialchars($booking['bike_city']); ?></div>
                    </div>
                    <div>
                      <div class="text-gray-400">Cancelled</div>
                      <div class="font-semibold text-red-500">❌ Declined</div>
                    </div>
                  </div>

                  <div class="bg-red-900/30 border border-red-700 rounded-lg p-4">
                    <div class="text-sm text-red-400">
                      ❌ This booking was declined by the owner. You can book another bike or try different dates.
                    </div>
                  </div>
                </div>

              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- Completed Bookings -->
    <?php if (!empty($completed_bookings)): ?>
      <div>
        <h2 class="text-2xl font-bold mb-6 flex items-center gap-3">
          <span>✅</span>
          <span>Completed Trips</span>
        </h2>

        <div class="space-y-4">
          <?php foreach ($completed_bookings as $booking): ?>
            <div class="bg-gray-900 border border-gray-800 rounded-xl p-6 opacity-75 hover:opacity-100 transition">
              <div class="flex flex-col md:flex-row justify-between items-start gap-4">
                <div>
                  <div class="flex items-center gap-3 mb-2">
                    <h3 class="text-lg font-bold"><?php echo htmlspecialchars($booking['bike_type']); ?></h3>
                    <?php echo getStatusBadge($booking['status']); ?>
                  </div>
                  <div class="text-sm text-gray-400 space-y-1">
                    <div>📅 <?php echo date('M d, Y', strtotime($booking['rental_date'])); ?> • <?php echo $booking['rental_days']; ?> days</div>
                    <div>👤 <?php echo htmlspecialchars($booking['owner_name']); ?></div>
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

    <!-- Past Bookings -->
    <?php if (!empty($past_bookings)): ?>
      <div>
        <h2 class="text-2xl font-bold mb-6 flex items-center gap-3">
          <span>📜</span>
          <span>Past Bookings</span>
        </h2>

        <div class="space-y-4">
          <?php foreach ($past_bookings as $booking): ?>
            <div class="bg-gray-900 border border-gray-800 rounded-xl p-6 opacity-75 hover:opacity-100 transition">
              <div class="flex flex-col md:flex-row justify-between items-start gap-4">
                <div>
                  <div class="flex items-center gap-3 mb-2">
                    <h3 class="text-lg font-bold"><?php echo htmlspecialchars($booking['bike_type']); ?></h3>
                    <?php echo getStatusBadge($booking['status']); ?>
                  </div>
                  <div class="text-sm text-gray-400 space-y-1">
                    <div>📅 <?php echo date('M d, Y', strtotime($booking['rental_date'])); ?> • <?php echo $booking['rental_days']; ?> days</div>
                    <div>👤 <?php echo htmlspecialchars($booking['owner_name']); ?></div>
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
