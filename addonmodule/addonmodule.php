<?php
/**
 * WHMCS SDK Sample Addon Module
 *
 * An addon module allows you to add additional functionality to WHMCS. It
 * can provide both client and admin facing user interfaces, as well as
 * utilise hook functionality within WHMCS.
 *
 * This sample file demonstrates how an addon module for WHMCS should be
 * structured and exercises all supported functionality.
 *
 * Addon Modules are stored in the /modules/addons/ directory. The module
 * name you choose must be unique, and should be all lowercase, containing
 * only letters & numbers, always starting with a letter.
 *
 * Within the module itself, all functions must be prefixed with the module
 * filename, followed by an underscore, and then the function name. For this
 * example file, the filename is "addonmodule" and therefore all functions
 * begin "addonmodule_".
 *
 * For more information, please refer to the online documentation.
 *
 * @see https://developers.whmcs.com/addon-modules/
 *
 * @copyright Copyright (c) WHMCS Limited 2017
 * @license http://www.whmcs.com/license/ WHMCS Eula
 */

/**
 * Require any libraries needed for the module to function.
 * require_once __DIR__ . '/path/to/library/loader.php';
 *
 * Also, perform any initialization required by the service's library.
 */

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\AddonModule\Admin\AdminDispatcher;
use WHMCS\Module\Addon\AddonModule\Client\ClientDispatcher;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Define addon module configuration parameters.
 *
 * Includes a number of required system fields including name, description,
 * author, language and version.
 *
 * Also allows you to define any configuration parameters that should be
 * presented to the user when activating and configuring the module. These
 * values are then made available in all module function calls.
 *
 * Examples of each and their possible configuration parameters are provided in
 * the fields parameter below.
 *
 * @return array
 */
function addonmodule_config()
{
    return [
        // Display name for your module
        'name' => 'WHMCS Wordpress Website Builder',
        // Description displayed within the admin interface
        'description' => 'This module turn your whmcs system into a wordpress website builder'
            . ' You can create wordpress website template and sell it to your customer with hosting.',
        // Module author name
        'author' => 'SmartingGoods',
        // Default language
        'language' => 'english',
        // Version number
        'version' => '1.1',
    
    ];
}

/**
 * Activate.
 *
 * Called upon activation of the module for the first time.
 * Use this function to perform any database and schema modifications
 * required by your module.
 *
 * This function is optional.
 *
 * @see https://developers.whmcs.com/advanced/db-interaction/
 *
 * @return array Optional success/failure message
 */
function addonmodule_activate() {
    try {
        // Create license table
        if (!Capsule::schema()->hasTable('licenseAdd')) {
            Capsule::schema()->create('licenseAdd', function ($table) {
                $table->integer('id')->default(1);
                $table->string('license_key')->nullable();
                $table->string('local_key')->nullable();
            });
        }

        // Create WordPress queue table
        if (!Capsule::schema()->hasTable('mod_wordpress_queue')) {
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
        }

        return [
            'status' => 'success',
            'description' => 'Module activated successfully. Please update the license key in the module settings.',
        ];
    } catch (\Exception $e) {
        return [
            'status' => "error",
            'description' => 'Unable to create the table: ' . $e->getMessage(),
        ];
    }
}

/**
 * Deactivate.
 *
 * Called upon deactivation of the module.
 * Use this function to undo any database and schema modifications
 * performed by your module.
 *
 * This function is optional.
 *
 * @see https://developers.whmcs.com/advanced/db-interaction/
 *
 * @return array Optional success/failure message
 */
function addonmodule_deactivate()
{
    try {
        $message = [];
        
        // Remove WordPress queue table
        if (Capsule::schema()->hasTable('mod_wordpress_queue')) {
            Capsule::schema()->dropIfExists('mod_wordpress_queue');
            $message[] = 'WordPress queue table removed';
        }

        // Remove license table
        if (Capsule::schema()->hasTable('licenseAdd')) {
            Capsule::schema()->dropIfExists('licenseAdd');
            $message[] = 'License data removed';
        }

        if (empty($message)) {
            return [
                'status' => 'success',
                'description' => 'Module deactivated successfully. No tables needed removal.',
            ];
        }

        return [
            'status' => 'success',
            'description' => 'Module deactivated successfully. ' . implode(', ', $message) . '.',
        ];
    } catch (\Exception $e) {
        return [
            'status' => "error",
            'description' => "An error occurred while attempting to remove the license data: {$e->getMessage()}",
        ];
    }
}

/**
 * Helper function to update or insert the license key.
 * This function should be called with the actual license key value at the appropriate place,
 * such as from a custom admin interface within your module.
 */
