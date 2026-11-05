<?php
require_once 'auth.php';

$errors = [];
$success = false;
$pendingSetup = $_SESSION['pending_staff_setup'] ?? null;
$email = $pendingSetup['email'] ?? trim($_GET['email'] ?? $_POST['email'] ?? '');
$otpVerified = !empty($pendingSetup['otp_verified']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $email = trim($_POST['email'] ?? '');

    if ($action === 'verify') {
      $otpCode = trim($_POST['otp_code'] ?? '');
      if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $otpCode === '') {
          $errors[] = 'Enter the email address from your staff invitation and the OTP code.';
      } else {
          $userStatement = $conn->prepare("SELECT id FROM users WHERE email = ? AND role = 'helper' LIMIT 1");
          if (!$userStatement) {
              throw new RuntimeException('Unable to prepare staff account verification.');
          }
          $userStatement->bind_param('s', $email);
          if (!$userStatement->execute()) {
              throw new RuntimeException('Unable to verify the staff account.');
          }
          $staff = $userStatement->get_result()->fetch_assoc();
          $userStatement->close();  
          if (!$staff || !verify_user_otp((int)$staff['id'], $otpCode)) {
              $errors[] = 'The OTP is incorrect or expired. Please contact the administrator for a new invitation.';
          } else {
              $_SESSION['pending_staff_setup'] = [
                  'user_id' => (int)$staff['id'],
                  'email' => $email,
                  'otp_verified' => true,
                  'verified_at' => time(),
              ];
              $pendingSetup = $_SESSION['pending_staff_setup'];
              $otpVerified = true;
          }
      }
    } elseif ($action === 'complete') {
      $pendingSetup = $_SESSION['pending_staff_setup'] ?? null;
      $password = $_POST['password'] ?? '';
      $confirmPassword = $_POST['confirm_password'] ?? '';  
      if (!$pendingSetup || empty($pendingSetup['otp_verified'])
          || time() > ($pendingSetup['verified_at'] ?? 0) + 600) {
          unset($_SESSION['pending_staff_setup']);
          $pendingSetup = null;
          $otpVerified = false;
          $errors[] = 'Your verification has expired. Please open a current staff invitation and verify the OTP again.';
      } elseif ($password === '' || $confirmPassword === '') {
          $errors[] = 'Enter and confirm your password.';
      } elseif ($password !== $confirmPassword) {
          $errors[] = 'Passwords do not match.';
      } elseif (strlen($password) < 8 || strlen($password) > 20) {
          $errors[] = 'Password must be between 8 and 20 characters long.';
      } elseif (!preg_match('/[A-Z]/', $password)) {
          $errors[] = 'Password must contain at least one uppercase letter.';
      } elseif (!preg_match('/[a-z]/', $password)) {
          $errors[] = 'Password must contain at least one lowercase letter.';
      } elseif (!preg_match('/[0-9]/', $password)) {
          $errors[] = 'Password must contain at least one number.';
      } elseif (!preg_match('/[^A-Za-z0-9]/', $password)) {
          $errors[] = 'Password must contain at least one special character.';
      } else {
          $passwordHash = password_hash($password, PASSWORD_DEFAULT);
          $updateStatement = $conn->prepare(
              "UPDATE users SET password = ?, otp_code = NULL, otp_expires = NULL
               WHERE id = ? AND role = 'helper'"
          );
          if (!$updateStatement) {
              throw new RuntimeException('Unable to prepare the staff account update.');
          }
          $updateStatement->bind_param('si', $passwordHash, $pendingSetup['user_id']);
          if (!$updateStatement->execute()) {
              throw new RuntimeException('Unable to save the staff account password.');
          }
          if ($updateStatement->affected_rows !== 1) {
              $errors[] = 'The staff account could not be updated. Please contact the administrator.';
          } else {
              unset($_SESSION['pending_staff_setup']);
              $success = true;
          }
          $updateStatement->close();
      }   
      if (!$success && $pendingSetup) {
          $email = $pendingSetup['email'];
          $otpVerified = !empty($pendingSetup['otp_verified']);
      }
    } else {
        $errors[] = 'Invalid request. Please use the link from your staff invitation.';
    }
}

$email = $pendingSetup['email'] ?? $email;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Staff Account Setup</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body::before {
      content: '';
      position: fixed;
      inset: 0;
      z-index: -1;
      background-image: url('./img/inti.jpg');
      background-size: cover;
      background-position: center;
      filter: blur(2px) brightness(0.7);
    }
  </style>
  <?php if ($success): ?>
    <meta http-equiv="refresh" content="3;url=Login.php">
  <?php endif; ?>
</head>
<body class="bg-transparent flex items-center justify-center min-h-screen">
  <main class="bg-white p-6 sm:p-8 rounded-2xl shadow-lg w-full max-w-sm m-4">
    <h1 class="text-2xl font-bold text-center mb-6 text-slate-800">
      <?php echo $otpVerified ? 'Set Staff Password' : 'Staff Account Setup'; ?>
    </h1>

    <?php if ($success): ?>
      <div role="status" class="mb-4 rounded-xl bg-green-100 border border-green-300 p-4 text-green-700">
        Your staff account is ready. Redirecting to login...
      </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div class="mb-4 rounded-xl bg-red-100 border border-red-300 p-4 text-red-700">
        <p class="font-semibold mb-3">The following issue occurred:</p>
        <ul class="list-disc pl-5">
          <?php foreach ($errors as $error): ?>
            <li><?php echo htmlspecialchars($error); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if (!$success): ?>
      <form method="POST" action="staff_setup.php<?php echo $email !== '' ? '?email=' . rawurlencode($email) : ''; ?>" class="space-y-4">
        <div>
          <label for="email" class="block text-sm font-semibold mb-2">Email</label>
          <input id="email" type="email" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES); ?>"
            class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-400" required readonly>
        </div>

        <?php if (!$otpVerified): ?>
          <div class="rounded-xl bg-blue-50 border border-blue-200 p-4 text-blue-700">
            <p class="text-sm">Enter the OTP code from your staff invitation email to continue.</p>
          </div>
          <div>
            <label for="otp_code" class="block text-sm font-semibold mb-2">OTP Code</label>
            <input id="otp_code" type="text" name="otp_code" inputmode="numeric" autocomplete="one-time-code"
              class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-400" required>
          </div>
          <button type="submit" name="action" value="verify" class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600">
            Verify Code
          </button>
        <?php else: ?>
          <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-emerald-700">
            <p class="text-sm font-semibold">OTP verified. Set a password to complete your staff account setup.</p>
          </div>
          <div>
            <label for="password" class="block text-sm font-semibold mb-2">Password</label>
            <input id="password" type="password" name="password" autocomplete="new-password"
              class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-400" required>
          </div>
          <div>
            <label for="confirm_password" class="block text-sm font-semibold mb-2">Confirm Password</label>
            <input id="confirm_password" type="password" name="confirm_password" autocomplete="new-password"
              class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-400" required>
          </div>
          <button type="submit" name="action" value="complete" class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600">
            Complete Staff Setup
          </button>
        <?php endif; ?>
      </form>
    <?php endif; ?>

    <p class="text-center text-sm mt-4 text-slate-600">
      Already set up your account? <a href="Login.php" class="text-blue-500 hover:underline">Login</a>
    </p>
  </main>
</body>
</html>
