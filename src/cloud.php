<?php

use \LeanCloud\Engine\Cloud;
use \LeanCloud\Query;
use \LeanCloud\Object;
use \LeanCloud\SaveOption;
use \LeanCloud\CloudException;
use \LeanCloud\Engine\FunctionError;

/*
 * Define cloud functions and hooks on LeanCloud
 */
/*
// /1.1/functions/sayHello
Cloud::define("sayHello", function($params, $user) {
    return "hello {$params["name"]}";
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


Cloud::define("sayHello", function($params, $user) {
    error_log('LEANCLOUD_APP_ID:'.getenv("LEANCLOUD_APP_ID"));
    error_log('LEANCLOUD_APP_MASTER_KEY:'.getenv("LEANCLOUD_APP_MASTER_KEY"));
    $client = new GuzzleHttp\Client(['headers' => [
        'X-LC-Id' => getenv("LEANCLOUD_APP_ID"), 
        'X-LC-Key' => getenv("LEANCLOUD_APP_MASTER_KEY").',master',
        'Content-Type' => 'application/json']
        ]);
    $client->request('POST', 'https://api.leancloud.cn/1.1/rtm/messages', [
        'json' => ['from_peer' => 'NoticeMessage',
                    'to_peers' => ['5944a9d75c497d006bdb8f13'],
                    'message' => ['_lctype' => 2,
                                  '_lctext' => '给你的心情点了赞',
                                  '_lcattrs' => ['type' => 2,
                                                'typeTitle' => '您有一条通知',
                                                'fromId' => '593ea352ac502e006c139972',
                                                'sid' => '598a97bc8d6d810062340008',
                                  ],
                    ],
                    'conv_id' => '5955040cac502e006077817b',
                    'transient' => false
        ]
    ]);

    //$code = $response->getStatusCode(); // 200
    //$body = $response->getBody();
    //error_log('code:'.$code);
    //error_log('body:'.$body);
    //'{"from_peer": "NoticeMessage", "to_peers":["593ea352ac502e006c139972"],"message": "{\"_lctype\":2,\"_lctext\":\"给你的心情点了赞\",\"_lcattrs\":{\"type\":2,\"typeTitle\":\"您有一条通知\",\"fromId\":\"5944a9d75c497d006bdb8f13\",\"sid\":\"598a97bc8d6d810062340008\"}}", "conv_id": "5955040cac502e006077817b", "transient": false}'

    // model.send('NoticeMessage'
            // ,'{\"_lctype\":2,\"_lctext\":\"给你的心情点了赞\",\"_lcattrs\":{\"type\":2,\"typeTitle\":\"您有一条通知\",\"fromId\":\"' + request.object.get('user').id +'\",\"sid\":\"' + request.object.get('status').id + '\"}}'
            // , {"toClients":[status.get('creater').id]});

    return 'Hello333';
});


Cloud::define("sayHello", function($params, $user) {
    $month = 2;
    $until = new \DateTime('2017-07-30');

    $datetime = new \DateTime();

    if($until->getTimestamp() > $datetime->getTimestamp()) {
        $datetime = $until;
    }

    $interval = new \DateInterval('P'.$month.'M');
    $datetime->add($interval);
    error_log($datetime->format('Y-m-d H:i:s'));
    return 'Hello '.$datetime->format('Y-m-d H:i:s');
});
*/
Cloud::define("generateOrder", function($params, $user) {
    $type = intval($params["type"]);
    $count = intval($params["count"]);
    //error_log($type);
    //error_log($count);

    if (empty($user)) {
        return array('errcode' => -5, 'message' => '用户未登录');
    }

    $userId = $user->getObjectId();
    //error_log($userId);

    $appId = getenv('ALIPAY_appId');
    $rsaPrivateKey = getenv('ALIPAY_userPrivateKey');
    $alipayrsaPublicKey = getenv('ALIPAY_serverPublicKey');
    $notifyUrl = getenv('ALIPAY_notifyUrl');
    $seller_id = getenv('ALIPAY_seller_id');

    if (empty($appId)||empty($rsaPrivateKey)||empty($alipayrsaPublicKey)||empty($notifyUrl)||empty($seller_id)) {
        return array('errcode' => -6, 'message' => '支付参数配置错误');
    }

    if (empty($userId)||empty($type)||empty($count)) {
        return array('errcode' => -1, 'message' => '传入参数错误');
    }

    if ($type < 1 || $type > 3) {
        return array('errcode' => -2, 'message' => '参数类型错误');
    }

    $price;
    $query = new Query("PriceConfig");
    try {
        $price = $query->first();
    }
    catch(CloudException $ex) {
        return array('errcode' => -3, 'message' => '查询配置信息错误');
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
        return array('errcode' => -4, 'message' => '生成订单错误');
    }

    try {
        if ($type == 1) {
            $query2 = new Query('IdentityAuth');
            $query2->equalTo('creater', $order->get('creater'));
            $model = $query2->first();
            $model->set('orderId', $order->getObjectId());
            $model->save();
        }
        else if ($type == 2) {
            $query2 = new Query('VideoAuth');
            $query2->equalTo('creater', $order->get('creater'));
            $model = $query2->first();
            $model->set('orderId', $order->getObjectId());
            $model->save();
        }
    }
    catch(CloudException $ex) {
        return array('errcode' => -4, 'message' => '关联数据错误');
    }
    

    $out_trade_no = $order->getObjectId();
    $subject .= $out_trade_no;

    $total = "0.01";

    $aop = new AopClient;
    $aop->gatewayUrl = "https://openapi.alipay.com/gateway.do";
    $aop->appId = $appId;
    $aop->rsaPrivateKey = $rsaPrivateKey;
    $aop->format = "json";
    $aop->charset = "UTF-8";
    $aop->signType = "RSA2";
    $aop->alipayrsaPublicKey = $alipayrsaPublicKey;
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

    $alipayrequest->setNotifyUrl($notifyUrl);
    $alipayrequest->setBizContent($bizcontent);
    //这里和普通的接口调用不同，使用的是sdkExecute
    $orderString = $aop->sdkExecute($alipayrequest);
    //htmlspecialchars是为了输出到页面时防止被浏览器将关键参数html转义，实际打印到日志以及http传输不会有这个问题
    //error_log(htmlspecialchars($orderString));//就是orderString 可以直接给客户端请求，无需再做处理。
    //error_log($orderString);

    $order->set("orderString", $orderString);
    try {
        $order->save();
    }
    catch(CloudException $ex) {
        
    }

    return array('orderString' => $orderString, 'errcode' => 0);
});

