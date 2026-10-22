<?php
require_once 'auth.php';
require_login();

$user = current_user();
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle car plate actions
    if (isset($_POST['plate_action'])) {
        if ($_POST['plate_action'] === 'add') {
            $plate = trim($_POST['new_plate'] ?? '');
            if ($plate !== '') {
                add_user_car_plate($user['id'], $plate);
                $success = true;
            }
        } elseif ($_POST['plate_action'] === 'delete') {
            $plateId = (int)($_POST['plate_id'] ?? 0);
            delete_user_car_plate($user['id'], $plateId);
            $success = true;
        }
    }

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Handle profile picture upload
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/avatars/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_info = pathinfo($_FILES['avatar']['name']);
        $extension = strtolower($file_info['extension']);
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($extension, $allowed_extensions)) {
            $new_filename = 'avatar_' . $user['id'] . '_' . time() . '.' . $extension;
            $upload_path = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
                $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                $stmt->bind_param('si', $upload_path, $user['id']);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    if ($name === '' || $email === '') {
        $errors[] = 'Name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please use a valid email address.';
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
        $stmt->bind_param('si', $email, $user['id']);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $errors[] = 'This email is already registered to another account.';
        }
        $stmt->close();
    }

    if (empty($errors)) {
        if ($password !== '') {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, password = ? WHERE id = ?");
            $stmt->bind_param('sssi', $name, $email, $passwordHash, $user['id']);
        } else {
            $stmt = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
            $stmt->bind_param('ssi', $name, $email, $user['id']);
        }
        if ($stmt->execute()) {
            refresh_current_user();
            $success = true;
            $user = current_user();
        } else {
            $errors[] = 'Unable to update profile at this time.';
        }
        $stmt->close();
    }
}
$carPlates = get_user_car_plates($user['id']);
?>
<!DOCTYPE html>
<html>
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Profile</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 dark:bg-slate-900 min-h-screen transition-colors duration-300 text-slate-800 dark:text-slate-200">
<?php include 'components/navbar.php'; ?>
<div class="flex min-h-screen">
<?php
if ($user['role'] === 'admin' || $user['role'] === 'helper') {
    include 'components/admin_sidebar.php';
} else {
    include 'components/sidebar.php';
}
?>
<div class="p-6 max-w-4xl mx-auto">
  <div class="bg-white dark:bg-slate-800 rounded-3xl shadow-lg p-8 mt-8 dark:shadow-none dark:border dark:border-slate-700">
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-3xl font-semibold text-slate-900 dark:text-white">My Profile</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-2">Manage your account information and update your password.</p>
      </div>
    </div>

    <!-- Notification Area -->
    <div id="notification-area" class="mb-6">
      <?php if ($success): ?>
        <div class="rounded-2xl bg-green-50 border border-green-200 p-4 text-green-700">Profile updated successfully.</div>
      <?php elseif (!empty($errors)): ?>
      <div class="mb-6 rounded-2xl bg-red-50 border border-red-200 p-4 text-red-700">
        <ul class="list-disc pl-5">
          <?php foreach ($errors as $error): ?>
            <li><?php echo htmlspecialchars($error); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
    </div>

    <div class="grid md:grid-cols-2 gap-12">
      <!-- Left Column: Profile Info -->
      <form method="POST" action="profile.php" class="space-y-6">
        <div>
          <span class="text-sm font-semibold text-slate-700 dark:text-gray-300">Name</span>
          <p class="mt-2 w-full rounded-2xl border dark:border-slate-200 dark:border-slate-600 px-4 py-3 bg-gray-50 dark:bg-slate-700/50 text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars($user['name']); ?></p>
        </div>

        <div>
          <span class="text-sm font-semibold text-slate-700 dark:text-gray-300">Email</span>
          <p class="mt-2 w-full rounded-2xl border dark:border-slate-200 dark:border-slate-600 px-4 py-3 bg-gray-50 dark:bg-slate-700/50 text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars($user['email']); ?></p>
        </div>

        <label class="block">
          <span class="text-sm font-semibold text-slate-700 dark:text-gray-300">New Password</span>
          <input type="password" name="password" placeholder="Leave blank to keep current password" class="mt-2 w-full rounded-2xl border dark:border-slate-600 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 dark:focus:ring-blue-500 dark:bg-slate-700 dark:text-white">
        </label>

        <div class="rounded-2xl bg-slate-50 dark:bg-slate-700/50 p-4 text-sm text-slate-600 dark:text-slate-300">
          Role: <span class="font-semibold text-slate-900 dark:text-white"><?php echo htmlspecialchars(ucfirst($user['role'])); ?></span>
        </div>

        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white rounded-2xl py-3 text-sm font-semibold">Save Profile</button>
      </form>

      <!-- Right Column: Car Plates -->
      <div class="space-y-6">
        <div>
          <h3 class="text-lg font-semibold text-slate-700 dark:text-gray-300">Car Plate Numbers</h3>
          <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Manage your registered vehicle plates.</p>
        </div>

        <div id="car-plates-list" class="space-y-3">
          <?php echo generate_plate_list_html($carPlates); ?>
        </div>
        
        <div class="flex gap-2 pt-2">
          <input type="text" id="new_plate_input" placeholder="Enter plate number (e.g. ABC 1234)" class="flex-1 rounded-2xl border dark:border-slate-600 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 dark:focus:ring-blue-500 dark:bg-slate-700 dark:text-white">
          <button type="button" onclick="addPlate()" class="bg-emerald-500 hover:bg-emerald-600 text-white rounded-2xl px-4 py-2 text-sm font-semibold">
            Add Plate
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
function generate_plate_list_html($plates) {
    if (empty($plates)) {
        return '<p id="no-plates-msg" class="text-sm text-gray-500 dark:text-gray-400 italic">No car plates added yet.</p>';
    }
    $html = '';
    foreach ($plates as $plate) {
        $html .= '
          <div id="plate-'. $plate['id'] .'" class="flex items-center justify-between bg-gray-50 dark:bg-slate-700/50 p-3 rounded-xl border border-gray-200 dark:border-slate-600 transition-all duration-300">
            <span class="font-mono font-bold text-gray-700 dark:text-white">'. htmlspecialchars($plate['plate_number']) .'</span>
            <button type="button" onclick="deletePlate('. $plate['id'] .')" class="text-red-500 hover:text-red-700 p-1">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
            </button>
          </div>
        ';
    }
    return $html;
}
?>

