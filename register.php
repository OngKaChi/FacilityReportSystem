<?php
require_once 'auth.php';

$errors = [];
$success = false;
$otpSent = false;
$otpVerified = false;
$otpMessage = '';
$otpStep = '';
$pendingRegister = $_SESSION['pending_register'] ?? null;

function add_step_error(&$errors, $step, $message) {
    $errors[] = $message;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['otp_action']) && $_POST['otp_action'] === 'send') {
        $otpStep = 'send';
        $email = trim($_POST['email'] ?? '');

        if ($email === '') {
            add_step_error($errors, 'Step 1', 'Please enter your email address before requesting an OTP.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            add_step_error($errors, 'Step 1', 'Please enter a valid email address.');
        }

        if (empty($errors)) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                add_step_error($errors, 'Step 1', 'An account with that email already exists.');
            }
            $stmt->close();
        }

        if (empty($errors)) {
            $otpCode = generate_otp_code();
            $expiresAt = time() + 600;
            $_SESSION['pending_register'] = [
                'email' => $email,
                'otp_code' => $otpCode,
                'otp_expires' => $expiresAt,
                'otp_verified' => false,
            ];
            $otpSent = true;
            $sent = send_otp_email($email, $otpCode, 'registration');
            if (!$sent) {
                $otpMessage = 'OTP could not be delivered by email. Please try again later.';
            }
        }
    }

    if (isset($_POST['otp_action']) && $_POST['otp_action'] === 'resend') {
        $otpStep = 'resend';
        $pendingRegister = $_SESSION['pending_register'] ?? null;

        if (!$pendingRegister) {
            add_step_error($errors, 'Step 2', 'No pending registration found. Please request an OTP first.');
        } else {
            $otpCode = generate_otp_code();
            $expiresAt = time() + 600;
            $_SESSION['pending_register']['otp_code'] = $otpCode;
            $_SESSION['pending_register']['otp_expires'] = $expiresAt;
            $_SESSION['pending_register']['otp_verified'] = false;
            $otpSent = true;

            $sent = send_otp_email($pendingRegister['email'], $otpCode, 'registration');
            if ($sent) {
                $otpMessage = 'A new OTP has been sent to your email.';
            } else {
                $otpMessage = 'OTP could not be delivered by email.Please try again later.';
            }
        }
    }

    if (isset($_POST['otp_action']) && $_POST['otp_action'] === 'verify') {
        $otpStep = 'verify';
        $otpCode = trim($_POST['otp_code'] ?? '');
        $pendingRegister = $_SESSION['pending_register'] ?? null;

        if (!$pendingRegister) {
            add_step_error($errors, 'Step 2', 'No pending registration found. Please request an OTP first.');
        } elseif ($otpCode === '') {
            add_step_error($errors, 'Step 3', 'Please enter the OTP code.');
        } elseif ($otpCode !== $pendingRegister['otp_code']) {
            add_step_error($errors, 'Step 3', 'Incorrect OTP code.');
        } elseif (time() > $pendingRegister['otp_expires']) {
            add_step_error($errors, 'Step 3', 'The OTP code has expired. Please request a new one.');
        }

        if (empty($errors)) {
            $_SESSION['pending_register']['otp_verified'] = true;
            $otpVerified = true;
            $otpSent = true;
            $otpMessage = 'OTP correct. Now enter your name and password to complete registration.';
        }
    }

    if (isset($_POST['otp_action']) && $_POST['otp_action'] === 'complete') {
        $otpStep = 'complete';
        $name = trim($_POST['name'] ?? '');
        $password = $_POST['password'] ?? '';
        $pendingRegister = $_SESSION['pending_register'] ?? null;

        if (!$pendingRegister || empty($pendingRegister['otp_verified'])) {
            add_step_error($errors, 'Step 4', 'OTP verification is required before completing registration.');
        }

        if (empty($errors)) {
            if ($name === '' || $password === '') {
                add_step_error($errors, 'Step 4', 'Please enter a name and password to complete registration.');
            }
        }

        if (empty($errors)) {
            $email = $pendingRegister['email'];
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $role = 'user';

            $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('ssss', $name, $email, $passwordHash, $role);
            if ($stmt->execute()) {
                $stmt->close();
                unset($_SESSION['pending_register']);
                $success = true;
            } else {
                add_step_error($errors, 'Step 4', 'Unable to create account. Please try again.');
                $stmt->close();
            }
        }
    }
}

