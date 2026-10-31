<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "fyp";

// Optional PHPMailer support via Composer autoload.
// Install with composer require phpmailer/phpmailer and ensure vendor/autoload.php exists.
$phpMailerAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($phpMailerAutoload)) {
    require_once $phpMailerAutoload;
}

// Set this to true to bypass SMTP and save emails to c:\xampp\htdocs\FYP\mail_log\
// Set to false when you are ready to use a real SMTP provider like Brevo or Gmail.
define('MAIL_DEBUG_TO_FILE', false);

// SMTP configuration for PHPMailer/Gmail.
// Use a Google App Password if your account has 2-step verification enabled.
$smtpHost = 'smtp.gmail.com';
$smtpUser = ''; // Your Gmail address here
$smtpPass = ''; // Your Gmail App Password here
$smtpSecure = 'tls';
$smtpPort = 587;
$smtpFrom = 'no-reply@yourdomain.com';
$smtpFromName = 'Facility System';
$smtpReplyTo = 'no-reply@yourdomain.com';

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function is_logged_in() {
    return !empty($_SESSION['user']);
}

function current_user() {
    return $_SESSION['user'] ?? null;
}

function require_login($role = null) {
    if (!is_logged_in()) {
        if (!validate_remember_cookie()) {
            header("Location: Login.php");
            exit;
        }
    }
    if ($role) {
        $userRole = $_SESSION['user']['role'] ?? '';
        $isAdminLevel = in_array($userRole, ['admin', 'helper']);
        if (($role === 'admin' && !$isAdminLevel) || ($role !== 'admin' && $userRole !== $role)) {
            header("Location: Login.php");
            exit;
        }
    }
}

function validate_remember_cookie() {
    if (!isset($_COOKIE['remember_token'])) {
        return false;
    }
    global $conn;
    $token = $_COOKIE['remember_token'];
    $stmt = $conn->prepare("SELECT id, name, email, role, remember_expiry FROM users WHERE remember_token = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if (strtotime($row['remember_expiry']) > time()) {
            unset($row['remember_expiry']);
            $_SESSION['user'] = $row;
            if (!isset($_SESSION['login_role'])) {
                $_SESSION['login_role'] = $row['role'];
            }
            return true;
        }
    }
    clear_remember_cookie();
    return false;
}

function set_remember_cookie($userId) {
    global $conn;
    $token = bin2hex(random_bytes(32));
    $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
    $stmt = $conn->prepare("UPDATE users SET remember_token = ?, remember_expiry = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('ssi', $token, $expiry, $userId);
        $stmt->execute();
        $stmt->close();
        setcookie('remember_token', $token, time() + 60 * 60 * 24 * 30, '/', '', false, true);
    }
}

function clear_remember_cookie() {
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/', '', false, true);
        unset($_COOKIE['remember_token']);
    }
}

function clear_remember_data($userId = null) {
    global $conn;
    if ($userId !== null) {
        $stmt = $conn->prepare("UPDATE users SET remember_token = NULL, remember_expiry = NULL WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();
        }
    }
    clear_remember_cookie();
}

function refresh_current_user() {
    global $conn;
    if (!is_logged_in()) {
        return;
    }
    $userId = $_SESSION['user']['id'];
    $stmt = $conn->prepare("SELECT id, name, email, role, remember_token, remember_expiry, otp_code, otp_expires FROM users WHERE id = ?");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        unset($row['remember_token'], $row['remember_expiry'], $row['otp_code'], $row['otp_expires']);
        $_SESSION['user'] = $row;
    }
    $stmt->close();
}

