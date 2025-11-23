<?php
// FILE: /app/helpers/EmailService.php

/**
 * SplashEstate CRM - Email Service
 * Handles email sending with templates and SMTP configuration
 */

class EmailService {

    private $smtpHost;
    private $smtpPort;
    private $smtpUser;
    private $smtpPass;
    private $smtpFrom;
    private $smtpFromName;

    /**
     * Constructor - Initialize SMTP settings
     */
    public function __construct() {
        // Use new MAIL_ constants with fallback to old SMTP_ constants
        $this->smtpHost = defined('MAIL_HOST') ? MAIL_HOST : (defined('SMTP_HOST') ? SMTP_HOST : 'localhost');
        $this->smtpPort = defined('MAIL_PORT') ? MAIL_PORT : (defined('SMTP_PORT') ? SMTP_PORT : 25);
        $this->smtpUser = defined('MAIL_USERNAME') ? MAIL_USERNAME : (defined('SMTP_USER') ? SMTP_USER : '');
        $this->smtpPass = defined('MAIL_PASSWORD') ? MAIL_PASSWORD : (defined('SMTP_PASS') ? SMTP_PASS : '');
        $this->smtpFrom = defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : (defined('SMTP_FROM') ? SMTP_FROM : 'noreply@splashestate.com');
        $this->smtpFromName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : (defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'SplashEstate CRM');
    }

    /**
     * Send email using template
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $template Template name
     * @param array $data Template data
     * @return bool Success status
     */
    public function send($to, $subject, $template, $data = array()) {
        $body = $this->renderTemplate($template, $data);

        return $this->sendEmail($to, $subject, $body);
    }

    /**
     * Send raw email
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $body Email body (HTML)
     * @param string $altBody Plain text alternative
     * @return bool Success status
     */
    public function sendEmail($to, $subject, $body, $altBody = '') {
        // Log email attempt
        $this->logEmail($to, $subject, 'sending');

        $success = false;

        // Check if SMTP driver is configured
        if (defined('MAIL_DRIVER') && MAIL_DRIVER === 'smtp' && !empty($this->smtpHost) && !empty($this->smtpUser)) {
            // Use SMTP
            require_once APP_PATH . '/helpers/SMTPMailer.php';

            $mailer = new SMTPMailer(array(
                'host' => $this->smtpHost,
                'port' => $this->smtpPort,
                'username' => $this->smtpUser,
                'password' => $this->smtpPass,
                'encryption' => defined('MAIL_ENCRYPTION') ? MAIL_ENCRYPTION : 'tls'
            ));

            $success = $mailer->send($to, $subject, $body, $this->smtpFrom, $this->smtpFromName);

        } else {
            // Fallback to PHP mail() function
            $headers = array();
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-type: text/html; charset=utf-8';
            $headers[] = 'From: ' . $this->smtpFromName . ' <' . $this->smtpFrom . '>';
            $headers[] = 'Reply-To: ' . $this->smtpFrom;
            $headers[] = 'X-Mailer: SplashEstate CRM';

            $success = mail($to, $subject, $body, implode("\r\n", $headers));
        }

        // Log result
        $this->logEmail($to, $subject, $success ? 'sent' : 'failed');

        return $success;
    }

    /**
     * Render email template
     * @param string $template Template name
     * @param array $data Template variables
     * @return string Rendered HTML
     */
    private function renderTemplate($template, $data = array()) {
        $templatePath = ROOT_PATH . '/app/views/emails/' . $template . '.php';

        if (!file_exists($templatePath)) {
            return '<html><body>Template not found: ' . $template . '</body></html>';
        }

        // Extract data to variables
        extract($data);

        // Start output buffering
        ob_start();
        include $templatePath;
        $html = ob_get_clean();

        return $html;
    }

    /**
     * Log email activity
     * @param string $to Recipient
     * @param string $subject Subject
     * @param string $status Status
     */
    private function logEmail($to, $subject, $status) {
        // Log to file or database
        $logFile = STORAGE_PATH . '/logs/email.log';
        $logDir = dirname($logFile);

        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logEntry = date('Y-m-d H:i:s') . " | To: {$to} | Subject: {$subject} | Status: {$status}\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }

    /**
     * Send welcome email to new user
     * @param string $to User email
     * @param array $data User data
     * @return bool
     */
    public function sendWelcomeEmail($to, $data) {
        return $this->send($to, 'Welcome to SplashEstate CRM', 'welcome', $data);
    }

    /**
     * Send lead notification to agent
     * @param string $to Agent email
     * @param array $data Lead data
     * @return bool
     */
    public function sendLeadNotification($to, $data) {
        return $this->send($to, 'New Lead Assigned', 'lead_notification', $data);
    }

    /**
     * Send task reminder
     * @param string $to User email
     * @param array $data Task data
     * @return bool
     */
    public function sendTaskReminder($to, $data) {
        return $this->send($to, 'Task Reminder: ' . $data['task_title'], 'task_reminder', $data);
    }

    /**
     * Send deal won notification
     * @param string $to User email
     * @param array $data Deal data
     * @return bool
     */
    public function sendDealWonNotification($to, $data) {
        return $this->send($to, 'Congratulations! Deal Closed', 'deal_won', $data);
    }

    /**
     * Send password reset email
     * @param string $to User email
     * @param array $data Reset data
     * @return bool
     */
    public function sendPasswordReset($to, $data) {
        return $this->send($to, 'Password Reset Request', 'password_reset', $data);
    }

    /**
     * Send invoice email
     * @param string $to Tenant email
     * @param array $data Invoice data
     * @return bool
     */
    public function sendInvoice($to, $data) {
        return $this->send($to, 'Invoice #' . $data['invoice_number'], 'invoice', $data);
    }
}
