<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/cloud.php';
include(__DIR__ . '/../src/alipay/AopSdk.php');
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
use \LeanCloud\Object;
use \LeanCloud\CloudException;

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
Client::useRegion(getenv("LEANCLOUD_REGION"));
Client::useMasterKey(true);

SlimEngine::enableHttpsRedirect();
$app->add(new SlimEngine());

// 使用 Slim/PHP-View 作为模版引擎
$container = $app->getContainer();
$container["view"] = function($container) {
    return new \Slim\Views\PhpRenderer(__DIR__ . "/views/");
};

$app->get('/', function (Request $request, Response $response) {
    return $this->view->render($response, "index.phtml", array(
        "currentTime" => new \DateTime(),
    ));
});
/*
// 显示 todo 列表
$app->get('/todos', function(Request $request, Response $response) {
    $query = new Query("Todo");
    $query->descend("createdAt");
    try {
        $todos = $query->find();
    } catch (Exception $ex) {
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
    $todo = new Object("Todo");
    $todo->set("content", $data["content"]);
    $todo->save();
    return $response->withStatus(302)->withHeader("Location", "/todos");
});

$app->get('/hello/{name}', function (Request $request, Response $response) {
    $name = $request->getAttribute('name');
    $response->getBody()->write("Hello, $name");
    error_log('lwz'.$name);
    return $response;
});
*/
$app->post("/verifyOrder", function(Request $request, Response $response) {
    //error_log('verifyOrder call.');
    $appId = getenv('ALIPAY_appId');
    $seller_id = getenv('ALIPAY_seller_id');
    $alipayrsaPublicKey = getenv('ALIPAY_serverPublicKey');
    if (empty($appId)||empty($alipayrsaPublicKey)||empty($seller_id)) {
        error_log('verifyOrder:支付参数配置错误');
        $response->getBody()->write('failure');
        return $response;
    }

    $aop = new AopClient;
    $aop->alipayrsaPublicKey = $alipayrsaPublicKey;
    $flag = $aop->rsaCheckV1($_POST, NULL, "RSA2");

    $out_trade_no = $_POST['out_trade_no'];
    $trade_status = $_POST['trade_status'];
    //error_log('out_trade_no '.$out_trade_no);
    error_log('trade_status '.$trade_status);
    //error_log('seller_id '.$_POST['seller_id']);
    //error_log('app_id '.$_POST['app_id']);

    if($flag) {
        //1、商户需要验证该通知数据中的out_trade_no是否为商户系统中创建的订单号，
        //2、判断total_amount是否确实为该订单的实际金额（即商户订单创建时的金额），
        //3、校验通知中的seller_id（或者seller_email) 是否为out_trade_no这笔单据的对应的操作方（有的时候，一个商户可能有多个seller_id/seller_email），
        //4、验证app_id是否为该商户本身。
        if ($seller_id != $_POST['seller_id']) {
            error_log('seller_id is not equal');
            $response->getBody()->write('failure');
            return $response;
        }
        if ($appId != $_POST['app_id']) {
            error_log('app_id is not equal');
            $response->getBody()->write('failure');
            return $response;
        }

        $query = new Query('PayOrder');
        try {
            $order = $query->get($out_trade_no);

            if ($trade_status == 'TRADE_SUCCESS' || $trade_status == 'TRADE_FINISHED') {
                $total_amount = floatval($_POST['total_amount']);
                $type = $order->get('type');
                $orderStatus = $order->get('orderStatus');
                $total = floatval($order->get('total'));

                error_log('total_amount '.$total_amount);
                error_log('total '.$total);
                //测试时先屏蔽
                /*
                if ($total_amount != $total) {
                    error_log('total_amount is not equal');
                    $response->getBody()->write('failure');
                    return $response;
                }
                */
                if($orderStatus == 1) {
                    //该订单已处理
                    error_log('out_trade_no '.$out_trade_no.' is repeat.');
                    $response->getBody()->write('success');
                    return $response;
                }

                if ($type == 1) {
                    error_log('user '.$order->get('creater')->getObjectId().' 身份认证支付成功.');
                }
                else if ($type == 2) {
                    error_log('user '.$order->get('creater')->getObjectId().' 视频认证支付成功.');
                }
                else if ($type == 3) {
                    //相应的延长商户的订阅服务时间
                    $query2 = new Query('BusinessConfig');
                    $query2->equalTo('userId', $order->get('creater')->getObjectId());
                    $config = $query2->first();

                    $month = $order->get('count');
                    $until = $config->get('validUntil');

                    $datetime = new \DateTime();
                    if(!empty($until) && $until->getTimestamp() > $datetime->getTimestamp()) 
                    {
                        $datetime = $until;
                    }

                    $interval = new \DateInterval('P'.$month.'M');
                    $datetime->add($interval);

                    $config->set('validUntil', $datetime);
                    $config->save();
                    error_log('user '.$order->get('creater')->getObjectId().' 订阅服务支付成功.');
                }
                $order->set("orderStatus", 1);
                $order->save();

                $response->getBody()->write('success');
                return $response;
            }

            
        }
        catch(CloudException $ex) {
            error_log('out_trade_no '.$out_trade_no.' is error or not find BusinessConfig/IdentityAuth/VideoAuth');
        }
    }

    $response->getBody()->write('failure');
    return $response;
});


