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
    $aop = new AopClient;
    $aop->alipayrsaPublicKey = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAnuE6yS6NGUJq7994smQ5NKtgnpHCiso0CUjrB9fGjkhnlpopKTgGYPSEzo+EsonnOIW8hO8khfcjp3Eru28Jfo/UY0KUk5F1mUQ4llvFu3biSJPLrBYp/EsHlr8oIr6tRGzJZIAEhaBqJ5akptAsXkPk9GfftrHpVjpKf8SfuZhmEb8qgqLjC3XsyVLU33OdIP3CfoxvFfdNXVWCH9Ajt81HpNg2MvAhOrcErVUOr4H0P3r487JMRhHveKbybe8pm0VbUEZ5Rd+gVIqmvDAhVvcgdMMNiFoNXTs0Hk9HiZu9nnTN6mAxKf+qHDbNC+r/YKysj5B5T35TBkRSGg2slQIDAQAB';
    $flag = $aop->rsaCheckV1($_POST, NULL, "RSA2");

    //1、商户需要验证该通知数据中的out_trade_no是否为商户系统中创建的订单号，
    //2、判断total_amount是否确实为该订单的实际金额（即商户订单创建时的金额），
    //3、校验通知中的seller_id（或者seller_email) 是否为out_trade_no这笔单据的对应的操作方（有的时候，一个商户可能有多个seller_id/seller_email），
    //4、验证app_id是否为该商户本身。
    error_log('out_trade_no'.$_POST["out_trade_no"]);
    error_log('total_amount'.$_POST["total_amount"]);

    if($flag) {
        //$response->getBody()->write("success");

        $query = new Query("PayOrder");
        try {
            $order = $query->get($_POST["out_trade_no"]);
            $total_amount = $_POST["total_amount"];
            
            if ($order->get("type") == 3) {
                $query2 = new Query("BusinessConfig");
                $config = $query2->get($order->get("creater")->getObjectId());
                $date = new DateTime('2017-08-30');
                $config->set("validUntil", $date);
                $config->save();
                error_log("OYEYE");
                $response->getBody()->write("success");
            }
            else {
                $response->getBody()->write("success");
            }
        }
        catch(CloudException $ex) {
            $response->getBody()->write("failure");
        }
    }
    else {
        $response->getBody()->write("failure");
    }

    return $response;
});



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
    $aop->appId = "2017070107616733";
    $aop->rsaPrivateKey = 'MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQCYztLQ+SMTsnZt+7IhohIdTGq2mqOiTJv/+L26AYMnYQjheVIljydjWqHiMMb1eQZ7Kdg48mL2NwtMROYDa1SYJwC+oeG+9BVp8/mH1jGwV1weAn/Wu7yu8xUvxcoS5LF3w1Lej6uUWQBG8vkEuilzJG3UYP1nGj3TBRPKydjV0CK/2IPee7e9xl0og7978Bv2FVIOd5hyrenVCU2qIVLCQdgAr7+ZlNQImOAIl1M9v79zDw/WoPb0FxFWEMGVmO+K9KSq/fgq0rU3iyuH54cMIHbR1v0uBaK2dzbDNni6OtIEZ8DOk+Eh4GqsLImHLgWuC3xK10amJjX5B98y72zfAgMBAAECggEAJL3mNtUQuBW7ICra4/diP6U2K333RnkBMYUPqX/fl0JfrkdLlzhakisirY5o6HEXO9oN4XN2lBkcIFSYsc3G42bNaQjnjNCHrZg6MY0xGWOIBLc5Idq2PaK5P2lhczWF7nQKovUMnnjf9i9J7PcOLF9gASbpBzdqEikwXxw1hQNNg0HYhdttSlluNGmhFQy6l4cz4RXeAEAsqbc7JQZfKpv8ux/BWQR8nnb8GoH/pZjV2C8SrPDkAnSnhBYEqhrQua0WvMd2/qyt1R1fmssMWpIb2+rqlt/iHbHI/MLQOqctyDL+LOAsp+B2jmKPFsgxElpKNxyJAg43mJaiJWxuUQKBgQDOn0Qr01wdzQ/1OUzqezFEd57P/MtbSfqE5pPU2NbQ8239kDaym1HYKQpaKgXQK4DGjtvFHN4zCE+itxuoe8p0Z6fYBysUiON3vxtuTotItKsfmfu7y+8nVWxaAqpsqxU2vOoeuwFoYzwvEpQbITrhGZWVlvh+/DicLJpqswhonQKBgQC9U08btjEQJdMFni7pjxfwaSbhltOqdVANmKaVw2R/QfVc14ccpDsHhbEd+FUboAfgNr8GCdv2lfVXzMckmcx8TLJ1Vm0IP4X1qvDp1QQj/0008HXPFOm5o7dpyeAWG5F4lqjn1nM49pLXxcQCw8v16dNvTmt3RTefNiak8YP8qwKBgChyWujtVgHra21Iizr3ZJyPggIa7T/wil7LuDKZQ+vhSy2wtlRePTZASmt+AGdQrMOxoWnDjeeVf+lNSNfBa88/n0aVmKRLa6O8QEVmkLNp0nm8LeAEOsuLWEuCbBQbpWpyrq3XU544lsZsL5vj9F+uH28J/5j0DKzduliatVGtAoGAG6Mwtivnh6Lt5jEMSh4QcZD4ExBwf762W/W/w7cNUaJwTghMefrjfxqeG3DoA6td2vZC9n+z85A6i4GiRI6LEk4j8wsVyZF0XcOBfbER9KtNOwArQnqcD/R9Tt0gcDnAB6l+qLFeip88GnGNRpYMjS6AJgx9laCuGPjPtV5oVRcCgYEAsorpGBvKEWxcOAowYQ3dAQi5ZhofSdHH4xEqhH0fH7IEaLLZ217n1hog6dWPYtES0L5Qo6nkQR/z/Qu6f4IZdkINFj0EG9I5XrZi/2d/4w4CG64WNilU0EQat3fnb+HK06KCnuxLTc0Pve7ZYJOMWjjZB39K7EX1z+c+XLHQvqI=';
    $aop->format = "json";
    $aop->charset = "UTF-8";
    $aop->signType = "RSA2";
    $aop->alipayrsaPublicKey = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAnuE6yS6NGUJq7994smQ5NKtgnpHCiso0CUjrB9fGjkhnlpopKTgGYPSEzo+EsonnOIW8hO8khfcjp3Eru28Jfo/UY0KUk5F1mUQ4llvFu3biSJPLrBYp/EsHlr8oIr6tRGzJZIAEhaBqJ5akptAsXkPk9GfftrHpVjpKf8SfuZhmEb8qgqLjC3XsyVLU33OdIP3CfoxvFfdNXVWCH9Ajt81HpNg2MvAhOrcErVUOr4H0P3r487JMRhHveKbybe8pm0VbUEZ5Rd+gVIqmvDAhVvcgdMMNiFoNXTs0Hk9HiZu9nnTN6mAxKf+qHDbNC+r/YKysj5B5T35TBkRSGg2slQIDAQAB';
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

$app->run();

