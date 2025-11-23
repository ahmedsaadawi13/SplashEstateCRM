<?php
// FILE: /app/views/partials/advanced_filters.php
/**
 * Advanced Filter UI Component
 * Can be included in any list view
 *
 * Required variables:
 * - $module: Current module name (leads, clients, properties, deals, tasks)
 * - $filterFields: Array of filter field configurations
 * - $currentFilters: Current active filters (from GET/session)
 */
?>

<div class="advanced-filters" id="advancedFilters">
    <div class="filter-header">
        <div class="filter-actions">
            <button type="button" class="btn btn-sm" onclick="toggleAdvancedFilters()">
                <span id="filterToggleIcon">▼</span> Advanced Filters
            </button>
            <button type="button" class="btn btn-sm btn-success" onclick="saveCurrentSearch()">
                💾 Save Search
            </button>
            <select id="savedSearchSelect" onchange="loadSavedSearch(this.value)" class="btn btn-sm">
                <option value="">Load Saved Search...</option>
            </select>
            <button type="button" class="btn btn-sm btn-secondary" onclick="clearAllFilters()">
                Clear All
            </button>
        </div>
    </div>

    <div class="filter-panel" id="filterPanel" style="display: none;">
        <form method="GET" action="" id="advancedFilterForm">
            <div class="filter-grid">
                <?php foreach ($filterFields as $field): ?>
                    <div class="filter-field">
                        <label for="filter_<?php echo $field['name']; ?>">
                            <?php echo htmlspecialchars($field['label']); ?>
                        </label>

                        <?php if ($field['type'] === 'select'): ?>
                            <select name="<?php echo $field['name']; ?>"
                                    id="filter_<?php echo $field['name']; ?>"
                                    class="form-control filter-input">
                                <option value="">All</option>
                                <?php foreach ($field['options'] as $value => $label): ?>
                                    <option value="<?php echo htmlspecialchars($value); ?>"
                                            <?php echo (isset($currentFilters[$field['name']]) && $currentFilters[$field['name']] == $value) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                        <?php elseif ($field['type'] === 'date'): ?>
                            <input type="date"
                                   name="<?php echo $field['name']; ?>"
                                   id="filter_<?php echo $field['name']; ?>"
                                   class="form-control filter-input"
                                   value="<?php echo isset($currentFilters[$field['name']]) ? htmlspecialchars($currentFilters[$field['name']]) : ''; ?>">

                        <?php elseif ($field['type'] === 'daterange'): ?>
                            <div class="date-range-group">
                                <input type="date"
                                       name="<?php echo $field['name']; ?>_from"
                                       id="filter_<?php echo $field['name']; ?>_from"
                                       class="form-control filter-input"
                                       placeholder="From"
                                       value="<?php echo isset($currentFilters[$field['name'] . '_from']) ? htmlspecialchars($currentFilters[$field['name'] . '_from']) : ''; ?>">
                                <span class="range-separator">to</span>
                                <input type="date"
                                       name="<?php echo $field['name']; ?>_to"
                                       id="filter_<?php echo $field['name']; ?>_to"
                                       class="form-control filter-input"
                                       placeholder="To"
                                       value="<?php echo isset($currentFilters[$field['name'] . '_to']) ? htmlspecialchars($currentFilters[$field['name'] . '_to']) : ''; ?>">
                            </div>

                        <?php elseif ($field['type'] === 'numericrange'): ?>
                            <div class="numeric-range-group">
                                <input type="number"
                                       name="<?php echo $field['name']; ?>_min"
                                       id="filter_<?php echo $field['name']; ?>_min"
                                       class="form-control filter-input"
                                       placeholder="Min"
                                       value="<?php echo isset($currentFilters[$field['name'] . '_min']) ? htmlspecialchars($currentFilters[$field['name'] . '_min']) : ''; ?>">
                                <span class="range-separator">-</span>
                                <input type="number"
                                       name="<?php echo $field['name']; ?>_max"
                                       id="filter_<?php echo $field['name']; ?>_max"
                                       class="form-control filter-input"
                                       placeholder="Max"
                                       value="<?php echo isset($currentFilters[$field['name'] . '_max']) ? htmlspecialchars($currentFilters[$field['name'] . '_max']) : ''; ?>">
                            </div>

                        <?php else: // text input ?>
                            <input type="text"
                                   name="<?php echo $field['name']; ?>"
                                   id="filter_<?php echo $field['name']; ?>"
                                   class="form-control filter-input"
                                   placeholder="<?php echo isset($field['placeholder']) ? htmlspecialchars($field['placeholder']) : ''; ?>"
                                   value="<?php echo isset($currentFilters[$field['name']]) ? htmlspecialchars($currentFilters[$field['name']]) : ''; ?>">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Sorting Options -->
            <div class="filter-sorting">
                <div class="filter-field">
                    <label for="filter_sort">Sort By</label>
                    <select name="sort" id="filter_sort" class="form-control filter-input">
                        <?php foreach ($sortOptions as $value => $label): ?>
                            <option value="<?php echo htmlspecialchars($value); ?>"
                                    <?php echo (isset($currentFilters['sort']) && $currentFilters['sort'] == $value) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-field">
                    <label for="filter_direction">Direction</label>
                    <select name="direction" id="filter_direction" class="form-control filter-input">
                        <option value="DESC" <?php echo (!isset($currentFilters['direction']) || $currentFilters['direction'] == 'DESC') ? 'selected' : ''; ?>>Newest First</option>
                        <option value="ASC" <?php echo (isset($currentFilters['direction']) && $currentFilters['direction'] == 'ASC') ? 'selected' : ''; ?>>Oldest First</option>
                    </select>
                </div>
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <button type="button" class="btn btn-secondary" onclick="clearAllFilters()">Clear</button>
            </div>
        </form>
    </div>

    <!-- Active Filters Display -->
    <div class="active-filters" id="activeFilters">
        <!-- Dynamically populated by JavaScript -->
    </div>
