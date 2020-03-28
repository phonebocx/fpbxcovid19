<?php

include 'vendor/autoload.php';
use xrobau\Google;

if (!file_exists(__DIR__ . "/data/sheetid.json")) {
	throw new \Exception("I don't have a sheetid");
}
$tmp = json_decode(file_get_contents(__DIR__ . "/data/sheetid.json"), true);
$sheetid = $tmp['sheetid'];

/* $pubid = "1PANUnrrUTvoQY3ju7mZFcf5Aqn1fhjtK0OikROVchvs";
$pubid = "2PACX-1vTcJ217cMwTn9XQGfmG0NWRUiJdJyB89PCT6qrbs-nWQd54ygCz86PuSm6tGvlEishmxLouZpRG1bqv";
$g = new Google($pubid);
var_dump($g->getSheets());
exit;
 */

$g = new Google();

$title = "Test Sheet";

$service = new \Google_Service_Sheets($g->getClient());
$spreadsheet = new \Google_Service_Sheets_Spreadsheet([
	    'properties' => [ 'title' => $title ] ]);

$spreadsheet = $service->spreadsheets->create($spreadsheet, [ 'fields' => 'spreadsheetId' ]);
printf("Spreadsheet ID: %s\n", $spreadsheet->spreadsheetId);

exit;


$opts = getopt("d", ["debug"]);
$cols = ["name", "exten", "mobile", "ringboth", "delay", "start", "end", "enabled", "current", "lastupdate"];

$sheet = $g->getSheet('Extensions');

$data = $g->getVal($sheet, 'A:G');

foreach ($data as $num => $row) {
	if ($num == 0) {
		continue;
	}
	$parsed = parseRow($row, $opts);
	if ($parsed['current'] != $parsed['shouldbe']) {
		if ($parsed['shouldbe'] == 'Active') {
			enableRedirect($parsed);
		} else {
			disableRedirect($parsed);
		}
		updateSheet($num + 1, $parsed);
	}
	exit;
}


function parseRow($row, $opts)
{
	global $cols;
	$retarr = ["valid" => true];
	foreach ($cols as $n => $k) {
		$retarr[$k] = isset($row[$n]) ? $row[$n] : "";
	}

	try {
		$startdt = new \DateTime(date('Y-m-d ' . $retarr['start']));
	} catch (\Exception $e) {
		$startdt = false;
	}

	try {
		$enddt = new \DateTime(date('Y-m-d ' . $retarr['end']));
	} catch (\Exception $e) {
		$enddt = false;
	}

	if (!$startdt || !$enddt) {
		$retarr["valid"] = false;
		$retarr["status"] = "Invalid start or end";
		return $retarr;
	}

	// If START is after END, it's overnight, so add a day to end
	if ($startdt > $enddt) {
		$enddt->add(new \DateInterval('P1D'));
	}

	$now = new \DateTimeImmutable();

	// If now is between startdt and enddt, then we should be active.
	if ($now > $startdt && $now < $enddt) {
		$retarr['shouldbe'] = "Active";
	} else {
		$retarr['shouldbe'] = "Inactive";
	}

	$enabled = strtolower($retarr['enabled']);
	if (!$enabled || $enabled == "no" || $enabled == "false") {
		$retarr['shouldbe'] = "Inactive";
	}

	return $retarr;
}

function enableRedirect($row)
{
	var_dump($row);
	print "I am an enabler\n";
}
function disableRedirect($row)
{
	var_dump($row);
	print "I am a DISABLER\n";
}
function updateSheet($rownum, $data)
{
	print "I want to update row number $rownum\n";
}
