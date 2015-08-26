<?php
session_start();
require_once __DIR__ . "/vendor/autoload.php";
require_once __DIR__ . "/vendor/google/apiclient/src/Google/autoload.php";

use Google\Spreadsheet\DefaultServiceRequest;
use Google\Spreadsheet\ServiceRequestFactory;

/**
 * @param $pathToFile
 * @return array
 */
function getFile($pathToFile){
    $headers = get_headers($pathToFile);
    if (strpos($headers[0], '200')){
        $fileContent = file($pathToFile);
        array_pop($fileContent);
        return $fileContent;
    } else {
        user_error("Invalid value: $pathToFile for path to file");
        exit;
    }
}

/**
 * @param $cellFeed
 * @param $i
 * @param $j
 * @param $batchRequest
 * @param $content
 */
function addEntryToBatch($cellFeed, $i, $j, $batchRequest, $content)
{
    $input = $cellFeed->createInsertionCell($i, $j, $content);
    $batchRequest->addEntry($input);
}


$client_id = '731304622813-7dqu772cehhj2qufojg5s8sg5t32gg36.apps.googleusercontent.com';
$service_account_name = '731304622813-7dqu772cehhj2qufojg5s8sg5t32gg36@developer.gserviceaccount.com';  // email address
$key_file_location = __DIR__ . "/keys/P12/spreadsheet-data-updating-429d6c668fc1.p12"; //key.p12
$key = file_get_contents($key_file_location);


if (isset($_REQUEST['url'])){
    $pathToFile = $_REQUEST['url'];
} else {
    user_error('Value: $_REQUEST[\'url\'] is absent');
    exit;
}
$fileContent = getFile($pathToFile);
$numberOfRowsInNewWorksheet = count($fileContent);
$numberOfColumnsInNewWorksheet = '';
if (isset($fileContent[0])){
    $separator = strpos($fileContent[0], ';') ? ';' : ',';
    $dataInRow = explode($separator, trim($fileContent[0]));
    $numberOfColumnsInNewWorksheet = count($dataInRow);
} else {
    user_error('Can not get file');
}


/**
 * Build the client object
 */
$client = new Google_Client();
$client->setApplicationName("my-google-spreadsheet-data-updating");
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

// Create tmp worksheet
$createTmpWorksheet = $spreadsheet->addWorksheet('tmp', 1, 1);
$worksheetFeed = $spreadsheet->getWorksheets();

// Delete old primary worksheet
$worksheetFeed = $spreadsheet->getWorksheets();
$oldPrimaryWorksheet = $worksheetFeed->getByTitle('test.csv');
$deleteOldPrimaryWorksheet = $oldPrimaryWorksheet->delete();

// Create new primary worksheet
$spreadsheet->addWorksheet('test.csv', $numberOfRowsInNewWorksheet, $numberOfColumnsInNewWorksheet);

// Delete tmp worksheet
$deleteTmpWorksheet = $worksheetFeed->getByTitle('tmp')->delete();

// Work with new primary worksheet
$worksheetFeed = $spreadsheet->getWorksheets();
$newPrimaryWorksheet = $worksheetFeed->getByTitle('test.csv');
/**
 * Handling the result
 */
// Save data in cells
$batchRequest = new \Google\Spreadsheet\Batch\BatchRequest();
$cellFeed = $newPrimaryWorksheet->getCellFeed();
foreach($fileContent as $rows => $dataInRows){
    $dataInRow = explode($separator, trim($dataInRows));
    foreach($dataInRow as $cells => $dataInCell){
        addEntryToBatch($cellFeed, $rows+1, $cells+1, $batchRequest, $dataInCell);
    }
    if($rows % 1000 == 1){
        $cellFeed->insertBatch($batchRequest);
        $batchRequest = new \Google\Spreadsheet\Batch\BatchRequest();
    }
}
$cellFeed->insertBatch($batchRequest);

echo '<h2>SUCCESS!!!</h2>';
die;