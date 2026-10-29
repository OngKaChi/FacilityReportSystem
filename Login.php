<?php
require_once 'auth.php';

define('MAX_ATTEMPTS', 5);
define('LOCKED_TIME', 30);

if (is_logged_in() || validate_remember_cookie()) {
    $user = $_SESSION['user'];
    $loginRole = $_SESSION['login_role'] ?? $user['role'];
    header('Location: ' . ($loginRole === 'admin' ? 'admindashboard.php' : 'dashboard.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $loginRole = $_POST['login_role'] ?? 'user';

    if (isset($_SESSION['locked_until']) && time() < $_SESSION['locked_until']) {
      $remainingTime = $_SESSION['locked_until'] - time();
      $error = "Too many failed login attempts. Please try again in $remainingTime seconds.";
    } elseif ($email === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } elseif (!in_array($loginRole, ['user', 'admin'], true)) {
        $error = 'Please choose a valid login type.';
    } else {
        $stmt = $conn->prepare('SELECT id, name, email, password, role FROM users WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
                // Allow admin and helper accounts to log in through Admin route.
                $isAdminRoute = ($loginRole === 'admin');
                $hasAdminPrivilege = in_array($user['role'], ['admin', 'helper']);
                if ($isAdminRoute && !$hasAdminPrivilege) {
                    $error = 'This login route is for Head of AFM and AFM Staff only.';
                } else {
                  unset($_SESSION['login_attempts']);
                  unset($_SESSION['locked_until']);

                  unset($user['password']);
                  $_SESSION['user'] = $user;
                  $_SESSION['login_role'] = $loginRole;

                  if (!empty($_POST['remember'])) {
                      set_remember_cookie($user['id']);
                  } else {
                      clear_remember_data($user['id']);
                  }

                  $redirect = $loginRole === 'admin' ? 'admindashboard.php' : 'dashboard.php';
                  header('Location: ' . $redirect);
                  exit;
                }
            }
        }
        if (!$error) {
            $error = 'Invalid email or password.';
        }

        $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
        if ($_SESSION['login_attempts'] >= MAX_ATTEMPTS) {
          $_SESSION['locked_until'] = time() + LOCKED_TIME;
          $error = "Too many failed login attempts. Please try again in 30 seconds.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login</title>
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
    .toggle-switch {
      display: flex;
      gap: 0.5rem;
      background: #e2e8f0;
      border-radius: 9999px;
      padding: 0.25rem;
    }
    .toggle-switch input {
      display: none;
    }
    .toggle-switch label {
      flex: 1;
      cursor: pointer;
      border-radius: 9999px;
      padding: 0.75rem 0.75rem;
      text-align: center;
      transition: all 0.2s ease;
      color: #475569;
    }
    .toggle-switch input:checked + label {
      background: #0ea5e9;
      color: #ffffff;
    }
  </style>
</head>

<body class="bg-transparent flex items-center justify-center min-h-screen transition-colors duration-300">

<div class="mx-4 w-full max-w-md rounded-2xl bg-white p-6 shadow-lg sm:mx-0 sm:w-1/2 sm:p-8 lg:w-96">

  <div class="flex flex-col items-center justify-center mb-8">
    <img src="./img/logo.png" alt="INTI Logo" class="h-18 w-auto mb-2">
    <h2 class="text-2xl font-bold text-slate-800 tracking-tight">Facility System</h2>
  </div>
  <?php if ($error): ?>
    <div class="mb-4 rounded-xl bg-red-100 border border-red-300 p-4 text-red-700"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <form method="POST" action="Login.php">
    <label class="block text-sm font-semibold mb-2 justify-center items-center" >Login As</label>
    <div class="toggle-switch mb-4">
      <input id="role-user" type="radio" name="login_role" value="user" <?php echo (($_POST['login_role'] ?? 'user') === 'user' ? 'checked' : ''); ?> />
      <label for="role-user">User</label>
      <input id="role-admin" type="radio" name="login_role" value="admin" <?php echo (($_POST['login_role'] ?? '') === 'admin' ? 'checked' : ''); ?> />
      <label for="role-admin">AFM</label>
    </div>

    <label class="block text-sm font-semibold mb-2">Email</label>
    <input type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" class="w-full mb-3 px-3 py-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400" required>

    <label class="block text-sm font-semibold mb-2">Password</label>
    <input type="password" name="password" class="w-full mb-3 px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400" required>

    <label class="inline-flex items-center mb-4">
      <input type="checkbox" name="remember" class="mr-2">
      <span class="text-sm">Remember Me</span>
    </label>

    <button type="submit" class="w-full bg-blue-500 text-white py-3 rounded-lg hover:bg-blue-600">Login</button>
  </form>

  <div class="mt-4 flex justify-between text-sm text-slate-600">
    <a href="register.php" class="text-blue-500 hover:underline">Create account</a>
    <a href="forgot_password.php" class="text-blue-500 hover:underline">Forgot password</a>
  </div>
</div>
</body>
</html>