Cloud::define('_messageReceived', function($params, $user) {
    $fromPeer = $params['fromPeer'];
    $convId = $params['convId'];
    $toPeers = $params['toPeers'];
    $content = $params['content'];

    //error_log('fromPeer:'.$fromPeer);
    //error_log('convId:'.$convId);
    //error_log('toPeers:'.$toPeers);
    //error_log('content:'.$content);
    
    $query = new Query('ConversationBlackList');
    $query->containedIn('createrId', $toPeers);
    $query->equalTo('userId', $fromPeer);
    $list;
    try {
        $list = $query->find();
    } catch (Exception $e) {
        return array();
    }
    

    if (count($list)) {
        if (count($list) == count($toPeers)) {
            error_log('drop message convId '.$convId.' fromPeer '.$fromPeer);
            return array("drop" => true);
        }
        else {
            //屏蔽黑名单消息
            for ($i = count($toPeers) - 1; $i >= 0; $i--) {
                for ($j = count($list) - 1; $j >= 0; $j--) {
                    $bl = $list[j];
                    if ($toPeers[i] == $bl->get('createrId')) {
                        array_splice($toPeers, i, 1);
                        break;
                    }
                }
            }
            error_log('change message convId '.$convId.' fromPeer '.$fromPeer.' toPeers '.$toPeers);
            return array("toPeers" => $toPeers);
        }
    }
    
    return array();
});


