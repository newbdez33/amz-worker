<?php
require_once('vendor/autoload.php');
require "./common.inc.php";

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$url = "https://www.amazon.co.uk/dp/1945572574/ref=cm_sw_r_oth_api_BNLmzbD8QFQB5";
$fetched = fetchAmazonUrl($url);
print_r($fetched);
