<?php
// modules/addons/wordpress_provisioning/wordpress_provisioning.php

use Illuminate\Database\Capsule\Manager as Capsule;

if (!defined('WHMCS')) {
    die('You cannot access this file directly.');
}

function wordpress_provisioning_install() {
    try {
        Capsule::schema()->create('mod_wordpress_queue', function ($table) {
            $table->increments('id');
            $table->string('domain');
            $table->string('username');
            $table->string('password');
            $table->string('ftp_user');
            $table->string('ftp_pass');
            $table->integer('install_time');
            $table->string('status', 20);
            $table->integer('attempts')->default(0);
            $table->integer('last_attempt')->nullable();
            $table->text('error_message')->nullable();
            $table->string('insid')->nullable();
            $table->timestamps();
        });
        return [
            'status' => 'success',
            'description' => 'WordPress Provisioning module installed successfully',
        ];
    } catch (\Exception $e) {
        return [
            'status' => "error",
            'description' => 'Unable to create mod_wordpress_queue: ' . $e->getMessage(),
        ];
    }
}

function perform_softaculous_provisioning($vars, $domainName, $ftpUser, $ftpPass) {
    $productId = $vars['params']['pid'];
    $username = $vars['params']['username'];
    $domain = $vars['params']['domain'];
    $password = $vars['params']['password'];
    $serviceId = $vars['params']['serviceid'];

    logActivity("Starting WordPress installation for domain: {$domain}");

    // Start Import
    $url = "https://{$username}:{$password}@{$domain}:2083/frontend/jupiter/softaculous/index.live.php?" .
           "&api=serialize" .
           "&act=import" .
           "&soft=26";

    $post = array(
        'remote_submit' => '1',
        'domain' => $domainName,
        'protocol' => 'ftp',
        'port' => '21',
        'ftp_user' => $ftpUser,
        'ftp_pass' => $ftpPass,
        'ftp_path' => '/public_html',
        'softdomain' => $domain,
        'softdb' => 'auto',
        'softproto' => 3,
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));

    $resp = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    logActivity("Import HTTP Response Code: " . $httpCode);

    if ($resp === false) {
        logActivity("cURL error during Softaculous request for domain {$domain}: {$curlError}");
        curl_close($ch);
        return;
    }

    curl_close($ch);

    $data = @unserialize($resp);
    if ($data === false) {
        logActivity("Invalid Softaculous response for domain {$domain}: " . print_r($resp, true));
        return;
    }

    if (isset($data['error'])) {
        logActivity("Import Error for domain {$domain}: " . print_r($data['error'], true));
        return;
    } elseif (isset($data['done'])) {
        // Add to queue for password change
        Capsule::table('mod_wordpress_queue')->insert([
            'domain' => $domain,
            'username' => $username,
            'password' => $password,
            'ftp_user' => $ftpUser,
            'ftp_pass' => $ftpPass,
            'install_time' => time(),
            'status' => 'pending',
            'attempts' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        logActivity("WordPress installation queued for domain: {$domain}");
    } else {
        logActivity("Unexpected Softaculous response for domain {$domain}: " . print_r($resp, true));
    }
}

function process_wordpress_queue() {
    // Remove failed installations that have attempted 3 times
    Capsule::table('mod_wordpress_queue')
        ->where('status', 'pending')
        ->where('attempts', '>=', 3)
        ->whereNotNull('error_message')
        ->delete();

    $pendingInstalls = Capsule::table('mod_wordpress_queue')
        ->where('status', 'pending')
        ->where('install_time', '<', time() - 50) // Only process items older than 50 seconds
        ->where('attempts', '<', 3)
        ->get();

    foreach ($pendingInstalls as $install) {
        logActivity("Processing queued installation for domain: {$install->domain}");

        // Get installation ID first
        $listUrl = "https://{$install->username}:{$install->password}@{$install->domain}:2083/frontend/jupiter/softaculous/index.live.php?" .
                   "&api=serialize" .
                   "&act=installations";

        $chList = curl_init();
        curl_setopt($chList, CURLOPT_URL, $listUrl);
        curl_setopt($chList, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($chList, CURLOPT_TIMEOUT, 60);
        curl_setopt($chList, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($chList, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($chList, CURLOPT_SSL_VERIFYHOST, FALSE);

        $listResp = curl_exec($chList);
        curl_close($chList);

        $listData = unserialize($listResp);
        $insid = null;

        if (!empty($listData['installations'])) {
            foreach ($listData['installations'] as $software) {
                foreach ($software as $installation) {
                    if ($installation['softdomain'] === $install->domain) {
                        $insid = $installation['insid'];
                        break 2;
                    }
                }
            }
        }

        if (!$insid) {
            Capsule::table('mod_wordpress_queue')
                ->where('id', $install->id)
                ->update([
                    'attempts' => $install->attempts + 1,
                    'last_attempt' => time(),
                    'error_message' => 'Could not find installation ID',
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            continue;
        }

        // Change WordPress password
        $editUrl = "https://{$install->username}:{$install->password}@{$install->domain}:2083/frontend/jupiter/softaculous/index.live.php?" .
                  "&api=serialize" .
                  "&act=wordpress" .
                  "&insid={$insid}";

        $editPost = array(
            'admin_password' => $install->password,
            'admin_username' => 'admin',
            'insid' => $insid,
            'save_admin_info' => '1'
        );

        $chEdit = curl_init();
        curl_setopt($chEdit, CURLOPT_URL, $editUrl);
        curl_setopt($chEdit, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($chEdit, CURLOPT_TIMEOUT, 60);
        curl_setopt($chEdit, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($chEdit, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($chEdit, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($chEdit, CURLOPT_POST, 1);
        curl_setopt($chEdit, CURLOPT_POSTFIELDS, http_build_query($editPost));

        $editResp = curl_exec($chEdit);
        curl_close($chEdit);

        $editData = unserialize($editResp);

        if (isset($editData['error'])) {
            Capsule::table('mod_wordpress_queue')
                ->where('id', $install->id)
                ->update([
                    'attempts' => $install->attempts + 1,
                    'last_attempt' => time(),
                    'error_message' => implode(", ", $editData['error']),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
        } elseif (isset($editData['done'])) {
            Capsule::table('mod_wordpress_queue')
                ->where('id', $install->id)
                ->delete();
            logActivity("Password changed successfully for domain: {$install->domain}");
        } else {
            Capsule::table('mod_wordpress_queue')
                ->where('id', $install->id)
                ->update([
                    'attempts' => $install->attempts + 1,
                    'last_attempt' => time(),
                    'error_message' => 'Unexpected response from server',
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
        }
    }
}

function wordpress_provisioning_cron() {
    process_wordpress_queue();
}

function after_module_create($vars) {
    $productId = $vars['params']['pid'];
    $productDetail = Capsule::table('module_provisioning_details')
                          ->where('pid', $productId)
                          ->first();
                            
    if ($productDetail) {
        perform_softaculous_provisioning(
            $vars, 
            $productDetail->domainName, 
            $productDetail->ftpUser, 
            $productDetail->ftpPass
        );
    } else {
        logActivity("No provisioning details found for productId: $productId");
    }
}

// Register hooks
add_hook('AfterModuleCreate', 1, 'after_module_create');
// Register for minute, regular, and daily cron jobs to ensure frequent processing
add_hook('MinuteCron', 1, 'wordpress_provisioning_cron');  // Runs every minute
// add_hook('CronJob', 1, 'wordpress_provisioning_cron');     // Backup hook
// add_hook('DailyCronJob', 1, 'wordpress_provisioning_cron'); // Additional backup