Cloud::define('checkActivityPush', function($params, $user) {
    $query1 = new Query('ActivityPushTemp');
    $query1->equalTo('state', 0);
    $query1->lessThanOrEqualTo('pushDate', new \DateTime());
    $pushList = $query1->find();

    if(count($pushList) > 0) {
        error_log('has push :'.count($pushList));
        forEach ($pushList as $push) {
            $type = $push->get('type');
            if($type == 1) {
                //当商家发起活动推送时，查询关注者并发送通知
                $creater = Object::create("_User", $push->get('userId'));
                $query = new Query('_Follower');
                $query->equalTo('user', $creater);
                $results = $query->find();
                error_log('query follower:'.count($results));
                if(count($results) > 0) {
                    $arr = array();
                    forEach ($results as $follower) {
                        //console.log('followerId:' + results[index].get('follower').id);
                        array_push($arr, $follower->get('follower')->getObjectId());
                    }

                    //var str ='{\"_lctype\":2,\"_lctext\":\"' + request.object.get('pushTitle') +'\",\"_lcattrs\":{\"type\":6,\"typeTitle\":\"' + request.object.get('pushTitle') +'\",\"fromId\":\"' + request.object.get('creater').id +'\",\"sid\":\"' + request.object.id + '\"}}';
                    //console.log('message:' + str);

                        // model.send('NoticeMessage'
                        //     ,'{\"_lctype\":2,\"_lctext\":\"' + push.get('pushTitle') +'\",\"_lcattrs\":{\"type\":6,\"typeTitle\":\"' + push.get('pushTitle') +'\",\"fromId\":\"' + push.get('userId') +'\",\"sid\":\"' + push.get('statusId') + '\"}}'
                        //     , {"toClients": arr});

                    $client = new GuzzleHttp\Client(['headers' => [
                        'X-LC-Id' => getenv("LEANCLOUD_APP_ID"), 
                        'X-LC-Key' => getenv("LEANCLOUD_APP_MASTER_KEY").',master',
                        'Content-Type' => 'application/json']
                        ]);
                    $client->request('POST', 'https://api.leancloud.cn/1.1/rtm/messages', [
                        'json' => ['from_peer' => 'NoticeMessage',
                                    'to_peers' => arr,
                                    'message' => ['_lctype' => 2,
                                                  '_lctext' => $push->get('pushTitle'),
                                                  '_lcattrs' => ['type' => 6,
                                                                'typeTitle' => $push->get('pushTitle'),
                                                                'fromId' => $push->get('userId'),
                                                                'sid' => $push->get('statusId')
                                                  ],
                                    ],
                                    'conv_id' => '595cfbe361ff4b006476c77c',
                                    'transient' => false
                        ]
                    ]);
                    
                    $push->set("state", 1);
                    $push->save();
                    error_log('send activity message.');
                    
                }
                else {
                    $push->set("state", 1);
                    $push->save();
                    error_log('nobody follower.');
                }
            }
            else if($type == 2) {
                $client = new GuzzleHttp\Client(['headers' => [
                        'X-LC-Id' => getenv("LEANCLOUD_APP_ID"), 
                        'X-LC-Key' => getenv("LEANCLOUD_APP_MASTER_KEY").',master',
                        'Content-Type' => 'application/json']
                        ]);
                $client->request('POST', 'https://api.leancloud.cn/1.1/rtm/broadcast', [
                    'json' => ['from_peer' => 'SystemMessage',
                                'message' => ['_lctype' => 2,
                                              '_lctext' => $push->get('pushTitle'),
                                              '_lcattrs' => ['type' => 6,
                                                            'typeTitle' => $push->get('pushTitle'),
                                                            'sid' => $push->get('statusId')
                                              ],
                                ],
                                'conv_id' => '594f297da22b9d005918deaf',
                                'transient' => false
                    ]
                ]);
                
                    // model.broadcast('SystemMessage','{\"_lctype\":2,\"_lctext\":\"' + push.get('pushTitle') +'\",\"_lcattrs\":{\"typeTitle\":\"' + push.get('pushTitle') +'\",\"sid\":\"' + push.get('statusId') + '\"}}');
                
                $push->set("state", 1);
                $push->save();
                error_log('send broadcast message.');
            }
        }
    }
    
});


Cloud::beforeSave('ConversationBlackList', function($obj, $user) {
    $creater = $obj->get('creater');
    if (!empty($creater)) 
    {
        $obj->set('createrId', $creater->getObjectId());
        error_log('set createrId '.$creater->getObjectId());
    }
});

