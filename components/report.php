<?php
/**
 * This file is included from dashboard.php, which already has $conn.
 * We will include facility_map.php to get the $floorRooms definition,
 * which is the single source of truth for room layouts.
 */
ob_start();
include 'facility_map.php';
ob_end_clean();
$roomMapping = $floorRooms;

// Fetch custom labels if any exist in DB to ensure the form matches the map's edited names
$dbLabels = $conn->query("SELECT floor, room_id, label FROM facility_rooms");
while ($row = $dbLabels->fetch_assoc()) {
    if (isset($roomMapping[$row['floor']])) {
        foreach ($roomMapping[$row['floor']] as &$rm) {
            if ($rm['id'] === $row['room_id']) $rm['label'] = $row['label'];
        }
    }
}
?>
<div class="max-w-5xl mx-auto bg-white dark:bg-slate-800 p-6 md:p-10 lg:p-12 rounded-2xl shadow-lg border border-gray-200 dark:border-slate-700">
  <div class="text-center mb-6 md:mb-8">
    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 dark:text-white mb-2">Report an Issue</h2>
    <p class="text-base md:text-lg text-gray-600 dark:text-gray-400">Provide details to help us resolve it efficiently.</p>
  </div>

  <form id="reportForm" action="components/submit_report.php" method="POST" enctype="multipart/form-data" class="space-y-6">
  
    <div id="switchView"class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div>
        <label class="block text-base font-semibold text-gray-800 dark:text-gray-300 mb-3" for="floor">Floor</label>
        <select id="floor" name="floor" class="w-full border-2 border-gray-300 dark:border-slate-600 bg-gray-50 dark:bg-slate-700 dark:text-white px-5 py-4 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all duration-300 text-base" required>
          <option value="">Select floor</option>
          <option value="Rooftop">Rooftop</option>
          <option value="Level 6">Level 6</option>
          <option value="Level 5">Level 5</option>
          <option value="Level 4">Level 4</option>
          <option value="Level 3">Level 3</option>
          <option value="Level 2">Level 2</option>
          <option value="Level 1">Level 1</option>
          <option value="Basement">Basement</option>
        </select>
      </div>

      <div>
        <label class="block text-base font-semibold text-gray-800 dark:text-gray-300 mb-3" for="category">Issue Type</label>
        <select id="category" name="category" class="w-full border-2 border-gray-300 dark:border-slate-600 bg-gray-50 dark:bg-slate-700 dark:text-white px-5 py-4 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all duration-300 text-base" required>
          <option value="">Select issue type</option>
          <option value="Broken Equipment">Broken Equipment</option>
          <option value="Maintenance">Maintenance</option>
          <option value="Double Parking" id="doubleParkingOption" hidden>Double Parking</option>
        </select>
      </div>
    </div>

    <!-- Dynamic Room Selection (Pops out when floor is chosen) -->
    <div id="roomSelectionContainer" class="hidden animate-fade-in">
      <label class="block text-base font-semibold text-gray-800 dark:text-gray-300 mb-3" for="room_id">
        Specific Room / Area <span class="text-red-500">*</span>
      </label>
      <div class="relative">
        <input type="text" id="roomSearchInput" placeholder="Search for a room or click to select..." 
          class="w-full border-2 border-gray-300 dark:border-slate-600 bg-gray-50 dark:bg-slate-700 dark:text-white px-5 py-4 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all duration-300 text-base cursor-pointer" autocomplete="off" />
        <div id="customRoomDropdown" class="hidden absolute z-10 w-full mt-1 bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-600 rounded-xl shadow-lg max-h-60 overflow-y-auto">
          <!-- Room options will be injected here by JS -->
        </div>
      </div>
    </div>

    <div id="maintenanceTypeContainer" class="hidden">
      <label class="block text-base font-semibold text-gray-800 dark:text-gray-300 mb-3" for="maintenance_type">Maintenance Sub-Category</label>
      <select id="maintenance_type" name="maintenance_type" class="w-full border-2 border-gray-300 dark:border-slate-600 bg-gray-50 dark:bg-slate-700 dark:text-white px-5 py-4 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all duration-300 text-base">
        <option value="">Select specific maintenance type</option>
        <option value="Air conditioning">Air conditioning</option>
        <option value="Electrical">Electrical</option>
        <option value="General work order">General work order</option>
        <option value="Plumbing">Plumbing</option>
        <option value="Repairing">Repairing</option>
      </select>
      <p class="mt-2 text-sm text-gray-500">Please specify the type of maintenance required.</p>
    </div>

    <div>
      <label class="block text-base font-semibold text-gray-800 dark:text-gray-300 mb-3" for="emergency_tier">Emergency Tier</label>
      <select id="emergency_tier" name="emergency_tier" class="w-full border-2 border-gray-300 dark:border-slate-600 bg-gray-50 dark:bg-slate-700 dark:text-white px-5 py-4 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all duration-300 text-base" required>
        <option value="">Select emergency tier</option>
        <option value="Normal">Normal (4-10 days)</option>
        <option value="Urgent">Urgent (1-3 days)</option>
        <option value="Emergency">Emergency (Immediately)</option>
      </select>
      <input type="hidden" id="emergencyTierOverride" name="emergency_tier" value="Emergency" disabled>
      <p id="emergencyTierHelp" class="mt-2 text-sm text-gray-500">Select the expected response tier. Double Parking reports are automatically marked Emergency.</p>
    </div>

    <!-- Location hidden input, populated automatically by room selection -->
    <input type="hidden" id="location" name="location" required value="">

    <div>
      <label class="block text-base font-semibold text-gray-800 dark:text-gray-300 mb-3" for="description">Description</label>
      <textarea id="description" name="description" rows="6"
        placeholder="Please describe the issue in detail, including any relevant information..."
        class="w-full border-2 border-gray-300 dark:border-slate-600 bg-gray-50 dark:bg-slate-700 dark:text-white px-5 py-4 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all duration-300 resize-none text-base" required></textarea>
    </div>

    <div>
      <label class="block text-base font-semibold text-gray-800 dark:text-gray-300 mb-3" for="photo">Photo</label>
      <div class="relative">
        <input id="photo" name="photo" type="file" accept="image/*" capture="environment" required
          class="w-full text-base text-gray-700 dark:text-slate-300 file:mr-4 file:py-4 file:px-6 file:rounded-xl file:border-0 file:text-base file:font-semibold file:bg-blue-50 dark:file:bg-blue-900/40 file:text-blue-700 dark:file:text-blue-300 hover:file:bg-blue-100 file:cursor-pointer file:transition-colors" />
        <p class="mt-3 text-sm text-gray-500">Please attach a photo of the issue.</p>
      </div>
      <div id="photoPreview" class="mt-5 hidden rounded-xl overflow-hidden border-2 border-gray-300 dark:border-slate-600 shadow-md">
        <img id="previewImage" src="" alt="Preview" class="w-full object-cover max-h-80" />
      </div>
    </div>

    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-4 rounded-xl shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-300 text-base md:text-lg">
      Submit Report
    </button>

  </form>

