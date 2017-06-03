<?php
require_once('vendor/autoload.php');
require "./common.inc.php";

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$url = "https://www.amazon.co.jp/dp/B016MO9AR6/";
$fetched = fetchAmazonUrl($url);
print_r($fetched);
