<?php
session_start();
require_once __DIR__ . "/vendor/autoload.php";
//include_once __DIR__ . "/vendor/google/apiclient/examples/templates/base.php";
require_once __DIR__ . "/vendor/google/apiclient/src/Google/autoload.php";


// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
require_once __DIR__ . '/WorkWithFile.php';
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!1

use Google\Spreadsheet\DefaultServiceRequest;
use Google\Spreadsheet\ServiceRequestFactory;

$client_id = '731304622813-7dqu772cehhj2qufojg5s8sg5t32gg36.apps.googleusercontent.com';
$service_account_name = '731304622813-7dqu772cehhj2qufojg5s8sg5t32gg36@developer.gserviceaccount.com';  // email address
$key_file_location = __DIR__ . "/keys/P12/spreadsheet-data-updating-429d6c668fc1.p12"; //key.p12
$key = file_get_contents($key_file_location);
//!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!

function getFile($pathToFile){
    $headers = get_headers($pathToFile);
    if (strpos($headers[0], '200')){
        $fileContent = file($pathToFile);
        array_pop($fileContent);
        return $fileContent;
    } else {
        user_error("Invalid value: $pathToFile for path to file");
    }
}
//var_dump($_REQUEST); die;
//$pathToFile = 'http://sorting.local/test.csv';
if (isset($_REQUEST['url'])){
    $pathToFile = $_REQUEST['url'];
} else {
    user_error('Value: ' . $_REQUEST['url'] .  'is absend');
}
$fileContent = getFile($pathToFile);
$numberOfRowsInNewWorksheet = count($fileContent);
$numberOfColumnsInNewWorksheet = '';
if (isset($fileContent[0])){
    $dataInRow = explode(';', trim($fileContent[0]));
    $numberOfColumnsInNewWorksheet = count($dataInRow);
}
//var_dump($numberOfColumnsInNewWorksheet); die;
//var_dump(count($fileContent)); die;
//!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!

/**
 * @param $cellFeed
 * @param $i
 * @param $j
 * @param $batchRequest
 */
function addEntryToBatch($cellFeed, $i, $j, $batchRequest, $content)
{
    $input = $cellFeed->createInsertionCell($i, $j, $content);
    $batchRequest->addEntry($input);
}



/**
 * Build the client object
 */
$client = new Google_Client();
$client->setApplicationName("my-google-spreadsheet-data-updating");
//$client->setDeveloperKey("AIzaSyA4BK1O_pz9cVKk-uvDiHmZOp5mIC-Gbvw");

$credentials = new Google_Auth_AssertionCredentials(
    $service_account_name,
    array('https://spreadsheets.google.com/feeds'),
    $key
);

$client->setAssertionCredentials($credentials);
if ($client->getAuth()->isAccessTokenExpired()){
    $client->getAuth()->refreshTokenWithAssertion($credentials);
}

$_SESSION['service_token'] = $client->getAccessToken();
// Build the service object
$resultArray = json_decode($_SESSION['service_token']);
$accessToken = $resultArray->access_token;


/**
 * Calling an API
 */

// Bootstrapping (initialize the service request factory)
$serviceRequest = new DefaultServiceRequest($accessToken);
ServiceRequestFactory::setInstance($serviceRequest);


// Adding a list row
$spreadsheetService = new Google\Spreadsheet\SpreadsheetService();
$spreadsheetFeed = $spreadsheetService->getSpreadsheets();
$spreadsheet = $spreadsheetFeed->getByTitle('test');
//$worksheetFeed = $spreadsheet->getWorksheets();
//$worksheet = $worksheetFeed->getByTitle('list_1');

// Create tmp worksheet
$createTmpWorksheet = $spreadsheet->addWorksheet('tmp', 1, 1);


$worksheetFeed = $spreadsheet->getWorksheets();
// Delete old primary worksheet
$oldPrimaryWorksheet = $worksheetFeed->getByTitle('test.csv');
$deleteOldPrimaryWorksheet = $oldPrimaryWorksheet->delete();

// Create new primary worksheet
$spreadsheet->addWorksheet('test.csv', $numberOfRowsInNewWorksheet, $numberOfColumnsInNewWorksheet);
// Delete
$deleteTmpWorksheet = $worksheetFeed->getByTitle('tmp')->delete();

$worksheetFeed = $spreadsheet->getWorksheets();
$newPrimaryWorksheet = $worksheetFeed->getByTitle('test.csv');

//$headers = [
//    "Offer_ID_Reporting",
//    "Offer_ID_View",
//    "Offer_ID_Cart",
//    "Offer_Name",
//    "Category_ID",
//    "Category_Name",
//    "Price",
//    "Image_URL",
//    "Exit_URL",
//    "Default",
//    "Active",
//];

$batchRequest = new \Google\Spreadsheet\Batch\BatchRequest();

$cellFeed = $newPrimaryWorksheet->getCellFeed();



foreach($fileContent as $columns => $dataInColumns){
    $dataInRow = explode(';', trim($dataInColumns));
    foreach($dataInRow as $rows => $dataInCell){
        addEntryToBatch($cellFeed, $columns+1, $rows+1, $batchRequest, $dataInCell);
    }
    if($columns % 1000 == 1){
        $cellFeed->insertBatch($batchRequest);
        $batchRequest = new \Google\Spreadsheet\Batch\BatchRequest();
    }
}




$cellFeed->insertBatch($batchRequest);

// Delete tmp worksheet

die;



/**
 * Handling the result
 */