function addonmodule_update_license_key($licenseKey) {
    try {
        // Validate the license key if necessary
        if (!empty($licenseKey)) {
            // Assuming you want to update the first row or insert if the table is empty.
            $existing = Capsule::table('licenseAdd')->first();
            if ($existing) {
                Capsule::table('licenseAdd')->where('id', $existing->id)->update(['license_key' => $licenseKey]);
            } else {
                Capsule::table('licenseAdd')->insert(['license_key' => $licenseKey]);
            }
            return ['status' => 'success', 'description' => 'License key updated successfully.'];
        } else {
            return ['status' => 'error', 'description' => 'License key is empty.'];
        }
    } catch (\Exception $e) {
        return ['status' => 'error', 'description' => 'Error updating the license key: ' . $e->getMessage()];
    }
}

/**
 * Upgrade.
 *
 * Called the first time the module is accessed following an update.
 * Use this function to perform any required database and schema modifications.
 *
 * This function is optional.
 *
 * @see https://laravel.com/docs/5.2/migrations
 *
 * @return void
 */
function addonmodule_upgrade($vars)
{
    $currentlyInstalledVersion = $vars['version'];

    // Handle upgrades based on version
    if ($currentlyInstalledVersion < 1.1) {
        try {
            // Log upgrade attempt
            logActivity("Upgrading module from version " . $currentlyInstalledVersion . " to 1.1");
        } catch (\Exception $e) {
            // Log error but don't prevent upgrade
            logActivity("Error during upgrade to v1.1: " . $e->getMessage());
        }
    }
}

/**
 * Admin Area Output.
 *
 * Called when the addon module is accessed via the admin area.
 * Should return HTML output for display to the admin user.
 *
 * This function is optional.
 *
 * @see AddonModule\Admin\Controller::index()
 *
 * @return string
 */
function addonmodule_output($vars)
{
    // Get common module parameters
    $modulelink = $vars['modulelink']; // eg. addonmodules.php?module=addonmodule
    $version = $vars['version']; // eg. 1.0
    $_lang = $vars['_lang']; // an array of the currently loaded language variables

    // Get module configuration parameters
    $configTextField = $vars['Text Field Name'];
    $configPasswordField = $vars['Password Field Name'];
    $configCheckboxField = $vars['Checkbox Field Name'];
    $configDropdownField = $vars['Dropdown Field Name'];
    $configRadioField = $vars['Radio Field Name'];
    $configTextareaField = $vars['Textarea Field Name'];

    // Dispatch and handle request here. What follows is a demonstration of one
    // possible way of handling this using a very basic dispatcher implementation.

    $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

    $dispatcher = new AdminDispatcher();
    $response = $dispatcher->dispatch($action, $vars);
    echo $response;
}

/**
 * Admin Area Sidebar Output.
 *
 * Used to render output in the admin area sidebar.
 * This function is optional.
 *
 * @param array $vars
 *
 * @return string
 */
function addonmodule_sidebar($vars)
{
    // Get common module parameters
    $modulelink = $vars['modulelink'];
    $version = $vars['version'];
    $_lang = $vars['_lang'];

    // Get module configuration parameters
    $configTextField = $vars['Text Field Name'];
    $configPasswordField = $vars['Password Field Name'];
    $configCheckboxField = $vars['Checkbox Field Name'];
    $configDropdownField = $vars['Dropdown Field Name'];
    $configRadioField = $vars['Radio Field Name'];
    $configTextareaField = $vars['Textarea Field Name'];

    $sidebar = '<p>Thank you for supporting our software. you can buy another license key <a href="https://billing.smartinggoods.com/index.php/store/licensing" target="_blank">here</a>. </p>';
    
    return $sidebar;
}

/**
 * Client Area Output.
 *
 * Called when the addon module is accessed via the client area.
 * Should return an array of output parameters.
 *
 * This function is optional.
 *
 * @see AddonModule\Client\Controller::index()
 *
 * @return array
 */
function addonmodule_clientarea($vars)
{
    // Get common module parameters
    $modulelink = $vars['modulelink']; // eg. index.php?m=addonmodule
    $version = $vars['version']; // eg. 1.0
    $_lang = $vars['_lang']; // an array of the currently loaded language variables

    // Get module configuration parameters
    $configTextField = $vars['Text Field Name'];
    $configPasswordField = $vars['Password Field Name'];
    $configCheckboxField = $vars['Checkbox Field Name'];
    $configDropdownField = $vars['Dropdown Field Name'];
    $configRadioField = $vars['Radio Field Name'];
    $configTextareaField = $vars['Textarea Field Name'];

    /**
     * Dispatch and handle request here. What follows is a demonstration of one
     * possible way of handling this using a very basic dispatcher implementation.
     */

    $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

    $dispatcher = new ClientDispatcher();
    return $dispatcher->dispatch($action, $vars);
}
