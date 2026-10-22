<!DOCTYPE html>
<html>
<head>
  <title>Chatbot Training Interface</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 dark:bg-slate-900 min-h-screen transition-colors duration-300">

<?php include 'components/navbar.php'; ?>

<div class="max-w-4xl mx-auto p-6">

  <div class="mb-8">
    <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">Chatbot Training Interface</h2>
    <p class="text-gray-600 dark:text-gray-400">Train your AI chatbot to better help users with facility issues</p>
  </div>

  <!-- Current Knowledge Base -->
  <div class="bg-white dark:bg-slate-800 dark:border dark:border-slate-700 rounded-xl shadow-lg p-6 mb-8">
    <h3 class="text-xl font-semibold text-gray-800 dark:text-white mb-4">Current Knowledge Base</h3>
    <div id="knowledgeList" class="space-y-4">
      <!-- Knowledge items will be loaded here -->
    </div>
  </div>

  <!-- Add New Knowledge -->
  <div class="bg-white dark:bg-slate-800 dark:border dark:border-slate-700 rounded-xl shadow-lg p-6 mb-8">
    <h3 class="text-xl font-semibold text-gray-800 dark:text-white mb-4">Add New Knowledge</h3>

    <form id="trainingForm" class="space-y-6">
      <div>
        <label class="block text-sm font-semibold text-gray-800 dark:text-gray-300 mb-2">Topic/Category</label>
        <input type="text" id="topic" placeholder="e.g., wifi_issues, elevator_problems"
          class="w-full border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
      </div>

      <div>
        <label class="block text-sm font-semibold text-gray-800 dark:text-gray-300 mb-2">Information/Knowledge</label>
        <textarea id="information" rows="4"
          placeholder="Enter detailed information about this topic that the chatbot should know..."
          class="w-full border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
      </div>

      <div>
        <label class="block text-sm font-semibold text-gray-800 dark:text-gray-300 mb-2">Keywords (comma-separated)</label>
        <input type="text" id="keywords" placeholder="wifi, internet, connection, slow"
          class="w-full border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
      </div>

      <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-3 rounded-lg">
        Add to Knowledge Base
      </button>
    </form>

    <div class="bg-slate-50 dark:bg-slate-700/50 border border-slate-200 dark:border-slate-700 rounded-xl p-6 mt-8">
      <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-3">Import Dataset</h3>
      <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Upload a CSV or JSON file to add multiple knowledge entries at once.</p>
      <input type="file" id="datasetFile" accept=".csv,.json"
        class="w-full text-sm text-gray-700 dark:text-gray-300 file:border-0 file:bg-blue-600 file:text-white file:px-4 file:py-2 file:rounded-lg file:mr-4" />
      <button id="importDatasetButton" class="mt-4 bg-green-600 hover:bg-green-700 text-white font-semibold px-6 py-3 rounded-lg">
        Import Dataset
      </button>
      <p id="importStatus" class="text-sm text-gray-600 mt-3 hidden"></p>
    </div>
  </div>

  <!-- Test Chatbot -->
  <div class="bg-white dark:bg-slate-800 dark:border dark:border-slate-700 rounded-xl shadow-lg p-6 mb-8">
    <h3 class="text-xl font-semibold text-gray-800 dark:text-white mb-4">Test Chatbot - Issue Classification & Examples</h3>

    <p class="text-gray-600 dark:text-gray-400 text-sm mb-4">Test how the chatbot helps users identify issue types and provides examples:</p>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
      <button onclick="testExample('My office lights are not working')" class="bg-yellow-50 hover:bg-yellow-100 border border-yellow-200 rounded-lg p-3 text-left transition">
        <p class="font-semibold text-sm text-yellow-900">Broken Equipment</p>
        <p class="text-xs text-yellow-700">"My lights are not working"</p>
      </button>
      <button onclick="testExample('The AC in the conference room is not working, what should I do?')" class="bg-purple-50 hover:bg-purple-100 border border-purple-200 rounded-lg p-3 text-left transition">
        <p class="font-semibold text-sm text-purple-900">Broken Equipment</p>
        <p class="text-xs text-purple-700">"The AC is broken"</p>
      </button>
      <button onclick="testExample('I need cleaning and maintenance for the lobby')" class="bg-green-50 hover:bg-green-100 border border-green-200 rounded-lg p-3 text-left transition">
        <p class="font-semibold text-sm text-green-900">Maintenance</p>
        <p class="text-xs text-green-700">"Cleaning needed"</p>
      </button>
      <button onclick="testExample('There is a car double parked in the parking garage blocking my spot')" class="bg-red-50 hover:bg-red-100 border border-red-200 rounded-lg p-3 text-left transition">
        <p class="font-semibold text-sm text-red-900">Double Parking</p>
        <p class="text-xs text-red-700">"Double parked vehicle"</p>
      </button>
      <button onclick="testExample('The elevator door is stuck and won\\'t open')" class="bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-lg p-3 text-left transition">
        <p class="font-semibold text-sm text-blue-900">Broken Equipment</p>
        <p class="text-xs text-blue-700">"Elevator problem"</p>
      </button>
      <button onclick="testExample('I found rust on the stairwell walls')" class="bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 rounded-lg p-3 text-left transition">
        <p class="font-semibold text-sm text-indigo-900">Maintenance</p>
        <p class="text-xs text-indigo-700">"Rust removal needed"</p>
      </button>

      <!-- Example Request Buttons -->
      <button onclick="testExample('What are examples of broken equipment issues?')" class="bg-orange-50 hover:bg-orange-100 border border-orange-200 rounded-lg p-3 text-left transition">
        <p class="font-semibold text-sm text-orange-900">Examples Request</p>
        <p class="text-xs text-orange-700">"Broken equipment examples"</p>
      </button>
      <button onclick="testExample('What counts as maintenance?')" class="bg-teal-50 hover:bg-teal-100 border border-teal-200 rounded-lg p-3 text-left transition">
        <p class="font-semibold text-sm text-teal-900">Examples Request</p>
        <p class="text-xs text-teal-700">"Maintenance examples"</p>
      </button>
      <button onclick="testExample('What are the different issue types?')" class="bg-pink-50 hover:bg-pink-100 border border-pink-200 rounded-lg p-3 text-left transition">
        <p class="font-semibold text-sm text-pink-900">Examples Request</p>
        <p class="text-xs text-pink-700">"Issue types overview"</p>
      </button>
      <button onclick="testExample('What kinds of parking issues should I report?')" class="bg-cyan-50 hover:bg-cyan-100 border border-cyan-200 rounded-lg p-3 text-left transition">
        <p class="font-semibold text-sm text-cyan-900">Examples Request</p>
        <p class="text-xs text-cyan-700">"Parking examples"</p>
      </button>
    </div>

    <div>
      <input type="text" id="testMessage" placeholder="Or enter your own test message..."
        class="w-full border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
      <button onclick="testChatbot()" class="mt-2 bg-green-600 hover:bg-green-700 text-white font-semibold px-4 py-2 rounded-lg">
        Test Response
      </button>
    </div>

    <div id="testResponse" class="bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-slate-900 dark:to-slate-900 rounded-lg p-4 min-h-[80px] hidden mt-4 border border-blue-200 dark:border-slate-700">
      <p id="responseText" class="text-gray-800 dark:text-slate-200 whitespace-pre-line text-sm"></p>
    </div>
  </div>

