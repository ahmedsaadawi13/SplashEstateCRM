<?php
// FILE: /app/views/emails/deal_won.php
$content = '
<h2>🎉 Congratulations! Deal Closed Successfully!</h2>

<p>Hi ' . htmlspecialchars($agent_name) . ',</p>

<p>Great news! The deal has been successfully closed.</p>

<div class="highlight">
    <strong>Deal:</strong> ' . htmlspecialchars($deal_title) . '<br>
    <strong>Client:</strong> ' . htmlspecialchars($client_name) . '<br>
    <strong>Deal Value:</strong> $' . number_format($deal_value, 2) . '<br>
    <strong>Commission:</strong> $' . number_format($commission, 2) . '<br>
    <strong>Closed Date:</strong> ' . date('F j, Y', strtotime($closed_date)) . '
</div>

<p>Congratulations on closing this deal! Your hard work has paid off.</p>

<p style="text-align: center;">
    <a href="' . BASE_URL . '/deals/view/' . $deal_id . '" class="btn">View Deal Details</a>
</p>

<p>Keep up the excellent work!<br>
SplashEstate CRM</p>
';

include ROOT_PATH . '/app/views/emails/layout.php';
?>
