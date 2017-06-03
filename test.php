<?php
require_once('vendor/autoload.php');
require "./common.inc.php";

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$url = "https://www.amazon.co.jp/dp/B00H2EIU86/ref=cm_sw_r_oth_api_fRAgzbJ61VWND?th=1&psc=1";
$fetched = fetchAmazonUrl($url);
print_r($fetched);
