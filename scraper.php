<?php

/*
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
*/

if (!isset($_GET['hotel'])) {
    die("Please select a hotel");
}

if (!isset($_GET['nights'])) {
    die("Please select number of nights");
}

set_time_limit(600);

$lowestPrice = 10000;
$prices = array();

// My IP accidentally got blocked, grab a proxy
$proxy = getProxy();

$SITE_URL = "";
$HOTEL_NAME = strtolower($_GET['hotel']);
$NUM_NIGHTS = intval($_GET['nights']);

$curls = array();

$todayDate = date("Y-m-d");

// Not very secure, but this isn't public facing so should be fine
$mysqli = new mysqli('localhost', '*****', '*****', '*****');

$mysqlResult = $mysqli->query("SELECT data FROM prices WHERE hotel='$HOTEL_NAME' AND nights='$NUM_NIGHTS' AND date='$todayDate' LIMIT 1");

if (mysqli_num_rows($mysqlResult) == 1) {
    echo mysqli_fetch_row($mysqlResult)[0];
} else {

    // Default to legoland
    $website = "http://legoland.merlinbreaks.co.uk";
    $seatType = "WPE";
    $location = "WIN";
    $area = "LLS";
    if ($HOTEL_NAME == "castle") {
        $area = "LLW";
    }
    $park = "THMAPS";
    $agent = "LGD01";

    if ($HOTEL_NAME == "alton" || $HOTEL_NAME == "splash" || $HOTEL_NAME == "woodland" || $HOTEL_NAME == "treehouse") {
        // Alton towers
        $website = "http://alton.merlinbreaks.co.uk";
        $seatType = "AGA";
        $location = "ALT";
        $area = "ACR";
        $park = "THMAHO";
        $agent = "ATO01";
    } else if ($HOTEL_NAME == "safari" || $HOTEL_NAME == "azteca" || $HOTEL_NAME == "glamping") {
        // Chessington
        $website = "http://secure.chessingtonholidays.co.uk";
        $seatType = "CRA";
        $location = "CHE";
        $area = "CHR";
        $park = "THMCHO";
        $agent = "CCP01";
    } else if ($HOTEL_NAME == "tower" || $HOTEL_NAME == "knight" || $HOTEL_NAME == "mediaeval") {
        // Warwick castle
        $website = "http://warwickcastle.merlinbreaks.co.uk";
        $seatType = "S1A";
        $location = "WAR";
        $area = "WWD";
        $park = "THMWHC";
        $agent = "WAR01";
    } else if ($HOTEL_NAME == "shark") {
        // Shark hotel
        $website = "http://thorpebreaks.merlinbreaks.co.uk";
        $seatType = "TRA";
        $location = "THO";
        $area = "TMA";
        $park = "THMTHC";
        $agent = "TBW01";
    }

	// Construct URL to open to check price
    $dataURL = $website . "/blueprint/r/availability";

    $params = "Nights=" . $NUM_NIGHTS . "&Adults=2&Children=2&parkInfants1=0&parkInfants2=0";
    $params .= "&SeatType=" . $seatType;
    $params .= "&request=21";
    $params .= "&revolverAction=availability";
    $params .= "&product=theme";
    $params .= "&Location=" . $location;
    $params .= "&Area=" . $area;
    $params .= "&chauntryReRequest=21";
    $params .= "&errortpl=availability";
    $params .= "&stage=availability";
    $params .= "&Park=" . $park . "&productCode=t";
    $params .= "&agent=" . $agent;
    $params .= "&originalSeatType=" . $seatType;

    $SITE_URL = $dataURL . "?" . $params;

    $months = array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);

    for ($month = 0; $month < 12; $month++) {
        $mh = curl_multi_init();

        for ($day = 0; $day < $months[$month]; $day++) {
            scrape((string)($day + 1), (string)($month + 1), 17);
        }

        $running = null;
        do {
            curl_multi_exec($mh, $running);
        } while ($running);

        foreach ($curls as $curl) {
            $response = curl_multi_getcontent($curl[0]);

            processResult($response, $curl[1], $curl[2], $curl[0]);
        }

        foreach ($curls as $curl) {
            curl_multi_remove_handle($mh, $curl[0]);
        }
        curl_multi_close($mh);
    }

    $result = $SITE_URL;

    for ($month = 0; $month < 12; $month++) {

        $result .= "|";

        $result .= implode(",", $prices[intval($month) + 1]);
    }

    $mysqli->query("INSERT INTO prices VALUES(NULL, '$HOTEL_NAME', '$NUM_NIGHTS', '$result', '$todayDate')");

    echo $result;
}

mysqli_close($mysqli);

function scrape($day, $month, $year)
{
    global $SITE_URL, $mh, $curls, $proxy;

    if (strlen($day) == 1) {
        $day = "0" . $day;
    }
    if (strlen($month) == 1) {
        $month = "0" . $month;
    }

    $url = $SITE_URL;
    $url .= "&ArrivalDate=" . $day . "%2F" . $month . "%2F" . $year;
    $url .= "&TicketDate=" . $day . "%2F" . $month . "%2F" . $year;

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_FAILONERROR, true);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 0);
    curl_setopt($curl, CURLOPT_TIMEOUT, 600);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);

    curl_setopt($curl, CURLOPT_PROXY, $proxy);

    curl_multi_add_handle($mh, $curl);

    array_push($curls, array($curl, $month, $day));
}

function processResult($result, $month, $day, $curl)
{
    global $HOTEL_NAME, $prices, $lowestPrice;

    $remainingData = $result;

    $START_ID = "<section";
    $END_ID = ">";

    $hotelPrice = "N/A";

    while (true) {
        $chunkStart = strpos($remainingData, $START_ID);
        $remainingData = substr($remainingData, $chunkStart);
        $chunkEnd = strpos($remainingData, $END_ID);

        if ($chunkStart === false) {
            break;
        }

        $chunk = substr($remainingData, 0, $chunkEnd + strlen($END_ID)) . "</section>";
        $remainingData = substr($remainingData, $chunkEnd);

        try {
            $xml = simplexml_load_string($chunk);

            $hotelName = $xml['data-name'];
            $hotelPrice = $xml['data-price'];
            $hotelChildren = $xml['data-children'];

            if (strpos(strtolower($hotelName), $HOTEL_NAME) !== false) {
                if ($hotelChildren == "") {
                    $hotelPrice = "N/A";
                } else {
                    if (floatval($hotelPrice) < $lowestPrice) {
                        $lowestPrice = floatval($hotelPrice);
                    }
                }
                break;
            }
        } catch (Exception $e) {
            continue;
        }
    }

    if (!isset($prices[intval($month)])) {
        $prices[intval($month)] = array();
    }

    if (!isset($result) || $result == "") {
        $hotelPrice = "EE";

        //print_r(curl_getinfo($curl));
        //print_r(curl_error($curl));
        die ("ERROR");
    }

    $date = "";

    if (strlen((string)$day) == 1) {
        $date .= "0" . $day;
    } else {
        $date .= $day;
    }

    $date .= "/";

    if (strlen($month) == 1) {
        $date .= "0" . $month;
    } else {
        $date .= $month;
    }

    $date .= "/17";

    $prices[intval($month)][intval($day)] = $hotelPrice . "--" . $date;
}


function getProxy()
{
    $proxystr = file_get_contents('http://gimmeproxy.com/api/getProxy?protocol=http');
    $data = json_decode($proxystr, 1);
    if (isset($data['error'])) { // there are no proxies left for this user-id and timeout
        die($data['error'] . "\n");
    }
    return $data['curl'];
}

?>