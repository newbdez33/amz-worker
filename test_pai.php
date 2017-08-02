<?php
require_once('vendor/autoload.php');
require "../pr/aws.inc.php";
require "./common.inc.php";

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

use ApaiIO\ApaiIO;
use ApaiIO\Configuration\GenericConfiguration;
use ApaiIO\Operations\Lookup;
$conf = new GenericConfiguration();
$client = new \GuzzleHttp\Client();
$request = new \ApaiIO\Request\GuzzleRequest($client);
try {
    $conf
        ->setCountry('com')
        ->setAccessKey(AWS_API_KEY)
        ->setSecretKey(AWS_API_SECRET_KEY)
        ->setAssociateTag(AWS_ASSOCIATE_TAG)
        ->setRequest($request)
        ->setResponseTransformer(new \ApaiIO\ResponseTransformer\XmlToArray());
} catch (\Exception $e) {
    echo $e->getMessage();
}
$apaiIO = new ApaiIO($conf);
$lookup = new Lookup();
$lookup->setItemId('B003EECCXC');
$lookup->setResponseGroup(array('Medium'));
$formattedResponse = $apaiIO->runOperation($lookup);
/*
Array
(
    [price] => 3400
    [currency] => ￥
    [title] => [アシックス] キッズシューズ AMPHIBIAN 5 TUS112(旧モデル)
    [photo] => https://images-na.ssl-images-amazon.com/images/I/71HbgCi8VVL._UX500_.jpg
)
*/
$item = array();
$obj = $formattedResponse["Items"]["Item"];
//print_r($obj);
echo "\n";
$item["photo"] = $obj["MediumImage"]["URL"];
$item["price"] = $obj["ItemAttributes"]["ListPrice"]["Amount"];
if ($obj["ItemAttributes"]["ListPrice"]["CurrencyCode"] == "USD" ) {
	$item["currency"] = "$";
}
$item["title"] = $obj["ItemAttributes"]["Title"];
print_r($item);
echo "\n";