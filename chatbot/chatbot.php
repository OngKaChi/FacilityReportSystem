<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../config.php';

if (!is_logged_in() && !validate_remember_cookie()) {
    http_response_code(401);
    echo json_encode(['reply' => 'Please log in to use the assistant.']);
    exit;
}

define('CHATBOT_MAX_REQUESTS', 5);
define('CHATBOT_RATE_LIMIT_WINDOW', 30);

$now = time();
$rateLimit = $_SESSION['chatbot_rate_limit'] ?? [
    'window_started_at' => $now,
    'requests' => 0,
];

if (
    !is_array($rateLimit)
    || !isset($rateLimit['window_started_at'], $rateLimit['requests'])
    || !is_int($rateLimit['window_started_at'])
    || !is_int($rateLimit['requests'])
    || $now - $rateLimit['window_started_at'] >= CHATBOT_RATE_LIMIT_WINDOW
) {
    $rateLimit = [
        'window_started_at' => $now,
        'requests' => 0,
    ];
}

if ($rateLimit['requests'] >= CHATBOT_MAX_REQUESTS) {
    $retryAfter = max(
        1,
        $rateLimit['window_started_at'] + CHATBOT_RATE_LIMIT_WINDOW - $now
    );
    header('Retry-After: ' . $retryAfter);
    http_response_code(429);
    echo json_encode([
        'reply' => "Too many assistant requests. Please try again in {$retryAfter} seconds.",
    ]);
    exit;
}

$rateLimit['requests']++;
$_SESSION['chatbot_rate_limit'] = $rateLimit;

// Get the raw POST data from the JavaScript frontend
$input = json_decode(file_get_contents('php://input'), true);
$history = $input['history'] ?? [];

if (empty($history)) {
    echo json_encode(['reply' => 'System Error: Chat history array payload is empty.']);
    exit;
}

$model = 'gemini-2.5-flash';
$apiKey = GEMINI_API_KEY; 

$url = "https://generativelanguage.googleapis.com/v1beta/models/" . $model . ":generateContent";

// Tuned configure setting
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
            - Normal (4 - 10days): Non-urgent issues that do not pose immediate risk or danger.
            - Urgent (1 - 3days): Issues that require prompt attention but do not pose immediate danger.
            - Emergency (immediately): Issues that pose immediate risk or danger and require immediate action.

            Operational Rules:
            - If the user's issue does not fit one of these three types, politely inform them that you can only help with Broken Equipment, Maintenance, Double Parking or Emergency Tiers.
            - Always prioritize user safety. Never suggest an unsafe DIY fix.
            - Double Parking issue must always be classified as Emergency Tier and only happens in the Basement, Floor 1 and Floor 2.
            - Always ask for the specific location (floor and room/area) of the issue.
            - Keep responses highly focused, punchy, and professional. Use Markdown bold text for key details.
            - Return a report draft when the user has described a reportable issue that requires staff attention. 
            - Leave unknown report fields null; do not invent details. Do not return a draft for general questions or issues that can safely be resolved by the user.
            - The draft floor must be one of: Rooftop, Level 6, Level 5, Level 4, Level 3, Level 2, Level 1, Basement. 
            - The location should be the specific room or area the user gave. Use only these categories: Broken Equipment, Maintenance, Double Parking. For Maintenance, use one of: Air conditioning, Electrical, General work order, Plumbing, Repairing.
            - Classify emergency_tier as Normal, Urgent, or Emergency based on the described risk. Include the user's issue details in description. Use null when the user has not provided enough information for a field.
            "]
        ]
    ],
    "generation_config" => [
        "temperature" => 0.3,
        "response_mime_type" => "application/json",
        "response_schema" => [
            "type" => "OBJECT",
            "properties" => [
                "reply" => ["type" => "STRING"],
                "draft" => [
                    "type" => "OBJECT",
                    "nullable" => true,
                    "properties" => [
                        "floor" => ["type" => "STRING", "nullable" => true, "enum" => ["Rooftop", "Level 6", "Level 5", "Level 4", "Level 3", "Level 2", "Level 1", "Basement"]],
                        "category" => ["type" => "STRING", "nullable" => true, "enum" => ["Broken Equipment", "Maintenance", "Double Parking"]],
                        "maintenance_type" => ["type" => "STRING", "nullable" => true, "enum" => ["Air conditioning", "Electrical", "General work order", "Plumbing", "Repairing"]],
                        "emergency_tier" => ["type" => "STRING", "nullable" => true, "enum" => ["Normal", "Urgent", "Emergency"]],
                        "location" => ["type" => "STRING", "nullable" => true],
                        "description" => ["type" => "STRING", "nullable" => true],
                    ],
                    "required" => ["floor", "category", "maintenance_type", "emergency_tier", "location", "description"],
                ],
            ],
            "required" => ["reply", "draft"],
        ],
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

$modelContent = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? null;
$assistantData = is_string($modelContent) ? json_decode($modelContent, true) : null;

if (
    !is_array($assistantData)
    || !isset($assistantData['reply'])
    || !is_string($assistantData['reply'])
    || (isset($assistantData['draft']) && !is_array($assistantData['draft']))
) {
    http_response_code(502);
    echo json_encode(['reply' => 'System Error: The assistant returned an invalid response.']);
    exit;
}

echo json_encode([
    'reply' => $assistantData['reply'],
    'draft' => $assistantData['draft'] ?? null,
    'history_content' => $modelContent,
]);
?>
