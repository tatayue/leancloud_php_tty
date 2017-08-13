<?php

use \LeanCloud\Engine\Cloud;
use \LeanCloud\Query;
use \LeanCloud\Object;
use \LeanCloud\CloudException;

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
*/

Cloud::define('_messageReceived', function($params, $user) {
    $fromPeer = $params['fromPeer'];
    $convId = $params['convId'];
    $toPeers = $params['toPeers'];
    $content = $params['content'];

    error_log('fromPeer'.$fromPeer);
    error_log('convId'.$convId);
    error_log('toPeers'.$toPeers);
    error_log('content'.$content);
    
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

                    $query2 = new Query('_Conversation');
                    $model = $query2->get('595cfbe361ff4b006476c77c');
                    
                        // model.send('NoticeMessage'
                        //     ,'{\"_lctype\":2,\"_lctext\":\"' + push.get('pushTitle') +'\",\"_lcattrs\":{\"type\":6,\"typeTitle\":\"' + push.get('pushTitle') +'\",\"fromId\":\"' + push.get('userId') +'\",\"sid\":\"' + push.get('statusId') + '\"}}'
                        //     , {"toClients": arr});
                    
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
                $query2 = new Query('_Conversation');
                $model = $query2->get('594f297da22b9d005918deaf');
                
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
    $query = new Query('_Conversation');
    $model = $query->get('5951c0bcac502e0060758c32');
    
        // model.send('NoticeMessage'
        //     ,'{\"_lctype\":2,\"_lctext\":\"刚刚关注了你\",\"_lcattrs\":{\"type\":1,\"typeTitle\":\"您有一条通知\",\"fromId\":\"' + request.object.get('user').id +'\"}}'
        //     , {"toClients":[request.object.get('followee').id]});

        //model.broadcast(request.object.get('user').id,'{\"_lctype\":2,\"_lctext\":\"刚刚关注了你\",\"_lcattrs\":{\"typeTitle\":\"您有一条通知\"}}');
    
    error_log('send followee message.');
});

Cloud::afterSave("UserStatusLikes", function($obj, $user) {
    $query = new Query('UserStatus');
    $status = $query->get($obj->get('status')->getObjectId());
    $status->increment('praise');
    $status->save();
    error_log('like status done.');

    if($obj->get('user')->getObjectId() != $status->get('creater')->getObjectId()) {
        error_log('myself like.');
        return;
    }

    $query2 = new Query('_Conversation');
    $model = $query2->get('5955040cac502e006077817b');
    
            // model.send('NoticeMessage'
            // ,'{\"_lctype\":2,\"_lctext\":\"给你的心情点了赞\",\"_lcattrs\":{\"type\":2,\"typeTitle\":\"您有一条通知\",\"fromId\":\"' + request.object.get('user').id +'\",\"sid\":\"' + request.object.get('status').id + '\"}}'
            // , {"toClients":[status.get('creater').id]});
    
    error_log('send status message.');
});

Cloud::afterDelete('UserStatusLikes', function($obj, $user) {
    $query = new Query('UserStatus');
    $status = $query->get($obj->get('status')->getObjectId());
    $status->increment('praise', -1);
    $status->save();
    error_log('cancel like status.');
});

Cloud::afterSave('ForumPostsLikes', function($obj, $user) {
    $query = new Query('ForumPosts');
    $post = $query->get($obj->get('post')->getObjectId());
    $post->increment('praise');
    $post->save();
    error_log('like post done.');

    if($obj->get('user')->getObjectId() != $post->get('creater')->getObjectId()) {
        error_log('myself like.');
        return;
    }

    $query2 = new Query('_Conversation');
    $model = $query2->get('5955040cac502e006077817b');
    
            // model.send('NoticeMessage'
            // ,'{\"_lctype\":2,\"_lctext\":\"给你的帖子点了赞\",\"_lcattrs\":{\"type\":3,\"typeTitle\":\"您有一条通知\",\"fromId\":\"' + request.object.get('user').id +'\",\"pid\":\"' + request.object.get('post').id + '\"}}'
            // , {"toClients":[post.get('creater').id]});
    
    error_log('send post message.');
});

Cloud::afterDelete('ForumPostsLikes', function($obj, $user) {
    $query = new Query('ForumPosts');
    $post = $query->get($obj->get('post')->getObjectId());
    $post->increment('praise', -1);
    $post->save();
    error_log('cancel like post.');
});

Cloud::afterSave('ForumComments', function($obj, $user) {
    $query = new Query('ForumPosts');
    $post = $query->get($obj->get('post')->getObjectId());
    $post->increment('commentCount');
    $post->save();
    error_log('comment done.');

    if($obj->get('creater')->getObjectId() != $post->get('creater')->getObjectId()) {
        error_log('myself comment.');
        return;
    }

    $query2 = new Query('_Conversation');
    $model = $query2->get('595503e58fd9c5005f250b01');
    
        // model.send('NoticeMessage'
        // ,'{\"_lctype\":2,\"_lctext\":\"刚刚评论了你的帖子\",\"_lcattrs\":{\"type\":4,\"typeTitle\":\"您有一条通知\",\"fromId\":\"' + request.object.get('creater').id +'\",\"pid\":\"' + request.object.get('post').id +'\",\"cid\":\"' + request.object.id + '\"}}'
        // , {"toClients":[post.get('creater').id]});
    
    error_log('send comment message.');
});

Cloud::afterSave('ForumCommentReplies', function($obj, $user) {
    $query = new Query('ForumComments');
    $comment = $query->get($obj->get('comment')->getObjectId());
    $comment->add('replies', $obj);
    $comment->save();
    error_log('add reply done.');
    if($obj->get('creater')->getObjectId() != $comment->get('creater')->getObjectId())
    {
        $query2 = new Query('_Conversation');
        $model = $query2->get('595503e58fd9c5005f250b01');
        
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

Cloud::onLogin(function($user) {
    if (!$user->get('active')) {
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