Cloud::afterSave('_Followee', function($obj, $user) {
    $client = new GuzzleHttp\Client(['headers' => [
        'X-LC-Id' => getenv("LEANCLOUD_APP_ID"), 
        'X-LC-Key' => getenv("LEANCLOUD_APP_MASTER_KEY").',master',
        'Content-Type' => 'application/json']
        ]);
    $client->request('POST', 'https://api.leancloud.cn/1.1/rtm/messages', [
        'json' => ['from_peer' => 'NoticeMessage',
                    'to_peers' => [$obj->get('followee')->getObjectId()],
                    'message' => ['_lctype' => 2,
                                  '_lctext' => '刚刚关注了你',
                                  '_lcattrs' => ['type' => 1,
                                                'typeTitle' => '您有一条通知',
                                                'fromId' => $obj->get('user')->getObjectId()
                                  ],
                    ],
                    'conv_id' => '5951c0bcac502e0060758c32',
                    'transient' => false
        ]
    ]);
    
        // model.send('NoticeMessage'
        //     ,'{\"_lctype\":2,\"_lctext\":\"刚刚关注了你\",\"_lcattrs\":{\"type\":1,\"typeTitle\":\"您有一条通知\",\"fromId\":\"' + request.object.get('user').id +'\"}}'
        //     , {"toClients":[request.object.get('followee').id]});

        //model.broadcast(request.object.get('user').id,'{\"_lctype\":2,\"_lctext\":\"刚刚关注了你\",\"_lcattrs\":{\"typeTitle\":\"您有一条通知\"}}');
    
    error_log('send followee message.');
});

