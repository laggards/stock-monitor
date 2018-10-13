<?php

function getLastBebalancingID($symbol){
    $base_url = 'https://xueqiu.com/p/';
    $last_rb_id = null;
    $client = new \GuzzleHttp\Client(['cookies'=>true]);
    $request_url = $base_url.$symbol;
    $res = $client->request('GET', $request_url,[
          'referer' => true,
          'headers' => [
            'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 11_0 like Mac OS X) AppleWebKit/604.1.38 (KHTML, like Gecko) Version/11.0 Mobile/15A372 Safari/604.1',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
            'Cookie' => 'xq_a_token=45f0c2debacfcbeb4924700fd74eac831c8c40fa; xq_r_token=003285fd18aa96023ff92d68d7fa8cc51321aad4;',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Host' => 'xueqiu.com',
            'Referer' => $request_url
          ]
    ]);
    preg_match("/\"last_rb_id\":(\d+)/i",$res->getBody(),$matches);
    if(isset($matches[1]) && !empty($matches[1])){
      $last_rb_id = $matches[1];
    }
    return $last_rb_id;
}
