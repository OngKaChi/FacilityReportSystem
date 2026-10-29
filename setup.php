<?php

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "fyp";

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) === TRUE) {
    echo "Database created successfully<br>";
} else {
    echo "Error creating database: " . $conn->error . "<br>";
}

// Select the database
$conn->select_db($dbname);

// Create reports table
$sql = "CREATE TABLE IF NOT EXISTS reports (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    floor VARCHAR(50) NOT NULL,
    category VARCHAR(100) NOT NULL,
    location VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    photo VARCHAR(255),
    emergency_tier VARCHAR(20) NOT NULL DEFAULT 'Normal',
    status VARCHAR(50) DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    assigned_to INT(10) UNSIGNED DEFAULT NULL,
    user_id INT(10) UNSIGNED DEFAULT NULL
)";

if ($conn->query($sql) === TRUE) {
    echo "Table 'reports' created successfully<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('user','admin','helper') DEFAULT 'user',
    remember_token VARCHAR(255) DEFAULT NULL,
    remember_expiry DATETIME DEFAULT NULL,
    otp_code VARCHAR(10) DEFAULT NULL,
    otp_expires DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Table 'users' created successfully<br>";
} else {
    echo "Error creating users table: " . $conn->error . "<br>";
}

$sql = "CREATE TABLE IF NOT EXISTS issue_assignments (
    id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_id INT(6) UNSIGNED NOT NULL,
    staff_id INT(10) UNSIGNED NOT NULL,
    assignment_start DATE NOT NULL,
    assignment_end DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    progress_description TEXT,
    FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "Table 'issue_assignments' created successfully<br>";
} else {
    echo "Error creating issue_assignments table: " . $conn->error . "<br>";
}

// Create user_car_plates table
$sql = "CREATE TABLE IF NOT EXISTS user_car_plates (
    id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(10) UNSIGNED NOT NULL,
    plate_number VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "Table 'user_car_plates' created successfully<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

// Create facility_rooms table to store custom room labels
$sql = "CREATE TABLE IF NOT EXISTS facility_rooms (
    id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    floor VARCHAR(50) NOT NULL,
    room_id VARCHAR(100) NOT NULL,
    label VARCHAR(255) NOT NULL,
    UNIQUE KEY floor_room (floor, room_id)
)";

if ($conn->query($sql) !== TRUE) {
    echo "Error creating facility_rooms table: " . $conn->error . "<br>";
}

// Create achievements table
$sql = "CREATE TABLE IF NOT EXISTS achievements (
    id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    threshold INT(10) NOT NULL,
    icon VARCHAR(50) DEFAULT '🏆'
)";
$conn->query($sql);

// Create user_achievements mapping table
$sql = "CREATE TABLE IF NOT EXISTS user_achievements (
    user_id INT(10) UNSIGNED NOT NULL,
    achievement_id INT(10) UNSIGNED NOT NULL,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, achievement_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE
)";
$conn->query($sql);

// Seed default achievements
$achievements = [
    ['First Discovery', 'Submitted your first report.', 1, '🌱'],
    ['Active Reporter', 'Submitted 5 reports.', 5, '🌿'],
    ['Facility Inspector', 'Submitted 10 reports.', 10, '🪴'],
    ['Eagle Eye', 'Submitted 15 reports.', 15, '🌳'],
    ['Part-Time Reporter', 'Submitted 20 reports.', 20, '🪵'],
    ['Facility Legend', 'Submitted 25 reports.', 25, '🪓'],
    ['Facility Overseer', 'Submitted 50 reports.', 50, '🏡'],
    ['Full-Time Reporter', 'Submitted 100 reports.', 100, '🏰']
];

