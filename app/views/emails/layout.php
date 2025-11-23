<!-- FILE: /app/views/emails/layout.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .email-header {
            background-color: #2c3e50;
            color: #ffffff;
            padding: 20px;
            text-align: center;
        }
        .email-body {
            padding: 30px;
        }
        .email-footer {
            background-color: #ecf0f1;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #7f8c8d;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: #3498db;
            color: #ffffff;
            text-decoration: none;
            border-radius: 4px;
            margin: 10px 0;
        }
        .btn:hover {
            background-color: #2980b9;
        }
        h1 {
            margin: 0;
            font-size: 24px;
        }
        h2 {
            color: #2c3e50;
            font-size: 20px;
        }
        .highlight {
            background-color: #fff3cd;
            padding: 15px;
            border-left: 4px solid: #f39c12;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <h1>SplashEstate CRM</h1>
        </div>
        <div class="email-body">
            <?php echo $content; ?>
        </div>
        <div class="email-footer">
            <p>&copy; <?php echo date('Y'); ?> SplashEstate CRM. All rights reserved.</p>
            <p>
                <a href="<?php echo BASE_URL; ?>" style="color: #3498db;">Visit Dashboard</a> |
                <a href="<?php echo BASE_URL; ?>/settings" style="color: #3498db;">Email Preferences</a>
            </p>
        </div>
    </div>
</body>
</html>
