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

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
  $_SESSION['error'] = "Invalid booking ID";
  header("Location: dashboard.php");
  exit;
}

$booking_id = $_GET['id'];

// Zpracování akce (approve/decline)
if (isset($_GET['action']) && in_array($_GET['action'], ['approve', 'decline'])) {
  $action = $_GET['action'];

  try {
    // Ověřit že rezervace patří mému kolu a je pending
    $stmt = $pdo->prepare("
      SELECT 
        b.id, 
        b.status, 
        b.bike_id,
        b.rental_date,
        b.rental_days,
        bk.owner_id, 
        u.name as renter_name
      FROM bookings b
      JOIN bikes bk ON b.bike_id = bk.id
      JOIN users u ON b.renter_id = u.id
      WHERE b.id = ? AND bk.owner_id = ?
    ");
    $stmt->execute([$booking_id, $user_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
      $_SESSION['error'] = "Booking not found or you don't have permission";
      header("Location: dashboard.php");
      exit;
    }

    if ($booking['status'] !== 'pending') {
      $_SESSION['error'] = "This booking has already been " . $booking['status'];
      header("Location: dashboard.php");
      exit;
    }

    // NOVÉ: Pokud schvalujeme, zkontrolovat překrývající se rezervace
    if ($action === 'approve') {

      // Vypočítat datum konce této rezervace
      $rental_start = $booking['rental_date'];
      $rental_end = date('Y-m-d', strtotime($rental_start . ' + ' . ($booking['rental_days'] - 1) . ' days'));

      // Zkontrolovat konflikty
      $stmt = $pdo->prepare("
        SELECT COUNT(*) as conflict_count
        FROM bookings
        WHERE bike_id = ?
        AND id != ?
        AND status = 'confirmed'
        AND (
          (rental_date <= ? AND DATE_ADD(rental_date, INTERVAL (rental_days - 1) DAY) >= ?)
          OR
          (rental_date <= ? AND DATE_ADD(rental_date, INTERVAL (rental_days - 1) DAY) >= ?)
          OR
          (rental_date >= ? AND DATE_ADD(rental_date, INTERVAL (rental_days - 1) DAY) <= ?)
        )
      ");
      $stmt->execute([
        $booking['bike_id'],
        $booking_id,
        $rental_start, $rental_start,
        $rental_end, $rental_end,
        $rental_start, $rental_end
      ]);

      $result = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($result['conflict_count'] > 0) {
        $_SESSION['error'] = "Cannot approve: This bike is already booked for these dates!";
        header("Location: manage_booking.php?id=" . $booking_id);
        exit;
      }
    }

    // Schválit nebo odmítnout
    $new_status = $action === 'approve' ? 'confirmed' : 'cancelled';
    $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $booking_id]);

    $message = $action === 'approve'
      ? 'Booking approved! ' . htmlspecialchars($booking['renter_name']) . ' has been notified.'
      : 'Booking declined. ' . htmlspecialchars($booking['renter_name']) . ' has been notified.';

    $_SESSION['success'] = $message;

  } catch(PDOException $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
  }

  header("Location: dashboard.php");
  exit;
}

// Získat detail rezervace
try {
  $stmt = $pdo->prepare("
    SELECT 
      b.*,
      bk.bike_type,
      bk.frame_size,
      bk.city as bike_city,
      bk.price_per_day,
      u.name as renter_name,
      u.email as renter_email,
      u.phone as renter_phone
    FROM bookings b
    JOIN bikes bk ON b.bike_id = bk.id
    JOIN users u ON b.renter_id = u.id
    WHERE b.id = ? AND bk.owner_id = ?
  ");
  $stmt->execute([$booking_id, $user_id]);
  $booking = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$booking) {
    $_SESSION['error'] = "Booking not found or you don't have permission to manage it";
    header("Location: dashboard.php");
    exit;
  }

} catch(PDOException $e) {
  $_SESSION['error'] = "Database error";
  header("Location: dashboard.php");
  exit;
}

// NOVÉ: Zkontrolovat konflikty pro zobrazení varování
$has_conflict = false;
$conflicting_bookings = [];

if ($booking['status'] === 'pending') {
  $rental_start = $booking['rental_date'];
  $rental_end = date('Y-m-d', strtotime($rental_start . ' + ' . ($booking['rental_days'] - 1) . ' days'));

  $stmt = $pdo->prepare("
    SELECT 
      b.*,
      u.name as conflict_renter_name
    FROM bookings b
    JOIN users u ON b.renter_id = u.id
    WHERE b.bike_id = ?
    AND b.id != ?
    AND b.status = 'confirmed'
    AND (
      (b.rental_date <= ? AND DATE_ADD(b.rental_date, INTERVAL (b.rental_days - 1) DAY) >= ?)
      OR
      (b.rental_date <= ? AND DATE_ADD(b.rental_date, INTERVAL (b.rental_days - 1) DAY) >= ?)
      OR
      (b.rental_date >= ? AND DATE_ADD(b.rental_date, INTERVAL (b.rental_days - 1) DAY) <= ?)
    )
  ");
  $stmt->execute([
    $booking['bike_id'],
    $booking['id'],
    $rental_start, $rental_start,
    $rental_end, $rental_end,
    $rental_start, $rental_end
  ]);

  $conflicting_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
  $has_conflict = !empty($conflicting_bookings);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <script src="https://cdn.tailwindcss.com"></script>
  <title>Manage Booking - BikeRent</title>
</head>
<body class="bg-black text-white min-h-screen">

<!-- Header -->
<header class="border-b border-gray-800 sticky top-0 bg-black z-50">
  <div class="container mx-auto px-6 py-4 flex justify-between items-center">
    <a href="dashboard.php" class="text-2xl font-bold hover:text-cyan-500 transition">BikeRent</a>
    <div class="flex items-center gap-4">
      <span class="text-gray-400 text-sm">Hello, <span class="text-white font-semibold"><?php echo htmlspecialchars($user_name); ?></span></span>
      <a href="../auth/logout.php" class="px-4 py-2 text-sm border border-red-600 text-red-600 hover:bg-red-600 hover:text-white rounded-lg transition">
        Logout
      </a>
    </div>
  </div>
</header>

<!-- Main Content -->
<main class="container mx-auto px-6 py-12 max-w-3xl">

  <div class="mb-6">
    <a href="dashboard.php" class="text-cyan-500 hover:text-cyan-400 text-sm inline-flex items-center gap-2">
      <span>←</span>
      <span>Back to Dashboard</span>
    </a>
  </div>

  <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">

    <!-- Header -->
    <div class="bg-gradient-to-r from-cyan-900 to-cyan-800 p-6">
      <h1 class="text-3xl font-bold mb-2">Review Booking Request</h1>
      <p class="text-cyan-200">Review the details and approve or decline this booking</p>
    </div>

    <!-- Booking Details -->
    <div class="p-8">

      <!-- Bike & Status -->
      <div class="flex items-start justify-between mb-8 pb-8 border-b border-gray-800">
        <div>
          <div class="flex items-center gap-3 mb-2">
            <div class="text-5xl">
              <?php
              $emoji = '🚴';
              if (strpos(strtolower($booking['bike_type']), 'mountain') !== false) $emoji = '🚵';
              if (strpos(strtolower($booking['bike_type']), 'electric') !== false) $emoji = '⚡';
              echo $emoji;
              ?>
            </div>
            <div>
              <h2 class="text-2xl font-bold"><?php echo htmlspecialchars($booking['bike_type']); ?></h2>
              <p class="text-gray-400">Frame size: <?php echo htmlspecialchars($booking['frame_size']); ?></p>
            </div>
          </div>
        </div>
        <div>
          <?php
          $status_badges = [
            'pending' => '<span class="bg-yellow-900/50 text-yellow-500 text-sm px-4 py-2 rounded">Pending</span>',
            'confirmed' => '<span class="bg-green-900/50 text-green-500 text-sm px-4 py-2 rounded">Confirmed</span>',
            'cancelled' => '<span class="bg-gray-700 text-gray-400 text-sm px-4 py-2 rounded">Cancelled</span>'
          ];
          echo $status_badges[$booking['status']] ?? '';
          ?>
        </div>
      </div>

      <!-- Renter Info -->
      <div class="mb-8">
        <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
          <span>👤</span>
          <span>Renter Information</span>
        </h3>
        <div class="bg-black border border-gray-800 rounded-lg p-6 space-y-3">
          <div class="grid md:grid-cols-2 gap-4">
            <div>
              <div class="text-sm text-gray-400 mb-1">Name</div>
              <div class="font-semibold text-lg"><?php echo htmlspecialchars($booking['renter_name']); ?></div>
            </div>
            <div>
              <div class="text-sm text-gray-400 mb-1">Email</div>
              <div class="font-semibold"><?php echo htmlspecialchars($booking['renter_email']); ?></div>
            </div>
          </div>
          <div>
            <div class="text-sm text-gray-400 mb-1">Phone</div>
            <div class="font-semibold text-lg"><?php echo htmlspecialchars($booking['renter_phone']); ?></div>
          </div>
        </div>
      </div>

      <!-- Rental Details -->
      <div class="mb-8">
        <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
          <span>📅</span>
          <span>Rental Details</span>
        </h3>
        <div class="grid md:grid-cols-3 gap-6">
          <div class="bg-black border border-gray-800 rounded-lg p-4">
            <div class="text-sm text-gray-400 mb-2">Start Date</div>
            <div class="text-xl font-bold"><?php echo date('M d, Y', strtotime($booking['rental_date'])); ?></div>
            <div class="text-sm text-gray-500 mt-1"><?php echo date('l', strtotime($booking['rental_date'])); ?></div>
          </div>

          <div class="bg-black border border-gray-800 rounded-lg p-4">
            <div class="text-sm text-gray-400 mb-2">Duration</div>
            <div class="text-xl font-bold"><?php echo $booking['rental_days']; ?> days</div>
            <div class="text-sm text-gray-500 mt-1">
              <?php
              $end_date = date('M d', strtotime($booking['rental_date'] . ' + ' . ($booking['rental_days']-1) . ' days'));
              echo "Until " . $end_date;
              ?>
            </div>
          </div>

          <div class="bg-black border border-gray-800 rounded-lg p-4">
            <div class="text-sm text-gray-400 mb-2">Location</div>
            <div class="text-xl font-bold"><?php echo htmlspecialchars($booking['bike_city']); ?></div>
            <div class="text-sm text-gray-500 mt-1">Pickup location</div>
          </div>
        </div>
      </div>

      <!-- Price Breakdown -->
      <div class="mb-8">
        <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
          <span>💰</span>
          <span>Price Breakdown</span>
        </h3>
        <div class="bg-cyan-900/20 border border-cyan-800 rounded-lg p-6">
          <div class="space-y-3 mb-4">
            <div class="flex justify-between text-gray-300">
              <span>Price per day:</span>
              <span class="font-semibold"><?php echo number_format($booking['price_per_day'], 0); ?> Kč</span>
            </div>
            <div class="flex justify-between text-gray-300">
              <span>Number of days:</span>
              <span class="font-semibold"><?php echo $booking['rental_days']; ?></span>
            </div>
          </div>
          <div class="border-t border-cyan-800 pt-4 flex justify-between items-center">
            <span class="text-lg font-bold">Total Amount:</span>
            <span class="text-4xl font-bold text-cyan-500"><?php echo number_format($booking['total_price'], 0); ?> Kč</span>
          </div>
        </div>
      </div>

      <!-- Special Requirements -->
      <?php if (!empty($booking['special_requirements'])): ?>
        <div class="mb-8">
          <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
            <span>📝</span>
            <span>Special Requirements</span>
          </h3>
          <div class="bg-black border border-gray-800 rounded-lg p-4">
            <p class="text-gray-300"><?php echo nl2br(htmlspecialchars($booking['special_requirements'])); ?></p>
          </div>
        </div>
      <?php endif; ?>

      <!-- Booking Date -->
      <div class="mb-8 text-sm text-gray-500 text-center">
        Request received: <?php echo date('M d, Y \a\t H:i', strtotime($booking['created_at'])); ?>
      </div>

      <!-- Action Buttons -->
      <?php if ($booking['status'] === 'pending'): ?>
        <div class="bg-yellow-900/20 border border-yellow-700 rounded-lg p-6 mb-6">
          <div class="flex items-start gap-3 mb-4">
            <span class="text-2xl">⚠️</span>
            <div>
              <div class="font-bold text-yellow-500 mb-1">Action Required</div>
              <p class="text-sm text-gray-300">This booking is waiting for your approval. Please review the details above and make a decision.</p>
            </div>
          </div>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
          <a href="?id=<?php echo $booking_id; ?>&action=approve"
             class="py-4 bg-green-600 hover:bg-green-700 rounded-lg font-bold transition text-center text-lg flex items-center justify-center gap-2">
            <span>✅</span>
            <span>Approve Booking</span>
          </a>
          <a href="?id=<?php echo $booking_id; ?>&action=decline"
             class="py-4 border-2 border-red-600 text-red-600 hover:bg-red-600 hover:text-white rounded-lg font-bold transition text-center text-lg flex items-center justify-center gap-2"
             onclick="return confirm('Are you sure you want to decline this booking request?')">
            <span>❌</span>
            <span>Decline Booking</span>
          </a>
        </div>
      <?php else: ?>
        <div class="bg-gray-800 border border-gray-700 rounded-lg p-6 text-center">
          <div class="text-lg font-semibold mb-2">
            This booking has already been <?php echo $booking['status']; ?>
          </div>
          <p class="text-gray-400 text-sm">No further action is required</p>
        </div>
      <?php endif; ?>

    </div>

  </div>

</main>

<!-- Footer -->
<footer class="border-t border-gray-800 mt-20 py-8 text-center text-gray-500 text-sm">
  <p>© 2026 BikeRent. Share the ride.</p>
</footer>

</body>
</html>