</div>

<!-- Save Search Modal -->
<div id="saveSearchModal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close" onclick="closeSaveSearchModal()">&times;</span>
        <h3>Save Current Search</h3>
        <form id="saveSearchForm" onsubmit="submitSaveSearch(event)">
            <div class="form-group">
                <label for="searchName">Search Name</label>
                <input type="text" id="searchName" class="form-control" required
                       placeholder="e.g., Hot Leads This Month">
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" id="setAsDefault">
                    Set as default search for this module
                </label>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save</button>
                <button type="button" class="btn btn-secondary" onclick="closeSaveSearchModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<style>
.advanced-filters {
    background: #f8f9fa;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 20px;
}

.filter-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.filter-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.filter-panel {
    margin-top: 15px;
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
    margin-bottom: 15px;
}

.filter-field {
    display: flex;
    flex-direction: column;
}

.filter-field label {
    font-weight: 600;
    margin-bottom: 5px;
    font-size: 14px;
}

.filter-input {
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.date-range-group,
.numeric-range-group {
    display: flex;
    align-items: center;
    gap: 5px;
}

.range-separator {
    color: #666;
    font-weight: 600;
}

.filter-sorting {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 15px;
    padding-top: 15px;
    border-top: 1px solid #ddd;
}

.active-filters {
    margin-top: 10px;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.filter-tag {
    background: #3498db;
    color: white;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 13px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.filter-tag .remove {
    cursor: pointer;
    font-weight: bold;
    margin-left: 5px;
}

.modal {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background-color: white;
    padding: 30px;
    border-radius: 8px;
    max-width: 500px;
    width: 90%;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.close {
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    color: #aaa;
}

.close:hover {
    color: #000;
}

@media (max-width: 768px) {
    .filter-grid {
        grid-template-columns: 1fr;
    }
    .filter-sorting {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// Module name from PHP
const filterModule = '<?php echo $module; ?>';

// Toggle advanced filters panel
function toggleAdvancedFilters() {
    const panel = document.getElementById('filterPanel');
    const icon = document.getElementById('filterToggleIcon');

    if (panel.style.display === 'none') {
        panel.style.display = 'block';
        icon.textContent = '▲';
    } else {
        panel.style.display = 'none';
        icon.textContent = '▼';
    }
}

// Clear all filters
function clearAllFilters() {
    const form = document.getElementById('advancedFilterForm');
    form.reset();

    // Remove all GET parameters except page
    window.location.href = window.location.pathname;
}

// Save current search
function saveCurrentSearch() {
    document.getElementById('saveSearchModal').style.display = 'flex';
}

function closeSaveSearchModal() {
    document.getElementById('saveSearchModal').style.display = 'none';
}

function submitSaveSearch(event) {
    event.preventDefault();

    const form = document.getElementById('advancedFilterForm');
    const formData = new FormData(form);

    // Convert form data to filters object
    const filters = {};
    for (let [key, value] of formData.entries()) {
        if (value) {
            filters[key] = value;
        }
    }

    const searchName = document.getElementById('searchName').value;
    const isDefault = document.getElementById('setAsDefault').checked;

    // Send to backend
    fetch('<?php echo BASE_URL; ?>/savedsearch/create', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            name: searchName,
            module: filterModule,
            filters: filters,
            is_default: isDefault ? 1 : 0
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Search saved successfully!');
            closeSaveSearchModal();
            loadSavedSearches();
        } else {
            alert('Error: ' + (data.error || 'Failed to save search'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to save search');
    });
}

// Load saved searches dropdown
function loadSavedSearches() {
    fetch('<?php echo BASE_URL; ?>/savedsearch/index?module=' + filterModule)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('savedSearchSelect');
                select.innerHTML = '<option value="">Load Saved Search...</option>';

                data.searches.forEach(search => {
                    const option = document.createElement('option');
                    option.value = search.id;
                    option.textContent = search.name + (search.is_default ? ' (Default)' : '');
                    select.appendChild(option);
                });
            }
        })
        .catch(error => console.error('Error loading saved searches:', error));
}

// Load a saved search
function loadSavedSearch(searchId) {
    if (!searchId) return;

    fetch('<?php echo BASE_URL; ?>/savedsearch/load/' + searchId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const filters = data.search.filters;

                // Populate form fields
                for (let key in filters) {
                    const field = document.getElementById('filter_' + key);
                    if (field) {
                        field.value = filters[key];
                    }
                }

                // Submit form to apply filters
                document.getElementById('advancedFilterForm').submit();
            }
        })
        .catch(error => console.error('Error loading search:', error));
}

// Display active filters as tags
function displayActiveFilters() {
    const container = document.getElementById('activeFilters');
    const urlParams = new URLSearchParams(window.location.search);

    container.innerHTML = '';

    for (let [key, value] of urlParams.entries()) {
        if (key !== 'page' && value) {
            const tag = document.createElement('span');
            tag.className = 'filter-tag';
            tag.innerHTML = `${key}: ${value} <span class="remove" onclick="removeFilter('${key}')">×</span>`;
            container.appendChild(tag);
        }
    }
}

// Remove individual filter
function removeFilter(filterKey) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.delete(filterKey);

    const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
    window.location.href = newUrl;
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadSavedSearches();
    displayActiveFilters();
});
</script>
