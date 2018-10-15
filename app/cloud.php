<?php

use \LeanCloud\Engine\Cloud;
use \LeanCloud\LeanObject;
use \LeanCloud\Query;

/*
 * Define cloud functions and hooks on LeanCloud
 */

// /1.1/functions/sayHello
Cloud::define("sayHello", function($params, $user) {
    return "hello {$params['name']}";
});

Cloud::define("logTimer", function($params, $user) {
    error_log("Log in timer");
});

// /1.1/functions/sieveOfPrimes
Cloud::define("sieveOfPrimes", function($params, $user) {
    $n = isset($params["n"]) ? $params["n"] : 1000;
    error_log("Find prime numbers less than {$n}");
    $primeMarks = array();
    for ($i = 0; $i <= $n; $i++) {
        $primeMarks[$i] = true;
    }
    $primeMarks[0] = false;
    $primeMarks[1] = false;

    $x = round(sqrt($n));
    for ($i = 2; $i <= $x; $i++) {
        if ($primeMarks[$i]) {
            for ($j = $i * $i; $j <= $n;  $j = $j + $i) {
                $primeMarks[$j] = false;
            }
        }
    }

    $numbers = array();
    forEach($primeMarks as $i => $mark) {
        if ($mark) {
            $numbers[] = $i;
        }
    }
    return $numbers;
});

Cloud::afterSave("Rebalancing", function($rebalancing, $currentUser) {
    $prev_bebalancing_id = $rebalancing->get('prev_bebalancing_id');
    error_log($prev_bebalancing_id);
    try {
        if(!empty($prev_bebalancing_id)){
          $rebalance = getRebalancing($prev_bebalancing_id);
          error_log($rebalance);
          if(!empty($rebalance)){
            $uniqueRbObj = new Query("Rebalancing");
            $uniqueRbObj->equalTo("origin_id", $rebalance->id);
            if($uniqueRbObj->count() == 0){
              $rbObj = new LeanObject("Rebalancing");
              $rbObj->set("portfolio", $rebalancing->get('portfolio'));
              $rbObj->set("origin_id", $rebalance->id);
              $rbObj->set("status", $rebalance->status);
              $rbObj->set("cube_id", $rebalance->cube_id);
              $rbObj->set("prev_bebalancing_id", $rebalance->prev_bebalancing_id);
              $rbObj->set("category", $rebalance->category);
              $rbObj->set("created_at", $rebalance->created_at);
              $rbObj->set("updated_at", $rebalance->updated_at);
              $rbObj->set("cash_value", $rebalance->cash_value);
              $rbObj->set("cash", $rebalance->cash);
              $rbObj->set("error_code", $rebalance->error_code);
              $rbObj->set("error_message", $rebalance->error_message);
              $rbObj->set("error_status", $rebalance->error_status);
              $rbObj->set("holdings", $rebalance->holdings);
              $rbObj->set("rebalancing_histories", json_encode($rebalance->rebalancing_histories));
              $rbObj->save();
            }
          }
        }
    } catch (CloudException $ex) {
        throw new FunctionError("保存 Post 对象失败: " . $ex->getMessage());
    }
});

/*
Cloud::beforeSave("Portfolios", function($portfolio, $currentUser) {
    $query = new Query("Portfolios");
    $query->equalTo("symbol", $portfolio->get('symbol'));
    if ($query->count() == 0) {
        $portfolio->set('name', 'test123');
    } else {
        // 返回错误，并取消数据保存
        throw new Exception("该标识已存在！");
    }
    // 如果正常返回，则数据会保存
});
*/
/*
Cloud::afterSave("Portfolios", function($portfolio, $currentUser) {
    $portfolio->set('name', 'test123');
    try {
        $portfolio->save();
    } catch (CloudException $ex) {
        throw new FunctionError("保存 Post 对象失败: " . $ex->getMessage());
    }
});
*/
/*

Cloud::onLogin(function($user) {
    // reject blocker user for login
    if ($user->get("isBlocked")) {
        throw new FunctionError("User is blocked!", 123);
    }
});

Cloud::onInsight(function($params) {
    return;
});

Cloud::onVerified("sms", function($user){
    return;
});

Cloud::beforeSave("TestObject", function($obj, $user) {
    return $obj;
});

Cloud::beforeUpdate("TestObject", function($obj, $user) {
    // $obj->updatedKeys is an array of keys that is changed in the request
    return $obj;
});

Cloud::afterSave("TestObject", function($obj, $user, $meta) {
    // function can accepts optional 3rd argument $meta, which for example
    // has "remoteAddress" of client.
    return ;
});

*/
