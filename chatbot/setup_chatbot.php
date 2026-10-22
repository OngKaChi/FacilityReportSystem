<?php
require_once __DIR__ . '/../config.php';

// Database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create knowledge_base table
$sql = "CREATE TABLE IF NOT EXISTS knowledge_base (
    id INT AUTO_INCREMENT PRIMARY KEY,
    topic VARCHAR(100) NOT NULL,
    information TEXT NOT NULL,
    keywords TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Knowledge base table created successfully<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

// Insert default knowledge
$defaultKnowledge = [
    ['topic' => 'facility_policies', 'information' => 'Our facility operates Monday-Friday 8AM-5PM. Emergency maintenance is available 24/7. All reports are reviewed within 24 hours.', 'keywords' => 'hours, open, time, facility, operation'],
    ['topic' => 'emergency_procedures', 'information' => 'For emergencies: Call security at ext 555 or emergency services. Stay in safe location and follow evacuation procedures if needed.', 'keywords' => 'emergency, urgent, safety, security, evacuation'],
    ['topic' => 'parking_rules', 'information' => 'Double parking blocks emergency access and may result in towing. Parking enforcement responds within 30 minutes during business hours.', 'keywords' => 'parking, double, tow, vehicle, enforcement'],
    ['topic' => 'maintenance_schedule', 'information' => 'Preventive maintenance occurs quarterly. Urgent repairs prioritized within 24 hours. Non-urgent within 5-7 business days.', 'keywords' => 'maintenance, schedule, preventive, repair, urgent'],
    ['topic' => 'equipment_issues', 'information' => 'Report broken equipment immediately with location and description. Safety hazards get immediate response.', 'keywords' => 'broken, equipment, malfunction, repair, fix'],
    ['topic' => 'report_process', 'information' => 'Use the Report Issue button to submit problems. Include floor, location, description, and photos when possible.', 'keywords' => 'report, submit, how, form, issue'],
    ['topic' => 'issue_type_guide', 'information' => 'There are three issue types:' . '<div>'.'(1) Broken Equipment - for malfunctioning lights, AC, doors, elevators, etc. (2) Maintenance - for cleaning, repairs, inspections, rust, wear and tear. (3) Double Parking - for parking violations and blocked access. Tell me your issue and I can help you categorize it!</div>', 'keywords' => 'issue type, category, which type, what category, help me categorize']
];

foreach ($defaultKnowledge as $item) {
    $stmt = $conn->prepare("INSERT IGNORE INTO knowledge_base (topic, information, keywords) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $item['topic'], $item['information'], $item['keywords']);
    $stmt->execute();
    $stmt->close();
}

echo "Default knowledge inserted<br>";
echo "Setup complete! Your chatbot is now trainable.";

$conn->close();
?>