<?php
// FILE: /app/views/auth/forgot.php
require_once '../app/views/layouts/header.php';
?>

<div class="auth-container">
    <div class="auth-box">
        <h1>Forgot Password</h1>

        <?php if (!empty($message)): ?>
            <div class="alert alert-info">
                <?php echo View::escape($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo BASE_URL; ?>/auth/forgot">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Reset Password</button>
        </form>

        <div class="auth-links">
            <a href="<?php echo BASE_URL; ?>/auth/login">Back to Login</a>
        </div>
    </div>
</div>

<?php require_once '../app/views/layouts/footer.php'; ?>
