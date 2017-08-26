<?php
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\Remote\WebDriverCapabilityType;
use Aws\DynamoDb\Marshaler;

use ApaiIO\ApaiIO;
use ApaiIO\Configuration\GenericConfiguration;
use ApaiIO\Operations\Lookup;

function getCleanUrl($url) {
	$parts = parse_url($url);
	$fetcher = new \Amazon\AsinParser($url);
    $asin = $fetcher->getAsin();
	return "{$parts['scheme']}://{$parts['host']}/dp/{$asin}/?psc=1";
}

function putPrice($price) {
	global $db;
	
	$marshaler = new Marshaler();
	$p_data = $marshaler->marshalItem($price);
	$result = $db->putItem(array(
	    'TableName' => 'prices_history',
	    'Item' => $p_data
	));
}

function jsonObjectFromItem($item) {
    $marshaler = new Marshaler();
    $data = $marshaler->unmarshalItem($item);
    return $data;
}

function updatePrices($pid, $current) {
	global $db;

	$lowest = $current;
    $result = $db->query(array(
        'TableName' => 'prices_history',
        'IndexName' => 'asin-price-index',
        'ScanIndexForward' => true,
        'Limit' => 1,
        'KeyConditionExpression' => "asin = :a",
        'ExpressionAttributeValues' => array(
                ":a" => ['S' => $pid],
        ),
    ));
    if ( is_object($result) && is_array($result["Items"]) ) {
        foreach ($result["Items"] as $key => $value) {
            $item = jsonObjectFromItem($value);
            if ( $current > $item["price"] ) {
            	$lowest = $item["price"];
            }
        }
    }

    $highest = $current;
    $result = $db->query(array(
        'TableName' => 'prices_history',
        'IndexName' => 'asin-price-index',
        'ScanIndexForward' => false,
        'Limit' => 1,
        'KeyConditionExpression' => "asin = :a",
        'ExpressionAttributeValues' => array(
                ":a" => ['S' => $pid],
        ),
    ));
    if ( is_object($result) && is_array($result["Items"]) ) {
        foreach ($result["Items"] as $key => $value) {
            $item = jsonObjectFromItem($value);
            if ( $current < $item["price"] ) {
            	$highest = $item["price"];
            }
        }
    }

    $resp = $db->updateItem([
    	'TableName' => 'products_amazon',
	    'Key' => [
	    	'asin' => [
	    		'S' => $pid
	    	] 
	    ],
	    'ExpressionAttributeNames' => [
	        '#L' => 'lowest',
	        '#H' => 'highest',
	        '#C' => 'price',
	        '#U' => 'updated_at'
	    ],
	    'ExpressionAttributeValues' =>  [
	        ':l' => ['N' => doubleval($lowest)],
	        ':h' => ['N' => doubleval($highest)],
	        ':c' => ['N' => doubleval($current)],
	        ':u' => ['N' => time()]
	    ] ,
	    'UpdateExpression' => 'set #L = :l, #H = :h, #C = :c, #U = :u',
	    'ReturnValues' => 'ALL_NEW' 
    ]);

}

function convertPrice($price, $url) {

	$price_arr = explode("-", $price);
	if ( count($price_arr) > 1 ) {
		$price = trim($price_arr[0]);
	}

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

function fetchAmazonUrl($url, $shouldQuite = true) {
	global $log;

	$capabilities = array(WebDriverCapabilityType::BROWSER_NAME => 'firefox');
	$webDriver = RemoteWebDriver::create('http://selenium:4444/wd/hub', $capabilities, 90*1000, 90*1000);

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
		//$log->debug(print_r($e, true));
		print_r($e);
		//TODO send alert mail.
	} finally {
		//
	}

	if ($data["photo"] == "") {
	try {
                echo "retry ...";
                $element = $webDriver->findElement(WebDriverBy::id("imgBlkFront"));
                $data["photo"] = $element->getAttribute("src");

        } catch(Exception $e) {
                //$log->debug(print_r($e, true));
                print_r($e);
                //TODO send alert mail.
        } finally {
                //
        }
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
		//$log->debug("find element price failed.");
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
			//$log->debug("find element price failed.");
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
			//$log->debug("find element price failed.");
			//print_r($e);
			//TODO send alert mail.
		} finally {
			//
		}
	}

	if ($data["price"] == '0') {
		try {
			$element = $webDriver->findElement(WebDriverBy::className("offer-price"));
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
			//$log->debug("find element price failed.");
			//print_r($e);
			//TODO send alert mail.
		} finally {
			//
		}
	}

	if ($data["price"] == '0') {
		try {
			$element = $webDriver->findElement(WebDriverBy::id("unqualifiedBuyBox"));
			$element = $element->findElement(WebDriverBy::className("a-color-price"));
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
			//$log->debug("find element price failed.");
			//print_r($e);
			//TODO send alert mail.
		} finally {
			//
		}
	}

	if ($data["price"] == '0') {
		try {
			$element = $webDriver->findElement(WebDriverBy::className("swatchElement selected"));
			$element = $element->findElement(WebDriverBy::className("a-color-price"));
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
			//$log->debug("find element price failed.");
			//print_r($e);
			//TODO send alert mail.
		} finally {
			//
		}
	}

	if ($data["price"] == '0') {
		try {
			$element = $webDriver->findElement(WebDriverBy::id("snsPrice"));
			$element = $element->findElement(WebDriverBy::className("a-color-price"));
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
			//$log->debug("find element price failed.");
			//print_r($e);
			//TODO send alert mail.
		} finally {
			//
		}
	}


	echo "close window\n";
	$webDriver->close();
	if ( $shouldQuite ) {
		$webDriver->quit();
	}
	
	//$log->debug("fetched price:".print_r($data, true));
	return $data;
}

function fetchItem($asin, $country) {
	$conf = new GenericConfiguration();
	$client = new \GuzzleHttp\Client();
	$request = new \ApaiIO\Request\GuzzleRequest($client);
	try {
	    $conf
	        ->setCountry($country)
	        ->setAccessKey(AWS_API_KEY)
	        ->setSecretKey(AWS_API_SECRET_KEY)
	        ->setAssociateTag(AWS_ASSOCIATE_TAG)
	        ->setRequest($request)
	        ->setResponseTransformer(new \ApaiIO\ResponseTransformer\XmlToArray());
	} catch (\Exception $e) {
	    return false;	//
	}
	$apaiIO = new ApaiIO($conf);
	$lookup = new Lookup();
	$lookup->setItemId($asin);
	$lookup->setResponseGroup(array('Medium'));
	$formattedResponse = $apaiIO->runOperation($lookup);
	$item = array();
	$obj = $formattedResponse["Items"]["Item"];

	$item["photo"] = $obj["MediumImage"]["URL"];
	$item["price"] = doubleval($obj["ItemAttributes"]["ListPrice"]["Amount"]);
	if ($obj["ItemAttributes"]["ListPrice"]["CurrencyCode"] == "USD" ) {
		$item["currency"] = "$";
	}
	$item["title"] = $obj["ItemAttributes"]["Title"];
	$item["clean_url"] = $obj["DetailPageURL"];
	return $item;
}

function slack_notify($data) {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, "https://hooks.slack.com/services/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");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "{\"text\":\"{$data}\"}");
    curl_setopt($ch, CURLOPT_POST, 1);

    $headers = array();
    $headers[] = "Content-Type: application/x-www-form-urlencoded";
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
    }
    curl_close ($ch);
}
