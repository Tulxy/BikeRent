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

// Získat informace o kole a ověřit vlastnictví
try {
  $stmt = $pdo->prepare("SELECT * FROM bikes WHERE id = ? AND owner_id = ?");
  $stmt->execute([$bike_id, $user_id]);
  $bike = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$bike) {
    $_SESSION['error'] = "Bike not found or you don't have permission to edit it";
    header("Location: my_bikes.php");
    exit;
  }

} catch(PDOException $e) {
  $_SESSION['error'] = "Database error";
  header("Location: my_bikes.php");
  exit;
}

// Zpracování formuláře
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

  $bike_type = trim($_POST['bike_type']);
  $frame_size = trim($_POST['frame_size']);
  $price_per_day = trim($_POST['price_per_day']);
  $description = trim($_POST['description']);
  $city = trim($_POST['city']);
  $address = trim($_POST['address']);

  $errors = [];

  // Validace
  if (empty($bike_type)) {
    $errors[] = "Bike type is required";
  }

  if (empty($frame_size)) {
    $errors[] = "Frame size is required";
  }

  if (empty($price_per_day)) {
    $errors[] = "Price per day is required";
  } elseif (!is_numeric($price_per_day) || $price_per_day <= 0) {
    $errors[] = "Price must be a positive number";
  }

  if (empty($city)) {
    $errors[] = "City is required";
  }

  if (strlen($description) > 500) {
    $errors[] = "Description is too long (max 500 characters)";
  }

  // Pokud jsou chyby
  if (!empty($errors)) {
    $_SESSION['errors'] = $errors;
    $_SESSION['old_data'] = $_POST;
  } else {
    // Uložit změny do databáze
    try {
      $stmt = $pdo->prepare("
                UPDATE bikes SET
                    bike_type = ?,
                    frame_size = ?,
                    price_per_day = ?,
                    description = ?,
                    city = ?,
                    address = ?
                WHERE id = ? AND owner_id = ?
            ");

      $stmt->execute([
        $bike_type,
        $frame_size,
        $price_per_day,
        $description,
        $city,
        $address,
        $bike_id,
        $user_id
      ]);

      $_SESSION['success'] = "Bike updated successfully!";
      header("Location: my_bikes.php");
      exit;

    } catch(PDOException $e) {
      $_SESSION['errors'] = ["Database error: " . $e->getMessage()];
      $_SESSION['old_data'] = $_POST;
    }
  }
}

// Získat chyby a použít stará data nebo současná data kola
$errors = $_SESSION['errors'] ?? [];
$old_data = $_SESSION['old_data'] ?? $bike;
unset($_SESSION['errors'], $_SESSION['old_data']);
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <script src="https://cdn.tailwindcss.com"></script>
  <title>Edit Bike - BikeRent</title>
</head>
<body class="bg-black text-white min-h-screen">

<!-- Header -->
<?php include '../components/header.php'; ?>

