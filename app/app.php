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
    return $this->view->render($response, "index.phtml", array(
        "currentTime" => new \DateTime(),
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
    try {
        $data = $request->getParsedBody();
        $query = new Query("Portfolios");
        $query->equalTo("symbol", $data["symbol"]);
        if ($query->count() == 0) {
            $portfolioProperty = getLastBebalancingID($data["symbol"]);
            $portfolio = new LeanObject("Portfolios");
            $portfolio->set("symbol", $data["symbol"]);
            $portfolio->set('name', $portfolioProperty['name']);
            $portfolio->set("last_rb_id", $portfolioProperty['last_rb_id']);
            $portfolio->set("period", $portfolioProperty['period']);
            $portfolio->set("status", true);
            $portfolio->save();
            return $response->withStatus(302)->withHeader("Location", "/portfolios");
        } else {
            return $response->withStatus(500)->withHeader('Content-Type', 'text/html')->write('Something went wrong!');
        }
    } catch (\Exception $ex) {
        return $response->withStatus(302)->withHeader("Location", "/portfolios");
    }

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


$app->run();
