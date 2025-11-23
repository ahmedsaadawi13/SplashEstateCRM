<?php
// FILE: /app/views/emails/lead_notification.php
$content = '
<h2>New Lead Assigned to You</h2>

<p>Hi ' . htmlspecialchars($agent_name) . ',</p>

<p>A new lead has been assigned to you. Please follow up promptly.</p>

<div class="highlight">
    <strong>Lead Name:</strong> ' . htmlspecialchars($lead_name) . '<br>
    <strong>Email:</strong> ' . htmlspecialchars($lead_email) . '<br>
    <strong>Phone:</strong> ' . htmlspecialchars($lead_phone) . '<br>
    <strong>Interest:</strong> ' . htmlspecialchars($interest_type) . '<br>
    <strong>Source:</strong> ' . htmlspecialchars($source) . '
</div>

' . (!empty($notes) ? '<p><strong>Notes:</strong> ' . nl2br(htmlspecialchars($notes)) . '</p>' : '') . '

<p style="text-align: center;">
    <a href="' . BASE_URL . '/leads/view/' . $lead_id . '" class="btn">View Lead Details</a>
</p>

<p>Best regards,<br>
SplashEstate CRM</p>
';

include ROOT_PATH . '/app/views/emails/layout.php';
?>
