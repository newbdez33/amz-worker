<?php
/*
sudo docker run -d -p 4444:4444 selenium/standalone-chrome:3.2.0-actinium
sudo docker run -d -p 4444:4444 -p 5900:5900 selenium/standalone-chrome-debug:3.2.0-actinium
*/
require_once('vendor/autoload.php');
require "./aws.inc.php";
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\Remote\WebDriverCapabilityType;
use Aws\Sqs\SqsClient;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;

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
	mainLoop();
	sleep(5);
}

function mainLoop() {
	global $q, $db, $qurl;

	$result = $q->receiveMessage(array(
	    "QueueUrl" => $qurl
	));

	$messages = $result["Messages"];
	if ( count($messages) > 0 ) {
		$m = $messages[0];
		$mid = $m['MessageId'];
		echo "Get message:".$mid."\n";	//TODO Log
		$json = $m['Body'];
		$receipt = $m['ReceiptHandle'];

		$data = json_decode($json, true);
		if ( !$data ) {
			echo "Invalied json\n";
		}else {
			$fetched = fetchAmazonUrl($data['url']);
			$updated = array_merge($fetched, $data);
			$updated["title"] = $fetched['title'];
			putItem($updated);
		}
		$q->deleteMessage(array("QueueUrl" => $qurl, "ReceiptHandle" => $receipt));
	}else {
		echo "No message.\n";
	}
}

function putItem($item) {
	global $db, $q;

	$marshaler = new Marshaler();
    $data = $marshaler->marshalItem($item);
	$result = $db->putItem(array(
	    'TableName' => 'products_amazon',
	    'Item' => $data
	));
    //Error handling

    return $result;
}

function fetchAmazonUrl($url) {

	$capabilities = array(WebDriverCapabilityType::BROWSER_NAME => 'firefox');
	$webDriver = RemoteWebDriver::create('http://selenium:4444/wd/hub', $capabilities);	
	$webDriver->get($url);
	$data = array();
	try {
		$element = $webDriver->findElement(WebDriverBy::id("productTitle"));
		$data["title"] = $element->getText();
		$element = $webDriver->findElement(WebDriverBy::id("landingImage"));
		$data["photo"] = $element->getAttribute("src");
		$element = $webDriver->findElement(WebDriverBy::id("priceblock_ourprice"));
		$data["price"] = $element->getText();
	} catch(Exception $e) {
		print_r($e);
		//TODO send alert mail.
	} finally {
		$webDriver->quit();
	}
	

	return $data;
}

