<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
  <div class="bg-white dark:bg-slate-800 p-6 rounded-xl shadow-lg w-full max-w-lg relative dark:shadow-none dark:border dark:border-slate-700">
    <button type="button" onclick="closeEditModal()" class="absolute top-2 right-3 text-xl text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">&times;</button>
    <h2 class="text-xl font-bold mb-4 text-slate-900 dark:text-white">Edit Issue</h2>

    <form id="editForm" method="POST" action="update_report.php" class="space-y-4">
      <input type="hidden" id="editId" name="id">

      <div>
        <label for="editDescription" class="block text-sm font-medium text-slate-700 dark:text-gray-300 mb-2">Description</label>
        <textarea id="editDescription" name="description" class="w-full border dark:border-slate-600 px-3 py-2 rounded dark:bg-slate-700 dark:text-white" rows="3" required></textarea>
      </div>

      <div class="grid gap-4 md:grid-cols-2">
        <div>
          <label for="editCategory" class="block text-sm font-medium text-slate-700 dark:text-gray-300 mb-2">Issue Type</label>
          <input id="editCategory" name="category" class="w-full border dark:border-slate-600 px-3 py-2 rounded dark:bg-slate-700 dark:text-white" required />
        </div>
        <div>
          <label for="editLocation" class="block text-sm font-medium text-slate-700 dark:text-gray-300 mb-2">Location</label>
          <input id="editLocation" name="location" class="w-full border dark:border-slate-600 px-3 py-2 rounded dark:bg-slate-700 dark:text-white" required />
        </div>
      </div>

      <div class="grid gap-4 md:grid-cols-2">
        <div>
          <label for="editFloor" class="block text-sm font-medium text-slate-700 dark:text-gray-300 mb-2">Floor</label>
          <input id="editFloor" name="floor" class="w-full border dark:border-slate-600 px-3 py-2 rounded dark:bg-slate-700 dark:text-white" required />
        </div>
        <div>
          <label for="editStatus" class="block text-sm font-medium text-slate-700 dark:text-gray-300 mb-2">Status</label>
          <select id="editStatus" name="status" class="w-full border dark:border-slate-600 px-3 py-2 rounded dark:bg-slate-700 dark:text-white" required>
            <option value="Pending">Pending</option>
            <option value="In Progress">In Progress</option>
            <option value="Resolved">Completed</option>
          </select>
        </div>
      </div>

      <div class="flex justify-end gap-3 pt-2 dark:text-white">
        <button type="button" onclick="closeEditModal()" class="rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-2 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">Cancel</button>
        <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-white">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditModal(button) {
  document.getElementById('editId').value = button.dataset.id;
  document.getElementById('editDescription').value = button.dataset.description;
  document.getElementById('editCategory').value = button.dataset.category;
  document.getElementById('editLocation').value = button.dataset.location;
  document.getElementById('editFloor').value = button.dataset.floor;
  document.getElementById('editStatus').value = button.dataset.status;

  const modal = document.getElementById('editModal');
  modal.classList.remove('hidden');
  modal.classList.add('flex');
}

function closeEditModal() {
  const modal = document.getElementById('editModal');
  modal.classList.add('hidden');
  modal.classList.remove('flex');
}
</script>