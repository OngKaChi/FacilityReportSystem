<?php
require_once 'auth.php';
require_login('admin');

// Security: Only full admins can manage helpers, helpers cannot manage other helpers
if ($_SESSION['user']['role'] !== 'admin') {
    header("Location: admindashboard.php");
    exit;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_helper'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if ($name === '' || $email === '') {
        $error = 'Name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = 'A user with this email already exists.';
        } else {
            // Create with random initial password
            $randomPass = bin2hex(random_bytes(16));
            $hash = password_hash($randomPass, PASSWORD_DEFAULT);
            $role = 'helper';
            $ins = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
            $ins->bind_param("ssss", $name, $email, $hash, $role);
            if ($ins->execute()) {
                $newUserId = $ins->insert_id;
                $otpCode = generate_otp_code();
                store_user_otp($newUserId, $otpCode);

                $baseUrl = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
                $resetUrl = $baseUrl . "/staff_setup.php?email=" . urlencode($email);

                // Fix: Check if email actually sent and use a clean category name.
                $sent = send_otp_email($email, $otpCode, 'account setup', $resetUrl);
                if ($sent) {
                    $success = 'AFM Staff added. Invitation email with OTP sent to ' . htmlspecialchars($email);
                } else {
                    $success = 'AFM Staff added, but the invitation email failed to send. Please provide this OTP to the staff manually: <b>' . $otpCode . '</b>';
                }
            } else {
                $error = 'Database error. Could not add helper.';
            }
            $ins->close();
        }
        $stmt->close();
    }
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $del = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'helper'");
    $del->bind_param("i", $id);
    $del->execute();
    $del->close();
    header("Location: manage_helpers.php?msg=deleted");
    exit;
}

$helpers = $conn->query("SELECT * FROM users WHERE role = 'helper' ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html>
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage AFM Staff - Head of AFM</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 dark:bg-slate-900 text-slate-900 dark:text-white transition-colors duration-300">

<?php include 'components/navbar.php'; ?>

<div class="flex min-h-screen">
  <?php 
    $view = 'helpers'; // Set the current view for the sidebar
    include 'components/admin_sidebar.php'; 
  ?>

  <main class="flex-1 p-4 sm:p-6 overflow-x-hidden md:ml-32">
    <div class="max-w-5xl mx-auto space-y-6">
      <header class="mb-8">
        <p class="text-sm uppercase tracking-[0.35em] text-slate-500">Dashboards / AFM Staff</p>
        <h1 class="mt-2 text-3xl font-bold">AFM Staff Management</h1>
      </header>

      <?php if ($success || isset($_GET['msg'])): ?>
        <div class="p-4 bg-emerald-100 border border-emerald-200 text-emerald-700 rounded-2xl">
          <?php echo $success ?: 'AFM Staff removed successfully.'; ?>
        </div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="p-4 bg-red-100 border border-red-200 text-red-700 rounded-2xl"><?php echo $error; ?></div>
      <?php endif; ?>

      <div class="grid gap-6 lg:grid-cols-[1fr_2fr]">
        <!-- Add Helper Form -->
        <section class="bg-white dark:bg-slate-800 p-6 rounded-[2rem] shadow-sm border dark:border-slate-700 h-fit">
          <h2 class="text-xl font-bold mb-6">Add New AFM Staff</h2>
          <form method="POST" class="space-y-4">
            <div>
              <label class="block text-sm font-semibold mb-1">Full Name</label>
              <input type="text" name="name" class="w-full px-4 py-3 rounded-xl border dark:bg-slate-700 dark:border-slate-600 outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            <div>
              <label class="block text-sm font-semibold mb-1">Email Address</label>
              <input type="email" name="email" class="w-full px-4 py-3 rounded-xl border dark:bg-slate-700 dark:border-slate-600 outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            <button type="submit" name="add_helper" class="w-full py-4 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 transition shadow-lg shadow-blue-500/20">
              Create AFM Staff Account
            </button>
          </form>
        </section>

        <!-- Helpers List -->
        <section class="bg-white dark:bg-slate-800 p-6 rounded-[2rem] shadow-sm border dark:border-slate-700">
          <h2 class="text-xl font-bold mb-6">Existing AFM Staff</h2>
          <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
              <thead>
                <tr class="border-b dark:border-slate-700 text-slate-500">
                  <th class="py-4 px-3">Name</th>
                  <th class="py-4 px-3">Email</th>
                  <th class="py-4 px-3 text-right">Action</th>
                </tr>
              </thead>
              <tbody class="divide-y dark:divide-slate-700">
                <?php while ($h = $helpers->fetch_assoc()): ?>
                  <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                    <td class="py-4 px-3 font-semibold"><?php echo htmlspecialchars($h['name']); ?></td>
                    <td class="py-4 px-3 text-slate-500"><?php echo htmlspecialchars($h['email']); ?></td>
                    <td class="py-4 px-3 text-right">
                      <a href="manage_helpers.php?delete=<?php echo $h['id']; ?>" 
                         onclick="return confirm('Remove this staff account?')"
                         class="text-rose-500 hover:text-rose-700 font-bold">
                        Delete
                      </a>
                    </td>
                  </tr>
                <?php endwhile; ?>
                <?php if ($helpers->num_rows === 0): ?>
                  <tr><td colspan="3" class="py-8 text-center text-slate-400">No AFM staff found.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </section>
      </div>
    </div>
  </main>
</div>

</body>
</html>