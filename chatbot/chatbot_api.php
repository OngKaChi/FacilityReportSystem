<?php
require_once __DIR__ . '/../config.php';
require_once 'advanced_chatbot.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message'] ?? '');

    if (!empty($message)) {
        ob_start();
        try {
            // Initialize chatbot based on configuration
            if (CHATBOT_MODE === 'ai' && defined('GEMINI_API_KEY')) {
                $chatbot = new AdvancedFacilityChatbot(GEMINI_API_KEY);
            } else {
                $chatbot = new AdvancedFacilityChatbot(); // Rule-based mode
            }

            $response = $chatbot->getResponse($message);

            // Log conversation if needed (optional)
            // logConversation($message, $response);

            if (ob_get_length() > 0) {
                ob_clean();
            }
            header('Content-Type: application/json');
            echo json_encode([
                'response' => $response,
                'mode' => CHATBOT_MODE,
                'timestamp' => date('Y-m-d H:i:s')
            ]);

        } catch (Exception $e) {
            if (ob_get_length() > 0) {
                ob_clean();
            }
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Chatbot service temporarily unavailable',
                'details' => $e->getMessage()
            ]);
        }
        exit;
    }
}

header('Content-Type: application/json');
echo json_encode(['error' => 'Invalid request']);
?>