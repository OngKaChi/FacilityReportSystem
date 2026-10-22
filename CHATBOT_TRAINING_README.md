# Facility Management Chatbot Training Guide

## Overview
Your chatbot is designed to help users with facility-related issues. It combines rule-based responses with optional AI integration for more intelligent conversations.

## 🎯 NEW: Issue Type Classification
The chatbot now **intelligently categorizes user problems** into the three report form issue types:

### **Broken Equipment**
- Triggers: "broken", "not working", "malfunction", "lights", "ac", "fan", "door", "elevator", etc.
- Response: Tells user this is "Broken Equipment" and explains what info to provide

### **Maintenance**
- Triggers: "maintenance", "cleaning", "inspection", "repair", "fix", "paint", "rust", etc.
- Response: Identifies as "Maintenance" and guides user on required information

### **Double Parking**
- Triggers: "parking", "double park", "blocked", "vehicle", "tow", etc.
- Response: Classifies as "Double Parking" and requests license plate, location, photos

## 🎯 NEW: Issue Type Examples
The chatbot can now provide detailed examples when users ask "what counts as..." or "what are examples of...":

### Example Questions & Responses

**"What are examples of broken equipment?"**
```
❌ Lights not working - Office lights, hallway lights, emergency lights
❌ AC/Cooling system broken - Air conditioning units, fans, ventilation
❌ Doors malfunctioning - Office doors, elevator doors, security doors
❌ Elevators stuck - Elevator not moving, doors not opening
❌ Locks not working - Door locks, cabinet locks, security locks
❌ Pumps failing - Water pumps, sewage pumps, HVAC pumps
❌ Machines broken - Coffee machines, copiers, vending machines

Report as: 'Broken Equipment'
Priority: High - Safety hazards get immediate response
```

**"What counts as maintenance?"**
```
🧹 Cleaning services - Office cleaning, restroom cleaning, common area cleaning
🔧 Repairs needed - Loose fixtures, minor leaks, broken furniture
🎨 Painting required - Touch-ups, wall repairs, ceiling repairs
🦠 Rust removal - Rusty fixtures, metal surfaces, structural elements
📋 Inspections - Regular check-ups, preventive maintenance
🗑️ Debris cleanup - Construction debris, accumulated dirt, clutter
⚡ Wear and tear - Carpet replacement, tile repairs, general upkeep

Report as: 'Maintenance'
Priority: Medium - Scheduled within 5-7 business days
```

**"What kinds of parking issues should I report?"**
```
🚗 Double parking - Vehicle parked across two spaces
🚫 Blocked access - Blocking driveway, emergency lane, or entrance
🚨 Emergency obstruction - Blocking fire lanes or emergency exits
⏰ Time violations - Exceeding parking time limits
🚷 Unauthorized parking - Parking in restricted or reserved areas
🏢 Loading zone abuse - Using loading zones for regular parking
👥 Handicap space misuse - Parking in handicap spaces without permit

Report as: 'Double Parking'
Priority: High - Enforcement responds within 30 minutes
Warning: May result in towing at owner's expense
```

## Current Implementation
- **Rule-based system**: Fast, reliable responses for common queries
- **AI integration**: Optional Google Gemini integration for complex questions
- **Perfect formatting**: AI responses include proper spacing, bullet points, and bold text for better readability
- **Training interface**: Web-based tool to add new knowledge

## How to Train Your Chatbot

### Method 1: Rule-Based Training (Recommended for Most Cases)

1. **Access Training Interface**
   - Visit `train_chatbot.php` in your browser
   - View current knowledge base
   - Add new topics and information

2. **Add Knowledge**
   - **Topic**: Use snake_case (e.g., `wifi_issues`, `elevator_problems`)
   - **Information**: Detailed explanation of procedures, policies, or solutions
   - **Keywords**: Comma-separated words that trigger this response

3. **Examples**:
   ```
   Topic: hvac_issues
   Information: HVAC problems should be reported immediately. Include room temperature and any unusual noises. Our technician responds within 2 hours for comfort issues.
   Keywords: hvac, heating, cooling, air conditioning, temperature, hot, cold
   ```

