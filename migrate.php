<?php
// FILE: /migrate.php

/**
 * SplashEstate CRM - Migration CLI Tool
 * Command-line tool for managing database migrations
 *
 * Usage:
 *   php migrate.php migrate          - Run pending migrations
 *   php migrate.php rollback          - Rollback last batch
 *   php migrate.php status            - Show migration status
 *   php migrate.php create <name>     - Create new migration
 */

// Load configuration
require_once __dirname__ . '/config/config.php';
require_once __dirname__ . '/app/core/Database.php';
require_once __dirname__ . '/app/core/Migration.php';
require_once __dirname__ . '/app/core/MigrationRunner.php';

// Color output helpers
function colorize($text, $color) {
    $colors = array(
        'green' => "\033[32m",
        'red' => "\033[31m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'reset' => "\033[0m"
    );

    return $colors[$color] . $text . $colors['reset'];
}

function success($message) {
    echo colorize("✓ ", 'green') . $message . PHP_EOL;
}

function error($message) {
    echo colorize("✗ ", 'red') . $message . PHP_EOL;
}

function info($message) {
    echo colorize("ℹ ", 'blue') . $message . PHP_EOL;
}

function warning($message) {
    echo colorize("⚠ ", 'yellow') . $message . PHP_EOL;
}

// Parse command
$command = isset($argv[1]) ? $argv[1] : 'help';
$runner = new MigrationRunner();

switch ($command) {
    case 'migrate':
        echo PHP_EOL;
        info("Running migrations...");
        echo PHP_EOL;

        $result = $runner->migrate();

        if ($result['success']) {
            if (!empty($result['executed'])) {
                foreach ($result['executed'] as $migration) {
                    success("Migrated: {$migration}");
                }
                echo PHP_EOL;
                success($result['message']);
            } else {
                info($result['message']);
            }
        } else {
            foreach ($result['executed'] as $migration) {
                success("Migrated: {$migration}");
            }

            if (!empty($result['failed'])) {
                foreach ($result['failed'] as $failed) {
                    error("Failed: {$failed['migration']}");
                    error("Error: {$failed['error']}");
                }
            }
        }

        echo PHP_EOL;
        break;

    case 'rollback':
        echo PHP_EOL;
        info("Rolling back migrations...");
        echo PHP_EOL;

        $result = $runner->rollback();

        if ($result['success']) {
            if (!empty($result['rolled_back'])) {
                foreach ($result['rolled_back'] as $migration) {
                    success("Rolled back: {$migration}");
                }
                echo PHP_EOL;
                success($result['message']);
            } else {
                info($result['message']);
            }
        } else {
            foreach ($result['rolled_back'] as $migration) {
                success("Rolled back: {$migration}");
            }

            if (!empty($result['failed'])) {
                foreach ($result['failed'] as $failed) {
                    error("Failed: {$failed['migration']}");
                    error("Error: {$failed['error']}");
                }
            }
        }

        echo PHP_EOL;
        break;

    case 'status':
        echo PHP_EOL;
        info("Migration Status:");
        echo PHP_EOL;

        $status = $runner->status();

        if (empty($status)) {
            warning("No migrations found");
        } else {
            printf("%-60s %-12s %-8s\n", "Migration", "Status", "Batch");
            echo str_repeat('-', 82) . PHP_EOL;

            foreach ($status as $item) {
                $statusText = $item['status'] === 'executed'
                    ? colorize('Executed', 'green')
                    : colorize('Pending', 'yellow');

                $batch = $item['batch'] ?: '-';

                printf("%-60s %-22s %-8s\n", $item['migration'], $statusText, $batch);
            }
        }

        echo PHP_EOL;
        break;

    case 'create':
        if (!isset($argv[2])) {
            error("Migration name is required");
            echo PHP_EOL;
            echo "Usage: php migrate.php create <migration_name>" . PHP_EOL;
            echo "Example: php migrate.php create add_avatar_to_users" . PHP_EOL;
            echo PHP_EOL;
            exit(1);
        }

        $name = $argv[2];
        $filename = $runner->create($name);

        echo PHP_EOL;
        success("Created migration: {$filename}");
        echo PHP_EOL;
        info("Edit the migration file at: database/migrations/{$filename}");
        echo PHP_EOL;
        break;

    case 'help':
    default:
        echo PHP_EOL;
        echo colorize("SplashEstate CRM - Migration Tool", 'blue') . PHP_EOL;
        echo PHP_EOL;
        echo "Usage:" . PHP_EOL;
        echo "  php migrate.php <command> [arguments]" . PHP_EOL;
        echo PHP_EOL;
        echo "Available commands:" . PHP_EOL;
        echo colorize("  migrate", 'green') . "              Run all pending migrations" . PHP_EOL;
        echo colorize("  rollback", 'green') . "             Rollback the last batch of migrations" . PHP_EOL;
        echo colorize("  status", 'green') . "               Show migration status" . PHP_EOL;
        echo colorize("  create <name>", 'green') . "        Create a new migration file" . PHP_EOL;
        echo colorize("  help", 'green') . "                 Show this help message" . PHP_EOL;
        echo PHP_EOL;
        echo "Examples:" . PHP_EOL;
        echo "  php migrate.php migrate" . PHP_EOL;
        echo "  php migrate.php create add_column_to_users" . PHP_EOL;
        echo "  php migrate.php status" . PHP_EOL;
        echo PHP_EOL;
        break;
}
