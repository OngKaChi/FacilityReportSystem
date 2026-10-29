<?php
require_once 'auth.php';

$errors = [];
$success = false;
$otpSent = false;
$otpVerified = false;
$otpMessage = '';
$pendingReset = $_SESSION['pending_password_reset'] ?? null;
if ($pendingReset) {
    $otpVerified = !empty($pendingReset['otp_verified']);
    $otpSent = true;
}

// Check if email is provided via GET (invitation link)
if (!$otpSent && !empty($_GET['email'])) {
    $otpSent = true;
    $otpMessage = 'Enter the OTP code sent to your email to set your password.';
}


function add_step_error(&$errors, $step, $message) {
    $errors[] = "{$step}: {$message}";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['otp_action']) && $_POST['otp_action'] === 'send') {
        $email = trim($_POST['email'] ?? '');

        if ($email === '') {
            add_step_error($errors, 'Please enter your email address.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            add_step_error($errors, 'Please enter a valid email address.');
        }

        if (empty($errors)) {
            $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows === 0) {
                add_step_error($errors, 'No account exists with that email address.');
            }
            $stmt->close();
        }

        if (empty($errors)) {
            $otpCode = generate_otp_code();
            $expiresAt = time() + 600;
            $_SESSION['pending_password_reset'] = [
                'email' => $email,
                'otp_code' => $otpCode,
                'otp_expires' => $expiresAt,
                'otp_verified' => false,
            ];
            $pendingReset = $_SESSION['pending_password_reset'];
            $otpSent = true;
            $sent = send_otp_email($email, $otpCode, 'password reset');
            if ($sent) {
                $otpMessage = 'An OTP has been sent to your email address.';
            } else {
                $otpMessage = 'Unable to send OTP by email. Please try again later.';
            }
        }
    }

    if (isset($_POST['otp_action']) && $_POST['otp_action'] === 'resend') {
        $pendingReset = $_SESSION['pending_password_reset'] ?? null;

        if (!$pendingReset) {
            add_step_error($errors, 'Please request an OTP first.');
        } else {
            $otpCode = generate_otp_code();
            $expiresAt = time() + 600;
            $_SESSION['pending_password_reset']['otp_code'] = $otpCode;
            $_SESSION['pending_password_reset']['otp_expires'] = $expiresAt;
            $_SESSION['pending_password_reset']['otp_verified'] = false;
            $pendingReset = $_SESSION['pending_password_reset'];
            $otpSent = true;
            $sent = send_otp_email($pendingReset['email'], $otpCode, 'password reset');
            if ($sent) {
                $otpMessage = 'A new OTP has been sent to your email address.';
            } else {
                $otpMessage = 'Unable to send OTP by email. Please try again later.';
            }
        }
    }

    if (isset($_POST['otp_action']) && $_POST['otp_action'] === 'verify') {
        $otpCode = trim($_POST['otp_code'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $pendingReset = $_SESSION['pending_password_reset'] ?? null;

        if ($otpCode === '') {
            add_step_error($errors, 'Please enter the OTP code.');
        } else {
            // If session exists, check session first
            if ($pendingReset && $otpCode === $pendingReset['otp_code']) {
                if (time() > $pendingReset['otp_expires']) {
                    add_step_error($errors, 'This OTP has expired. Please request a new one.');
                }
            } else {
                // Otherwise, check the database (this allows links from emails to work)
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $u = $stmt->get_result()->fetch_assoc();
                if ($u && verify_user_otp($u['id'], $otpCode)) {
                    // Validated via DB, now create the session so the next step works
                    $_SESSION['pending_password_reset'] = ['email' => $email];
                } else {
                    add_step_error($errors, 'Incorrect or expired OTP code.');
                }
                if ($stmt) $stmt->close();
            }
        }

        if (empty($errors)) {
            $_SESSION['pending_password_reset']['otp_verified'] = true;
            $pendingReset = $_SESSION['pending_password_reset'];
            $otpVerified = true;
            $otpSent = true;
            $otpMessage = 'OTP verified. Enter a new password to complete the reset.';
        }
    }

    if (isset($_POST['otp_action']) && $_POST['otp_action'] === 'reset') {
        $pendingReset = $_SESSION['pending_password_reset'] ?? null;
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!$pendingReset || empty($pendingReset['otp_verified'])) {
            add_step_error($errors, 'Please verify the OTP before resetting your password.');
        }

        if (empty($errors)) {
            if ($password === '' || $confirmPassword === '') {
                add_step_error($errors, 'Please enter and confirm your new password.');
            } elseif ($password !== $confirmPassword) {
                add_step_error($errors, 'Passwords do not match.');
            } elseif (strlen($password) < 8 || strlen($password) > 20) {
                add_step_error($errors, 'Password must be between 8 and 20 characters.');
            } elseif (!preg_match('/[A-Z]/', $password)) {
                add_step_error($errors, 'Password must contain at least one uppercase letter.');
            } elseif (!preg_match('/[a-z]/', $password)) {
                add_step_error($errors, 'Password must contain at least one lowercase letter.');
            } elseif (!preg_match('/[0-9]/', $password)) {
                add_step_error($errors, 'Password must contain at least one number.');
            } elseif (!preg_match('/[^A-Za-z0-9]/', $password)) {
                add_step_error($errors, 'Password must contain at least one special character.');
            }
        }

        if (empty($errors)) {
            $email = $pendingReset['email'];
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('UPDATE users SET password = ? WHERE email = ?');
            $stmt->bind_param('ss', $passwordHash, $email);
            if ($stmt->execute()) {
                $stmt->close();
                unset($_SESSION['pending_password_reset']);
                $success = true;
            } else {
                add_step_error($errors, 'Unable to update password. Please try again.');
                $stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Forgot Password</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body::before {
      content: '';
      position: fixed;
      top: 0;
      left: 0;
      height: 100%;
      width: 100%;
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
<div class="bg-white p-8 rounded-2xl shadow-lg w-96">
  <h2 class="text-2xl font-bold text-center mb-6 text-slate-800">Forgot Password</h2>

  <?php if ($success): ?>
    <div class="mb-4 rounded-xl bg-green-100 border border-green-300 p-4 text-green-700">
      Your password has been reset successfully. Redirecting to login page...
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

  <form method="POST" action="forgot_password.php">
    <label class="block text-sm font-semibold mb-2">Email</label>
    <input type="email" name="email" value="<?php echo htmlspecialchars($pendingReset['email'] ?? $_POST['email'] ?? $_GET['email'] ?? ''); ?>" class="w-full mb-3 px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-400" required <?php echo ($otpSent && !$otpVerified) || $otpVerified ? 'readonly' : ''; ?>>

    <?php if (!$otpSent && !$otpVerified): ?>
      <p class="text-sm text-slate-500 mb-4">Enter your email and click "Send Verification Code" to receive the reset code.</p>
      <button class="w-full bg-blue-500 text-white py-2 px-4 rounded-lg hover:bg-blue-600 mb-4" type="submit" name="otp_action" value="send">
        Send Verification Code
      </button>
    <?php endif; ?>

    <?php if (($otpSent && !$otpVerified) || $otpVerified): ?>
      <div class="mb-4 rounded-xl bg-blue-50 border border-blue-200 p-4 text-blue-700">
        <p class="text-sm mb-2"><?php echo htmlspecialchars($otpMessage ?: 'Enter the OTP sent to your email address.'); ?></p>
      </div>

      <?php if (!$otpVerified): ?>
        <label class="block text-sm font-semibold mb-2">OTP Code</label>
        <input type="text" name="otp_code" value="<?php echo htmlspecialchars($_POST['otp_code'] ?? ''); ?>" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-400" required>
        <div class="flex gap-2 mt-4">
            <button class="flex-1 bg-green-500 text-white py-2 rounded-lg hover:bg-green-600" type="submit" name="otp_action" value="verify">
                Verify Code
            </button>
            <button class="flex-1 bg-gray-200 text-gray-700 py-2 rounded-lg hover:bg-gray-300" type="submit" name="otp_action" value="resend" formnovalidate>
                Resend Code
            </button>
        </div>
        
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($otpVerified): ?>
      <div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-emerald-700">
        <p class="text-sm font-semibold">OTP verified. Enter your new password below.</p>
      </div>

      <label class="block text-sm font-semibold mb-2">New Password</label>
      <input type="password" name="password" class="w-full mb-3 px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-400" required>

      <label class="block text-sm font-semibold mb-2">Confirm Password</label>
      <input type="password" name="confirm_password" class="w-full mb-4 px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-400" required>

      <button class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600" type="submit" name="otp_action" value="reset">Reset Password</button>
    <?php endif; ?>
  </form>

  <p class="text-center text-sm mt-4">
    Remembered your password? <a href="Login.php" class="text-blue-500 hover:underline">Login</a>
  </p>
</div>
</body>
</html>
