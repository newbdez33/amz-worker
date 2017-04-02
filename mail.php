<?php
require 'vendor/autoload.php';
function sendMessage($msg) {
	$sc = new \SendCloud\SendCloud();
	$req = $sc->prepare('mail', 'send', array(
	    'apiUser'     => 'pricebot',
	    'apiKey'      => 'ZYHWk0ArWNoQ2ZDw',
	    'from'        => 'pricebot@mail.salmonapps.com', 
	    'fromName'    => 'PriceBot',
	    'to'          => 'newbdez33@gmail.com',# 收件人地址, 用正确邮件地址替代, 多个地址用';'分隔
	    'subject'     => 'Pricebot Daily collection:'.date("Y-m-d H:i:s"),
	    'html'        => '<pre>'.$msg.'</pre>',
	    'respEmailId' => 'true'
	));
	$data = $req->send();       // 提交API调用请求,返回数据
	//print_r($data);
}