</div>

<script>
const photoInput = document.getElementById('photo');
const photoPreview = document.getElementById('photoPreview');
const previewImage = document.getElementById('previewImage');
const categorySelect = document.getElementById('category');
const emergencyTierSelect = document.getElementById('emergency_tier');
const emergencyTierOverride = document.getElementById('emergencyTierOverride');
const maintenanceTypeContainer = document.getElementById('maintenanceTypeContainer');
const maintenanceTypeSelect = document.getElementById('maintenance_type');
const floorSelect = document.getElementById('floor');
const roomContainer = document.getElementById('roomSelectionContainer');
const locationInput = document.getElementById('location');
const roomSearchInput = document.getElementById('roomSearchInput');

// Data passed from PHP
const roomMapping = <?php echo json_encode($roomMapping); ?>;

floorSelect.addEventListener('change', function() {
    const floor = this.value;
    const showDoubleParkingOption = ["Basement", "Level 1", "Level 2"].includes(floor);
    const doubleParkingOption = document.getElementById('doubleParkingOption');
    const dropdown = document.getElementById('customRoomDropdown');

    if (showDoubleParkingOption === true) {
        doubleParkingOption.hidden = false;
    } else {
        doubleParkingOption.hidden = true;
        if (categorySelect.value === 'Double Parking') {
            categorySelect.value = '';
            categorySelect.dispatchEvent(new Event('change'));
        }
    }

    roomSearchInput.value = '';
    locationInput.value = '';
    dropdown.innerHTML = '';
  
    if (floor && roomMapping[floor]) {
        const labeledRooms = roomMapping[floor].filter(room => room.label && room.label.trim() !== '');
        
        if (labeledRooms.length > 0) {
            labeledRooms.forEach(room => {
                const item = document.createElement('div');
                item.textContent = room.label;
                item.dataset.value = room.label;
                item.className = 'px-5 py-3 cursor-pointer text-gray-800 dark:text-slate-200 hover:bg-blue-500 hover:text-white dark:hover:bg-blue-600 transition-colors duration-150';
                
                item.addEventListener('click', function() {
                    roomSearchInput.value = this.dataset.value;
                    locationInput.value = this.dataset.value;
                    dropdown.classList.add('hidden');
                });
                dropdown.appendChild(item);
            });
        } else {
            const noResults = document.createElement('div');
            noResults.textContent = 'No rooms defined for this floor.';
            noResults.className = 'px-5 py-3 text-gray-500 dark:text-gray-400 italic';
            dropdown.appendChild(noResults);
        }

        roomContainer.classList.remove('hidden');
        locationInput.setAttribute('required', 'required');
    } else {
        roomContainer.classList.add('hidden');
        locationInput.removeAttribute('required');
    }
});

