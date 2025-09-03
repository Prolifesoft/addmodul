<?php
// Enable error reporting first
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define WHMCS path by traversing up to find configuration.php
$dir = __DIR__;
while ($dir !== '/' && !file_exists($dir . '/configuration.php')) {
    $dir = dirname($dir);
}

if (!file_exists($dir . '/configuration.php')) {
    die("Error: Cannot locate WHMCS installation directory\n");
}

define('WHMCS_PATH', $dir);
define('WHMCS', true);

// Include WHMCS files
require_once WHMCS_PATH . '/init.php';
require_once WHMCS_PATH . '/configuration.php';
require_once __DIR__ . '/hooks.php';

// Verify required functions exist
echo "Checking required functions...\n";
$requiredFunctions = [
    'wordpress_provisioning_cron',
    'process_wordpress_queue'
];

foreach ($requiredFunctions as $function) {
    echo "Checking {$function}... " . (function_exists($function) ? "Found" : "Not found") . "\n";
}

use WHMCS\Database\Capsule;

// Start output
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting cron test at: " . date('Y-m-d H:i:s') . "\n";

try {
    echo "\nExecuting cron...\n";
    wordpress_provisioning_cron();
    echo "Cron execution completed\n";
} catch (Exception $e) {
    echo "Error in cron execution: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

// Check queue status
try {
    echo "\nChecking database connection...\n";
    $pendingInstalls = Capsule::table('mod_wordpress_queue')
        ->where('status', 'pending')
        ->get();
    
    $totalInstalls = Capsule::table('mod_wordpress_queue')->count();
    echo "Total installations in queue: $totalInstalls\n";
    
    echo "\nPending installations:\n";
    foreach ($pendingInstalls as $install) {
        echo "Domain: {$install->domain}\n";
        echo "Status: {$install->status}\n";
        echo "Attempts: {$install->attempts}\n";
        echo "Error: {$install->error_message}\n";
        echo "Install time: " . date('Y-m-d H:i:s', $install->install_time) . "\n";
        echo "Last attempt: " . ($install->last_attempt ? date('Y-m-d H:i:s', $install->last_attempt) : 'Never') . "\n";
        
        // Calculate when this installation will be processed
        $timeSinceInstall = time() - $install->install_time;
        $timeRemaining = 50 - $timeSinceInstall;
        echo "Time since installation: {$timeSinceInstall} seconds\n";
        if ($timeRemaining > 0) {
            echo "Will be processed in: {$timeRemaining} seconds\n";
        } else {
            echo "Ready for processing (waited {$timeSinceInstall} seconds)\n";
        }
        echo "-------------------\n";
    }
} catch (Exception $e) {
    echo "Error checking queue: " . $e->getMessage() . "\n";
}
