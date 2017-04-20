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
	    'TableName' => 'prices_history',
	    'Item' => $p_data
	));
}

function convertPrice($price, $url) {
	$urlparser = new \Amazon\AsinParser($url);
	preg_match('/(.*?)([\d\.,]+)$/', $price, $match);
	$value = $match[2];
	if ( in_array($urlparser->getTld(), array('es', 'fr', 'it', 'nl', 'com.br')) )  {	//这些情况把,当.用
		$comma = ".";
		$dot = ",";
	}else {
		$comma = ",";
		$dot = ".";
	}
	$value = str_replace($comma, "", $value);
	$value = str_replace($dot, ".", $value);
	$data["price"] = doubleval($value);
	$data["currency"] = $match[1];
	return $data;
}

function fetchAmazonUrl($url) {
	global $log;

	$capabilities = array(WebDriverCapabilityType::BROWSER_NAME => 'firefox');
	$webDriver = RemoteWebDriver::create('http://selenium:4444/wd/hub', $capabilities);

	$data = array();
	//default price
	$data["price"] = 0;
	$data["currency"] = '';

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
    		echo "price:{$price}\n";
			if ($price != "") {
				$result = convertPrice($price, $url);
				$data["price"] = doubleval($result["price"]);
				$data["currency"] = $result["currency"];
			}
		}
	} catch(Exception $e) {
		$log->debug("find element price failed.");
		//print_r($e);
		//TODO send alert mail.
	} finally {
		//
	}

	if ($data["price"] == '0') {
		try {
			$element = $webDriver->findElement(WebDriverBy::id("priceblock_dealprice"));
			if ($element->isDisplayed()) {
	    		$price = $element->getText();
	    		echo "price2:{$price}\n";
				if ($price != "") {
					$result = convertPrice($price, $url);
					$data["price"] = doubleval($result["price"]);
					$data["currency"] = $result["currency"];
				}
			}
		} catch(Exception $e) {
			$log->debug("find element price failed.");
			//print_r($e);
			//TODO send alert mail.
		} finally {
			//
		}
	}

	if ($data["price"] == '0') {
		try {
			$element = $webDriver->findElement(WebDriverBy::id("priceblock_saleprice"));
			if ($element->isDisplayed()) {
	    		$price = $element->getText();
	    		echo "price2:{$price}\n";
				if ($price != "") {
					$result = convertPrice($price, $url);
					$data["price"] = doubleval($result["price"]);
					$data["currency"] = $result["currency"];
				}
			}
		} catch(Exception $e) {
			$log->debug("find element price failed.");
			//print_r($e);
			//TODO send alert mail.
		} finally {
			//
		}
	}

	echo "close window\n";
	$webDriver->close();
	$webDriver->quit();
	
	$log->debug("fetched price:".print_r($data, true));
	return $data;
}
