<?php
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\Remote\WebDriverCapabilityType;
use Aws\DynamoDb\Marshaler;

function putPrice($price) {
	global $db;
	
	$marshaler = new Marshaler();
	$p_data = $marshaler->marshalItem($price);
	$result = $db->putItem(array(
	    'TableName' => 'prices_amazon',
	    'Item' => $p_data
	));
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