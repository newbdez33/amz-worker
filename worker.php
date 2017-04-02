<?php
/*
sudo docker run -d -p 4444:4444 selenium/standalone-chrome:3.2.0-actinium
sudo docker run -d -p 4444:4444 -p 5900:5900 selenium/standalone-chrome-debug:3.2.0-actinium
*/
require_once('vendor/autoload.php');
require "../pr/aws.inc.php";
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\Remote\WebDriverCapabilityType;
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
$log->pushHandler(new StreamHandler('./logs/debug.log', Logger::DEBUG));

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

	$price["date"] = date("Ymd");
	$price["asin"] = $item["asin"];
	//EUR 29.99
	$price["price"] = $item["price"];
	$price["currency"] = $item["currency"];
	$p_data = $marshaler->marshalItem($price);
	$result = $db->putItem(array(
	    'TableName' => 'prices_amazon',
	    'Item' => $p_data
	));

    //Error handling
	//$log->debug("put item");
    return $result;
}

function fetchAmazonUrl($url) {
	global $log;
	$capabilities = array(WebDriverCapabilityType::BROWSER_NAME => 'firefox');
	$webDriver = RemoteWebDriver::create('http://selenium:4444/wd/hub', $capabilities);

	$data = array();
	//default price
	$data["price"] = '0';
	$data["currency"] = ' ';

	try {
		echo "fetching...";
		
		$webDriver->get($url);
		$element = $webDriver->findElement(WebDriverBy::id("productTitle"));
		$data["title"] = $element->getText();
		$element = $webDriver->findElement(WebDriverBy::id("landingImage"));
		$data["photo"] = $element->getAttribute("src");

	} catch(Exception $e) {
		$log->debug(print_r($e, true));
		print_r($e);
		//TODO send alert mail.
	} finally {
		//
	}

	try {
		$element = $webDriver->findElement(WebDriverBy::id("priceblock_ourprice"));
		if ($element->isDisplayed()) {
    		$price = $element->getText();
			if ($price != "") {
				preg_match('/(.*?)([\d\.,]+)$/', $price, $match);
				$data["price"] = str_replace(",", "", $match[2]);
				$data["currency"] = $match[1];
			}
		}
	} catch(Exception $e) {
		$log->debug("find element price failed.");
		//print_r($e);
		//TODO send alert mail.
	} finally {
		//
	}

	echo "close window\n";
	$webDriver->close();
	$webDriver->quit();
	
	echo "fetched:\n";
	print_r($data);
	$log->debug("fetched price:".print_r($data, true));
	return $data;
}