</div>

<script>
// Load knowledge base on page load
document.addEventListener('DOMContentLoaded', loadKnowledgeBase);

function loadKnowledgeBase() {
  fetch('knowledge_base_api.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: 'action=get'
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      displayKnowledge(data.knowledge);
    }
  })
  .catch(error => console.error('Error loading knowledge:', error));
}

function displayKnowledge(knowledge) {
  const container = document.getElementById('knowledgeList');

  if (knowledge.length === 0) {
    container.innerHTML = '<p class="text-gray-500 text-center py-4">No knowledge base entries yet. Add some below!</p>';
    return;
  }

  container.innerHTML = knowledge.map(item => `
    <div class="border border-gray-200 rounded-lg p-4">
      <div class="flex justify-between items-start mb-2">
        <h4 class="font-semibold text-gray-800">${item.topic.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}</h4>
        <button onclick="deleteKnowledge(${item.id})" class="text-red-500 hover:text-red-700 text-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
          </svg>
        </button>
      </div>
      <p class="text-gray-600 text-sm mb-2">${item.information}</p>
      ${item.keywords ? `<p class="text-gray-500 text-xs"><strong>Keywords:</strong> ${item.keywords}</p>` : ''}
      <p class="text-gray-400 text-xs mt-2">Updated: ${new Date(item.updated_at).toLocaleDateString()}</p>
    </div>
  `).join('');
}

