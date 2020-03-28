#!/usr/bin/php
<?php

include __DIR__ . '/../vendor/autoload.php';

if (!file_exists(__DIR__ . "/../data/sheetid.json")) {
    throw new \Exception("I don't have a sheetid");
}
$tmp = json_decode(file_get_contents(__DIR__ . "/../data/sheetid.json"), true);
$sheetid = $tmp['sheetid'];

use xrobau\Google;

$g = new Google($sheetid);

$client = $g->getClient();
