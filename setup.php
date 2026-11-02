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

// Seed the selectable, labeled areas shown on the facility map.
$defaultFacilityRooms = [
    'Basement' => [
        'parking_0_1' => 'Hillside Parking',
        'parking_0_2' => 'Outside Parking',
        'staircase_0_1' => 'Stair1',
        'parking_0_3' => 'Parking Basement 1',
        'parking_0_4' => 'Parking Basement 2',
        'parking_0_5' => 'Parking Basement 3',
        'parking_0_6' => 'Parking Basement 4',
        'parking_0_7' => 'Parking Basement 5',
        'parking_0_8' => 'Parking Basement 6',
        'staircase_0_2' => 'Stair2',
    ],
    'Level 1' => [
        'parking_1_1' => 'Downhill Parking',
        'parking_1_2' => 'Uphill Parking',
        'parking_1_3' => 'Parking Ground 1',
        'staircase_1_1' => 'Stair1',
        'parking_1_4' => 'Parking Ground 2',
        'parking_1_5' => 'Parking Ground 3',
        'parking_1_6' => 'Parking Ground 4',
        'lift_1_1' => 'Lift',
        'parking_1_7' => 'Parking Ground 5',
        'room_1_1' => 'Store 1',
        'room_1_2' => 'Store 2',
        'room_1_3' => 'Mechanical Lab',
        'room_1_4' => 'Store 3',
        'room_1_5' => 'Store 4',
        'room_1_6' => 'Store 5',
        'room_1_7' => 'Music Room',
        'room_1_8' => 'Store 6',
        'staircase_1_2' => 'Stair2',
        'staircase_1_3' => 'Stair3',
        'parking_1_8' => 'Parking Ground 6',
        'parking_1_9' => 'Parking Ground 7',
    ],
    'Level 2' => [
        'parking_2_1' => 'Parking 1',
        'parking_2_2' => 'Parking 2',
        'parking_2_3' => 'Parking 3',
        'parking_2_4' => 'Parking 4',
        'parking_2_5' => 'Parking 5',
        'staircase_2_1' => 'Stair1',
        'room_2_1' => 'Alkira Restaurant',
        'room_2_2' => 'Registry Office',
        'room_2_3' => 'Finance Office',
        'room_2_4' => 'University Placement Office / Sick Bay',
        'room_2_5' => 'Artificial Intelligence Laboratory',
        'room_2_6' => 'Cloud Computing Laboratory',
        'toilets_2_1' => 'Male Toilet',
        'staircase_2_2' => 'Stair2',
        'room_2_7' => 'Pastry Kitchen',
        'cafeteria_2_1' => 'Cafeteria A',
        'lift_2_2' => 'Lift 2',
        'lift_2_1' => 'Lift 1',
        'lobby_2_1' => 'Lobby',
        'staircase_2_3' => 'Middle Staircase',
        'cafeteria_2_2' => 'Cafeteria B',
        'room_2_8' => 'Student Affairs Office',
        'room_2_9' => 'INTIMA Office',
        'room_2_10' => 'Hotel Mock Room',
        'room_2_11' => 'Startup Incubator',
        'room_2_12' => 'Bilik MDF',
        'staircase_2_4' => 'Stair3',
        'room_2_13' => 'Cafeteria',
        'room_2_14' => 'Admission & Counseling Office',
        'room_2_15' => 'Reception',
        'room_2_16' => 'Chef Executive / Government Liaison Complaince / HDRF Training Provider Office',
        'room_2_17' => 'AFM',
        'room_2_18' => 'Radio Conti Room / Photography Studio',
        'room_2_19' => 'Recording / Broadcast Studio',
        'toilets_2_2' => 'Female Toilet',
        'staircase_2_5' => 'Stair4',
    ],
    'Level 3' => [
        'staircase_3_1' => 'Stair1',
        'room_3_1' => 'Aristotle Room',
        'room_3_2' => 'CADP / SOHOS / Language & MPU Centre / Student Experience Office / International Office',
        'room_3_3' => 'Network Lab',
        'room_3_4' => 'Information Technology Services',
        'room_3_5' => 'IT Lab B',
        'room_3_6' => 'IT Lab A',
        'room_3_7' => 'Thermofluid Lab',
        'room_3_8' => 'School of Business',
        'toilets_3_1' => 'Male Toilet',
        'staircase_3_2' => 'Stair2',
        'room_3_9' => 'CEPS',
        'room_3_10' => 'Solid Mechanics Lab',
        'lift_3_2' => 'Lift 2',
        'lift_3_1' => 'Lift 1',
        'cubical_area' => 'Cubical Area',
        'staircase_3_3' => 'Middle Staircase',
        'room_3_11' => 'Corporate Training Room 1',
        'room_3_12' => 'Human Resource Office',
        'room_3_13' => 'Career Services Office',
        'room_3_14' => 'Convenience Store',
        'staircase_3_4' => 'Stair3',
        'room_3_15' => 'Psychology Lab / CAE / CCL',
        'room_3_16' => 'AAC / SOE / SOC',
        'room_3_17' => 'Academic Office',
        'toilets_3_2' => 'Female Toilet',
        'staircase_3_5' => 'Stair4',
    ],
    'Level 4' => [
        'staircase_4_1' => 'Stair1',
        'room_4_1' => 'LR401A',
        'room_4_2' => 'LR401',
        'room_4_3' => 'LR402',
        'room_4_4' => 'LR403',
        'room_4_5' => 'LR404',
        'room_4_6' => 'LR405',
        'room_4_7' => 'LR406',
        'room_4_8' => 'LR407',
        'toilets_4_1' => 'Male Toilet',
        'staircase_4_2' => 'Stair2',
        'room_4_9' => 'Design Simulation Lab',
        'room_4_10' => 'Science Lab Store',
        'lift_4_2' => 'Lift 2',
        'lift_4_1' => 'Lift 1',
        'study_area' => 'Study Area',
        'staircase_4_3' => 'Middle Staircase',
        'room_4_11' => 'Project Lab 2',
        'room_4_12' => 'Teletronic Lab',
        'staircase_4_4' => 'Stair3',
        'room_4_13' => 'Chemistry Lab 1',
        'room_4_14' => 'Chemistry Lab 2',
        'room_4_15' => 'Science Preparation Lab',
        'room_4_16' => 'Physics Lab',
        'room_4_17' => 'Biology Lab',
        'room_4_18' => 'Motorola Solutions Embedded Lab',
        'room_4_19' => 'Electrical Lab',
        'room_4_20' => 'Project Lab 1',
        'toilets_4_2' => 'Female Toilet',
        'staircase_4_5' => 'Stair4',
    ],
    'Level 5' => [
        'staircase_5_1' => 'Stair1',
        'room_5_1' => 'LR501',
        'room_5_2' => 'LR502',
        'room_5_3' => 'LR503',
        'room_5_4' => 'LR504',
        'room_5_5' => 'LR505',
        'room_5_6' => 'LR506',
        'room_5_7' => 'LR507',
        'room_5_8' => 'LR508',
        'toilets_5_1' => 'Male Toilet',
        'staircase_5_2' => 'Stair2',
        'room_5_9' => 'Mock Reception',
        'room_5_22' => 'iMAC Lab',
        'lift_5_2' => 'Lift 2',
        'lift_5_1' => 'Lift 1',
        'staircase_5_3' => 'Middle Staircase',
        'room_5_10' => 'STEM Lab',
        'room_5_11' => 'LR509B',
        'room_5_12' => 'LR501A',
        'room_5_13' => 'LR501B',
        'staircase_5_4' => 'Stair3',
        'room_5_14' => 'LR514',
        'room_5_15' => 'LR513',
        'room_5_16' => 'LR512',
        'room_5_17' => 'Maintenance',
        'room_5_18' => 'Lecture Theater',
        'room_5_19' => 'LR511',
        'room_5_20' => 'Sarau Lelaki',
        'room_5_21' => 'Sarau Perempuan',
        'toilets_5_2' => 'Female Toilet',
        'staircase_5_5' => 'Stair4',
    ],
    'Level 6' => [
        'staircase_6_1' => 'Stair1',
        'room_6_1' => 'Exam Room',
        'library_6_1' => 'Study Area',
        'library_6_2' => 'Discussion Room 4',
        'library_6_3' => 'Discussion Room 5',
        'library_6_4' => 'Library',
        'library_6_5' => 'Discussion Room 1',
        'library_6_6' => 'Discussion Room 2',
        'library_6_7' => 'Discussion Room 3',
        'toilets_6_1' => 'Female Toilet',
        'toilets_6_2' => 'Male Toilet',
        'staircase_6_2' => 'Stair2',
        'room_6_2' => 'LR604',
        'room_6_3' => 'LR603',
        'lift_6_2' => 'Lift 2',
        'lift_6_1' => 'Lift 1',
        'staircase_6_3' => 'Middle Staircase',
        'room_6_4' => 'Multi Purpose Hall',
        'store_6_1' => 'Store',
        'staircase_6_4' => 'Stair3',
        'room_6_5' => 'LR602',
        'room_6_6' => 'LR601',
        'room_6_7' => 'Student Counselling Service',
        'room_6_8' => 'Lecture Theater',
        'room_6_9' => 'LR605',
        'room_6_10' => 'LR606',
        'staircase_6_5' => 'Stair4',
    ],
    'Rooftop' => [
        'staircase_7_1' => 'Stair1',
        'rooftop_7_1' => 'Rooftop Outside Seat 1',
        'rooftop_7_2' => 'Rooftop Outside Seat 2',
        'rooftop_7_3' => 'Rooftop Inside Seat 1',
        'rooftop_7_4' => 'Rooftop Inside Seat 2',
        'rooftop_7_5' => 'Rooftop Barbeque Area',
        'staircase_7_2' => 'Stair2',
        'staircase_7_3' => 'Middle Staircase',
    ],
];

$stmt = $conn->prepare("INSERT IGNORE INTO facility_rooms (floor, room_id, label) VALUES (?, ?, ?)");
if (!$stmt) {
    die("Error preparing default facility room labels: " . $conn->error);
}
foreach ($defaultFacilityRooms as $floor => $rooms) {
    foreach ($rooms as $roomId => $label) {
        $stmt->bind_param('sss', $floor, $roomId, $label);
        if (!$stmt->execute()) {
            die("Error seeding facility room labels: " . $stmt->error);
        }
    }
}
$stmt->close();
echo "Default facility room labels seeded successfully.<br>";

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