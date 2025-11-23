<?php
// FILE: /app/views/emails/task_reminder.php
$content = '
<h2>Task Reminder</h2>

<p>Hi ' . htmlspecialchars($user_name) . ',</p>

<p>This is a reminder about your upcoming task:</p>

<div class="highlight">
    <strong>Task:</strong> ' . htmlspecialchars($task_title) . '<br>
    <strong>Due Date:</strong> ' . date('F j, Y g:i A', strtotime($due_date)) . '<br>
    <strong>Priority:</strong> <span style="color: ' . ($priority === 'urgent' ? '#e74c3c' : ($priority === 'high' ? '#f39c12' : '#95a5a6')) . ';">' . ucfirst($priority) . '</span>
</div>

' . (!empty($description) ? '<p><strong>Description:</strong><br>' . nl2br(htmlspecialchars($description)) . '</p>' : '') . '

<p style="text-align: center;">
    <a href="' . BASE_URL . '/tasks/view/' . $task_id . '" class="btn">View Task</a>
</p>

<p>Stay productive!<br>
SplashEstate CRM</p>
';

include ROOT_PATH . '/app/views/emails/layout.php';
?>