### Method 2: AI Integration (Advanced)

1. **Get Google Gemini API Key**
   - Visit https://makersuite.google.com/app/apikey
   - Create a new API key
   - Add to `config.php`: `define('GEMINI_API_KEY', 'your-key-here');`

2. **Enable AI Mode**
   - In `config.php`, change: `define('CHATBOT_MODE', 'ai');`

3. **AI Training**
   - The AI automatically learns from your knowledge base
   - Add more detailed information to improve responses
   - Monitor conversations and add missing knowledge
   - AI responses are automatically formatted with proper spacing and visual elements

## Using Issue Classification

### How It Works
1. **User opens chatbot** in dashboard
2. **User describes their problem** - e.g., "My lights are not working"
3. **Chatbot automatically classifies** the issue type
4. **Chatbot provides guidance** on what info to include in report form
5. **User gets directed** to the correct issue category

### Example Interactions

**User:** "The elevator on floor 3 is stuck"
**Chatbot:** "This is a **Broken Equipment** issue. Elevators are high priority. Please select 'Broken Equipment' in the report form and provide location, description, and photos if possible."

**User:** "There's a car double parked blocking the entrance"
**Chatbot:** "This is a **Double Parking** issue. Please select 'Double Parking' and provide the license plate, location, and photos."

**User:** "I need the lobby cleaned"
**Chatbot:** "This is a **Maintenance** issue. Please select 'Maintenance' and describe what cleaning is needed."

### Testing Issue Classification
- Go to `train_chatbot.php`
- Click preset examples to test different issue types
- Check that the chatbot correctly identifies and guides users

## Training Best Practices

### 1. **Be Specific**
```
❌ Bad: "Report issues"
✅ Good: "Use the Report Issue button to submit problems. Include floor, location, description, and photos when possible."
```

### 2. **Include Procedures**
```
❌ Bad: "Contact maintenance"
✅ Good: "Contact maintenance at ext 123 during business hours. For emergencies, call security at ext 555."
```

### 3. **Add Context**
```
❌ Bad: "Elevator broken"
✅ Good: "Elevator issues are high priority. Stay calm, use emergency phone if stuck. Technician responds within 30 minutes."
```

### 4. **Use Keywords Strategically**
- Include synonyms: "broken, not working, malfunction, repair"
- Add specific terms: "floor 3, main lobby, parking garage"
- Consider misspellings: "maintainance" for "maintenance"

## Testing Your Chatbot

1. **Use the Training Interface**
   - Test responses in real-time
   - Verify keyword matching

2. **Common Test Cases**
   - "The elevator is stuck"
   - "WiFi not working"
   - "Where do I park?"
   - "Report broken light"

3. **Monitor Real Usage**
   - Check user satisfaction
   - Add missing knowledge based on actual questions

## Advanced Features

### Conversation History (Optional)
```php
// Add to config.php
define('ENABLE_CONVERSATION_LOG', true);

// Create conversations table
CREATE TABLE conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_message TEXT,
    bot_response TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### User Feedback
```php
// Add thumbs up/down buttons to chat
// Store feedback to improve responses
```

### Multi-language Support
```php
// Add language detection
// Provide responses in user's language
```

## Troubleshooting

### Chatbot Not Responding
- Check `chatbot_api.php` is accessible
- Verify PHP error logs
- Test with simple message

### AI Not Working
- Verify API key is correct
- Check API quota/limits
- Ensure internet connection

### Poor Responses
- Add more specific keywords
- Include more detailed information
- Consider switching to AI mode

## Future Improvements

1. **Machine Learning**: Train custom model on your facility data
2. **Integration**: Connect to maintenance scheduling system
3. **Analytics**: Track common issues and response effectiveness
4. **Voice Support**: Add speech-to-text capabilities

## Support

For technical issues or advanced customization:
- Check PHP error logs
- Verify database connections
- Test API endpoints individually

Remember: Start with rule-based training for reliability, then add AI for sophistication!