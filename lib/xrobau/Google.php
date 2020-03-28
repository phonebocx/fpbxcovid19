<?php
// vim:set sw=4 ts=4 et ft=php fdm=marker:

namespace xrobau;

class Google
{

    public $sheetid = false;

    public static $sheetcache;

    private $pending = [];
    private $updates = [];
    private $ranges = [];

    public function __construct($sheetid = false)
    {
        $this->sheetid = $sheetid;
    }

    public function getClient()
    {
        $client = new \Google_Client();
        $client->setApplicationName('FreePBX COVID19 Helper');
        $client->setScopes([ \Google_Service_Sheets::DRIVE_FILE ]);
        $client->setAuthConfig(__DIR__ . '/../../data/credentials.json');
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');

        // Load previously authorized token from a file, if it exists.
        // The file token.json stores the user's access and refresh tokens, and is
        // created automatically when the authorization flow completes for the first
        // time.
        $tokenPath = __DIR__ . '/../../data/token.json';
        if (file_exists($tokenPath)) {
            $accessToken = json_decode(file_get_contents($tokenPath), true);
            $client->setAccessToken($accessToken);
        }

        // If there is no previous token or it's expired.
        if ($client->isAccessTokenExpired()) {
            // Refresh the token if possible, else fetch a new one.
            if ($client->getRefreshToken()) {
                $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            } else {
                // Request authorization from the user.
                $authUrl = $client->createAuthUrl();
                printf("Open the following link in your browser:\n%s\n", $authUrl);
                print 'Enter verification code: ';
                $authCode = trim(fgets(STDIN));

                // Exchange authorization code for an access token.
                $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
                $client->setAccessToken($accessToken);

                // Check to see if there was an error.
                if (array_key_exists('error', $accessToken)) {
                    throw new \Exception(join(', ', $accessToken));
                }
            }
            // Save the token to a file.
            if (!file_exists(dirname($tokenPath))) {
                mkdir(dirname($tokenPath), 0700, true);
            }
            file_put_contents($tokenPath, json_encode($client->getAccessToken()));
        }
        return $client;
    }

    public function getSheets()
    {
        if (empty(self::$sheetcache[$this->sheetid])) {
            $service = new \Google_Service_Sheets($this->getClient());
            $sheets = $service->spreadsheets->get($this->sheetid)->getSheets();
            $cache = [];
            foreach ($sheets as $s) {
                $title = (string) $s->properties->title;
                if (!empty($cache[$title])) {
                    throw new \Exception("Dup sheet somehow - $title");
                }
                $cache[$title] = $s;
            }
            self::$sheetcache[$this->sheetid] = $cache;
        }
        return self::$sheetcache[$this->sheetid];
    }

    public function getSheet($title)
    {
        $currentsheets = $this->getSheets();
        if (!isset($currentsheets[$title])) {
            self::$sheetcache[$this->sheetid][$title] = $this->createSheet($title);
        }
        return self::$sheetcache[$this->sheetid][$title];
    }

