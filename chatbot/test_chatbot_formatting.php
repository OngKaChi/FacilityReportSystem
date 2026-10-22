<?php
require_once 'config.php';
require_once 'advanced_chatbot.php';

// Test the formatting
$chatbot = new AdvancedFacilityChatbot();

echo "<h2>Testing Chatbot Response Formatting</h2>";

// Test rule-based response
$response1 = $chatbot->getResponse("The lights are broken");
echo "<h3>Rule-based Response:</h3>";
echo "<div style='border:1px solid #ccc; padding:10px; margin:10px 0;'>$response1</div>";

// Test fallback response
$response2 = $chatbot->getResponse("some random question that doesn't match anything");
echo "<h3>Fallback Response:</h3>";
echo "<div style='border:1px solid #ccc; padding:10px; margin:10px 0;'>$response2</div>";

echo "<p><strong>Note:</strong> AI responses will only work if GEMINI_API_KEY is configured in config.php</p>";
?>