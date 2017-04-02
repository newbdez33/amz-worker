<?php
function sendMessage($msg) {

        $url = 'https://sendcloud.sohu.com/webapi/mail.send.xml';

        $param = array(
                'api_user' => 'pricebot',
                'api_key' => 'ZYHWk0ArWNoQ2ZDw',
                'from' => 'pricebot@mail.salmonapps.com',
                'fromname' => 'PriceBot',
                'to' => 'newbdez33@gmail.com',
                'subject' => 'Pricebot Daily collection:'.date("Y-m-d H:i:s"),
                'html' => '<pre>'.$msg.'</pre>'
        );

        $options = array(
                'http' => array(
                        'method' => 'POST',
                        'content' => http_build_query($param)));
        $context  = stream_context_create($options);
        $result = file_get_contents($url, false, $context);

        return $result;
}

