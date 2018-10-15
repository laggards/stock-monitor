<?php

function getLastBebalancingID($symbol){
    $base_url = 'https://xueqiu.com/p/';
    $ret = null;
    $client = new \GuzzleHttp\Client(['cookies'=>true]);
    $request_url = $base_url.$symbol;
    $res = $client->request('GET', $request_url,[
          'referer' => true,
          'headers' => [
            'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 11_0 like Mac OS X) AppleWebKit/604.1.38 (KHTML, like Gecko) Version/11.0 Mobile/15A372 Safari/604.1',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Host' => 'xueqiu.com',
            'Referer' => $request_url
          ]
    ]);
    $ret = ['name' => '', 'period'=> '', 'last_rb_id' => ''];
    preg_match_all("/<div[\s]*class=\"name\">(.*)<\/div>/isU",$res->getBody(),$matches);
    if(isset($matches[1]) && !empty($matches[1][0])){
      $ret['name'] = $matches[1];
    }

    preg_match_all("/<div[\s]*class=\"per\">(.*)<\/div>/isU",$res->getBody(),$matches);
    if(isset($matches[1]) && !empty($matches[1][0])){
      $ret['period'] = $matches[1];
    }

    preg_match("/\"last_rb_id\":(\d+)/i",$res->getBody(),$matches);
    if(isset($matches[1]) && !empty($matches[1])){
      $ret['last_rb_id'] = $matches[1];
    }
    //var_dump($ret['period']);
    //exit();
    return $ret;
}
