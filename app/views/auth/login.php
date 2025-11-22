<?php
// FILE: /app/views/auth/login.php
require_once '../app/views/layouts/header.php';
?>

<div class="auth-container">
    <div class="auth-box">
        <h1>Login to SplashEstate CRM</h1>

        <?php if (isset($errors['general'])): ?>
            <div class="alert alert-error">
                <?php echo View::escape($errors['general']); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo BASE_URL; ?>/auth/login">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control"
                       value="<?php echo isset($email) ? View::escape($email) : ''; ?>" required>
                <?php if (isset($errors['email'])): ?>
                    <span class="error"><?php echo View::escape($errors['email']); ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required>
                <?php if (isset($errors['password'])): ?>
                    <span class="error"><?php echo View::escape($errors['password']); ?></span>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Login</button>
        </form>

        <div class="auth-links">
            <a href="<?php echo BASE_URL; ?>/auth/register">Create an account</a>
            <a href="<?php echo BASE_URL; ?>/auth/forgot">Forgot password?</a>
        </div>
    </div>
</div>

<?php require_once '../app/views/layouts/footer.php'; ?>