    public function createSheet($title)
    {
        $service = new \Google_Service_Sheets($this->getClient());
        $body = new \Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
            'requests' => ['addSheet' => ['properties' => ['title' => $title, 'gridProperties' => ['rowCount' => 2000]]]]
        ]);

        $result = $service->spreadsheets->batchUpdate($this->sheetid, $body, ['quotaUser' => "fpbxcovid19"])->getReplies();
        if (empty($result[0])) {
            throw new \Exception("Something's wrong");
        }
        return $result[0]->getAddSheet();
    }

    public function setVal($sheet, $row, $col, $value)
    {
        $title = (string) $sheet->properties->title;
        $this->pending[$title] = true;
        if (empty($this->updates[$title])) {
            $this->updates[$title] = [];
        }
        if (empty($this->updates[$title][$row])) {
            $this->updates[$title][$row] = [$col => $value];
        } else {
            $this->updates[$title][$row][$col] = $value;
        }
    }

    public function setRange($sheet, $range, $values)
    {
        $title = (string) $sheet->properties->title;
        $this->pending[$title] = true;
        if (empty($this->ranges[$title])) {
            $this->ranges[$title] = [];
        }
        $this->ranges[$title][] = new \Google_Service_Sheets_ValueRange(["range" => "$title!$range", "values" => [$values]]);
    }

    public function addThing($sheet, $thing)
    {
        $title = (string) $sheet->properties->title;
        $this->pending[$title] = true;
        if (empty($this->ranges[$title])) {
            $this->ranges[$title] = [];
        }
        $this->ranges[$title][] = $thing;
    }

    public function commit($raw = true)
    {
        $b = new \Google_Service_Sheets_BatchUpdateValuesRequest();
        if ($raw) {
            $b->setValueInputOption("RAW");
        } else {
            $b->setValueInputOption("USER_ENTERED");
        }

        $gdata = [];

        foreach ($this->pending as $title => $var) {
            if (empty($this->updates[$title])) {
                $this->updates[$title] = [];
            }
            $sorted = ksort($this->updates[$title]);
            foreach ($this->updates[$title] as $col => $data) {
                foreach ($data as $row => $val) {
                    $gdata[] = new \Google_Service_Sheets_ValueRange(["range" => "$title!$col$row", "values" => [[$val]]]);
                }
            }
            if (!empty($this->ranges[$title])) {
                foreach ($this->ranges[$title] as $data) {
                    $gdata[] = $data;
                }
            }
            $this->updates[$title] = [];
            $this->ranges[$title] = [];
            unset($this->pending[$title]);
        }
        if (!$gdata) {
            return;
        }
        $b->setData($gdata);
        $service = new \Google_Service_Sheets($this->getClient());
        return $service->spreadsheets_values->batchUpdate($this->sheetid, $b);
    }

    public function getVal($sheet, $range = "A1")
    {
        $fullrange = (string) $sheet->properties->title . "!$range";
        $service = new \Google_Service_Sheets($this->getClient());
        $response = $service->spreadsheets_values->get($this->sheetid, $fullrange);
        $vals = $response->getValues();
        return $vals;
    }

    public function sortSheetBy($sheet, $col, $order = "ASCENDING")
    {
        if (!is_numeric($col)) {
            throw new \Exception("Nope, you need to use a col offset number, not a string. A=0, B=1, etc");
        }
        $sheetId = $sheet->properties->sheetId;
        $rowCount = $sheet->properties->rowCount;
        $body = new \Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
            'requests' => [[
                'sortRange' => [
                    'range' => ["sheetId" => $sheetId, 'startRowIndex' => 1, 'endRowIndex' => $rowCount, 'startColumnIndex' => 0, 'endColumnIndex' => 26],
                    'sortSpecs' => [
                        ["dimensionIndex" => $col, "sortOrder" => $order],
                        ["dimensionIndex" => 9, "sortOrder" => $order],
                        ["dimensionIndex" => 7, "sortOrder" => $order],
                        ["dimensionIndex" => 3, "sortOrder" => $order],
                    ],
                ],
            ]],
        ]);

        $service = new \Google_Service_Sheets($this->getClient());
        return $service->spreadsheets->batchUpdate($this->sheetid, $body, ['quotaUser' => "fpbxcovid19"])->getReplies();
    }

    public function cutPaste($fromsheet, $tosheet, $fromrow, $torow)
    {
        $fid = $fromsheet->properties->sheetId;
        $tid = $tosheet->properties->sheetId;
        $body = new \Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
            'requests' => [[
                'cutPaste' => [
                    'source' => ['sheetId' => $fid, 'startRowIndex' => $fromrow - 1, 'endRowIndex' => $fromrow, 'startColumnIndex' => 0],
                    'destination' => ['sheetId' => $tid, 'rowIndex' => $torow - 1, 'columnIndex' => 1],
                    'pasteType' => 'PASTE_NORMAL',
                ],
            ]],
        ]);
        $service = new \Google_Service_Sheets($this->getClient());
        return $service->spreadsheets->batchUpdate($this->sheetid, $body, ['quotaUser' => "fpbxcovid19"])->getReplies();
    }
}
