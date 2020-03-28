#!/usr/bin/php
<?php

include 'vendor/autoload.php';

use xrobau\Google;
$sourceid = "1QU2ZwKYJaz5_qa_BHq28F6rSAwrzGiZTozZNSGinsoE";

/*
$dest = new Google();
$title = "FreePBX CoVID19";
$service = new \Google_Service_Sheets($dest->getClient());
$spreadsheet = new \Google_Service_Sheets_Spreadsheet([ 'properties' => [ 'title' => $title ] ]); 
$spreadsheet = $service->spreadsheets->create($spreadsheet, [ 'fields' => 'spreadsheetId' ]);
$id = $spreadsheet->spreadsheetId;
$dest->sheetid = $id;
print "Created spreadsheet $id\n";
 */

$src = new Google($sourceid);
var_dump($src->getSheets());

