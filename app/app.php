<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/cloud.php';

/*
 * A simple Slim based sample application
 *
 * See Slim documentation:
 * http://www.slimframework.com/docs/
 */

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \Slim\Views\PhpRenderer;
use \LeanCloud\Client;
use \LeanCloud\Storage\CookieStorage;
use \LeanCloud\Engine\SlimEngine;
use \LeanCloud\Query;
use \LeanCloud\LeanObject;
use \Carbon\Carbon;
use \PhpOffice\PhpSpreadsheet\Spreadsheet;
use \PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$app = new \Slim\App();
// 禁用 Slim 默认的 handler，使得错误栈被日志捕捉
unset($app->getContainer()['errorHandler']);

Client::initialize(
    getenv("LEANCLOUD_APP_ID"),
    getenv("LEANCLOUD_APP_KEY"),
    getenv("LEANCLOUD_APP_MASTER_KEY")
);
// 将 sessionToken 持久化到 cookie 中，以支持多实例共享会话
Client::setStorage(new CookieStorage());
Client::useProduction((getenv("LEANCLOUD_APP_ENV") === "production") ? true : false);

SlimEngine::enableHttpsRedirect();
$app->add(new SlimEngine());

// 使用 Slim/PHP-View 作为模版引擎
$container = $app->getContainer();
$container["view"] = function($container) {
    return new \Slim\Views\PhpRenderer(__DIR__ . "/views/");
};

$container['HomeController'] = function($c) {
    $view = $c->get("view"); // retrieve the 'view' from the container
    return new HomeController($view);
};

$app->get('/test', \HomeController::class . ':home');

$app->get('/', function (Request $request, Response $response) {
    $query = new Query("Portfolios");
    $query->descend("createdAt");
    try {
        $portfolios = $query->equalTo('status', true)->find();
        $query = new Query("Rebalancing");
        foreach ($portfolios as $portfolio) {
          $lastRb = $query->descend("created_at")->equalTo('portfolio', $portfolio);
          if($lastRb->count() > 0){
            $portfolio->rebalancing = $lastRb->first();
          }else{
            $portfolio->rebalancing = null;
          }

        }
    } catch (\Exception $ex) {
        error_log("Query portfolio failed!");
        $portfolios = array();
    }
    return $this->view->render($response, "index.phtml", array(
        "currentTime" => new \DateTime(),
        "portfolios" => $portfolios,
    ));
});


// 显示 组合 列表
$app->get('/portfolios', function(Request $request, Response $response) {
    $query = new Query("Portfolios");
    $query->descend("createdAt");
    try {
        $portfolios = $query->find();
    } catch (\Exception $ex) {
        error_log("Query portfolio failed!");
        $portfolios = array();
    }
    return $this->view->render($response, "portfolios.phtml", array(
        "title" => "组合列表",
        "portfolios" => $portfolios,
    ));
});

$app->post("/portfolios", function(Request $request, Response $response) {
    $query = new Query("Portfolios");
    $query->descend("created_at");
    try {
        $data = $request->getParsedBody();
        $portfolioProperty = getLastBebalancingID($data["symbol"]);
        if(!empty($portfolioProperty['name'])){
          $query->equalTo("symbol", $data["symbol"]);
          if($query->count() == 0){
            $portfolio = new LeanObject("Portfolios");
            $portfolio->set("symbol", $data["symbol"]);
            $portfolio->set('name', $portfolioProperty['name']);
            $portfolio->set("last_rb_id", $portfolioProperty['last_rb_id']);
            $portfolio->set("period", $portfolioProperty['period']);
            $portfolio->set("status", true);
            $portfolio->save();
          }

        }
        return $response->withStatus(302)->withHeader("Location", "/portfolios");
    } catch (\Exception $ex) {
        return $response->withStatus(302)->withHeader("Location", "/portfolios");
    }
});

$app->get('/portfolio/{objectId}', function(Request $request, Response $response, $args) {
    $query = new Query("Portfolios");

    //$query->descend("createdAt");
    try {
        $portfolio = $query->get($args['objectId']);
        $allBalanceQuery = new Query("Rebalancing");
        $allBalance = $allBalanceQuery->descend("created_at")->equalTo('portfolio', $portfolio)->find();

    } catch (\Exception $ex) {
        error_log("Query portfolio failed!");
        $portfolio = array();
    }
    return $this->view->render($response, "portfolio.phtml", array("portfolio" => $portfolio, 'allBalance' => $allBalance));
});

