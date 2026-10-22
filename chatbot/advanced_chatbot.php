<?php

class AdvancedFacilityChatbot {
    private $gemini_api_key;
    private $knowledgeBase;
    private $knowledgeKeywords;

    public function __construct($api_key = null) {
        $this->gemini_api_key = $api_key ?: (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : null);
        $this->knowledgeKeywords = [];
        $this->initializeKnowledgeBase();
    }

    private function initializeKnowledgeBase() {
        // Load from database if available
        $this->knowledgeBase = $this->loadKnowledgeFromDatabase();

        // Fallback default knowledge if database is empty
        if (empty($this->knowledgeBase)) {
            $this->knowledgeBase = [
                "facility_policies" => "Our facility operates Monday-Friday 8AM-5PM. Emergency maintenance is available 24/7. All reports are reviewed within 24 hours.",
                "emergency_procedures" => "For emergencies: Call security at ext 555 or emergency services. Stay in safe location and follow evacuation procedures if needed.",
                "parking_rules" => "Double parking blocks emergency access and may result in towing. Parking enforcement responds within 30 minutes during business hours.",
                "maintenance_schedule" => "Preventive maintenance occurs quarterly. Urgent repairs prioritized within 24 hours. Non-urgent within 5-7 business days.",
                "equipment_issues" => "Report broken equipment immediately with location and description. Safety hazards get immediate response.",
                "report_process" => "Use the Report Issue button to submit problems. Include floor, location, description, and photos when possible."
            ];
        }
    }

    private function loadKnowledgeFromDatabase() {
        try {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if ($conn->connect_error) {
                return [];
            }

            $result = $conn->query("SELECT topic, information, keywords FROM knowledge_base");
            $knowledge = [];
            $keywords = [];

            while ($row = $result->fetch_assoc()) {
                $topic = $row['topic'];
                $knowledge[$topic] = $row['information'];
                $keywords[$topic] = array_filter(array_map('trim', explode(',', strtolower($row['keywords'] ?? ''))));
            }

            $this->knowledgeKeywords = $keywords;
            $conn->close();
            return $knowledge;

        } catch (Exception $e) {
            return [];
        }
    }

    public function getResponse($userMessage) {
        // First try rule-based responses for common queries
        $ruleResponse = $this->getRuleBasedResponse($userMessage);
        if ($ruleResponse) {
            return $this->formatResponse($ruleResponse);
        }

        // If Gemini API key is available, use AI
        if ($this->gemini_api_key) {
            return $this->getGeminiResponse($userMessage);
        }

        // Fallback to basic responses
        return $this->formatResponse($this->getFallbackResponse($userMessage));
    }

    private function getRuleBasedResponse($message) {
        $message = strtolower($message);

        // Check for custom knowledge base entries first
        $customResponse = $this->getKnowledgeBaseResponse($message);
        if ($customResponse) {
            return $customResponse;
        }

        // Check for simple greeting responses
        $greetings = ['hi', 'hello', 'hey', 'good morning', 'good afternoon', 'good evening'];
        foreach ($greetings as $greeting) {
            if (preg_match('/\b' . preg_quote($greeting, '/') . '\b/u', $message)) {
                return "Hello! I'm your facility assistant. Ask me about issue categories, reporting, maintenance, parking, or emergencies.";
            }
        }

        // Check for issue type classification first
        $issueType = $this->classifyIssueType($message);
        if ($issueType) {
            return $issueType;
        }

        $rules = [
            ['keywords' => ['emergency', 'urgent', 'safety', 'fire', 'hazard', 'medical', 'security'], 'response' => $this->knowledgeBase['emergency_procedures']],
            ['keywords' => ['parking', 'double', 'tow', 'blocked', 'access', 'lane', 'garage', 'space'], 'response' => $this->knowledgeBase['parking_rules']],
            ['keywords' => ['maintenance', 'schedule', 'preventive', 'repair', 'fix', 'service', 'clean', 'cleaning', 'inspection', 'rust', 'debris', 'spill', 'leak', 'plumbing', 'water'], 'response' => $this->knowledgeBase['maintenance_schedule']],
            ['keywords' => ['broken', 'equipment', 'not working', 'fail', 'failure', 'lights', 'power', 'electrical', 'ac', 'cooling', 'heater', 'vent', 'door', 'lock', 'elevator', 'pump', 'machine', 'system'], 'response' => $this->knowledgeBase['equipment_issues']],
            ['keywords' => ['how', 'report', 'submit', 'issue type', 'category', 'problem', 'help', 'support'], 'response' => $this->knowledgeBase['report_process']],
            ['keywords' => ['hours', 'open', 'time', 'schedule', 'business hours', 'office hours'], 'response' => $this->knowledgeBase['facility_policies']]
        ];

        foreach ($rules as $rule) {
            foreach ($rule['keywords'] as $keyword) {
                if (strpos($message, $keyword) !== false) {
                    return $rule['response'];
                }
            }
        }

        return null;
    }

