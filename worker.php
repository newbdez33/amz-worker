<?php
/*
sudo docker run -d -p 4444:4444 selenium/standalone-chrome:3.2.0-actinium
sudo docker run -d -p 4444:4444 -p 5900:5900 selenium/standalone-chrome-debug:3.2.0-actinium
*/
require_once('vendor/autoload.php');
require "../pr/aws.inc.php";
require "./common.inc.php";
use Aws\Sqs\SqsClient;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

//
// $d = date("Ymd-His");
// rename("./debug.log", "./debug_{$d}.log");
//TODO Docker restart

$log = new Logger('info');
$log->pushHandler(new StreamHandler('./logs/worker.log', Logger::DEBUG));

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

$qurl = "https://sqs.ap-northeast-1.amazonaws.com/426901641069/fetch_jobs";
while ( true ) {
	//$log->debug("start mainloop");
	mainLoop();
	sleep(5);
}

function mainLoop() {
	global $q, $db, $qurl, $log;

	$result = $q->receiveMessage(array(
	    "QueueUrl" => $qurl
	));

	$messages = $result["Messages"];
	if ( count($messages) > 0 ) {
		$m = $messages[0];
		$mid = $m['MessageId'];
		echo "Get message:".$mid."\n";
		$log->debug("Get message:".$mid);
		$json = $m['Body'];
		$receipt = $m['ReceiptHandle'];

		$data = json_decode($json, true);
		if ( !$data ) {
			$log->debug("Invalied json");
		}else {
			$url = $data['url'];
			$log->debug("Get:".$url);
			$fetched = fetchAmazonUrl($url);
			print_r($fetched);
			if (!array_key_exists("title", $fetched)) {
				echo "fetch price failed. may try it again later.\n";
				return;
			}
			$updated = array_merge($fetched, $data);
			$updated["highest"] = $fetched["price"];
			$updated["lowest"] = $fetched["price"];
			putItem($updated);

			$price["t"] = time();
		    $price["asin"] = $updated["asin"];
		    $price["price"] = doubleval($updated["price"]);
		    $price["currency"] = trim($updated["currency"]);
			putPrice($price);
		}
		$q->deleteMessage(array("QueueUrl" => $qurl, "ReceiptHandle" => $receipt));
	}else {
		//$log->debug("No message.");
	}
}

function putItem($item) {
	global $db, $q, $log;

	$marshaler = new Marshaler();
    $data = $marshaler->marshalItem($item);
	$result = $db->putItem(array(
	    'TableName' => 'products_amazon',
	    'Item' => $data
	));

	//Error handling
	//$log->debug("put item");
    return $result;
}



