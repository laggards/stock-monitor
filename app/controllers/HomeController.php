<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Psr\Container\ContainerInterface;
use \LeanCloud\Query;

class HomeController
{
    protected $view;

    public function __construct($view) {
        $this->view = $view;
    }
    public function home($request, $response, $args) {
      // your code here
      // use $this->view to render the HTML
      
      return getLastBebalancingID('ZH1230914');
      return $this->view->render($response, "index.phtml", array(
          "currentTime" => new \DateTime(),
      ));
    }
}
