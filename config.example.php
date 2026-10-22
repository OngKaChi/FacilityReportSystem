<?php
// Configuration file for API keys and settings
// Add your API keys here

// Google Gemini API Key (get from https://makersuite.google.com/app/apikey)
// Uncomment and add your key to enable AI responses
define('GEMINI_API_KEY', 'Your-Google-Gemini-API-Key-Here');

// Legacy OpenAI API Key (deprecated - use Gemini instead)
// define('OPENAI_API_KEY', 'your-openai-api-key-here');

// Other configuration
define('CHATBOT_MODE', 'ai'); // 'rule_based' or 'ai'
define('MAX_RESPONSE_LENGTH', 300);

// Database settings (if needed for conversation history)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'fyp');
?>