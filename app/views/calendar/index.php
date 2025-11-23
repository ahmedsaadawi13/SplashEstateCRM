<?php
// FILE: /app/views/calendar/index.php
require_once '../app/views/layouts/header.php';
?>

<div class="page-header">
    <h1>Task Calendar</h1>
    <div class="header-actions">
        <button type="button" class="btn btn-primary" onclick="showCreateTaskModal()">
            ➕ Create Task
        </button>
    </div>
</div>

<!-- Calendar Filters -->
<div class="calendar-filters">
    <div class="filter-group">
        <label for="filterAssignedTo">Assigned To:</label>
        <select id="filterAssignedTo" class="form-control" onchange="filterCalendar()">
            <option value="">All Users</option>
            <?php foreach ($users as $user): ?>
                <option value="<?php echo $user['id']; ?>">
                    <?php echo View::escape($user['first_name'] . ' ' . $user['last_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="filter-group">
        <label for="filterStatus">Status:</label>
        <select id="filterStatus" class="form-control" onchange="filterCalendar()">
            <option value="">All Statuses</option>
            <option value="pending">Pending</option>
            <option value="in_progress">In Progress</option>
            <option value="completed">Completed</option>
        </select>
    </div>

    <div class="filter-group">
        <label for="filterPriority">Priority:</label>
        <select id="filterPriority" class="form-control" onchange="filterCalendar()">
            <option value="">All Priorities</option>
            <option value="urgent">Urgent</option>
            <option value="high">High</option>
            <option value="normal">Normal</option>
            <option value="low">Low</option>
        </select>
    </div>

    <div class="filter-group">
        <button type="button" class="btn btn-secondary" onclick="clearCalendarFilters()">
            Clear Filters
        </button>
    </div>
</div>

<!-- Calendar Container -->
<div id="calendar"></div>

<!-- Create/Edit Task Modal -->
<div id="taskModal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close" onclick="closeTaskModal()">&times;</span>
        <h3 id="taskModalTitle">Create Task</h3>

        <form id="taskForm" onsubmit="submitTaskForm(event)">
            <input type="hidden" id="taskId" value="">

            <div class="form-group">
                <label for="taskTitle">Title *</label>
                <input type="text" id="taskTitle" class="form-control" required
                       placeholder="Task title">
            </div>

            <div class="form-group">
                <label for="taskDescription">Description</label>
                <textarea id="taskDescription" class="form-control" rows="4"
                          placeholder="Task description..."></textarea>
            </div>

            <div class="form-group">
                <label for="taskDueDate">Due Date *</label>
                <input type="datetime-local" id="taskDueDate" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="taskAssignedTo">Assigned To *</label>
                <select id="taskAssignedTo" class="form-control" required>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>"
                                <?php echo ($user['id'] == $_SESSION['user_id']) ? 'selected' : ''; ?>>
                            <?php echo View::escape($user['first_name'] . ' ' . $user['last_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="taskPriority">Priority *</label>
                <select id="taskPriority" class="form-control" required>
                    <option value="low">Low</option>
                    <option value="normal" selected>Normal</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                </select>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Task</button>
                <button type="button" class="btn btn-secondary" onclick="closeTaskModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Task Details Modal -->
<div id="taskDetailsModal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close" onclick="closeTaskDetailsModal()">&times;</span>
        <h3 id="taskDetailsTitle"></h3>

        <div class="task-details">
            <div class="detail-row">
                <strong>Status:</strong>
                <span id="taskDetailsStatus"></span>
            </div>
            <div class="detail-row">
                <strong>Priority:</strong>
                <span id="taskDetailsPriority"></span>
            </div>
            <div class="detail-row">
                <strong>Due Date:</strong>
                <span id="taskDetailsDueDate"></span>
            </div>
            <div class="detail-row">
                <strong>Assigned To:</strong>
                <span id="taskDetailsAssignedTo"></span>
            </div>
            <div class="detail-row" id="taskDetailsDescRow" style="display: none;">
                <strong>Description:</strong>
                <p id="taskDetailsDescription"></p>
            </div>
        </div>

        <div class="form-actions">
            <a id="taskDetailsViewLink" href="#" class="btn btn-primary">View Full Details</a>
            <button type="button" class="btn btn-secondary" onclick="closeTaskDetailsModal()">Close</button>
        </div>
    </div>
</div>

<style>
.calendar-filters {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 20px;
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    align-items: flex-end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    min-width: 150px;
}

.filter-group label {
    font-weight: 600;
    margin-bottom: 5px;
    font-size: 14px;
}

#calendar {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.task-details {
    margin: 20px 0;
}

.detail-row {
    padding: 10px 0;
    border-bottom: 1px solid #eee;
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-row strong {
    display: inline-block;
    width: 120px;
    color: #2c3e50;
}

.detail-row p {
    margin-top: 5px;
    margin-left: 120px;
    color: #666;
}

@media (max-width: 768px) {
    .calendar-filters {
        flex-direction: column;
    }

    .filter-group {
        width: 100%;
    }
}
</style>

<!-- Include FullCalendar CSS and JS -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.9/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.9/index.global.min.js"></script>

<script>
let calendar;
const BASE_URL = '<?php echo BASE_URL; ?>';

// Initialize calendar on page load
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');

    calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
        },
        editable: true,
        droppable: true,
        events: function(info, successCallback, failureCallback) {
            loadCalendarEvents(info.start, info.end, successCallback, failureCallback);
        },
        eventClick: function(info) {
            showTaskDetails(info.event);
        },
        eventDrop: function(info) {
            updateTaskDate(info.event.id, info.event.start);
        },
        dateClick: function(info) {
            showCreateTaskModal(info.dateStr);
        },
        eventTimeFormat: {
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        },
        height: 'auto',
        nowIndicator: true
    });

    calendar.render();
});

