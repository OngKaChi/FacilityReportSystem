<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';


// Get the raw POST data from the JavaScript frontend
$input = json_decode(file_get_contents('php://input'), true);
$history = $input['history'] ?? [];

if (empty($history)) {
    echo json_encode(['reply' => 'System Error: Chat history array payload is empty.']);
    exit;
}

// 1. Enter your exact key securely here (Make sure there are no spaces!)
$model = 'gemini-2.5-flash';
$apiKey = GEMINI_API_KEY; 

// 2. Clean endpoint URL string without append concatenations
$url = "https://generativelanguage.googleapis.com/v1beta/models/" . $model . ":generateContent";

// 3. System context schema layout (tuned configuration settings)
$data = [
    "contents" => $history, 
    "system_instruction" => [
        "parts" => [
            ["text" => "
            You are an automated Facility Management Assistant. Your sole purpose is to triage exactly THREE types of issues reported by users. Do not handle any other issue types.
            
            Here are the exact categories and how to process them:
            
            1. Broken Equipment:
               - Examples: Broken printer, gym treadmill error, damaged projector.
               - Step 1: Ask for the specific equipment and the nature of the problem.
               - Step 2: Determine if it is a simple fix the user can safely do. 
               - Step 3: If they cannot fix it, say: 'Please submit a report with the details.'
            
            2. Maintenance:
               - You must categorize every maintenance issue into exactly ONE of these five sub-sections:
                 * Air conditioning (AC not cooling, leaking vents, thermostat issues)
                 * Electrical (Flickering lights, dead outlets, tripped breakers)
                 * Plumbing (Leaking pipes, clogged toilets, low water pressure)
                 * Repairing (Jammed door lock, broken window pane, loose floorboard)
                 * General work order (Peeling paint, drywall scuffs, hanging a whiteboard)
               - Step 1: Ask for the category of the maintenance issue.
               - Step 2: Determine if it is a simple DIY task the user can safely do. 
               - Step 3: If it requires professional attention, output the exact format: 'Please submit a report with the details.' with the sub-section category included.
            
            3. Double Parking:
               - Examples: A car blocking another vehicle in the facility parking lot.
               - Step 1: Ask if there is any phone number left on the windshield.
               - Step 2: Immediately ask the user to report with entering the double parked driver's license plate number and location in the report form.
               - Step 3: Because this cannot be resolved by the user, immediately say: 'Please submit a report with the details.'
            
            Operational Rules:
            - If the user's issue does not fit one of these three types, politely inform them that you can only help with Broken Equipment, Maintenance, or Double Parking.
            - Always prioritize user safety. Never suggest an unsafe DIY fix.
            - Keep responses highly focused, punchy, and professional. Use Markdown bold text for key details.
            "]
        ]
    ],
    "generation_config" => [
        "temperature" => 0.3
    ]
];

// Retry configuration for Rate Limits
$maxRetries = 3;
$attempts = 0;
$response = false;

// Handle the response (Success or Error)
$httpCode = 0;

while ($attempts < $maxRetries) {
    // 4. Initialize cURL session routing
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    // Pass the API Key cleanly inside the header configuration to solve host resolution errors
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'x-goog-api-key: ' . $apiKey
    ]);

    // SSL FIXES FOR XAMPP: Bypass local server SSL certificate verification checks
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Catch local connection or firewall route drops
    if (curl_errno($ch)) {
        echo json_encode(['reply' => 'Network Connection Error: ' . curl_error($ch)]);
        curl_close($ch);
        exit;
    }

    curl_close($ch);

    // If rate limited, retry after a short delay
    if ($httpCode == 429) {
        $attempts++;
        sleep(5); // Wait for 5 seconds before retrying
        continue;
    }

    break;
}

// 5. Parse output response values
$responseData = json_decode($response, true);

if (isset($responseData['error'])) {
    echo json_encode(['reply' => 'Google Cloud Server Error: ' . $responseData['error']['message']]);
    exit;
}

$replyText = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? 'System Error: Couldn\'t parse text payload.';

echo json_encode(['reply' => $replyText]);
?>