$app->get('/portfolio/{objectId}/export', function(Request $request, Response $response, $args) {
    $query = new Query("Portfolios");
    $portfolio = $query->get($args['objectId']);
    $fileName= $portfolio->get('name').'_'.$portfolio->get('symbol').'_'.date('Y-m-d_H:i:s').'.csv';
    $dt = Carbon::create(date('Y'), 1, 1, 0, 0, 0);
    $allBalanceQuery = new Query("Rebalancing");
    $allBalance = $allBalanceQuery->descend("created_at")->equalTo('portfolio', $portfolio)->greaterThan('updated_at',$dt->getTimestamp()*1000)->limit(1000)->find();
    $stream = fopen('php://memory', 'w+');
    $firstRow = ['时间','类别','状态','证券名称','证券代码','前持仓(Prev Weight Adjusted)','后持仓(Target Weight)','成交价(Price)','Volume','Net Value','Weight','Target Weight','Prev Weight','Prev Target Weight','Prev Weight Adjusted','Prev Volume','Prev Price','Prev Net Value','Proactive','Cash','Cash_value','Diff','New Buy Count'];
    fputcsv($stream, $firstRow, ';');
    foreach ($allBalance as $balance) {
        $histories = json_decode($balance->get('rebalancing_histories'));
        $update_at = date("Y-m-d H:i:s", $balance->get('updated_at')/1000);
        $cat = $balance->get('category') == 'user_rebalancing'? '用户调仓' : '系统调仓';
        $status = $balance->get('error_code') == 'null' ? '成功': '部份未成交';
        $cash = $balance->get('cash');
        $cash_value = $balance->get('cash_value');
        $diff = $balance->get('diff');
        $new_buy_count = $balance->get('new_buy_count');
        foreach ($histories as $history) {
          $data = [
            $update_at,
            $cat,
            $status,
            $history->stock_name,
            $history->stock_symbol,
            $history->prev_weight_adjusted == 0 ? '0.00%': number_format($history->prev_weight_adjusted, 2).'%',
            $history->target_weight == 0 ? '0.00%': number_format($history->target_weight, 2).'%',
            $history->price,
            $history->volume,
            $history->net_value,
            $history->weight,
            $history->target_weight,
            $history->prev_weight,
            $history->prev_target_weight,
            $history->prev_weight_adjusted,
            $history->prev_volume,
            $history->prev_price,
            $history->prev_net_value,
            $history->proactive,
            $cash,
            $cash_value,
            $diff,
            $new_buy_count
          ];
          fputcsv($stream, $data, ';');
        }


    }

    rewind($stream);

    $response = $response->withHeader('Content-Type', 'text/csv');
    $response = $response->withHeader('Content-Disposition', 'attachment; filename="'.$fileName.'"');

    return $response->withBody(new \Slim\Http\Stream($stream));
});

$app->get('/portfolio/{objectId}/update', function(Request $request, Response $response, $args) {
    $query = new Query("Portfolios");
    $portfolio = $query->get($args['objectId']);

    try {
        $rbQuery = new Query("Rebalancing");
        //$lastRb = $rbQuery->equalTo('origin_id', (int)$portfolio->get('last_rb_id'))->first();
        $rbQuery->ascend("updated_at");
        $olderRb = $rbQuery->equalTo('portfolio', $portfolio)->first();
        $older_rb_id = $olderRb->get('prev_bebalancing_id');

        if(!empty($older_rb_id)){
          $rebalance = getRebalancing($older_rb_id);

          if(!empty($rebalance)){
            $uniqueRbObj = new Query("Rebalancing");
            $uniqueRbObj->equalTo("origin_id", $rebalance->id);
            if($uniqueRbObj->count() == 0){
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
      }
    } catch (\Exception $ex) {
      if($ex->getCode() == 101){
        $rebalance = getRebalancing($portfolio->get('last_rb_id'));
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
        $rbObj->set("error_code", $rebalance->error_code == null ? 'null':$rebalance->error_code);
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

    //return $this->view->render($response, "portfolio.phtml", array("portfolio" => $portfolio));
});

// 显示 todo 列表
$app->get('/todos', function(Request $request, Response $response) {
    $query = new Query("Todo");
    $query->descend("createdAt");
    try {
        $todos = $query->find();
    } catch (\Exception $ex) {
        error_log("Query todo failed!");
        $todos = array();
    }
    return $this->view->render($response, "todos.phtml", array(
        "title" => "TODO 列表",
        "todos" => $todos,
    ));
});

$app->post("/todos", function(Request $request, Response $response) {
    $data = $request->getParsedBody();
    $todo = new LeanObject("Todo");
    $todo->set("content", $data["content"]);
    $todo->save();
    return $response->withStatus(302)->withHeader("Location", "/todos");
});

$app->get('/hello/{name}', function (Request $request, Response $response) {
    $name = $request->getAttribute('name');
    $response->getBody()->write("Hello, $name");

    return $response;
});

// 显示 todo 列表
$app->get('/mobile', function(Request $request, Response $response) {
  $query = new Query("Portfolios");
  $query->descend("updatedAt");
  try {
      $portfolios = $query->equalTo('status',true)->find();
      $balanceQuery = new Query("Rebalancing");
      foreach ($portfolios as $portfolio) {
        $portfolio->lastBalance = $balanceQuery->descend("created_at")->equalTo('portfolio', $portfolio)->first();
        $dt = new Carbon(unixtime_to_date($portfolio->lastBalance->get('created_at')));
        $portfolio->updatedAtDiff = $dt->locale('zh_CN')->diffForHumans();
      }
  } catch (\Exception $ex) {
      error_log("Query Portfolios failed!");
      $todos = array();
  }

  return $this->view->render($response, "mobile.phtml", array(
      "title" => "监控组合列表",
      "portfolios" => $portfolios,
  ));
});

$app->get('/mobile/p/{objectId}', function(Request $request, Response $response, $args) {
    $query = new Query("Portfolios");

    //$query->descend("createdAt");
    try {
        $portfolio = $query->get($args['objectId']);
        $allBalanceQuery = new Query("Rebalancing");
        $allBalance = $allBalanceQuery->descend("created_at")->equalTo('portfolio', $portfolio)->limit(30)->find();

    } catch (\Exception $ex) {
        error_log("Query portfolio failed!");
        $portfolio = array();
    }
    return $this->view->render($response, "p.phtml", array("portfolio" => $portfolio, 'allBalance' => $allBalance));
});

$app->run();
