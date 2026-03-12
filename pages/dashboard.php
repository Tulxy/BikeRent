<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../auth/login.php");
    exit;
}

// Jdi o složku výš do rootu
require_once '../config.php';
$pdo = getDB();

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Získat statistiky pro dashboard
try {
  // Počet mých kol
  $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM bikes WHERE owner_id = ?");
  $stmt->execute([$user_id]);
  $my_bikes_count = $stmt->fetch()['count'];

  // Počet aktivních rezervací (co jsem si půjčil)
  $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM bookings b
        JOIN bikes bk ON b.bike_id = bk.id
        WHERE b.renter_id = ? 
        AND b.status IN ('pending', 'confirmed')
    ");
  $stmt->execute([$user_id]);
  $my_bookings_count = $stmt->fetch()['count'];

  // Počet lidí co si půjčují moje kola
  $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM bookings b
        JOIN bikes bk ON b.bike_id = bk.id
        WHERE bk.owner_id = ? 
        AND b.status IN ('pending', 'confirmed')
    ");
  $stmt->execute([$user_id]);
  $my_rentals_count = $stmt->fetch()['count'];

  // Celkové výdělky
  $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(b.total_price), 0) as total 
        FROM bookings b
        JOIN bikes bk ON b.bike_id = bk.id
        WHERE bk.owner_id = ? 
        AND b.status = 'completed'
    ");
  $stmt->execute([$user_id]);
  $total_earnings = $stmt->fetch()['total'];

  // Nejnovější rezervace mých kol (co čekají na potvrzení)
  $stmt = $pdo->prepare("
        SELECT 
            b.id,
            b.rental_date,
            b.rental_days,
            b.total_price,
            b.status,
            u.name as renter_name,
            bk.bike_type
        FROM bookings b
        JOIN bikes bk ON b.bike_id = bk.id
        JOIN users u ON b.renter_id = u.id
        WHERE bk.owner_id = ? 
        AND b.status = 'pending'
        ORDER BY b.created_at DESC
        LIMIT 5
    ");
  $stmt->execute([$user_id]);
  $pending_rentals = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Moje nadcházející výlety (co jsem si půjčil)
  $stmt = $pdo->prepare("
        SELECT 
            b.id,
            b.rental_date,
            b.rental_days,
            b.total_price,
            b.status,
            u.name as owner_name,
            bk.bike_type,
            bk.city
        FROM bookings b
        JOIN bikes bk ON b.bike_id = bk.id
        JOIN users u ON bk.owner_id = u.id
        WHERE b.renter_id = ? 
        AND b.status IN ('confirmed', 'pending')
        AND b.rental_date >= CURDATE()
        ORDER BY b.rental_date ASC
        LIMIT 5
    ");
  $stmt->execute([$user_id]);
  $my_upcoming_trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
  $error = "Database error: " . $e->getMessage();
}
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <script src="https://cdn.tailwindcss.com"></script>
  <title>Dashboard - BikeRent</title>
</head>
<body class="bg-black text-white min-h-screen">

<!-- Header -->
<?php include '../components/header.php'; ?>

<!-- Main Content -->
<main class="container mx-auto px-6 py-12">

  <!-- Welcome Section -->
  <div class="mb-12">
    <h1 class="text-4xl font-bold mb-2">Dashboard</h1>
    <p class="text-gray-400">Welcome back! Here's what's happening with your bikes.</p>
  </div>

  <!-- Stats Cards -->
  <div class="grid md:grid-cols-3 gap-6 mb-12">
    <!-- My Bikes -->
    <div class="bg-gray-900 border border-gray-700 rounded-xl p-6">
      <div class="flex items-center justify-between mb-2">
        <span class="text-gray-400 text-sm">My Bikes</span>
        <span class="text-2xl">🚴</span>
      </div>
      <div class="text-3xl font-bold text-cyan-500"><?php echo $my_bikes_count; ?></div>
      <a href="my_bikes.php" class="text-xs text-gray-500 hover:text-cyan-500 transition">View all →</a>
    </div>

    <!-- Active Bookings -->
    <div class="bg-gray-900 border border-gray-700 rounded-xl p-6">
      <div class="flex items-center justify-between mb-2">
        <span class="text-gray-400 text-sm">My Bookings</span>
        <span class="text-2xl">📅</span>
      </div>
      <div class="text-3xl font-bold text-cyan-500"><?php echo $my_bookings_count; ?></div>
      <a href="my_bookings.php" class="text-xs text-gray-500 hover:text-cyan-500 transition">View all →</a>
    </div>

    <!-- Pending Rentals -->
    <div class="bg-gray-900 border border-gray-700 rounded-xl p-6">
      <div class="flex items-center justify-between mb-2">
        <span class="text-gray-400 text-sm">Pending Rentals</span>
        <span class="text-2xl">⏳</span>
      </div>
      <div class="text-3xl font-bold text-yellow-500"><?php echo $my_rentals_count; ?></div>
      <a href="my_rentals.php" class="text-xs text-gray-500 hover:text-cyan-500 transition">Review →</a>
    </div>
  </div>

  <!-- Quick Actions -->
  <div class="mb-12">
    <h2 class="text-2xl font-bold mb-6">Quick Actions</h2>
    <div class="grid md:grid-cols-3 gap-6">

      <!-- Browse Bikes -->
      <a href="browse_bikes.php" class="bg-gray-900 border border-gray-700 rounded-xl p-6 hover:from-cyan-800 hover:to-cyan-700 transition group">
        <div class="text-4xl mb-4">🔍</div>
        <h3 class="text-xl font-bold mb-2">Browse Bikes</h3>
        <p class="text-gray-300 text-sm mb-4">Find bikes to rent</p>
        <span class="text-cyan-400 text-sm group-hover:text-cyan-300">Start browsing →</span>
      </a>

      <!-- Add Your Bike -->
      <a href="add_bike.php" class="bg-gray-900 border border-gray-700 rounded-xl p-6 hover:to-green-700 transition group">
        <div class="text-4xl mb-4">➕</div>
        <h3 class="text-xl font-bold mb-2">List Your Bike</h3>
        <p class="text-gray-300 text-sm mb-4">Add your bike and start earning</p>
        <span class="text-cyan-400 text-sm group-hover:text-cyan-300">Add bike →</span>
      </a>

    </div>
  </div>

  <!-- Two Column Layout -->
  <div class="grid md:grid-cols-2 gap-6">

    <!-- Pending Rental Requests -->
    <div class="bg-gray-900 border border-gray-700 rounded-xl p-6">
      <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-bold">Pending Requests</h2>
        <?php if (count($pending_rentals) > 0): ?>
          <span class="bg-yellow-900/50 text-yellow-500 text-xs px-2 py-1 rounded-full"><?php echo count($pending_rentals); ?> new</span>
        <?php endif; ?>
      </div>

      <?php if (empty($pending_rentals)): ?>
        <div class="text-center py-8 text-gray-500">
          <div class="text-4xl mb-2">📭</div>
          <p>No pending requests</p>
        </div>
      <?php else: ?>
        <div class="space-y-4">
          <?php foreach ($pending_rentals as $rental): ?>
            <div class="bg-black border border-gray-800 rounded-lg p-4 hover:border-gray-700 transition">
              <div class="flex justify-between items-start mb-2">
                <div>
                  <div class="font-semibold"><?php echo htmlspecialchars($rental['renter_name']); ?></div>
                  <div class="text-sm text-gray-400"><?php echo htmlspecialchars($rental['bike_type']); ?></div>
                </div>
                <span class="bg-yellow-900/50 text-yellow-500 text-xs px-2 py-1 rounded">Pending</span>
              </div>
              <div class="text-sm text-gray-400 mb-3">
                📅 <?php echo date('M d, Y', strtotime($rental['rental_date'])); ?> • <?php echo $rental['rental_days']; ?> days • <?php echo number_format($rental['total_price'], 0); ?> Kč
              </div>
              <div class="flex gap-2">
                <a href="manage_booking.php?id=<?php echo $rental['id']; ?>" class="flex-1 text-center py-2 bg-green-600 hover:bg-green-700 rounded text-sm transition">
                  Approve
                </a>
                <a href="manage_booking.php?id=<?php echo $rental['id']; ?>" class="flex-1 text-center py-2 border border-red-600 text-red-600 hover:bg-red-600 hover:text-white rounded text-sm transition">
                  Decline
                </a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Upcoming Trips -->
    <div class="bg-gray-900 border border-gray-700 rounded-xl p-6">
      <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-bold">Upcoming Trips</h2>
        <?php if (count($my_upcoming_trips) > 0): ?>
          <a href="my_bookings.php" class="text-cyan-500 text-sm hover:text-cyan-400 transition">View all →</a>
        <?php endif; ?>
      </div>

      <?php if (empty($my_upcoming_trips)): ?>
        <div class="text-center py-8 text-gray-500">
          <div class="text-4xl mb-2">🗓️</div>
          <p>No upcoming trips</p>
          <a href="browse_bikes.php" class="inline-block mt-4 px-4 py-2 bg-gray-950 border border- hover:bg-gray-800 rounded text-sm transition">
            Browse bikes
          </a>
        </div>
      <?php else: ?>
        <div class="space-y-4">
          <?php foreach ($my_upcoming_trips as $trip): ?>
            <div class="bg-black border border-gray-800 rounded-lg p-4 hover:border-gray-700 transition">
              <div class="flex justify-between items-start mb-2">
                <div>
                  <div class="font-semibold"><?php echo htmlspecialchars($trip['bike_type']); ?></div>
                  <div class="text-sm text-gray-400">by <?php echo htmlspecialchars($trip['owner_name']); ?></div>
                </div>
                <span class="<?php echo $trip['status'] === 'confirmed' ? 'bg-green-900/50 text-green-500' : 'bg-yellow-900/50 text-yellow-500'; ?> text-xs px-2 py-1 rounded capitalize">
                <?php echo $trip['status']; ?>
              </span>
              </div>
              <div class="text-sm text-gray-400 mb-3">
                📍 <?php echo htmlspecialchars($trip['city']); ?> • 📅 <?php echo date('M d, Y', strtotime($trip['rental_date'])); ?> • <?php echo $trip['rental_days']; ?> days
              </div>
              <div class="text-cyan-500 font-semibold"><?php echo number_format($trip['total_price'], 0); ?> Kč</div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  </div>

</main>

<!-- Footer -->
<?php include '../components/footer.php'; ?>

</body>
</html>