function generate_otp_code() {
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function send_otp_email($email, $code, $purpose = 'verification', $link = null) {
    global $smtpHost, $smtpUser, $smtpPass, $smtpSecure, $smtpPort, $smtpFrom, $smtpFromName, $smtpReplyTo;

    // Create HTML version of the message for better alignment and UI
    $htmlMessage = "
    <div style='font-family: Arial, sans-serif; max-width: 500px; margin: 20px auto; padding: 30px; border: 1px solid #e5e7eb; border-radius: 16px; text-align: center; color: #374151;'>
        <div style='margin-bottom: 20px;'>
            <h2 style='color: #1d4ed8; margin: 0;'>Facility System</h2>
        </div>
        <p style='font-size: 16px; margin-bottom: 10px;'>Your one-time password (OTP) for <strong>" . htmlspecialchars($purpose) . "</strong> is:</p>
        <div style='font-size: 32px; font-weight: bold; letter-spacing: 6px; color: #111827; background-color: #f3f4f6; padding: 15px; margin: 20px 0; border-radius: 12px; display: inline-block;'>
            {$code}
        </div>
        " . ($link ? "
        <div style='margin: 30px 0;'>
            <a href='{$link}' style='background-color: #2563eb; color: #ffffff; padding: 14px 28px; text-decoration: none; border-radius: 10px; font-weight: bold; font-size: 14px; display: inline-block;'>Complete Your Setup</a>
        </div>" : "") . "
        <hr style='border: 0; border-top: 1px solid #e5e7eb; margin: 30px 0;'>
        <p style='font-size: 12px; color: #6b7280; line-height: 1.5;'>
            This code expires in <strong>10 minutes</strong>.<br>
            If you did not request this email, you can safely ignore it.
        </p>
    </div>";

    $subject = "OTP Code for INTI Penang Facility System";
    $message = "Your one-time password (OTP) for {$purpose} is: {$code}\n\n" .
               ($link ? "Please complete your setup here: {$link}\n\n" : "") .
               "This code expires in 10 minutes. If you did not request this, please ignore this email.";

    // Use PHPMailer if available (Highly Recommended)
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isHTML(true);
            $mail->isSMTP();
            // Set debug mode to 0 to disable technical output now that it works
            $mail->SMTPDebug = 0; 
            $mail->Host = $smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $smtpUser; 
            $mail->Password = $smtpPass; 
            $mail->SMTPSecure = $smtpSecure; // Usually 'tls' or 'ssl'
            $mail->Port = $smtpPort;
            
            // Bypass SSL certificate verification for xampp local host
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            
            // Gmail often requires the 'From' address to match the authenticated Username
            $mail->setFrom($smtpUser, $smtpFromName);
            $mail->addReplyTo($smtpUser, $smtpFromName);
            
            $mail->addAddress($email);
            $mail->Subject = $subject;
            $mail->Body = $htmlMessage;
            $mail->AltBody = $message;
            $mail->send();
            return true;
        } catch (\Exception $e) {
            error_log('PHPMailer error: ' . $e->getMessage());
            return false;
        }
    }

    $headers = "From: {$smtpFrom}\r\n" .
               "Reply-To: {$smtpReplyTo}\r\n" .
               "X-Mailer: PHP/" . phpversion();

    // Suppress warnings when no local SMTP server is available.
    return @mail($email, $subject, $message, $headers);
}

