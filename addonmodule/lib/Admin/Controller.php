<?php

namespace WHMCS\Module\Addon\AddonModule\Admin;

require_once __DIR__ . '/../../../../../init.php';
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

        

        $formRow = "<tr>" .
                   "<form method='post'>" .
                   "<td><input type='text' name='domainName' placeholder='Domain Name' required></td>" .
                   "<td><input type='text' name='ftpUser' placeholder='FTP User' required></td>" .
                   "<td><input type='text' name='ftpPass' placeholder='FTP Password' required></td>" .
                   "<td><select name='product'>{$productOptions}</select></td>" .
                   "<td><input type='submit' value='Add'></td>" .
                   "</form>" .
                   "</tr>";

        $tableHtml ="<div><blockquote>Note : Make sure to keep your wordpress template username as 'admin'<br></blockquote><br><br><br></div>" .
                    "<h2>Map Wordpress template with WHMCS Product </h2>" .
                    "<table border='1' style='width: 100%; border-collapse: collapse;'>" .
                    "<thead><tr><th>Domain Name</th><th>FTP User</th><th>Password</th><th>Product</th><th>Action</th></tr></thead>" .
                    "<tbody>" .
                    "{$entriesRows}" .
                    "{$formRow}" .
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
