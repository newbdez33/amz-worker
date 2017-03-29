<?php
/*
sudo docker run -d -p 4444:4444 selenium/standalone-chrome:3.2.0-actinium
sudo docker run -d -p 4444:4444 -p 5900:5900 selenium/standalone-chrome-debug:3.2.0-actinium
*/
require_once('vendor/autoload.php');
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\Remote\WebDriverCapabilityType;

$capabilities = array(WebDriverCapabilityType::BROWSER_NAME => 'chrome');
$webDriver = RemoteWebDriver::create('http://selenium:4444/wd/hub', $capabilities);
$webDriver->get("https://www.amazon.co.jp/任天堂-Nintendo-Switch-Joy-Con-グレー/dp/B01N5QLLT3/ref=s9_ri_gw_g63_i1_r?pf_rd_m=AN1VRQENFRJN5&pf_rd_s=&pf_rd_r=EE7PJVZHDGBY29G8EM1H&pf_rd_t=36701&pf_rd_p=9c50a930-f257-4558-a46f-d3236140b37a&pf_rd_i=desktop");

$data = array();
$element = $webDriver->findElement(WebDriverBy::id("productTitle"));
$data["title"] = $element->getText();
$element = $webDriver->findElement(WebDriverBy::id("landingImage"));
$data["photo"] = $element->getAttribute("src");
$element = $webDriver->findElement(WebDriverBy::id("priceblock_ourprice"));
$data["price"] = $element->getText();

print_r($data);
