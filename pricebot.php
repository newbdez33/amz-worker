<?php
require_once('vendor/autoload.php');
require "../pr/aws.inc.php";
require "./common.inc.php";
require "./mail.php";
use Aws\Sqs\SqsClient;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

//本job实现将所有的

$log = new Logger('info');
$log->pushHandler(new StreamHandler('./logs/pricebot.log', Logger::DEBUG));

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

$qurl = "https://sqs.ap-northeast-1.amazonaws.com/426901641069/daily_queue";
echo "started.\n";
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
            $item = fetchAmazonUrl($url);
            //print_r($item);
            $price["t"] = time();
            $price["asin"] = $data["asin"];
            //EUR 29.99
            $price["price"] = $item["price"];
            $price["currency"] = trim($item["currency"]);
            if ($price["price"] != 0 && $price["currency"] != "") {
                try {
                    putPrice($price);
                } catch(Exception $e) {
                    //TODO error report
                    echo "put to db error.\n";
                    sendMessage(print_r($price, true));
                }
            }else {
                $log->debug("Invalied price:". print_r($price, true));
            }
        }

        $q->deleteMessage(array("QueueUrl" => $qurl, "ReceiptHandle" => $receipt));
    }else {
        //$log->debug("No message.");
    }
}