Cloud::beforeSave("UserStatusLikes", function($obj, $user) {
    $query = new Query('UserStatus');
    $status = $query->get($obj->get('status')->getObjectId());
    $status->increment('praise');
    $status->save();
    error_log('like status done.');
}

Cloud::afterSave("UserStatusLikes", function($obj, $user) {
    if($obj->get('user')->getObjectId() == $status->get('creater')->getObjectId()) {
        error_log('myself like.');
        return;
    }

    $client = new GuzzleHttp\Client(['headers' => [
        'X-LC-Id' => getenv("LEANCLOUD_APP_ID"), 
        'X-LC-Key' => getenv("LEANCLOUD_APP_MASTER_KEY").',master',
        'Content-Type' => 'application/json']
        ]);
    $client->request('POST', 'https://api.leancloud.cn/1.1/rtm/messages', [
        'json' => ['from_peer' => 'NoticeMessage',
                    'to_peers' => [$status->get('creater')->getObjectId()],
                    'message' => ['_lctype' => 2,
                                  '_lctext' => '给你的心情点了赞',
                                  '_lcattrs' => ['type' => 2,
                                                'typeTitle' => '您有一条通知',
                                                'fromId' => $obj->get('user')->getObjectId(),
                                                'sid' => $status->getObjectId(),
                                  ],
                    ],
                    'conv_id' => '5955040cac502e006077817b',
                    'transient' => false
        ]
    ]);
            // model.send('NoticeMessage'
            // ,'{\"_lctype\":2,\"_lctext\":\"给你的心情点了赞\",\"_lcattrs\":{\"type\":2,\"typeTitle\":\"您有一条通知\",\"fromId\":\"' + request.object.get('user').id +'\",\"sid\":\"' + request.object.get('status').id + '\"}}'
            // , {"toClients":[status.get('creater').id]});
    
    error_log('send status message.');
});

Cloud::beforeDelete('UserStatusLikes', function($obj, $user) {
    $query = new Query('UserStatus');
    $status = $query->get($obj->get('status')->getObjectId());

    if ($status->get('praise') < 0) {
        $query3 = new Query('ForumPostsLikes');
        $query3->equalTo('post', $obj->get('post'));
        $status->set('praise', $query3->count());
        $status->save();
        error_log('status '.$obj->get('status')->getObjectId().' change like.');
    }
    $status->increment('praise', -1);

    $query2 = new Query('UserStatus');
    $query2->greaterThan('praise', 0);

    $option = new SaveOption();
    $option->where = $query2;

    $status->save($option);

    error_log('cancel like status.');
});

Cloud::beforeSave('ForumPostsLikes', function($obj, $user) {
    $query = new Query('ForumPosts');
    $post = $query->get($obj->get('post')->getObjectId());
    $post->increment('praise');
    $post->save();
    error_log('like post done.');
}

Cloud::afterSave('ForumPostsLikes', function($obj, $user) {
    if($obj->get('user')->getObjectId() == $post->get('creater')->getObjectId()) {
        error_log('myself like.');
        return;
    }

    $client = new GuzzleHttp\Client(['headers' => [
        'X-LC-Id' => getenv("LEANCLOUD_APP_ID"), 
        'X-LC-Key' => getenv("LEANCLOUD_APP_MASTER_KEY").',master',
        'Content-Type' => 'application/json']
        ]);
    $client->request('POST', 'https://api.leancloud.cn/1.1/rtm/messages', [
        'json' => ['from_peer' => 'NoticeMessage',
                    'to_peers' => [$post->get('creater')->getObjectId()],
                    'message' => ['_lctype' => 2,
                                  '_lctext' => '给你的帖子点了赞',
                                  '_lcattrs' => ['type' => 3,
                                                'typeTitle' => '您有一条通知',
                                                'fromId' => $obj->get('user')->getObjectId(),
                                                'pid' => $post->getObjectId(),
                                  ],
                    ],
                    'conv_id' => '5955040cac502e006077817b',
                    'transient' => false
        ]
    ]);
            // model.send('NoticeMessage'
            // ,'{\"_lctype\":2,\"_lctext\":\"给你的帖子点了赞\",\"_lcattrs\":{\"type\":3,\"typeTitle\":\"您有一条通知\",\"fromId\":\"' + request.object.get('user').id +'\",\"pid\":\"' + request.object.get('post').id + '\"}}'
            // , {"toClients":[post.get('creater').id]});
    
    error_log('send post message.');
});

Cloud::beforeDelete('ForumPostsLikes', function($obj, $user) {
    $query = new Query('ForumPosts');
    $post = $query->get($obj->get('post')->getObjectId());

    if ($post->get('praise') < 0) {
        $query3 = new Query('ForumPostsLikes');
        $query3->equalTo('post', $obj->get('post'));
        $post->set('praise', $query3->count());
        $post->save();
        error_log('post '.$obj->get('post')->getObjectId().' change like.');
    }
    $post->increment('praise', -1);

    $query2 = new Query('ForumPosts');
    $query2->greaterThan('praise', 0);

    $option = new SaveOption();
    $option->where = $query2;

    $post->save($option);
    
    error_log('cancel like post.');
});

Cloud::beforeSave('ForumComments', function($obj, $user) {
    $query = new Query('ForumPosts');
    $post = $query->get($obj->get('post')->getObjectId());
    $post->increment('commentCount');
    $post->save();
    error_log('comment done.');
}

Cloud::afterSave('ForumComments', function($obj, $user) {
    if($obj->get('creater')->getObjectId() == $post->get('creater')->getObjectId()) {
        error_log('myself comment.');
        return;
    }

    $client = new GuzzleHttp\Client(['headers' => [
        'X-LC-Id' => getenv("LEANCLOUD_APP_ID"), 
        'X-LC-Key' => getenv("LEANCLOUD_APP_MASTER_KEY").',master',
        'Content-Type' => 'application/json']
        ]);
    $client->request('POST', 'https://api.leancloud.cn/1.1/rtm/messages', [
        'json' => ['from_peer' => 'NoticeMessage',
                    'to_peers' => [$post->get('creater')->getObjectId()],
                    'message' => ['_lctype' => 2,
                                  '_lctext' => '刚刚评论了你的帖子',
                                  '_lcattrs' => ['type' => 4,
                                                'typeTitle' => '您有一条通知',
                                                'fromId' => $obj->get('creater')->getObjectId(),
                                                'pid' => $post->getObjectId(),
                                                'cid' => $obj->getObjectId(),
                                  ],
                    ],
                    'conv_id' => '595503e58fd9c5005f250b01',
                    'transient' => false
        ]
    ]);
    
        // model.send('NoticeMessage'
        // ,'{\"_lctype\":2,\"_lctext\":\"刚刚评论了你的帖子\",\"_lcattrs\":{\"type\":4,\"typeTitle\":\"您有一条通知\",\"fromId\":\"' + request.object.get('creater').id +'\",\"pid\":\"' + request.object.get('post').id +'\",\"cid\":\"' + request.object.id + '\"}}'
        // , {"toClients":[post.get('creater').id]});
    
    error_log('send comment message.');
});

Cloud::afterSave('ForumCommentReplies', function($obj, $user) {
    $query = new Query('ForumComments');
    $comment = $query->get($obj->get('comment')->getObjectId());
    $comment->addIn('replies', $obj);
    $comment->save();
    error_log('add reply done.');
    if($obj->get('creater')->getObjectId() != $comment->get('creater')->getObjectId())
    {

        $client = new GuzzleHttp\Client(['headers' => [
        'X-LC-Id' => getenv("LEANCLOUD_APP_ID"), 
        'X-LC-Key' => getenv("LEANCLOUD_APP_MASTER_KEY").',master',
        'Content-Type' => 'application/json']
        ]);
        $client->request('POST', 'https://api.leancloud.cn/1.1/rtm/messages', [
            'json' => ['from_peer' => 'NoticeMessage',
                        'to_peers' => [$comment->get('creater')->getObjectId()],
                        'message' => ['_lctype' => 2,
                                      '_lctext' => '刚刚回复了你的评论',
                                      '_lcattrs' => ['type' => 5,
                                                    'typeTitle' => '您有一条通知',
                                                    'fromId' => $obj->get('creater')->getObjectId(),
                                                    'pid' => $comment->get('post')->getObjectId(),
                                                    'cid' => $comment->getObjectId(),
                                      ],
                        ],
                        'conv_id' => '595503e58fd9c5005f250b01',
                        'transient' => false
            ]
        ]);
        
        // model.send('NoticeMessage'
        //         ,'{\"_lctype\":2,\"_lctext\":\"刚刚回复了你的评论\",\"_lcattrs\":{\"type\":5,\"typeTitle\":\"您有一条通知\",\"fromId\":\"' + request.object.get('creater').id +'\",\"pid\":\"' + comment.get('post').id +'\",\"cid\":\"' + comment.id + '\"}}'
        //         , {"toClients":[comment.get('creater').id]});
        
        error_log('send reply message.');
    }
});

Cloud::afterUpdate('BusinessApply', function($obj, $user) {
  if($obj->get('state') == 1) {
    //当申请商户审核通过后，把信息更新至UserDetail表中
    $query = new Query('UserDetail');
    $query->equalTo('userId', $obj->get('creater')->getObjectId());
    $detail = $query->first();
    $detail->set('name', $obj->get('name'));
    $detail->set('address', $obj->get('area'));
    $detail->set('address2', $obj->get('address'));
    $detail->set('phone', $obj->get('phone'));
    $detail->set('brief', $obj->get('brief'));
    $detail->save();
    error_log('update detail done.'); 
  }
});

Cloud::afterUpdate('IdentityAuth', function($obj, $user) {
  if($obj->get('state') == 1) {
    //当身份认证审核通过后，更新_User表字段
    $creater = $obj->get('creater');
    $creater->fetch();
    $level = $creater->get('authLevel');
    if($level == 2) {
        $creater->set('authLevel', 3);
        $creater->save();
    error_log('user '.$creater->getObjectId().' set authLevel '.$creater->get('authLevel')); 
    }
    else if($level == 0) {
        $creater->set('authLevel', 1);
        $creater->save();
    error_log('user '.$creater->getObjectId().' set authLevel '.$creater->get('authLevel')); 
    }
  }
});

Cloud::afterUpdate('VideoAuth', function($obj, $user) {
  if($obj->get('state') == 1) {
    //当视频认证审核通过后，更新_User表字段
    $creater = $obj->get('creater');
    $creater->fetch();
    $level = $creater->get('authLevel');
    if($level == 1) {
        $creater->set('authLevel', 3);
        $creater->save();
    error_log('user '.$creater->getObjectId().' set authLevel '.$creater->get('authLevel')); 
    }
    else if($level == 0) {
        $creater->set('authLevel', 2);
        $creater->save();
    error_log('user '.$creater->getObjectId().' set authLevel '.$creater->get('authLevel')); 
    }
  }
});

Cloud::onLogin(function($user) {
    // 如果正常执行，则用户将正常登录
    $active = $user['active'];
    if ($active == false) {
        // 如果是 error 回调，则用户无法登录（收到 142 响应）
        throw new FunctionError('该用户已被禁用', 142);
    }
});


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
