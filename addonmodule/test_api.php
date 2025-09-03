<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define WHMCS path
define('WHMCS_PATH', '/home/smartinggoods/public_html');
define('WHMCS', true);

// Include WHMCS files
require_once WHMCS_PATH . '/init.php';
require_once WHMCS_PATH . '/configuration.php';

use WHMCS\Database\Capsule;

function test_softaculous_api($domain) {
    // Get credentials from queue
    $install = Capsule::table('mod_wordpress_queue')
        ->where('domain', $domain)
        ->first();

    if (!$install) {
        echo "Domain not found in queue\n";
        return;
    }

    echo "Testing API connection for {$domain}\n";
    echo "Username: {$install->username}\n";

    // Test basic cPanel authentication
    $cpanelUrl = "https://{$install->domain}:2083/login/?user={$install->username}&pass={$install->password}";
    echo "\nTesting cPanel connection...\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $cpanelUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    echo "cPanel HTTP Response Code: {$httpCode}\n";
    if ($error) {
        echo "cURL Error: {$error}\n";
    }

    // Test Softaculous API
    $listUrl = sprintf(
        "https://%s:%s@%s:2083/frontend/jupiter/softaculous/index.live.php?api=serialize&act=installations",
        urlencode($install->username),
        urlencode($install->password),
        $install->domain
    );
    
    echo "\nTesting Softaculous API...\n";
    echo "URL: {$listUrl}\n";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $listUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($ch, CURLOPT_USERPWD, $install->username . ":" . $install->password);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: */*'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    echo "Softaculous HTTP Response Code: {$httpCode}\n";
    if ($error) {
        echo "cURL Error: {$error}\n";
    }
    echo "Raw Response:\n";
    echo $response . "\n";

    // Try to parse response
    $data = @unserialize($response);
    if ($data === false) {
        echo "Failed to unserialize response\n";
    } else {
        echo "Response parsed successfully\n";
        print_r($data);
    }
}

// Test for specific domain
if ($argc > 1) {
    test_softaculous_api($argv[1]);
} else {
    echo "Usage: php test_api.php domain.com\n";
}
