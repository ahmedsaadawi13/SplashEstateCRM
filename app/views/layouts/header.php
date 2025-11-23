<!-- FILE: /app/views/layouts/header.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($title) ? View::escape($title) . ' - ' : ''; ?>SplashEstate CRM</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
</head>
<body>
    <?php if (isset($_SESSION['user_id'])): ?>
    <nav class="navbar">
        <div class="container">
            <div class="navbar-brand">
                <a href="<?php echo BASE_URL; ?>/dashboard/index">SplashEstate CRM</a>
            </div>
            <ul class="navbar-menu">
                <li><a href="<?php echo BASE_URL; ?>/dashboard/index">Dashboard</a></li>
                <li><a href="<?php echo BASE_URL; ?>/leads/index">Leads</a></li>
                <li><a href="<?php echo BASE_URL; ?>/clients/index">Clients</a></li>
                <li><a href="<?php echo BASE_URL; ?>/properties/index">Properties</a></li>
                <li><a href="<?php echo BASE_URL; ?>/deals/index">Deals</a></li>
                <li><a href="<?php echo BASE_URL; ?>/tasks/index">Tasks</a></li>
                <li><a href="<?php echo BASE_URL; ?>/reports/index">Reports</a></li>
                <li class="navbar-user">
                    <span><?php echo View::escape($_SESSION['user_name']); ?></span>
                    <a href="<?php echo BASE_URL; ?>/auth/logout">Logout</a>
                </li>
            </ul>
        </div>
    </nav>
    <?php endif; ?>

    <div class="container">
        <?php if (isset($_SESSION['flash'])): ?>
            <?php $flash = $_SESSION['flash']; unset($_SESSION['flash']); ?>
            <div class="alert alert-<?php echo $flash['type']; ?>">
                <?php echo View::escape($flash['message']); ?>
            </div>
        <?php endif; ?>
