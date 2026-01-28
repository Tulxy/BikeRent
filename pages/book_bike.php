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

// Získat ID kola
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
  $_SESSION['error'] = "Invalid bike ID";
  header("Location: browse_bikes.php");
  exit;
}

$bike_id = $_GET['id'];

// Získat informace o kole
try {
  $stmt = $pdo->prepare("
        SELECT 
            b.*,
            u.name as owner_name,
            u.phone as owner_phone,
            u.email as owner_email
        FROM bikes b
        JOIN users u ON b.owner_id = u.id
        WHERE b.id = ? AND b.available = 1
    ");
  $stmt->execute([$bike_id]);
  $bike = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$bike) {
    $_SESSION['error'] = "Bike not found or not available";
    header("Location: browse_bikes.php");
    exit;
  }

  // Kontrola, že uživatel nepůjčuje vlastní kolo
  if ($bike['owner_id'] == $user_id) {
    $_SESSION['error'] = "You cannot book your own bike";
    header("Location: my_bikes.php");
    exit;
  }

} catch(PDOException $e) {
  $_SESSION['error'] = "Database error: " . $e->getMessage();
  header("Location: browse_bikes.php");
  exit;
}

// Zpracování rezervace
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

  $rental_date = trim($_POST['rental_date']);
  $rental_days = intval($_POST['rental_days']);

  $errors = [];

  // Validace
  if (empty($rental_date)) {
    $errors[] = "Rental date is required";
  } else {
    $date = strtotime($rental_date);
    $today = strtotime(date('Y-m-d'));

    if ($date < $today) {
      $errors[] = "Rental date cannot be in the past";
    }
  }

  if ($rental_days < 1 || $rental_days > 30) {
    $errors[] = "Rental days must be between 1 and 30";
  }

  // NOVÉ: Kontrola dostupnosti kola pro vybrané datum
  if (empty($errors)) {
    try {
      $rental_end = date('Y-m-d', strtotime($rental_date . ' + ' . ($rental_days - 1) . ' days'));

      $stmt = $pdo->prepare("
                SELECT COUNT(*) as conflict_count
                FROM bookings
                WHERE bike_id = ?
                AND status IN ('confirmed', 'pending')
                AND (
                    (rental_date <= ? AND DATE_ADD(rental_date, INTERVAL (rental_days - 1) DAY) >= ?)
                    OR
                    (rental_date <= ? AND DATE_ADD(rental_date, INTERVAL (rental_days - 1) DAY) >= ?)
                    OR
                    (rental_date >= ? AND DATE_ADD(rental_date, INTERVAL (rental_days - 1) DAY) <= ?)
                )
            ");
      $stmt->execute([
        $bike_id,
        $rental_date, $rental_date,
        $rental_end, $rental_end,
        $rental_date, $rental_end
      ]);

      $result = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($result['conflict_count'] > 0) {
        $errors[] = "This bike is not available for the selected dates. Please choose different dates.";
      }

    } catch(PDOException $e) {
      $errors[] = "Error checking availability: " . $e->getMessage();
    }
  }

  if (!empty($errors)) {
    $_SESSION['errors'] = $errors;
    $_SESSION['old_data'] = $_POST;
  } else {
    // Vypočítat celkovou cenu
    $total_price = $bike['price_per_day'] * $rental_days;

    try {
      // Vytvořit rezervaci
      $stmt = $pdo->prepare("
                INSERT INTO bookings (
                    bike_id,
                    renter_id,
                    rental_date,
                    rental_days,
                    total_price,
                    status,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, 'pending', NOW())
            ");

      $stmt->execute([
        $bike_id,
        $user_id,
        $rental_date,
        $rental_days,
        $total_price,
        $special_requirements
      ]);

      $_SESSION['success'] = "Booking request sent! Waiting for owner's approval.";
      header("Location: my_bookings.php");
      exit;

    } catch(PDOException $e) {
      $_SESSION['errors'] = ["Database error: " . $e->getMessage()];
      $_SESSION['old_data'] = $_POST;
    }
  }
}

// Získat chyby a stará data
$errors = $_SESSION['errors'] ?? [];
$old_data = $_SESSION['old_data'] ?? [];
unset($_SESSION['errors'], $_SESSION['old_data']);

// Nastavit defaultní datum (zítra)
$default_date = $old_data['rental_date'] ?? date('Y-m-d', strtotime('+1 day'));
$default_days = $old_data['rental_days'] ?? 3;
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <script src="https://cdn.tailwindcss.com"></script>
  <title>Book Bike - BikeRent</title>
</head>
<body class="bg-black text-white min-h-screen">

<!-- Header -->
<header class="border-b border-gray-800 sticky top-0 bg-black z-50">
<?php include '../components/header.php' ?>
<!-- Main Content -->
<main class="container mx-auto px-6 py-12 max-w-5xl">

  <!-- Page Header -->
  <div class="mb-8">
    <a href="browse_bikes.php" class="text-cyan-500 hover:text-cyan-400 transition text-sm mb-2 inline-block">
      ← Back to Browse
    </a>
    <h1 class="text-4xl font-bold mb-2">Book This Bike</h1>
    <p class="text-gray-400">Complete the form below to send a booking request</p>
  </div>

  <!-- Error Messages -->
  <?php if (!empty($errors)): ?>
    <div class="bg-red-900/50 border border-red-700 rounded-lg p-4 mb-6">
      <div class="font-semibold mb-2">Please fix the following errors:</div>
      <ul class="text-sm space-y-1">
        <?php foreach ($errors as $error): ?>
          <li>• <?php echo htmlspecialchars($error); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="grid md:grid-cols-5 gap-8">

    <!-- Bike Details (Left Side) -->
    <div class="md:col-span-2">
      <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden sticky top-24">

        <!-- Bike Image -->
        <div class="bg-gradient-to-br from-gray-800 to-gray-900 h-64 flex items-center justify-center text-8xl">
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
          <h2 class="text-2xl font-bold mb-2"><?php echo htmlspecialchars($bike['bike_type']); ?></h2>

          <div class="text-3xl font-bold text-cyan-500 mb-4">
            <?php echo number_format($bike['price_per_day'], 0); ?> Kč
            <span class="text-sm text-gray-500">/day</span>
          </div>

          <div class="space-y-3 text-sm mb-4">
            <div class="flex items-center gap-2 text-gray-400">
              <span>👤</span>
              <span><?php echo htmlspecialchars($bike['owner_name']); ?></span>
            </div>
            <div class="flex items-center gap-2 text-gray-400">
              <span>📍</span>
              <span><?php echo htmlspecialchars($bike['city']); ?></span>
            </div>
            <div class="flex items-center gap-2 text-gray-400">
              <span>📏</span>
              <span>Frame size: <?php echo htmlspecialchars($bike['frame_size']); ?></span>
            </div>
          </div>

          <?php if (!empty($bike['description'])): ?>
            <div class="border-t border-gray-800 pt-4">
              <div class="text-sm text-gray-400 font-semibold mb-2">Description:</div>
              <p class="text-sm text-gray-300"><?php echo htmlspecialchars($bike['description']); ?></p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Booking Form (Right Side) -->
    <div class="md:col-span-3">
      <div class="bg-gray-900 border border-gray-800 rounded-xl p-8">
        <h3 class="text-xl font-bold mb-6">Booking Details</h3>

        <form method="POST" action="" class="space-y-6" id="bookingForm">

          <!-- Rental Date -->
          <div>
            <label for="rental_date" class="block text-sm text-gray-400 mb-2">
              Rental Date <span class="text-red-500">*</span>
            </label>
            <input
              type="date"
              id="rental_date"
              name="rental_date"
              required
              min="<?php echo date('Y-m-d'); ?>"
              value="<?php echo htmlspecialchars($default_date); ?>"
              class="w-full px-4 py-3 bg-black border border-gray-800 rounded-lg focus:border-cyan-600 focus:outline-none transition"
            >
            <p class="text-xs text-gray-500 mt-1">When do you want to pick up the bike?</p>
          </div>

          <!-- Rental Days -->
          <div>
            <label for="rental_days" class="block text-sm text-gray-400 mb-2">
              Number of Days <span class="text-red-500">*</span>
            </label>
            <input
              type="number"
              id="rental_days"
              name="rental_days"
              required
              min="1"
              max="30"
              value="<?php echo htmlspecialchars($default_days); ?>"
              class="w-full px-4 py-3 bg-black border border-gray-800 rounded-lg focus:border-cyan-600 focus:outline-none transition"
              oninput="calculateTotal()"
            >
            <p class="text-xs text-gray-500 mt-1">How many days do you need the bike? (1-30 days)</p>
          </div>


          <!-- Price Summary -->
          <div class="bg-cyan-900/30 border border-cyan-800 rounded-lg p-6">
            <h4 class="font-bold mb-4">Price Summary</h4>
            <div class="space-y-2 text-sm mb-4">
              <div class="flex justify-between text-gray-400">
                <span>Price per day:</span>
                <span><?php echo number_format($bike['price_per_day'], 0); ?> Kč</span>
              </div>
              <div class="flex justify-between text-gray-400">
                <span>Number of days:</span>
                <span id="days-display"><?php echo $default_days; ?></span>
              </div>
            </div>
            <div class="border-t border-cyan-800 pt-4 flex justify-between items-center">
              <span class="font-bold">Total Price:</span>
              <span class="text-3xl font-bold text-cyan-500" id="total-price">
                <?php echo number_format($bike['price_per_day'] * $default_days, 0); ?> Kč
              </span>
            </div>
          </div>

          <!-- Important Info -->
          <div class="bg-yellow-900/30 border border-yellow-700 rounded-lg p-4">
            <div class="flex items-start gap-3">
              <span class="text-2xl">ℹ️</span>
              <div class="text-sm text-gray-300">
                <div class="font-semibold mb-1 text-yellow-500">Important:</div>
                <ul class="space-y-1 text-gray-400">
                  <li>• Your booking request will be sent to the owner</li>
                  <li>• Wait for the owner to approve your request</li>
                  <li>• You'll receive a notification when approved</li>
                  <li>• Contact the owner for pickup details</li>
                </ul>
              </div>
            </div>
          </div>

          <!-- Submit Buttons -->
          <div class="flex gap-4 pt-4">
            <button
              type="submit"
              class="flex-1 py-3 bg-green-600 hover:bg-green-700 rounded-lg font-semibold transition"
            >
              Send Booking Request
            </button>
            <a
              href="browse_bikes.php"
              class="flex-1 py-3 border border-gray-700 hover:bg-gray-800 rounded-lg font-semibold transition text-center"
            >
              Cancel
            </a>
          </div>

        </form>
      </div>
    </div>

  </div>

</main>

<!-- Footer -->
<footer class="border-t border-gray-800 mt-20 py-8 text-center text-gray-500 text-sm">
  <p>© 2026 BikeRent. Share the ride.</p>
</footer>

<script>
  function calculateTotal() {
    const days = parseInt(document.getElementById('rental_days').value) || 0;
    const pricePerDay = <?php echo $bike['price_per_day']; ?>;
    const total = days * pricePerDay;

    document.getElementById('days-display').textContent = days;
    document.getElementById('total-price').textContent = total.toLocaleString('cs-CZ') + ' Kč';
  }
</script>

</body>
</html>