foreach ($achievements as $a) {
    $stmt = $conn->prepare("INSERT IGNORE INTO achievements (title, description, threshold, icon) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('ssis', $a[0], $a[1], $a[2], $a[3]);
    $stmt->execute();
    $stmt->close();
}

// Seed default room labels for Level 4
$level4Rooms = [
    ['floor' => 'Level 4', 'room_id' => 'staircase_4_1', 'label' => 'Stair1'],
    ['floor' => 'Level 4', 'room_id' => 'room_4_1', 'label' => 'LR401A'],
    ['floor' => 'Level 4', 'room_id' => 'room_4_2', 'label' => 'LR401'],
    ['floor' => 'Level 4', 'room_id' => 'room_4_3', 'label' => 'LR402'],
    ['floor' => 'Level 4', 'room_id' => 'room_4_4', 'label' => 'LR403'],
    ['floor' => 'Level 4', 'room_id' => 'room_4_5', 'label' => 'LR404'],
    ['floor' => 'Level 4', 'room_id' => 'room_4_6', 'label' => 'LR405'],
    ['floor' => 'Level 4', 'room_id' => 'room_4_7', 'label' => 'LR406'],
    ['floor' => 'Level 4', 'room_id' => 'room_4_8', 'label' => 'LR407'],
    ['floor' => 'Level 4', 'room_id' => 'toilets_4_1', 'label' => 'Male Toilet'],
    ['floor' => 'Level 4', 'room_id' => 'staircase_4_2', 'label' => 'Stair2'],
    ['floor' => 'Level 4', 'room_id' => 'room_4_9', 'label' => 'Design Simulation Lab'],
    ['floor' => 'Level 4', 'room_id' => 'room_4_10', 'label' => 'Science Lab Store'],
    ['floor' => 'Level 4', 'room_id' => 'lift_4_2', 'label' => 'Lift 2'],
    ['floor' => 'Level 4', 'room_id' => 'lift_4_1', 'label' => 'Lift 1'],
    ['floor' => 'Level 4', 'room_id' => 'study_area', 'label' => 'Study Area'],
    ['floor' => 'Level 4', 'room_id' => 'staircase_4_3', 'label' => 'Main Stair'],
    ['floor' => 'Level 4', 'room_id' => 'room_4_11', 'label' => 'Project Lab 2'],
    ['floor' => 'Level 4', 'room_id' => 'room_4_12', 'label' => 'Teletronic Lab'],
    ['floor' => 'Level 4', 'room_id' => 'staircase_4_4', 'label' => 'Stair3'],
    ['floor' => 'Level 4', 'room_id' => 'room_4_13', 'label' => 'Chemistry Lab 1'],
    ['floor' => 'Level 4', 'room_id' => 'room_4_14', 'label' => 'Chemistry Lab 2'],
    ['floor' => 'Level 4', 'room_id' => 'room_4_15', 'label' => 'Science Preparation Lab'],
    ['floor' => 'Level 4', 'room_id' => 'room_4_16', 'label' => 'Physics Lab'],
    ['floor' => 'Level 4', 'room_id' => 'room_4_17', 'label' => 'Biology Lab'],
    ['floor' => 'Level 4', 'room_id' => 'room_4_18', 'label' => 'Motorola Solutions Embedded Lab'],
    ['floor' => 'Level 4', 'room_id' => 'room_4_19', 'label' => 'Electrical Lab'],
    ['floor' => 'Level 4', 'room_id' => 'room_4_20', 'label' => 'Project Lab 1'],
    ['floor' => 'Level 4', 'room_id' => 'toilets_4_2', 'label' => 'Female Toilet'],
    ['floor' => 'Level 4', 'room_id' => 'staircase_4_5', 'label' => 'Stair4'],
];

foreach ($level4Rooms as $room) {
    // Use INSERT IGNORE to avoid errors if the room already exists
    $stmt = $conn->prepare("INSERT IGNORE INTO facility_rooms (floor, room_id, label) VALUES (?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param('sss', $room['floor'], $room['room_id'], $room['label']);
        $stmt->execute();
        $stmt->close();
    }
}
echo "Default Level 4 room labels seeded successfully.<br>";

$alterions = [
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS remember_token VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS remember_expiry DATETIME DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS otp_code VARCHAR(10) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS otp_expires DATETIME DEFAULT NULL",
    "ALTER TABLE users MODIFY COLUMN role ENUM('user','admin','helper') DEFAULT 'user'",
    "ALTER TABLE reports ADD COLUMN IF NOT EXISTS assigned_to INT(10) UNSIGNED DEFAULT NULL",
    "ALTER TABLE reports ADD COLUMN IF NOT EXISTS user_id INT(10) UNSIGNED DEFAULT NULL",
    "ALTER TABLE reports ADD COLUMN IF NOT EXISTS emergency_tier VARCHAR(20) NOT NULL DEFAULT 'Normal'",
    "ALTER TABLE reports ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
];

foreach ($alterions as $alterSql) {
    if ($conn->query($alterSql) !== TRUE) {
        echo "Error updating users table: " . $conn->error . "<br>";
    }
}

$adminEmail = 'admin@example.com';
$adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
$adminName = 'Administrator';
$adminRole = 'admin';

$stmt = $conn->prepare("INSERT INTO users (name, email, password, role) SELECT ?, ?, ?, ? FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = ?)");
if ($stmt) {
    $stmt->bind_param('sssss', $adminName, $adminEmail, $adminPassword, $adminRole, $adminEmail);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        echo "Default admin account created: admin@example.com / admin123<br>";
    }
    $stmt->close();
}

$conn->close();

echo "Setup complete! You can now use the dashboard.";
?>