function send_notification_email($email, $reportId, $case, $purpose = 'notify', $emergencyTier) {
    global $smtpHost, $smtpUser, $smtpPass, $smtpSecure, $smtpPort, $smtpFrom, $smtpFromName, $smtpReplyTo;
    
    if ($reportId === null && $emergencyTier === null) {
        if ($purpose === 'Resolved') {
            $text = "Your Reported Case Has Been Resolved";
        } elseif ($purpose === 'In Progress') {
            $text = "Your Reported Case is Now In Progress";
        } else {
            $text = "Your Reported Case Has Been Updated";
        }
        // Create HTML version of the message for better alignment and UI
        $htmlMessage = "
        <div style='font-family: Arial, sans-serif; max-width: 500px; margin: 20px auto; padding: 30px; border: 1px solid #e5e7eb; border-radius: 16px; text-align: center; color: #374151;'>
            <div style='margin-bottom: 20px;'>
                <h2 style='color: #1d4ed8; margin: 0;'>Facility System</h2>
            </div>
                <p style='font-size: 16px; margin-bottom: 10px;'> $text </p>
            <hr style='border: 0; border-top: 1px solid #e5e7eb; margin: 30px 0;'>
            <p style='font-size: 12px; color: #6b7280; line-height: 1.5;'>
                You can safely ignore this email.
            </p>
        </div>";

        $subject = "Notification: Your Reported Case Has Been " . ucfirst($purpose);
        $message = "$text:\n\n" . "You can safely ignore this email.";
    } else {
        $text = "The Reported Case (ID: $case {$reportId}) Has Been Updated to '{$purpose}'";
        // Create HTML version of the message for better alignment and UI
        $htmlMessage = "
        <div style='font-family: Arial, sans-serif; max-width: 500px; margin: 20px auto; padding: 30px; border: 1px solid #e5e7eb; border-radius: 16px; text-align: center; color: #374151;'>
            <div style='margin-bottom: 20px;'>
                <h2 style='color: #1d4ed8; margin: 0;'>Facility System</h2>
            </div>
                <p style='font-size: 16px; margin-bottom: 10px;'> The case (ID: $case {$reportId}) has been updated to '{$purpose}'.</p>
                <p style='font-size: 16px; margin-bottom: 10px;'> Emergency Tier: {$emergencyTier}</p>
            <hr style='border: 0; border-top: 1px solid #e5e7eb; margin: 30px 0;'>
            <p style='font-size: 12px; color: #6b7280; line-height: 1.5;'>
                You can safely ignore this email.
            </p>
        </div>";
        $subject = "Notification: The Assigned Reported Case Has Been " . ucfirst($purpose);
        $message = "$text:\n\n" . "You can safely ignore this email.";
    }

    // Use PHPMailer if available (Highly Recommended)
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isHTML(true);
            $mail->isSMTP();
            // Set debug mode to 0 to disable technical output now that it works
            $mail->SMTPDebug = 0; 
            $mail->Host = $smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $smtpUser; 
            $mail->Password = $smtpPass; 
            $mail->SMTPSecure = $smtpSecure; // Usually 'tls' or 'ssl'
            $mail->Port = $smtpPort;
            
            // Bypass SSL certificate verification for xampp local host
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            
            // Gmail often requires the 'From' address to match the authenticated Username
            $mail->setFrom($smtpUser, $smtpFromName);
            $mail->addReplyTo($smtpUser, $smtpFromName);
            
            $mail->addAddress($email);
            $mail->Subject = $subject;
            $mail->Body = $htmlMessage;
            $mail->AltBody = $message;
            $mail->send();
            return true;
        } catch (\Exception $e) {
            error_log('PHPMailer error: ' . $e->getMessage());
            return false;
        }
    }

    $headers = "From: {$smtpFrom}\r\n" .
               "Reply-To: {$smtpReplyTo}\r\n" .
               "X-Mailer: PHP/" . phpversion();

    // Suppress warnings when no local SMTP server is available.
    return @mail($email, $subject, $message, $headers);
}

function send_notification_email_to_staff($email, $subject, $message) {
    global $smtpHost, $smtpUser, $smtpPass, $smtpSecure, $smtpPort, $smtpFrom, $smtpFromName, $smtpReplyTo;

    // Create HTML version of the message for better alignment and UI
    $htmlMessage = "
        <div style='font-family: Arial, sans-serif; max-width: 500px; margin: 20px auto; padding: 30px; border: 1px solid #e5e7eb; border-radius: 16px; text-align: center; color: #374151;'>
            <div style='margin-bottom: 20px;'>
                <h2 style='color: #1d4ed8; margin: 0;'>Facility System</h2>
            </div>
            <p style='font-size: 16px; margin-bottom: 10px;'>$message</p>
            <hr style='border: 0; border-top: 1px solid #e5e7eb; margin: 30px 0;'>
            <p style='font-size: 12px; color: #6b7280; line-height: 1.5;'>
                You can safely ignore this email if you did not expect it.
            </p>
        </div>";
    $subject = "New Case Assigned";
    $message = "$text:\n\n" . "You can safely ignore this email.";

    // Use PHPMailer if available (Highly Recommended)
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isHTML(true);
            $mail->isSMTP();
            // Set debug mode to 0 to disable technical output now that it works
            $mail->SMTPDebug = 0; 
            $mail->Host = $smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $smtpUser; 
            $mail->Password = $smtpPass; 
            $mail->SMTPSecure = $smtpSecure; // Usually 'tls' or 'ssl'
            $mail->Port = $smtpPort;
            
            // Bypass SSL certificate verification for xampp local host
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            
            // Gmail often requires the 'From' address to match the authenticated Username
            $mail->setFrom($smtpUser, $smtpFromName);
            $mail->addReplyTo($smtpUser, $smtpFromName);
            
            $mail->addAddress($email);
            $mail->Subject = $subject;
            $mail->Body = $htmlMessage;
            $mail->AltBody = $message;
            $mail->send();
            return true;
        } catch (\Exception $e) {
            error_log('PHPMailer error: ' . $e->getMessage());
            return false;
        }
    }

    $headers = "From: {$smtpFrom}\r\n" .
               "Reply-To: {$smtpReplyTo}\r\n" .
               "X-Mailer: PHP/" . phpversion();

    // Suppress warnings when no local SMTP server is available.
    return @mail($email, $subject, $message, $headers);
}