<script>
async function addPlate() {
  const input = document.getElementById('new_plate_input');
  const plateNumber = input.value.trim();
  if (!plateNumber) return;

  const formData = new FormData();
  formData.append('plate_action', 'add');
  formData.append('new_plate', plateNumber);

  const response = await fetch('profile.php', { method: 'POST', body: formData });
  if (response.ok) {
    input.value = '';
    showNotification('Car plate added successfully.', 'success');
    // The page will reload from server to show the new list
    location.reload();
  } else {
    showNotification('Failed to add car plate.', 'error');
  }
}

async function deletePlate(id) {
  if (!confirm('Are you sure you want to delete this plate number?')) return;

  const plateRow = document.getElementById('plate-' + id);
  plateRow.style.opacity = '0.5';

  const formData = new FormData();
  formData.append('plate_action', 'delete');
  formData.append('plate_id', id);

  const response = await fetch('profile.php', { method: 'POST', body: formData });
  if (response.ok) {
    plateRow.style.transition = 'opacity 0.3s ease, transform 0.3s ease, max-height 0.3s ease';
    plateRow.style.transform = 'scale(0.95)';
    plateRow.style.opacity = '0';
    plateRow.style.maxHeight = '0px';
    plateRow.style.padding = '0';
    plateRow.style.margin = '0';
    setTimeout(() => {
      plateRow.remove();
      if (document.getElementById('car-plates-list').children.length === 0) {
          document.getElementById('car-plates-list').innerHTML = '<p id="no-plates-msg" class="text-sm text-gray-500 dark:text-gray-400 italic">No car plates added yet.</p>';
      }
    }, 300);
    showNotification('Car plate deleted.', 'success');
  } else {
    plateRow.style.opacity = '1';
    showNotification('Failed to delete car plate.', 'error');
  }
}

function showNotification(message, type = 'success') {
  const container = document.getElementById('notification-area');
  const color = type === 'success' ? 'green' : 'red';
  const notification = document.createElement('div');
  notification.className = `mb-6 rounded-2xl bg-${color}-50 border border-${color}-200 p-4 text-${color}-700 transition-all duration-300`;
  notification.style.opacity = '0';
  notification.style.transform = 'translateY(-10px)';
  notification.textContent = message;
  
  container.innerHTML = '';
  container.appendChild(notification);

  setTimeout(() => {
    notification.style.opacity = '1';
    notification.style.transform = 'translateY(0)';
  }, 10);

  setTimeout(() => {
    notification.style.opacity = '0';
    notification.style.transform = 'translateY(-10px)';
    setTimeout(() => notification.remove(), 300);
  }, 4000);
}
</script>
</body>
</html>
