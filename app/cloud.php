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

/*定时更新所有组合信息*/
Cloud::define("updatePortfolio", function($params, $user) {
    $query = new Query("Portfolios");
    $portfolios = $query->equalTo('status', true)->find();
    foreach ($portfolios as $portfolio) {
      $portfolioProperty = getLastBebalancingID($portfolio->get('symbol'));
      if($portfolioProperty['last_rb_id'] != $portfolio->get('last_rb_id') || $portfolioProperty['period'] != $portfolio->get('period')){
        $portfolio->set('name', $portfolioProperty['name']);
        $portfolio->set('period', $portfolioProperty['period']);
        $portfolio->set('last_rb_id', $portfolioProperty['last_rb_id']);
        $portfolio->save();
      }
    }
});

Cloud::define("updateRebalance", function($params, $user) {
    //$prev_bebalancing_id = $rebalancing->get('prev_bebalancing_id');
    $query = new Query("Portfolios");
    $portfolios = $query->equalTo('status', true)->find();
    try {
        if(!empty($prev_bebalancing_id)){
          $rebalance = getRebalancing($prev_bebalancing_id);
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
              $rbObj->set("error_code", $rebalance->error_code == null ? 'null': $rebalance->error_code);
              $rbObj->set("error_message", $rebalance->error_message);
              $rbObj->set("error_status", $rebalance->error_status == null ? 'null':$rebalance->error_status);
              $rbObj->set("holdings", $rebalance->holdings == null ? 'null':$rebalance->holdings);
              $rbObj->set("rebalancing_histories", json_encode($rebalance->rebalancing_histories));
              $rbObj->set("comment", $rebalance->comment);
              $rbObj->set("diff", $rebalance->diff);
              $rbObj->set("new_buy_count", $rebalance->new_buy_count);
              $rbObj->save();
            }
          }
        }
    } catch (CloudException $ex) {
        error_log('prev_bebalancing_id->'.$prev_bebalancing_id);
        throw new FunctionError("保存 Post 对象失败: " . $ex->getMessage());
    }
});

Cloud::afterSave("Portfolios", function($portfolio, $currentUser) {
    $last_rb_id = $portfolio->get('last_rb_id');
    try {
      if($last_rb_id){
        $rebalance = getRebalancing($older_rb_id);
        if(!empty($rebalance)){
          $rbObj = new LeanObject("Rebalancing");
          $rbObj->set("portfolio", $portfolio);
          $rbObj->set("origin_id", $rebalance->id);
          $rbObj->set("status", $rebalance->status);
          $rbObj->set("cube_id", $rebalance->cube_id);
          $rbObj->set("prev_bebalancing_id", $rebalance->prev_bebalancing_id);
          $rbObj->set("category", $rebalance->category);
          $rbObj->set("created_at", $rebalance->created_at);
          $rbObj->set("updated_at", $rebalance->updated_at);
          $rbObj->set("cash_value", $rebalance->cash_value);
          $rbObj->set("cash", $rebalance->cash);
          $rbObj->set("error_code", $rebalance->error_code == null ? 'null': $rebalance->error_code);
          $rbObj->set("error_message", $rebalance->error_message);
          $rbObj->set("error_status", $rebalance->error_status == null ? 'null':$rebalance->error_status);
          $rbObj->set("holdings", $rebalance->holdings == null ? 'null':$rebalance->holdings);
          $rbObj->set("rebalancing_histories", json_encode($rebalance->rebalancing_histories));
          $rbObj->set("comment", $rebalance->comment);
          $rbObj->set("diff", $rebalance->diff);
          $rbObj->set("new_buy_count", $rebalance->new_buy_count);
          $rbObj->save();
        }
      }
    } catch (CloudException $ex) {
        throw new FunctionError("保存 Post 对象失败: " . $ex->getMessage());
    }
});

Cloud::afterDelete("Portfolios", function($portfolio, $currentUser) {
    $query = new Query("Rebalancing");
    $query->equalTo("portfolio", $portfolio);
    try {
        // 删除相关的 photos
        $allBalance = $query->find();
        LeanObject::destroyAll($allBalance);
    } catch (CloudException $ex) {
        throw new FunctionError("删除关联 调仓记录 失败: {$ex->getMessage()}");
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