function store_user_otp($userId, $code) {
    global $conn;
    $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    $stmt = $conn->prepare("UPDATE users SET otp_code = ?, otp_expires = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('ssi', $code, $expires, $userId);
        $stmt->execute();
        $stmt->close();
        return true;
    }
    return false;
}

function verify_user_otp($userId, $code) {
    global $conn;
    $stmt = $conn->prepare("SELECT otp_code, otp_expires FROM users WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $valid = false;
    if ($row = $result->fetch_assoc()) {
        if ($row['otp_code'] === $code && strtotime($row['otp_expires']) > time()) {
            $valid = true;
        }
    }
    $stmt->close();
    return $valid;
}

/**
 * Get all car plates for a specific user
 */
function get_user_car_plates($userId) {
    global $conn;
    $plates = [];
    $stmt = $conn->prepare("SELECT id, plate_number FROM user_car_plates WHERE user_id = ? ORDER BY created_at DESC");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $plates[] = $row;
        }
        $stmt->close();
    }
    return $plates;
}

/**
 * Add a new car plate for a user
 */
function add_user_car_plate($userId, $plateNumber) {
    global $conn;
    $plateNumber = strtoupper(trim($plateNumber));
    if (empty($plateNumber)) return false;
    
    $stmt = $conn->prepare("INSERT INTO user_car_plates (user_id, plate_number) VALUES (?, ?)");
    if ($stmt) {
        $stmt->bind_param('is', $userId, $plateNumber);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
    return false;
}

/**
 * Delete a car plate
 */
function delete_user_car_plate($userId, $plateId) {
    global $conn;
    $stmt = $conn->prepare("DELETE FROM user_car_plates WHERE id = ? AND user_id = ?");
    if ($stmt) {
        $stmt->bind_param('ii', $plateId, $userId);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
    return false;
}

/**
 * Check and grant new achievements for a user
 */
function check_user_achievements($userId) {
    global $conn;
    
    // Do not process achievements for admin or helper roles
    $roleStmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    if (!$roleStmt) {
        throw new RuntimeException('Unable to check the user role for achievements.');
    }
    $roleStmt->bind_param('i', $userId);
    if (!$roleStmt->execute()) {
        $roleStmt->close();
        throw new RuntimeException('Unable to check the user role for achievements.');
    }
    $u = $roleStmt->get_result()->fetch_assoc();
    $roleStmt->close();

    if (!$u || in_array($u['role'], ['admin', 'helper'])) {
        return;
    }

    // Count only reports completed by the facility team toward achievements.
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM reports WHERE user_id = ? AND status = 'Resolved'");
    if (!$stmt) {
        throw new RuntimeException('Unable to count reports for achievements.');
    }
    $stmt->bind_param('i', $userId);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Unable to count reports for achievements.');
    }
    $reportCount = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    $grantStmt = $conn->prepare(
        "INSERT IGNORE INTO user_achievements (user_id, achievement_id)
         SELECT ?, id FROM achievements WHERE threshold <= ?"
    );
    if (!$grantStmt) {
        throw new RuntimeException('Unable to prepare achievement rewards.');
    }
    $grantStmt->bind_param('ii', $userId, $reportCount);
    if (!$grantStmt->execute()) {
        $grantStmt->close();
        throw new RuntimeException('Unable to save earned achievements.');
    }
    $grantStmt->close();
}