// Load calendar events
function loadCalendarEvents(start, end, successCallback, failureCallback) {
    const assignedTo = document.getElementById('filterAssignedTo').value;
    const status = document.getElementById('filterStatus').value;
    const priority = document.getElementById('filterPriority').value;

    const params = new URLSearchParams({
        start: start.toISOString().split('T')[0],
        end: end.toISOString().split('T')[0]
    });

    if (assignedTo) params.append('assigned_to', assignedTo);
    if (status) params.append('status', status);
    if (priority) params.append('priority', priority);

    fetch(BASE_URL + '/calendar/getTasks?' + params.toString())
        .then(response => response.json())
        .then(data => {
            successCallback(data);
        })
        .catch(error => {
            console.error('Error loading events:', error);
            failureCallback(error);
        });
}

// Filter calendar
function filterCalendar() {
    calendar.refetchEvents();
}

// Clear filters
function clearCalendarFilters() {
    document.getElementById('filterAssignedTo').value = '';
    document.getElementById('filterStatus').value = '';
    document.getElementById('filterPriority').value = '';
    filterCalendar();
}

// Show create task modal
function showCreateTaskModal(dateStr = null) {
    document.getElementById('taskModal').style.display = 'flex';
    document.getElementById('taskModalTitle').textContent = 'Create Task';
    document.getElementById('taskForm').reset();
    document.getElementById('taskId').value = '';

    // Set due date if provided
    if (dateStr) {
        const date = new Date(dateStr);
        document.getElementById('taskDueDate').value = formatDateTimeLocal(date);
    }
}

// Close task modal
function closeTaskModal() {
    document.getElementById('taskModal').style.display = 'none';
}

// Submit task form
function submitTaskForm(event) {
    event.preventDefault();

    const taskData = {
        title: document.getElementById('taskTitle').value,
        description: document.getElementById('taskDescription').value,
        due_date: document.getElementById('taskDueDate').value,
        assigned_to: document.getElementById('taskAssignedTo').value,
        priority: document.getElementById('taskPriority').value
    };

    fetch(BASE_URL + '/calendar/createTask', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(taskData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Task created successfully!');
            closeTaskModal();
            calendar.refetchEvents();
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to create task');
    });
}

// Update task date (drag and drop)
function updateTaskDate(taskId, newDate) {
    fetch(BASE_URL + '/calendar/updateTaskDate', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            task_id: taskId,
            new_date: newDate.toISOString().split('T')[0] + ' ' + newDate.toTimeString().split(' ')[0]
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Success feedback
            console.log('Task rescheduled successfully');
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
            calendar.refetchEvents(); // Revert on error
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to update task date');
        calendar.refetchEvents(); // Revert on error
    });
}

// Show task details
function showTaskDetails(event) {
    document.getElementById('taskDetailsModal').style.display = 'flex';
    document.getElementById('taskDetailsTitle').textContent = event.title;

    const props = event.extendedProps;

    document.getElementById('taskDetailsStatus').textContent = props.status.toUpperCase();
    document.getElementById('taskDetailsPriority').textContent = props.priority.toUpperCase();
    document.getElementById('taskDetailsDueDate').textContent = formatDateTime(event.start);
    document.getElementById('taskDetailsAssignedTo').textContent = props.assigned_to_name;

    if (props.description) {
        document.getElementById('taskDetailsDescRow').style.display = 'block';
        document.getElementById('taskDetailsDescription').textContent = props.description;
    } else {
        document.getElementById('taskDetailsDescRow').style.display = 'none';
    }

    document.getElementById('taskDetailsViewLink').href = BASE_URL + '/tasks/view/' + event.id;
}

// Close task details modal
function closeTaskDetailsModal() {
    document.getElementById('taskDetailsModal').style.display = 'none';
}

// Format date time for datetime-local input
function formatDateTimeLocal(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day}T${hours}:${minutes}`;
}

// Format date time for display
function formatDateTime(date) {
    return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}
</script>

<?php require_once '../app/views/layouts/footer.php'; ?>