/*
$app->get('/generateOrder', function (Request $request, Response $response) {
    $params = $request->getQueryParams();
    $userId = $params["userId"];
    $type = intval($params["type"]);
    $count = intval($params["count"]);
    error_log($userId);
    error_log($type);
    error_log($count);


    if (empty($userId)||empty($type)||empty($count)) {
        $arr = array('code'=>-1);
        $response->getBody()->write(json_encode($arr));
        return $response;
    }

    if ($type < 1 || $type > 3) {
        $arr = array('code'=>-2);
        $response->getBody()->write(json_encode($arr));
        return $response;
    }

    $price;
    $query = new Query("PriceConfig");
    try {
        $price = $query->first();
    }
    catch(CloudException $ex) {
        $arr = array('code'=>-3);
        $response->getBody()->write(json_encode($arr));
        return $response;
    }
    
    $order = new Object("PayOrder");

    $subject;
    $body;
    $unitPrice;
    switch($type){
        case 1:
            $subject = "身份认证-订单号";
            $body = "她他约身份认证";
            $unitPrice = $price->get("idAuth");
            break;
        case 2:
            $subject = "视频认证-订单号";
            $body = "她他约视频认证";
            $unitPrice = $price->get("videoAuth");
            break;
        case 3:
            $subject = "商家订阅服务费*".$count."月-";
            $body = "她他约商家订阅服务费";
            $unitPrice = $price->get("businessService");
            break;
        default:
            
            break;
    }
    
    $total = $unitPrice * $count;

    $order->set("creater", Object::create("_User", $userId));
    $order->set("type", $type);
    $order->set("unitPrice", $unitPrice);
    $order->set("count", $count);
    $order->set("total", $total);
    $order->set("orderStatus", 0);
    try {
        $order->save();
    }
    catch(CloudException $ex) {
        $arr = array('code'=>-4);
        $response->getBody()->write(json_encode($arr));
        return $response;
    }

    $out_trade_no = $order->getObjectId();
    $subject .= $out_trade_no;

    $total = "0.01";

    $aop = new AopClient;
    $aop->gatewayUrl = "https://openapi.alipay.com/gateway.do";
    $aop->appId = "";
    $aop->rsaPrivateKey = '';
    $aop->format = "json";
    $aop->charset = "UTF-8";
    $aop->signType = "RSA2";
    $aop->alipayrsaPublicKey = '';
    //实例化具体API对应的request类,类名称和接口名称对应,当前调用接口名称：alipay.trade.app.pay
    $alipayrequest = new AlipayTradeAppPayRequest();
    //SDK已经封装掉了公共参数，这里只需要传入业务参数
    $bizcontent = "{\"body\":\"$body\"," 
                    . "\"subject\": \"$subject\","
                    . "\"out_trade_no\": \"$out_trade_no\","
                    . "\"timeout_express\": \"30m\"," 
                    . "\"total_amount\": \"$total\","
                    . "\"product_code\":\"QUICK_MSECURITY_PAY\""
                    . "}";

    error_log($bizcontent);

    $alipayrequest->setNotifyUrl("http://stg-tatayue.leanapp.cn/verifyOrder");
    $alipayrequest->setBizContent($bizcontent);
    //这里和普通的接口调用不同，使用的是sdkExecute
    $orderString = $aop->sdkExecute($alipayrequest);
    //htmlspecialchars是为了输出到页面时防止被浏览器将关键参数html转义，实际打印到日志以及http传输不会有这个问题
    //error_log(htmlspecialchars($orderString));//就是orderString 可以直接给客户端请求，无需再做处理。
    //error_log($orderString);

    $arr = array('orderString'=>$orderString,'code'=>0);

    $response->getBody()->write(json_encode($arr));

    return $response;
});
*/
$app->run();