// --- Custom Dropdown & Search Logic ---
const dropdown = document.getElementById('customRoomDropdown');

// Show dropdown on focus/click
roomSearchInput.addEventListener('focus', () => dropdown.classList.remove('hidden'));
roomSearchInput.addEventListener('click', () => dropdown.classList.remove('hidden'));

// Hide dropdown when clicking outside
document.addEventListener('click', function(e) {
    if (!roomContainer.contains(e.target)) {
        dropdown.classList.add('hidden');
    }
});

// Live search filtering
roomSearchInput.addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const items = dropdown.querySelectorAll('div');
    let hasResults = false;

    items.forEach(item => {
        const itemText = item.textContent.toLowerCase();
        const isVisible = itemText.includes(searchTerm);
        item.style.display = isVisible ? '' : 'none';
        if (isVisible) hasResults = true;
    });

    // Clear selection if user is typing something new
    if (locationInput.value !== this.value) {
        locationInput.value = '';
    }

    // Show "No results" message
    let noResultsMsg = dropdown.querySelector('.no-results');
    if (!hasResults && !noResultsMsg) {
        noResultsMsg = document.createElement('div');
        noResultsMsg.textContent = 'No matching rooms found.';
        noResultsMsg.className = 'no-results px-5 py-3 text-gray-500 dark:text-gray-400 italic';
        dropdown.appendChild(noResultsMsg);
    } else if (hasResults && noResultsMsg) {
        noResultsMsg.remove();
    }
});

categorySelect.addEventListener('change', function() {
  const isDoubleParking = this.value === 'Double Parking';
  emergencyTierSelect.disabled = isDoubleParking;
  emergencyTierOverride.disabled = !isDoubleParking;
  if (isDoubleParking) {
    emergencyTierSelect.value = 'Emergency';
  }

  if (this.value === 'Maintenance') {
    maintenanceTypeContainer.classList.remove('hidden');
    maintenanceTypeSelect.setAttribute('required', 'required');
  } else {
    maintenanceTypeContainer.classList.add('hidden');
    maintenanceTypeSelect.removeAttribute('required');
    maintenanceTypeSelect.value = '';
  }
});

photoInput.addEventListener('change', function () {
  const file = this.files && this.files[0];
  if (!file) {
    photoPreview.classList.add('hidden');
    previewImage.src = '';
    return;
  }
  const reader = new FileReader();
  reader.onload = function (event) {
    previewImage.src = event.target.result;
    photoPreview.classList.remove('hidden');
    photoPreview.classList.add('animate-fade-in');
  };
  reader.readAsDataURL(file);
});

// Add fade-in animation
const style = document.createElement('style');
style.textContent = `
  @keyframes fade-in {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
  }
  .animate-fade-in {
    animation: fade-in 0.3s ease-out;
  }
`;
document.head.appendChild(style);
</script>