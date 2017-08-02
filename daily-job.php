<?php
require_once('vendor/autoload.php');
require "../pr/aws.inc.php";
require "./mail.php";

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\Remote\WebDriverCapabilityType;
use Aws\Sqs\SqsClient;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Iterator\ItemIterator;
use Aws\DynamoDb\Marshaler;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

//本job实现将所有的

$log = new Logger('info');
$log->pushHandler(new StreamHandler('./logs/daily.'.date("Ymd").'.log', Logger::DEBUG));

$q = SqsClient::factory(array(
    'credentials' => array(
        'key'    => J_ASSESS_KEY,
        'secret' => J_SECRET_KEY,
    ),
    'region' => 'ap-northeast-1'
));

$db = DynamoDbClient::factory(array(
    'credentials' => array(
        'key'    => J_ASSESS_KEY,
        'secret' => J_SECRET_KEY,
    ),
    'region' => 'ap-northeast-1'
));

$products = new ItemIterator($db->getScanIterator(array(
    'TableName' => 'products_amazon'
)));

$msg = "";
$c = 0;
foreach ($products as $p) {
    $data = array("asin" => $p['asin'], "url" => $p["url"], "aac" => $p["aac"]);
	$r = $q->sendMessage(array(
	    "QueueUrl" => "https://sqs.ap-northeast-1.amazonaws.com/426901641069/daily_queue",
	    "MessageBody" => json_encode($data)
	));
	$msid = $r['MessageId'];
    $msg .= "{$p['asin']} {$p['title']}, {$msid}\n";
    $c++;
}

$msg = "Total count:".$c."\n\n".$msg;

sendMessage($msg);