if ($pendingRegister && time() <= $pendingRegister['otp_expires']) {
    $otpSent = true;

    if (!empty($pendingRegister['otp_verified'])) {
        $otpVerified = true;
    }
} else {
    if ($pendingRegister) {
        unset($_SESSION['pending_register']);
        $pendingRegister = null;
    }
}

$showRequestOtp = !$otpSent && !$otpVerified;
$showOtpSection = $otpSent && !$otpVerified;
$showCompleteSection = $otpVerified;
?>


<!DOCTYPE html>
<html>
<head>
  <title>Register</title>
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
<body class="bg-transparent flex items-center justify-center h-screen">
<div class="bg-white p-6 sm:p-8 rounded-2xl shadow-lg w-full max-w-sm m-4">
  <h2 class="text-2xl font-bold text-center mb-6 text-slate-800">Create Account</h2>

  <?php if ($success): ?>
    <div class="mb-4 rounded-xl bg-green-100 border border-green-300 p-4 text-green-700">
      Account created successfully. Redirecting to login page...
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

  <form method="POST" action="register.php">
    <label class="block text-sm font-semibold mb-2">Email</label>
        <input id="emailInput"
            type="email"
            name="email"
            value="<?php echo htmlspecialchars($pendingRegister['email'] ?? $_POST['email'] ?? ''); ?>"
            class="w-full mb-3 px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-400"
            required
            <?php echo ($otpSent || $otpVerified) ? 'readonly' : ''; ?>
        >

    <?php if ($showRequestOtp): ?>
        <button
            type="submit"
            name="otp_action"
            value="send"
            class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600 mb-4"
        >
            Send Verification Code
        </button>
    <?php endif; ?>

    <?php if ($showOtpSection): ?>
        <div class="mb-4 rounded-xl bg-blue-50 border border-blue-200 p-4 text-blue-700">
        <p class="text-sm mb-2">
            OTP has been sent to
            <?php echo htmlspecialchars($pendingRegister['email'] ?? $_POST['email'] ?? ''); ?>.
            Please check your inbox (and spam folder).
        </p>

        <?php if ($otpMessage): ?>
            <p class="text-sm font-semibold text-blue-800">
                <?php echo htmlspecialchars($otpMessage); ?>
            </p>
        <?php endif; ?>
        </div>

        <label class="block text-sm font-semibold mb-2">Enter OTP</label>

        <input
        type="text"
        name="otp_code"
        value="<?php echo htmlspecialchars($_POST['otp_code'] ?? ''); ?>"
        class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-400"
        required
        >

        <div class="flex gap-2 mt-4">
            <button
                class="flex-1 bg-green-500 text-white py-2 rounded-lg hover:bg-green-600"
                type="submit"
                name="otp_action"
                value="verify"
            >
                Verify Code
            </button>
            <button
                type="submit"
                name="otp_action"
                value="resend"
                formnovalidate
                class="flex-1 bg-gray-200 text-gray-700 py-2 rounded-lg hover:bg-gray-300"
            >
                Resend Code
            </button>
        </div>
    <?php endif; ?>

    <?php if ($showCompleteSection): ?>
      <div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-emerald-700">
        <p class="text-sm font-semibold">OTP verified. Enter your name and password to complete registration.</p>
      </div>

      <label class="block text-sm font-semibold mb-2">Name</label>
      <input name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" class="w-full mb-3 px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-400" required>

      <label class="block text-sm font-semibold mb-2">Password</label>
      <input type="password" name="password" class="w-full mb-4 px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-400" required>

      <button class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600" type="submit" name="otp_action" value="complete">Complete Registration</button>
    <?php endif; ?>

  </form>

  <p class="text-center text-sm mt-4 text-slate-600">
    Already have an account? <a href="Login.php" class="text-blue-500 hover:underline">Login</a>
  </p>
</div>
</body>
</html>
