<?php
// Same-origin, authenticated staff endpoint. Raw text is never put in jobs, logs or provider bodies.
define('GD_JSON',true);define('LCCT_AI_INTERNAL',true);
require dirname(__DIR__).'/config/app.php';require __DIR__.'/ai-core.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');
try {
    if (($_SERVER['REQUEST_METHOD']??'')!=='POST') gai_fail('Use POST.',405);
    if ((int)($_SERVER['CONTENT_LENGTH']??0)>8192) gai_fail('Request is too large.',413);
    if (($_SERVER['HTTP_SEC_FETCH_SITE']??'')==='cross-site') gai_fail('Cross-site requests are not accepted.',403);
    $u=g_actor();$role=gai_role(g_staff($u));
    if (!is_string($_POST['csrf']??null)||!hash_equals((string)($_SESSION['csrf']??''),$_POST['csrf'])||empty($_SESSION['csrf']))gai_fail('Session expired. Refresh the page.',419);
    $action=$_POST['action']??'';if(!is_string($action))gai_fail('Invalid action.');
    $fields=['chat'=>['message','topic','context','enhance'],'report'=>['kind','mode','preset','from','to'],'poll'=>['job'],'status'=>[],'clear'=>[],'pause'=>['paused']];
    if(!isset($fields[$action])||array_diff(array_keys($_POST),array_merge(['csrf','action'],$fields[$action])))gai_fail('Unsupported request fields.');
    foreach($_POST as $v)if(!is_string($v))gai_fail('Invalid input type.');
    $lim=$_SESSION['gai_http']??['at'=>time(),'n'=>0];if(time()-$lim['at']>=60)$lim=['at'=>time(),'n'=>0];$lim['n']++;$_SESSION['gai_http']=$lim;if($lim['n']>80)gai_fail('Too many assistant requests. Wait a minute.',429);
    $scope=$_SESSION['gai_scope']??[];
    if(($scope['uid']??0)!==(int)$u['id']||($scope['role']??'')!==$role)$scope=['uid'=>(int)$u['id'],'role'=>$role,'nonce'=>bin2hex(random_bytes(16))];
    $_SESSION['gai_scope']=$scope;
    if($action==='clear')$_SESSION['gai_scope']['nonce']=bin2hex(random_bytes(16));
    session_write_close(); // Never hold the PHP session lock while waiting on the worker/state file.
    $identity=fn()=>gai_identity((int)$u['id'],$role,$scope['nonce']);
    switch($action) {
    case 'status':
        $out=gai_status();if($role!=='head')unset($out['dailyUsed']);break;
    case 'chat':
        $message=$_POST['message']??'';$context=$_POST['context']??'welcome';
        if(strlen($context)>40)gai_fail('Invalid context.');
        $topic=$_POST['topic']??gai_match($message,$context);$t=gai_topic($role,$topic);
        // Even explicit-topic requests validate message bounds, but do not store or forward it.
        if(strlen($message)>1800||!preg_match('//u',$message))gai_fail('Question is too long or invalid.');
        $route=$t['route'];if($topic==='urgent'&&$role!=='administrator')$route='guidance/staff.php?tab=urgent';
        $out=['topic'=>$topic,'title'=>$t['title'],'steps'=>$t['steps'],'note'=>$t['note'],'route'=>url($route),'cloudAllowed'=>$t['cloud'],'ai'=>['state'=>'local','reason'=>'Built-in guide; no Google request.']];
        if(in_array($topic,['reports','planning'],true)){$out['report']=gai_report($role,gai_report_intent($role,$message,$topic==='planning'));foreach(['preview','csv','print'] as $k)$out['report'][$k]=url($out['report'][$k]);}
        if(($_POST['enhance']??'')==='1'&&$t['cloud']&&($topic!=='welcome'||isset($_POST['topic'])||preg_match('/\A(?:hi|hello|help|start|tulong)[!.?\s]*\z/i',trim($message)))) {
            try{$out['ai']=gai_enqueue($identity(),$topic);}catch(Throwable $e){$out['ai']=['state'=>'local','reason'=>'Gemini is unavailable. The built-in guide still works.'];}
        }
        break;
    case 'report':
        $out=gai_report($role,array_intersect_key($_POST,array_flip(['kind','mode','preset','from','to'])));
        foreach(['preview','csv','print'] as $k)$out[$k]=url($out[$k]);break;
    case 'poll':$out=gai_poll($identity(),$_POST['job']??'');break;
    case 'clear':try{gai_clear($identity());}catch(RuntimeException $e){if($e->getCode()!==503)throw $e;}$out=['cleared'=>true];break;
    case 'pause':if(!in_array($_POST['paused']??'', ['0','1'],true))gai_fail('Invalid pause setting.');gai_pause($role,$_POST['paused']==='1');$out=gai_status();break;
    }
    echo json_encode(['ok'=>true,'data'=>$out],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(Throwable $e){$status=$e instanceof RuntimeException&&in_array($e->getCode(),[400,401,403,404,405,413,419,429,503],true)?$e->getCode():500;http_response_code($status);echo json_encode(['ok'=>false,'error'=>$status===500?'The assistant could not complete this request. No business record was changed.':$e->getMessage()]);}
