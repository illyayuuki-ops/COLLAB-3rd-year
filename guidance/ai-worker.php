<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('LCCT_AI_INTERNAL',true);require __DIR__.'/ai-core.php';ini_set('display_errors','0');
try {
    $mode=$argv[1]??'--run';
    if($mode==='--init'){
        $path=getenv('LCCT_AI_RUNTIME')?:((getenv('LOCALAPPDATA')?:'').DIRECTORY_SEPARATOR.'LCCT-Guidance-AI'.DIRECTORY_SEPARATOR.'assistant-runtime');
        if(!getenv('LOCALAPPDATA')&&!getenv('LCCT_AI_RUNTIME'))gai_fail('Runtime parent is not configured.',503);
        $parent=realpath(dirname($path));if(!$parent||is_link(dirname($path)))gai_fail('Initialize the private parent first.',503);
        $site=strtolower(str_replace('\\','/',realpath(dirname(__DIR__))));$target=strtolower(str_replace('\\','/',$parent.'/'.basename($path)));
        if($target===$site||str_starts_with($target,$site.'/'))gai_fail('Runtime must be outside the app.',503);
        if(!is_dir($path)&&!mkdir($path,0700))gai_fail('Cannot initialize runtime.',503);
        $root=gai_root();$files=['owner.key'=>random_bytes(32),'policy.json'=>json_encode(['version'=>1,'approved'=>false]),'state.json'=>json_encode(['version'=>1,'paused'=>true,'heartbeat'=>0,'jobs'=>new stdClass(),'cache'=>new stdClass(),'usage'=>new stdClass()])];
        $existing=array_filter(array_keys($files),fn($f)=>file_exists($root.'/'.$f));
        if(count($existing)===3){gai_config();gai_secret();gai_state(fn(&$s)=>true);echo "Runtime already initialized; no reset or policy change.\n";exit;}
        if($existing)gai_fail('Partial runtime exists; preserve it and request review.',503);
        foreach($files as $name=>$data){$h=fopen($root.'/'.$name,'xb');if(!$h||fwrite($h,$data)!==strlen($data))gai_fail('Runtime initialization failed.',503);fclose($h);@chmod($root.'/'.$name,0600);}
        echo "Runtime initialized. Gemini OFF; no database or API key changed.\n";exit;
    }
    if(in_array($mode,['--approve-reviewed','--disable'],true)){
        $c=gai_config();$c['approved']=$mode==='--approve-reviewed';
        $p=gai_root().'/policy.json';$h=fopen($p,'r+b');if(!$h||!flock($h,LOCK_EX))gai_fail('Cannot lock policy.',503);
        $data=json_encode($c,JSON_THROW_ON_ERROR);try{ftruncate($h,0);rewind($h);if(fwrite($h,$data)!==strlen($data))gai_fail('Policy write failed.',503);fflush($h);}finally{flock($h,LOCK_UN);fclose($h);}
        gai_state(function(&$s)use($mode){$s['paused']=$mode==='--disable';foreach($s['jobs'] as &$j)if(in_array($j['state'],['queued','working'],true))$j['state']='cancelled';});
        echo $c['approved']?"Operator review acknowledgment recorded; staff cloud gate enabled.\n":"Cloud gate disabled.\n";exit;
    }
    if($mode==='--curl-probe'){echo extension_loaded('curl')?'yes':'no';exit;}
    if($mode==='--transport-check'){if(!gai_transport_ready())gai_fail('Required worker HTTPS extensions are unavailable.',503);echo "Worker HTTPS extensions available. No network request made.\n";exit;}
    if(!in_array($mode,['--run','--once'],true))gai_fail('Unknown worker option.');
    if(!gai_transport_ready())gai_fail('Worker requires cURL with HTTPS support.',503);
    $root=gai_root();$lockPath=$root.'/worker.lock';if(is_link($lockPath))gai_fail('Linked worker lock refused.',503);
    $lock=fopen($lockPath,'c+b');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))gai_fail('A worker already holds this runtime.',503);
    echo "Staff Gemini worker running. No local model; no school-record access. Ctrl+C stops this worker.\n";
    try{do{gai_tick();if($mode==='--once')break;sleep(2);}while(true);}finally{flock($lock,LOCK_UN);fclose($lock);}
}catch(Throwable $e){fwrite(STDERR,"Assistant worker stopped safely. Check runtime, approval gate, worker exclusivity and HTTPS support. No raw errors or credentials are printed.\n");exit(1);}