<!-- Main Content -->
<main class="container mx-auto px-6 py-12 max-w-3xl">

  <!-- Page Header -->
  <div class="mb-8">
    <a href="my_bikes.php" class="text-cyan-500 hover:text-cyan-400 transition text-sm mb-2 inline-block">
      ← Back to My Bikes
    </a>
    <h1 class="text-4xl font-bold mb-2">Edit Bike</h1>
    <p class="text-gray-400">Update your bike listing information</p>
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

  <!-- Edit Bike Form -->
  <div class="bg-gray-900 border border-gray-800 rounded-xl p-8">
    <form method="POST" action="" class="space-y-6">

      <!-- Bike Type -->
      <div>
        <label for="bike_type" class="block text-sm text-gray-400 mb-2">
          Bike Type <span class="text-red-500">*</span>
        </label>
        <select
          id="bike_type"
          name="bike_type"
          required
          class="w-full px-4 py-3 bg-black border border-gray-800 rounded-lg focus:border-cyan-600 focus:outline-none transition"
        >
          <option value="">Select bike type</option>
          <option value="Mountain Bike" <?php echo ($old_data['bike_type'] ?? '') === 'Mountain Bike' ? 'selected' : ''; ?>>Mountain Bike</option>
          <option value="Road Bike" <?php echo ($old_data['bike_type'] ?? '') === 'Road Bike' ? 'selected' : ''; ?>>Road Bike</option>
          <option value="Electric Bike" <?php echo ($old_data['bike_type'] ?? '') === 'Electric Bike' ? 'selected' : ''; ?>>Electric Bike</option>
          <option value="City Bike" <?php echo ($old_data['bike_type'] ?? '') === 'City Bike' ? 'selected' : ''; ?>>City Bike</option>
          <option value="BMX" <?php echo ($old_data['bike_type'] ?? '') === 'BMX' ? 'selected' : ''; ?>>BMX</option>
          <option value="Kids Bike" <?php echo ($old_data['bike_type'] ?? '') === 'Kids Bike' ? 'selected' : ''; ?>>Kids Bike</option>
        </select>
      </div>

      <!-- Frame Size -->
      <div>
        <label for="frame_size" class="block text-sm text-gray-400 mb-2">
          Frame Size <span class="text-red-500">*</span>
        </label>
        <select
          id="frame_size"
          name="frame_size"
          required
          class="w-full px-4 py-3 bg-black border border-gray-800 rounded-lg focus:border-cyan-600 focus:outline-none transition"
        >
          <option value="">Select frame size</option>
          <option value="S" <?php echo ($old_data['frame_size'] ?? '') === 'S' ? 'selected' : ''; ?>>S (Small)</option>
          <option value="M" <?php echo ($old_data['frame_size'] ?? '') === 'M' ? 'selected' : ''; ?>>M (Medium)</option>
          <option value="L" <?php echo ($old_data['frame_size'] ?? '') === 'L' ? 'selected' : ''; ?>>L (Large)</option>
          <option value="XL" <?php echo ($old_data['frame_size'] ?? '') === 'XL' ? 'selected' : ''; ?>>XL (Extra Large)</option>
        </select>
      </div>

      <!-- Price per Day -->
      <div>
        <label for="price_per_day" class="block text-sm text-gray-400 mb-2">
          Price per Day (Kč) <span class="text-red-500">*</span>
        </label>
        <input
          type="number"
          id="price_per_day"
          name="price_per_day"
          required
          min="1"
          step="1"
          value="<?php echo htmlspecialchars($old_data['price_per_day'] ?? ''); ?>"
          placeholder="200"
          class="w-full px-4 py-3 bg-black border border-gray-800 rounded-lg focus:border-cyan-600 focus:outline-none transition"
        >
        <p class="text-xs text-gray-500 mt-1">Recommended: 150-400 Kč per day</p>
      </div>

      <!-- Description -->
      <div>
        <label for="description" class="block text-sm text-gray-400 mb-2">
          Description
        </label>
        <textarea
          id="description"
          name="description"
          rows="4"
          maxlength="500"
          placeholder="Describe your bike, its condition, any special features..."
          class="w-full px-4 py-3 bg-black border border-gray-800 rounded-lg focus:border-cyan-600 focus:outline-none transition resize-none"
        ><?php echo htmlspecialchars($old_data['description'] ?? ''); ?></textarea>
        <p class="text-xs text-gray-500 mt-1">Max 500 characters</p>
      </div>

      <!-- City -->
      <div>
        <label for="city" class="block text-sm text-gray-400 mb-2">
          City <span class="text-red-500">*</span>
        </label>
        <input
          type="text"
          id="city"
          name="city"
          required
          value="<?php echo htmlspecialchars($old_data['city'] ?? ''); ?>"
          placeholder="Zlín"
          class="w-full px-4 py-3 bg-black border border-gray-800 rounded-lg focus:border-cyan-600 focus:outline-none transition"
        >
      </div>

      <!-- Address -->
      <div>
        <label for="address" class="block text-sm text-gray-400 mb-2">
          Address (optional)
        </label>
        <input
          type="text"
          id="address"
          name="address"
          value="<?php echo htmlspecialchars($old_data['address'] ?? ''); ?>"
          placeholder="Street name, pickup location..."
          class="w-full px-4 py-3 bg-black border border-gray-800 rounded-lg focus:border-cyan-600 focus:outline-none transition"
        >
        <p class="text-xs text-gray-500 mt-1">Where renters can pick up the bike</p>
      </div>

      <!-- Info Box -->
      <div class="bg-cyan-900/30 border border-cyan-800 rounded-lg p-4">
        <div class="flex items-start gap-3">
          <span class="text-2xl">ℹ️</span>
          <div class="text-sm text-gray-300">
            <div class="font-semibold mb-1">Note:</div>
            <ul class="space-y-1 text-gray-400">
              <li>• Updating bike info won't affect existing bookings</li>
              <li>• Price changes will apply to new bookings only</li>
              <li>• Make sure all information is accurate</li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Submit Buttons -->
      <div class="flex gap-4 pt-4">
        <button
          type="submit"
          class="flex-1 py-3 bg-cyan-600 hover:bg-cyan-700 rounded-lg font-semibold transition"
        >
          Save Changes
        </button>
        <a
          href="my_bikes.php"
          class="flex-1 py-3 border border-gray-700 hover:bg-gray-800 rounded-lg font-semibold transition text-center"
        >
          Cancel
        </a>
      </div>

    </form>
  </div>

  <!-- Danger Zone -->
  <div class="mt-8 bg-red-900/20 border border-red-800 rounded-xl p-6">
    <h3 class="text-lg font-bold text-red-500 mb-2 flex items-center gap-2">
      <span>⚠️</span>
      <span>Danger Zone</span>
    </h3>
    <p class="text-sm text-gray-400 mb-4">Once you delete a bike, there is no going back. Please be certain.</p>
    <a
      href="my_bikes.php?delete=<?php echo $bike_id; ?>"
      class="inline-block px-6 py-3 bg-red-600 hover:bg-red-700 rounded-lg font-semibold transition"
      onclick="return confirm('Are you sure you want to delete this bike? This action cannot be undone and will cancel all pending bookings.')">
      Delete This Bike
    </a>
  </div>

</main>

<!-- Footer -->
<footer class="border-t border-gray-800 mt-20 py-8 text-center text-gray-500 text-sm">
  <p>© 2026 BikeRent. Share the ride.</p>
</footer>

</body>
</html>
