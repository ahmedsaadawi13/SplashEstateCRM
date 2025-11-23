<?php
// FILE: /app/views/emails/welcome.php
$content = '
<h2>Welcome to SplashEstate CRM!</h2>

<p>Hi ' . htmlspecialchars($first_name) . ',</p>

<p>Thank you for joining SplashEstate CRM. We\'re excited to help you manage your real estate business more effectively.</p>

<p>Your account has been successfully created. Here are your account details:</p>

<div class="highlight">
    <strong>Email:</strong> ' . htmlspecialchars($email) . '<br>
    <strong>Company:</strong> ' . htmlspecialchars($company_name) . '
</div>

<p>Get started by exploring these features:</p>
<ul>
    <li>Lead Management - Track and convert prospects</li>
    <li>Property Listings - Manage your inventory</li>
    <li>Deal Pipeline - Close more deals</li>
    <li>Task Management - Stay organized</li>
</ul>

<p style="text-align: center;">
    <a href="' . BASE_URL . '/dashboard" class="btn">Go to Dashboard</a>
</p>

<p>If you have any questions, feel free to reach out to our support team.</p>

<p>Best regards,<br>
The SplashEstate Team</p>
';

include ROOT_PATH . '/app/views/emails/layout.php';
?>