document.getElementById('trainingForm').addEventListener('submit', function(e) {
  e.preventDefault();

  const topic = document.getElementById('topic').value;
  const information = document.getElementById('information').value;
  const keywords = document.getElementById('keywords').value;

  if (!topic || !information) {
    alert('Please fill in topic and information fields');
    return;
  }

  fetch('knowledge_base_api.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: `action=add&topic=${encodeURIComponent(topic)}&information=${encodeURIComponent(information)}&keywords=${encodeURIComponent(keywords)}`
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      alert('Knowledge added successfully!');
      this.reset();
      loadKnowledgeBase(); // Reload the knowledge base
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('Failed to add knowledge. Please try again.');
  });
});

function deleteKnowledge(id) {
  if (!confirm('Are you sure you want to delete this knowledge entry?')) {
    return;
  }

  fetch('knowledge_base_api.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: `action=delete&id=${id}`
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      loadKnowledgeBase(); // Reload the knowledge base
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('Failed to delete knowledge. Please try again.');
  });
}

// Import dataset support
const importButton = document.getElementById('importDatasetButton');
if (importButton) {
  importButton.addEventListener('click', () => {
    const fileInput = document.getElementById('datasetFile');
    const statusEl = document.getElementById('importStatus');
    const file = fileInput.files[0];

    if (!file) {
      alert('Please select a CSV or JSON dataset file to import.');
      return;
    }

    const formData = new FormData();
    formData.append('action', 'import');
    formData.append('dataset_file', file);

    statusEl.textContent = 'Importing dataset...';
    statusEl.classList.remove('hidden');

    fetch('knowledge_base_api.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        statusEl.textContent = `Import complete: ${data.inserted} entries added.`;
        fileInput.value = '';
        loadKnowledgeBase();
      } else {
        statusEl.textContent = 'Import failed: ' + data.message;
      }
    })
    .catch(error => {
      console.error('Error importing dataset:', error);
      statusEl.textContent = 'Import failed due to a connection error.';
    });
  });
}

function testChatbot() {
  const message = document.getElementById('testMessage').value;
  const responseDiv = document.getElementById('testResponse');
  const responseText = document.getElementById('responseText');

  if (!message) {
    alert('Please enter a test message');
    return;
  }

  responseDiv.classList.add('hidden');
  responseText.textContent = 'Testing...';

  // Call the chatbot API
  fetch('chatbot_api.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: 'message=' + encodeURIComponent(message)
  })
  .then(response => response.json())
  .then(data => {
    if (data.response) {
      responseText.textContent = data.response;
      responseDiv.classList.remove('hidden');
    } else {
      responseText.textContent = 'Error: ' + (data.error || 'Unknown error');
      responseDiv.classList.remove('hidden');
    }
  })
  .catch(error => {
    responseText.textContent = 'Error connecting to chatbot service';
    responseDiv.classList.remove('hidden');
  });
}

function testExample(exampleText) {
  document.getElementById('testMessage').value = exampleText;
  testChatbot();
}
</script>

</body>
</html>