    private function getKnowledgeBaseResponse($message) {
        foreach ($this->knowledgeBase as $topic => $information) {
            $normalizedTopic = str_replace('_', ' ', $topic);

            if ($this->matchesKeyword($message, $topic) || $this->matchesKeyword($message, $normalizedTopic)) {
                return $information;
            }

            foreach ($this->knowledgeKeywords[$topic] ?? [] as $keyword) {
                if ($this->matchesKeyword($message, $keyword)) {
                    return $information;
                }
            }

            foreach ($this->knowledgeKeywords[$topic] ?? [] as $keyword) {
                $keyword = trim($keyword);
                if ($keyword !== '' && strpos($message, $keyword) !== false) {
                    return $information;
                }
            }
        }

        return null;
    }

    private function matchesKeyword($message, $keyword) {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return false;
        }

        return preg_match('/\b' . preg_quote($keyword, '/') . '\b/u', $message) === 1;
    }

    private function classifyIssueType($message) {
        // Check for example requests first
        $examplesResponse = $this->provideIssueExamples($message);
        if ($examplesResponse) {
            return $examplesResponse;
        }

        // Broken Equipment detection
        $brokenEquipmentKeywords = ['broken', 'not working', 'malfunction', 'damaged', 'fail', 'failure', 'stop working',
                                    'lights', 'ac', 'fan', 'door', 'lock', 'elevator', 'pump', 'machine', 'apparatus'];
        foreach ($brokenEquipmentKeywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                return "Based on your description, this sounds like a **Broken Equipment** issue. \n\n" .
                       "This category includes: Malfunctioning lights, AC/cooling systems, fans, doors, locks, elevators, pumps, or any equipment that's not working properly.\n\n" .
                       "Please select 'Broken Equipment' in the report form and provide:\n" .
                       "• Which equipment is broken\n" .
                       "• Specific location (floor and room)\n" .
                       "• Description of the problem\n" .
                       "• Photos if possible";
            }
        }

        // Maintenance detection
        $maintenanceKeywords = ['maintenance', 'clean', 'cleaning', 'inspection', 'preventive', 'service', 'repair', 'fix',
                               'restore', 'maintain', 'check', 'paint', 'rust', 'wear', 'deteriorate', 'debris'];
        foreach ($maintenanceKeywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                return "Based on your description, this sounds like a **Maintenance** issue. \n\n" .
                       "This category includes: Cleaning services, general inspections, preventive maintenance, repairs, painting, rust removal, debris cleanup, or wear and tear issues.\n\n" .
                       "Please select 'Maintenance' in the report form and provide:\n" .
                       "• Type of maintenance needed\n" .
                       "• Specific location\n" .
                       "• Detailed description\n" .
                       "• Photos if available";
            }
        }

        // Parking/Double Parking detection
        $parkingKeywords = ['parking', 'double park', 'blocked', 'vehicle', 'car', 'lot', 'space', 'tow', 'illegal',
                           'obstruct', 'access', 'garage', 'driveway', 'entrance'];
        foreach ($parkingKeywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                return "Based on your description, this sounds like a **Double Parking** or Parking issue. \n\n" .
                       "This category includes: Illegally parked vehicles, double parking that blocks access, obstruction of emergency lanes, or parking violations.\n\n" .
                       "Please select 'Double Parking' in the report form and provide:\n" .
                       "• License plate number (if visible)\n" .
                       "• Specific location (parking lot, garage, level)\n" .
                       "• Description of the violation\n" .
                       "• Photos of the vehicle and location\n\n" .
                       "Warning: Double parking may result in vehicle towing at owner's expense.";
            }
        }

        return null;
    }

    private function provideIssueExamples($message) {
        $message = strtolower($message);

        // Check for example requests
        $exampleKeywords = ['example', 'examples', 'what counts', 'what is', 'what are', 'types of', 'kinds of', 'like what'];
        $hasExampleRequest = false;
        foreach ($exampleKeywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                $hasExampleRequest = true;
                break;
            }
        }

        if (!$hasExampleRequest) return null;

        // Broken Equipment examples
        if (strpos($message, 'broken equipment') !== false || strpos($message, 'equipment') !== false) {
            return "**Broken Equipment Examples:**\n\n" .
                   "❌ **Lights not working** - Office lights, hallway lights, emergency lights\n" .
                   "❌ **AC/Cooling system broken** - Air conditioning units, fans, ventilation\n" .
                   "❌ **Doors malfunctioning** - Office doors, elevator doors, security doors\n" .
                   "❌ **Elevators stuck** - Elevator not moving, doors not opening\n" .
                   "❌ **Locks not working** - Door locks, cabinet locks, security locks\n" .
                   "❌ **Pumps failing** - Water pumps, sewage pumps, HVAC pumps\n" .
                   "❌ **Machines broken** - Coffee machines, copiers, vending machines\n\n" .
                   "**Report as:** 'Broken Equipment'\n" .
                   "**Priority:** High - Safety hazards get immediate response";
        }

        // Maintenance examples
        if (strpos($message, 'maintenance') !== false) {
            return "**Maintenance Examples:**\n\n" .
                   "🧹 **Cleaning services** - Office cleaning, restroom cleaning, common area cleaning\n" .
                   "🔧 **Repairs needed** - Loose fixtures, minor leaks, broken furniture\n" .
                   "🎨 **Painting required** - Touch-ups, wall repairs, ceiling repairs\n" .
                   "🦠 **Rust removal** - Rusty fixtures, metal surfaces, structural elements\n" .
                   "📋 **Inspections** - Regular check-ups, preventive maintenance\n" .
                   "🗑️ **Debris cleanup** - Construction debris, accumulated dirt, clutter\n" .
                   "⚡ **Wear and tear** - Carpet replacement, tile repairs, general upkeep\n\n" .
                   "**Report as:** 'Maintenance'\n" .
                   "**Priority:** Medium - Scheduled within 5-7 business days";
        }

        // Double Parking examples
        if (strpos($message, 'parking') !== false || strpos($message, 'double') !== false) {
            return "**Double Parking Examples:**\n\n" .
                   "🚗 **Double parking** - Vehicle parked across two spaces\n" .
                   "🚫 **Blocked access** - Blocking driveway, emergency lane, or entrance\n" .
                   "🚨 **Emergency obstruction** - Blocking fire lanes or emergency exits\n" .
                   "⏰ **Time violations** - Exceeding parking time limits\n" .
                   "🚷 **Unauthorized parking** - Parking in restricted or reserved areas\n" .
                   "🏢 **Loading zone abuse** - Using loading zones for regular parking\n" .
                   "👥 **Handicap space misuse** - Parking in handicap spaces without permit\n\n" .
                   "**Report as:** 'Double Parking'\n" .
                   "**Priority:** High - Enforcement responds within 30 minutes\n" .
                   "**Warning:** May result in towing at owner's expense";
        }

        // General issue types overview
        if (strpos($message, 'issue type') !== false || strpos($message, 'categories') !== false) {
            return "**Facility Issue Categories:**\n\n" .
                   "🔧 **Broken Equipment** - Malfunctioning devices and systems\n" .
                   "   Examples: Lights, AC, elevators, doors, locks, pumps\n\n" .
                   "🧹 **Maintenance** - Cleaning, repairs, and upkeep\n" .
                   "   Examples: Cleaning services, painting, rust removal, inspections\n\n" .
                   "🚗 **Double Parking** - Parking violations and obstructions\n" .
                   "   Examples: Double parking, blocked access, time violations\n\n" .
                   "Ask me for specific examples of any category!";
        }

        return null;
    }

    private function getGeminiResponse($message) {
        $context = "You are a helpful facility management assistant. Use this knowledge base to answer questions:\n\n" .
                  implode("\n\n", array_values($this->knowledgeBase)) . "\n\n" .
                  "User question: " . $message . "\n\n" .
                  "IMPORTANT FORMATTING INSTRUCTIONS:\n" .
                  "- Use proper line breaks and spacing for readability\n" .
                  "- Use bullet points (•) for lists\n" .
                  "- Use numbered lists when appropriate\n" .
                  "- Use **bold text** for emphasis and important terms\n" .
                  "- Keep responses concise but well-structured\n" .
                  "- Use line breaks between different sections of information\n" .
                  "- Format issue categories clearly with proper spacing\n\n" .
                  "Provide a helpful, well-formatted response focused on facility management issues.";

        $data = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $context
                        ]
                    ]
                ]
            ]
        ];

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=' . $this->gemini_api_key;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            $result = json_decode($response, true);
            if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
                return trim($result['candidates'][0]['content']['parts'][0]['text']);
            }
        }

        return null;
    }

    private function formatResponse($response) {
        // Convert markdown-style formatting to HTML for better display
        $response = htmlspecialchars($response, ENT_QUOTES, 'UTF-8');

        // Convert **bold** to <strong>bold</strong>
        $response = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $response);

        // Convert bullet points to proper HTML
        $response = preg_replace('/^•\s*/m', '<br>• ', $response);

        // Ensure proper line breaks
        $response = nl2br($response);

        // Clean up excessive line breaks
        $response = preg_replace('/(<br\s*\/?>\s*){3,}/', '<br><br>', $response);

        // Add proper spacing for lists
        $response = preg_replace('/(<br>•\s*)/', '<br><br>• ', $response);

        return $response;
    }

    private function getFallbackResponse($message) {
        $responses = [
            "I'm here to help with facility-related questions. You can report issues using the Report Issue button.",
            "For specific facility problems, please use the report form with details about the issue.",
            "I can help answer questions about maintenance, parking, and facility procedures. What would you like to know?",
            "If you have an urgent issue, please contact security or use the emergency procedures."
        ];

        return $responses[array_rand($responses)];
    }

    public function addToKnowledgeBase($topic, $information) {
        $this->knowledgeBase[$topic] = $information;
    }
}

// Usage:
/*
$chatbot = new AdvancedFacilityChatbot('your-gemini-api-key-here');

// Add custom knowledge
$chatbot->addToKnowledgeBase(
    'wifi_issues',
    'WiFi problems: Reset your device, check if others are affected, contact IT at ext 123 if widespread.'
);

$response = $chatbot->getResponse("The WiFi is not working");
*/
?>