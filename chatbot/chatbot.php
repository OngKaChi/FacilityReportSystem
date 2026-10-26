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

            Emergency Tier Classification:
            - Normal: Non-urgent issues that do not pose immediate risk or danger.
            - Urgent: Issues that require prompt attention but do not pose immediate danger.
            - Emergency: Issues that pose immediate risk or danger and require immediate action.
            
            Operational Rules:
            - If the user's issue does not fit one of these three types, politely inform them that you can only help with Broken Equipment, Maintenance, Double Parking or Emergency Tiers.
            - Always prioritize user safety. Never suggest an unsafe DIY fix.
            - Keep responses highly focused, punchy, and professional. Use Markdown bold text for key details.
            "]
        ]
    ],
    "generation_config" => [
        "temperature" => 0.3
    ]
];

// Encode the request body once before sending it.
$requestBody = json_encode($data);
if ($requestBody === false) {
    echo json_encode(['reply' => 'System Error: Unable to prepare the assistant request.']);
    exit;
}

if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
    echo json_encode(['reply' => 'System Error: PHP URL streams are disabled. Enable allow_url_fopen to use the assistant.']);
    exit;
}

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => implode("\r\n", [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $apiKey,
        ]),
        'content' => $requestBody,
        'timeout' => 30,
        'ignore_errors' => true,
    ],
    'ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true,
    ],
]);

// Retry rate-limited requests up to three times.
$maxRetries = 3;
$attempts = 0;
$response = false;
$httpCode = 0;

while ($attempts < $maxRetries) {
    error_clear_last();
    $response = @file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header ?? [];

    foreach ($responseHeaders as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/', $header, $matches)) {
            $httpCode = (int) $matches[1];
        }
    }

    if ($response === false) {
        $lastError = error_get_last();
        $message = $lastError['message'] ?? 'The HTTPS request could not be completed.';
        echo json_encode(['reply' => 'Network Connection Error: ' . $message]);
        exit;
    }

    if ($httpCode === 429) {
        $attempts++;
        if ($attempts < $maxRetries) {
            sleep(5);
            continue;
        }
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
