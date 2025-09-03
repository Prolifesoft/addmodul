<?php

namespace WHMCS\Module\Addon\AddonModule\Admin;

require_once __DIR__ . '/../../../../../init.php';
require_once __DIR__ . '/licensecheck.php';
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

class Controller
{
    public function __construct()
    {
        // Check if the table exists and create it if it doesn't
        if (!Capsule::schema()->hasTable('module_provisioning_details')) {
            Capsule::schema()->create('module_provisioning_details', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('PID')->unsigned();
                $table->string('domainName', 255);
                $table->string('ftpUser', 255);
                $table->string('ftpPass', 255);
            });
        }
    }

    public function index($vars)
    {
        session_start();
        $this->handleFormSubmission($vars);
        $products = $this->getProducts();
        $productOptions = $this->generateProductOptionsHtml($products);
        $tableHtml = $this->generateTableHtml($productOptions);

        return $this->renderPage($tableHtml);
    }

private function handleFormSubmission($vars)
{
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $logFilePath = __DIR__ . '/error_log.log';

        // Handle License Key Submission
        if (isset($_POST['licenseKey'])) {
            $licenseKey = $_POST['licenseKey'];
            if ($licenseKey === '') {
                // License key input is empty, remove the license key from the session
                 $deleteResult =Capsule::table('licenseAdd')->where('id', 1)->delete();
                unset($_SESSION['licenseKey']);
                if ($deleteResult) {
                    error_log("License Key removed from database.\n", 3, $logFilePath);
                } else {
                    error_log("Failed to remove License Key from database.\n", 3, $logFilePath);
                }
                unset($_SESSION['licenseKey']);
                unset($_SESSION['licenseVerified']);
               // error_log("License Key removed.\n", 3, $logFilePath);
            } else {
                // Assuming addonmodule_update_license_key is accessible and takes the license key as a parameter
                $updateResult = \addonmodule_update_license_key($licenseKey);
                error_log("License Key Update: " . json_encode($updateResult) . "\n", 3, $logFilePath);
                
                
            // Verify license key logic
            require_once __DIR__ . '/licensecheck.php'; // Include your verification logic
            $licenseKey = $_POST['licenseKey'];
            $verificationResult = \check_license($licenseKey, $localKey); // Call your verification function
            
            // Handle verification result
            // For example, log the result or store it in the session
            $logFilePath = __DIR__ . '/verification.log'; // Path to your log file
            
           if ($verificationResult['status'] === 'Active') {
                // License is valid
                $_SESSION['licenseVerified'] = true;
                $_SESSION['licenseKeyStatus'] = 'Valid'; // Store verification result in session
                $_SESSION['licenseKeyMessage'] = 'The license key is valid.'; // Message for user feedback
                error_log("License Key Verified: Valid. Key: $licenseKey\n", 3, $logFilePath);
                
            } else {
                // License is not valid
                unset($_SESSION['licenseVerified']); // Ensure this is unset if the license is invalid
                $_SESSION['licenseKeyStatus'] = 'Invalid'; // Store verification result in session
                $_SESSION['licenseKeyMessage'] = 'The license key is invalid. Please try again.'; // Message for user feedback
                error_log("License Key Verified: Invalid. Key: $licenseKey\n", 3, $logFilePath);
            }
            
            
                // Assuming $localKey is defined earlier or as null if not used
            $verificationResult = \check_license($licenseKey, $localKey);
            if ($verificationResult['status'] === 'Active') {
                // License is valid
                $_SESSION['licenseVerified'] = true;
                
            } else {
                // License is not valid
                $_SESSION['notValid'] = false;
            }
                // Store license key in session
               // $_SESSION['licenseKey'] = $licenseKey;
               unset($_SESSION['licenseKey']);
            }
            
            } 
            

        if (isset($_POST['removeIndex']) && isset($_POST['domainName'])) {
            error_log("Error: Conflicting form submission received.\n", 3, $logFilePath);
            return;
        }

        if (isset($_POST['removeIndex'])) {
            $idToRemove = intval($_POST['removeIndex']);
            $deleted = Capsule::table('module_provisioning_details')->where('id', $idToRemove)->delete();
            if ($deleted) {
                error_log("Successfully removed entry with ID $idToRemove.\n", 3, $logFilePath);
            } else {
                error_log("Failed to remove entry with ID $idToRemove. Entry may not exist.\n", 3, $logFilePath);
            }
        } elseif (isset($_POST['domainName'])) {
            $productExists = Capsule::table('module_provisioning_details')->where('PID', $_POST['product'])->exists();

            if ($productExists) {
                error_log("Error: Product ID {$_POST['product']} has already been added. Only one product of each type can be added.\n", 3, $logFilePath);
            } else {
                try {
                    Capsule::table('module_provisioning_details')->insert([
                        'PID' => $_POST['product'],
                        'domainName' => $_POST['domainName'],
                        'ftpUser' => $_POST['ftpUser'],
                        'ftpPass' => $_POST['ftpPass'],
                    ]);
                    error_log("Successfully added new entry: {$_POST['domainName']}.\n", 3, $logFilePath);
                } catch (Exception $e) {
                    error_log("Failed to add new entry: {$_POST['domainName']}. Error: {$e->getMessage()}.\n", 3, $logFilePath);
                }
            }
        }
        $this->redirect($vars);
    }
}
    private function getProducts()
    {
        $results = localAPI('GetProducts', []);
        return $results['result'] == 'success' ? $results['products']['product'] : [];
    }

    private function generateProductOptionsHtml($products)
    {
        $optionsHtml = "<option value=''>Select a Product</option>";
        foreach ($products as $product) {
            $optionsHtml .= "<option value='{$product['pid']}'>{$product['name']}</option>";
        }
        return $optionsHtml;
    }

    private function generateTableHtml($productOptions)
    {
        $entries = Capsule::table('module_provisioning_details')->get();
        $entriesRows = '';
        foreach ($entries as $entry) {
            $productName = "Unknown Product";
            foreach ($this->getProducts() as $product) {
                if ($product['pid'] == $entry->PID) {
                    $productName = $product['name'];
                    break;
                }
            }

            $entriesRows .= "<tr>" .
                "<td>" . htmlspecialchars($entry->domainName) . "</td>" .
                "<td>" . htmlspecialchars($entry->ftpUser) . "</td>" .
                "<td>" . htmlspecialchars($entry->ftpPass) . "</td>" .
                "<td>" . htmlspecialchars($productName) . "</td>" .
                "<td><form method='post'><input type='hidden' name='removeIndex' value='{$entry->id}'><input type='submit' value='Remove'></form></td>" .
                "</tr>";
        }

        
         $licenseVerificationStatus = '';
        if (isset($_SESSION['licenseVerified'])) {
            $licenseVerificationStatus = "<span style='color: green; font-weight: bold;'> &#10004; License Verified</span>";
        }

    $licenseKeyResult = Capsule::table('licenseAdd')->select('license_key')->first();
    $licenseKeyValue = $licenseKeyResult ? htmlspecialchars($licenseKeyResult->license_key) : '';
    
    $licenseKeyRow = "<div class='row' style='margin-bottom: 20px;'>" .
                     "<div class='col-md-4'>" .
                     "<form method='post'>" .
                     "<div class='form-group'>" .
                     "<input type='text' class='form-control mb-2' id='licenseKey' name='licenseKey' placeholder='Enter License' value='{$licenseKeyValue}'>" .
                     "</div>" .
                     "<div class='form-group'>" .
                     "<button type='submit' class='btn btn-primary btn-block'>Save License</button>" .
                     "</div>" .
                     "</form>" .
                     "</div>" .
                     "<div class='col-md-8' style='padding-top: 8px;'>" .
                     $licenseVerificationStatus . // Display license verification status here
                     "</div>" .
                     "</div>";
                         
        $licenseKeyVerify = "<tr>" .
                 "<form method='post'>" .
                 "<td colspan='4'><input type='submit' name='verifyLicense' value='Verify License'></td>" .
                 "</form>" .
                 "</tr>";

        $formRow = "<tr>" .
                   "<form method='post'>" .
                   "<td><input type='text' name='domainName' placeholder='Domain Name' required></td>" .
                   "<td><input type='text' name='ftpUser' placeholder='FTP User' required></td>" .
                   "<td><input type='text' name='ftpPass' placeholder='FTP Password' required></td>" .
                   "<td><select name='product'>{$productOptions}</select></td>" .
                   "<td><input type='submit' value='Add'></td>" .
                   "</form>" .
                   "</tr>";

        $tableHtml ="<div><h1>Add your license Key<h1></div>".
                    "<div>{$licenseKeyRow}</div>.
                    <div>
                    <blockquote>Note : Make sure to keep your wordpress template username as 'admin'<br>
                    </blockquote><br><br><br></div>" . // Display license key row above the table
                    "<h2>Map Wordpress template with WHMCS Product </h2>".
                     "<table border='1' style='width: 100%; border-collapse: collapse;'>" .
                     "<thead><tr><th>Domain Name</th><th>FTP User</th><th>Password</th><th>Product</th><th>Action</th></tr></thead>" .
                     "<tbody>" .
                     "{$entriesRows}" .
                     "{$formRow}" . // Existing form row for adding new entries
                     "</tbody></table>";
        
        return $tableHtml;
    }

private function renderPage($tableHtml)
{
    return <<<HTML
    <style>
        input[type=text], select, button { 
            width: 100%; 
            padding: 8px; 
            margin: 4px 0; 
            display: inline-block; 
            border: 1px solid #ccc; 
            border-radius: 4px; 
            box-sizing: border-box; 
        }
        .btn-custom-size {
        padding: 8px 12px; /* Adjust padding to control the height */
    }
        table { 
            border-collapse: collapse; 
            width: 100%; 
        }
        th, td { 
            text-align: left; 
            padding: 8px; 
        }
        tr:nth-child(even) { 
            background-color: #f2f2f2; 
        }
        .form-inline .form-group { 
            margin-right: 10px; 
        }
        .btn-primary {
            color: #fff;
            background-color: #007bff;
            border-color: #007bff;
        }
        .btn-primary:hover {
            color: #fff;
            background-color: #0069d9;
            border-color: #0062cc;
        }
        .container {
            padding-top: 20px;
        }
    </style>
    <div class='container'>
        
        
        $tableHtml
    </div>
HTML;
}

    private function redirect($vars)
    {
        header('Location: ' . $_SERVER['PHP_SELF'] . '?module=' . $vars['module']);
        exit;
    }
}
