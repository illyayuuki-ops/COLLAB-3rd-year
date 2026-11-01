<?php
/** LCCT staff assistant: no school-record queries and no user text in provider requests. */
if (!defined('LCCT_AI_INTERNAL')) { http_response_code(404); exit; }
function gai_fail(string $message, int $code=400): never { throw new RuntimeException($message,$code); }
function gai_catalog(): array { static $c; return $c ??= require __DIR__.'/ai-catalog.php'; }
function gai_role(?string $role): string { if (!in_array($role,['head','counselor','administrator'],true)) gai_fail('This assistant is for authorized Guidance staff only.',403); return $role; }
function gai_topic(string $role, string $id): array { gai_role($role); $t=gai_catalog()[$id]??null; if (!$t || !in_array($role,$t['roles'],true)) gai_fail('This topic is not available for your role.',403); return $t; }
function gai_topics(string $role): array { gai_role($role); return array_values(array_filter(gai_catalog(),fn($t)=>in_array($role,$t['roles'],true))); }
function gai_match(string $message, string $context='welcome'): string {
    if (strlen($message)>1800 || !preg_match('//u',$message) || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$message)) gai_fail('Use a short plain-text question (up to 1,800 bytes).');
    $m=strtolower(trim($message)); if ($m==='') return 'welcome';
    $catalog=gai_catalog();
    // Local routing, not an AI privacy classifier: raw text NEVER leaves the application regardless of matches.
    foreach (['urgent','privacy'] as $id) foreach ($catalog[$id]['words'] as $w) if (str_contains($m,$w)) return $id;
    if(preg_match('/\b(planning|semester|continuity) report\b/i',$m))return 'planning';
    if(preg_match('/\b(reports?|analytics|statistics|csv|pdf|export|ulat)\b/i',$m))return 'reports';
    $best='';$score=0;
    foreach ($catalog as $id=>$t) { if ($id==='welcome') continue; foreach ($t['words'] as $w) {
        $pattern='/\b'.preg_quote($w,'/').'\b/i'; if (preg_match($pattern,$m) && strlen($w)>$score) { $score=strlen($w); $best=$id; }
    }}
    if ($best!=='') return $best;
    if (preg_match('/^(more|next|explain|steps|how|continue|paano)[?.!\s]*$/i',$m) && isset($catalog[$context])) return $context;
    return 'welcome';
}
function gai_report(string $role,array $input): array {
    gai_role($role);$kind=$input['kind']??'summary';if(!is_string($kind)||!in_array($kind,['summary','planning'],true))gai_fail('Unknown report type.');
    $mode=$input['mode']??($role==='counselor'?'clinical':'activities');
    $allowed=$role==='head'?['clinical','activities']:[$role==='counselor'?'clinical':'activities'];
    if (!is_string($mode)||!in_array($mode,$allowed,true)) gai_fail('This report area is not available for your role.',403);
    $preset=$input['preset']??'month'; if (!is_string($preset)||!in_array($preset,['month','30','90','year','custom'],true)) gai_fail('Choose a supported reporting period.');
    $q=['mode'=>$mode,'preset'=>$preset];
    if ($preset==='custom') {
        $ds=[];foreach (['from','to'] as $key) { $v=$input[$key]??'';
            if (!is_string($v)||!preg_match('/\A\d{4}-\d{2}-\d{2}\z/',$v)) gai_fail('Use YYYY-MM-DD dates.');
            $d=DateTimeImmutable::createFromFormat('!Y-m-d',$v,new DateTimeZone('Asia/Manila'));
            if (!$d||$d->format('Y-m-d')!==$v||$v<'1971-01-01'||$v>'9998-12-31') gai_fail('Use valid report dates.');
            $q[$key]=$v;$ds[]=$d;
        }
        if ($ds[0]>$ds[1]||$ds[0]->diff($ds[1])->days>365) gai_fail('Choose a range of 1 to 366 days.');
    }
    // No user/account/category/program identifiers, free text or arbitrary paths accepted here.
    if($kind==='planning')return ['filters'=>['kind'=>$kind]+$q,'preview'=>'guidance/staff.php?tab=planning&'.http_build_query($q),'csv'=>'guidance/staff.php?tab=planning&'.http_build_query($q+['format'=>'csv']),'print'=>'guidance/staff.php?tab=planning&'.http_build_query($q+['format'=>'print'])];
    return ['filters'=>['kind'=>$kind]+$q,'preview'=>'guidance/index.php?view=reports&'.http_build_query($q),'csv'=>'guidance/reports-export.php?'.http_build_query($q+['format'=>'csv']),'print'=>'guidance/reports-export.php?'.http_build_query($q+['format'=>'print'])];
}
function gai_report_intent(string $role,string $message,bool $planning=false): array {
    gai_role($role);$m=strtolower($message);$f=['kind'=>$planning?'planning':'summary','mode'=>$role==='counselor'?'clinical':'activities','preset'=>'month'];
    if(preg_match('/\b(clinical|counseling|counsell?or|case)\b/',$m))$f['mode']='clinical';
    elseif(preg_match('/\b(activities|activity|attendance|program)\b/',$m))$f['mode']='activities';
    $today=new DateTimeImmutable('today',new DateTimeZone('Asia/Manila'));
    preg_match_all('/\b\d{4}-\d{2}-\d{2}\b/',$m,$matches);
    if(count($matches[0])>0){if(count($matches[0])!==2)gai_fail('Supply both start and end dates, or use the report form.');$f+=['from'=>$matches[0][0],'to'=>$matches[0][1]];$f['preset']='custom';}
    elseif(preg_match('/\b(last month|previous month|nakaraang buwan)\b/',$m)){$d=$today->modify('first day of last month');$f+=['from'=>$d->format('Y-m-d'),'to'=>$d->modify('last day of this month')->format('Y-m-d')];$f['preset']='custom';}
    elseif(preg_match('/\b(january|february|march|april|may|june|july|august|september|october|november|december) (\d{4})\b/',$m,$parts)){$d=DateTimeImmutable::createFromFormat('!F Y',ucfirst($parts[1]).' '.$parts[2],new DateTimeZone('Asia/Manila'));if(!$d)gai_fail('Invalid report month.');$f+=['from'=>$d->format('Y-m-d'),'to'=>$d->modify('last day of this month')->format('Y-m-d')];$f['preset']='custom';}
    elseif(preg_match('/\b(last|past) 90 days\b/',$m))$f['preset']='90';
    elseif(preg_match('/\b(last|past) 30 days\b/',$m))$f['preset']='30';
    elseif(preg_match('/\b(this year|year to date|yearly)\b/',$m))$f['preset']='year';
    gai_report($role,$f);return $f; // Reuse the exact report authorization/date validator.
}
function gai_root(): string {
    $path=getenv('LCCT_AI_RUNTIME')?:((getenv('LOCALAPPDATA')?:'').DIRECTORY_SEPARATOR.'LCCT-Guidance-AI'.DIRECTORY_SEPARATOR.'assistant-runtime');
    if (!$path || (!getenv('LCCT_AI_RUNTIME')&&!getenv('LOCALAPPDATA'))) gai_fail('Assistant runtime has not been configured.',503);
    $real=realpath($path); if (!$real||!is_dir($real)) gai_fail('Assistant runtime is not initialized. Local help is still available.',503);
    $site=realpath(dirname(__DIR__)); $a=strtolower(str_replace('\\','/',$real));$b=strtolower(str_replace('\\','/',$site?:dirname(__DIR__)));
    if ($a===$b||str_starts_with($a,$b.'/')) gai_fail('Assistant runtime must be outside the application.',503);
    for ($p=$path; $p!==dirname($p); $p=dirname($p)) if (is_link($p)) gai_fail('Linked runtime paths are not accepted.',503);
    return $real;
}
function gai_read(string $path,int $max=2097152): array {
    if (is_link($path)||!is_file($path)||filesize($path)>$max) gai_fail('Assistant runtime needs administrator review.',503);
    try { $s=file_get_contents($path); if ($s===false||strlen($s)>$max) throw new RuntimeException(); $v=json_decode($s,true,32,JSON_THROW_ON_ERROR); }
    catch (Throwable $e) { gai_fail('Assistant runtime could not be read. Preserve its files for review.',503); }
    if (!is_array($v)) gai_fail('Assistant runtime is invalid.',503);return $v;
}
function gai_config(): array { $c=gai_read(gai_root().'/policy.json',8192); if (($c['version']??null)!==1||!is_bool($c['approved']??null)) gai_fail('Assistant policy is invalid.',503); return $c; }
function gai_secret(): string { $p=gai_root().'/owner.key'; if(is_link($p)||!is_file($p)||filesize($p)!==32)gai_fail('Assistant ownership key is unavailable.',503);$s=file_get_contents($p);if($s===false||strlen($s)!==32)gai_fail('Assistant ownership key is unavailable.',503);return $s; }
function gai_instance(): string { static $id;if($id!==null)return $id;$root=str_replace('\\','/',realpath(dirname(__DIR__)));if(PHP_OS_FAMILY==='Windows')$root=strtolower($root);return $id=hash('sha256',$root.'|'.hash_file('sha256',__DIR__.'/ai-catalog.php')); }
function gai_identity(int $uid,string $role,string $nonce): array { gai_role($role); if ($uid<1||!preg_match('/\A[a-f0-9]{32}\z/',$nonce)) gai_fail('Invalid assistant session.',403);$secret=gai_secret();return ['actor'=>hash_hmac('sha256','account:'.gai_instance().':'.$uid,$secret),'owner'=>hash_hmac('sha256',gai_instance().'|'.$uid.'|'.$role.'|'.$nonce,$secret),'role'=>$role,'instance'=>gai_instance()]; }
function gai_state(callable $f): mixed {
    $root=gai_root();$file=$root.'/state.json'; if (is_link($file)||!is_file($file)||filesize($file)>2097152) gai_fail('Assistant runtime needs review.',503);
    $h=fopen($file,'r+b');if(!$h)gai_fail('Assistant runtime is unavailable.',503);$until=microtime(true)+0.25;
    while (!flock($h,LOCK_EX|LOCK_NB)) { if(microtime(true)>$until){fclose($h);gai_fail('Assistant is busy. Local help remains available.',503);}usleep(10000); }
    try {
        $raw=stream_get_contents($h,2097153);$s=json_decode($raw?:'',true,32,JSON_THROW_ON_ERROR);
        if(!is_array($s)||($s['version']??null)!==1||!is_array($s['jobs']??null)||!is_array($s['usage']??null)||!is_array($s['cache']??null)||!is_bool($s['paused']??null))gai_fail('Assistant state is invalid. Do not reset it blindly.',503);
        $now=time();foreach($s['jobs'] as $id=>$j)if(($j['expires']??0)<$now)unset($s['jobs'][$id]);
        foreach($s['cache'] as $id=>$v)if(($v['expires']??0)<$now)unset($s['cache'][$id]);
        if(count($s['jobs'])>30||count($s['cache'])>50)gai_fail('Assistant state exceeded safe bounds.',503);
        $day=(new DateTimeImmutable('now',new DateTimeZone('Asia/Manila')))->format('Y-m-d');
        if(($s['usage']['day']??'')!==$day)$s['usage']=['day'=>$day,'total'=>0,'accounts'=>[],'last'=>0];
        $result=$f($s);$data=json_encode($s,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        if(strlen($data)>2097152)gai_fail('Assistant state is too large.',503);
        rewind($h);if(!ftruncate($h,0)||fwrite($h,$data)!==strlen($data)||!fflush($h))gai_fail('Assistant state write failed; pause use and request review.',503);
        return $result;
    } catch(JsonException $e){gai_fail('Assistant state is invalid. Preserve the file and request review.',503);}
    finally {flock($h,LOCK_UN);fclose($h);}
}
function gai_status(): array {
    try { $c=gai_config();return gai_state(function(&$s)use($c){return ['ready'=>$c['approved']&&!$s['paused']&&($s['cooldown']??0)<=time()&&($s['heartbeat']??0)>time()-60&&($s['heartbeatInstance']??'')===gai_instance(),'approved'=>$c['approved'],'paused'=>$s['paused'],'worker'=>($s['heartbeat']??0)>time()-60&&($s['heartbeatInstance']??'')===gai_instance(),'dailyUsed'=>(int)($s['usage']['total']??0),'dailyLimit'=>20];}); }
    catch(Throwable $e){return ['ready'=>false,'approved'=>false,'paused'=>true,'worker'=>false,'dailyUsed'=>0,'dailyLimit'=>20];}
}
function gai_enqueue(array $identity,string $topic): array {
    $t=gai_topic($identity['role'],$topic);if(!$t['cloud'])return ['state'=>'local','reason'=>'This topic uses local instructions only.'];
    $policy=gai_config();if(!$policy['approved'])return ['state'=>'local','reason'=>'Gemini is off pending the operator review.'];
    return gai_state(function(&$s)use($identity,$topic){
        if($s['paused']||($s['cooldown']??0)>time()||($s['heartbeat']??0)<time()-60||($s['heartbeatInstance']??'')!==$identity['instance'])return ['state'=>'local','reason'=>'Gemini is paused or its worker is offline.'];
        $key=$identity['instance'].':'.$identity['role'].':'.$topic;$cached=$s['cache'][$key]??null;
        if($cached)return ['state'=>'done','cached'=>true,'answer'=>$cached['answer']];
        foreach($s['jobs'] as $j)if($j['owner']===$identity['owner']&&in_array($j['state'],['queued','working'],true))return ['state'=>'local','reason'=>'Finish or clear the current request first.'];
        $u=&$s['usage'];$a=$u['accounts'][$identity['actor']]??['count'=>0,'last'=>0];
        if($u['total']>=20||$a['count']>=10||time()-($u['last']??0)<30||time()-$a['last']<60)return ['state'=>'local','reason'=>'The local request limit was reached. No API call was made; use the guide below.'];
        $waiting=count(array_filter($s['jobs'],fn($j)=>in_array($j['state'],['queued','working'],true)));
        if($waiting>=5||count($s['jobs'])>=30)return ['state'=>'local','reason'=>'The assistant queue is full. Use local help for now.'];
        $id=bin2hex(random_bytes(16));$s['jobs'][$id]=['id'=>$id,'instance'=>$identity['instance'],'owner'=>$identity['owner'],'role'=>$identity['role'],'topic'=>$topic,'state'=>'queued','created'=>time(),'expires'=>time()+900];
        $u['total']++;$u['last']=time();$u['accounts'][$identity['actor']]=['count'=>$a['count']+1,'last'=>time()];
        return ['state'=>'queued','job'=>$id];
    });
}
function gai_poll(array $identity,string $id): array {
    if(!preg_match('/\A[a-f0-9]{32}\z/',$id))gai_fail('Request not found.',404);
    return gai_state(function(&$s)use($identity,$id){$j=$s['jobs'][$id]??null;
        if(!$j||!hash_equals($j['owner'],$identity['owner'])||$j['role']!==$identity['role']||($j['instance']??'')!==$identity['instance'])gai_fail('Request not found.',404);
        gai_topic($identity['role'],$j['topic']);
        if(in_array($j['state'],['queued','working'],true)&&time()-$j['created']>120){$s['jobs'][$id]['state']='failed';$s['jobs'][$id]['reason']='Request expired. It may have reached Google; do not immediately retry.';$j=$s['jobs'][$id];}
        return array_intersect_key($j,array_flip(['state','answer','reason']));
    });
}
function gai_clear(array $identity): void { gai_state(function(&$s)use($identity){foreach($s['jobs'] as &$j)if(hash_equals($j['owner'],$identity['owner'])){$j['state']='cancelled';unset($j['answer']);}$s['lastEvent']=['time'=>time(),'kind'=>'conversation_cleared'];}); }
function gai_pause(string $role,bool $paused): void { if($role!=='head')gai_fail('Head access required.',403);$c=gai_config();if(!$paused&&!$c['approved'])gai_fail('Operator review is required before Gemini can be enabled.',403);
    gai_state(function(&$s)use($paused){$s['paused']=$paused;if($paused)foreach($s['jobs'] as &$j)if(in_array($j['state'],['queued','working'],true)){$j['state']='cancelled';unset($j['answer']);}$s['lastEvent']=['time'=>time(),'kind'=>$paused?'paused':'resumed'];});
}
function gai_request(string $role,string $topic): array {
    $t=gai_topic($role,$topic);if(!$t['cloud'])gai_fail('This topic is local-only.',403);
    // All outbound bytes are derived from the reviewed catalog, not a user's message, session or records.
    $text='Explain these generic software-navigation instructions clearly, without adding steps, claiming actions were performed, or offering clinical advice. Use only the supplied facts. No people or school records are provided. Title: '.$t['title'].'. Facts: '.implode(' ',$t['steps']).' Limit: '.$t['note'];
    return ['contents'=>[['role'=>'user','parts'=>[['text'=>$text]]]],'generationConfig'=>['candidateCount'=>1,'maxOutputTokens'=>768,'thinkingConfig'=>['thinkingLevel'=>'MINIMAL','includeThoughts'=>false],'responseFormat'=>['text'=>['mimeType'=>'APPLICATION_JSON','schema'=>['type'=>'object','additionalProperties'=>false,'properties'=>['title'=>['type'=>'string'],'steps'=>['type'=>'array','minItems'=>1,'maxItems'=>5,'items'=>['type'=>'string']],'note'=>['type'=>'string']],'required'=>['title','steps','note']]]]]];
}
function gai_answer(array $response): array {
    if(isset($response['promptFeedback']['blockReason'])&&$response['promptFeedback']['blockReason']!=='BLOCK_REASON_UNSPECIFIED')gai_fail('Provider blocked this response.',502);
    $cs=$response['candidates']??[];if(!is_array($cs)||count($cs)!==1||($cs[0]['finishReason']??'')!=='STOP')gai_fail('Provider did not return a completed answer.',502);
    $text='';$parts=$cs[0]['content']['parts']??[];if(!is_array($parts)||count($parts)>8)gai_fail('Unexpected response parts.',502);
    foreach($parts as $p){if(!is_array($p)||array_diff(array_keys($p),['text','thought','thoughtSignature']))gai_fail('Non-text response refused.',502);if(isset($p['thought'])&&!is_bool($p['thought']))gai_fail('Invalid response.',502);if($p['thought']??false)continue;if(!is_string($p['text']??null))gai_fail('Invalid response.',502);$text.=$p['text'];if(strlen($text)>12000)gai_fail('Response exceeded its limit.',502);}
    try{$a=json_decode($text,true,16,JSON_THROW_ON_ERROR);}catch(Throwable $e){gai_fail('Invalid structured answer.',502);}
    if(!is_array($a)||array_is_list($a)||count($a)!==3||array_diff(['title','steps','note'],array_keys($a)))gai_fail('Unexpected answer fields.',502);
    foreach(['title'=>160,'note'=>500] as $k=>$n)if(!is_string($a[$k])||strlen($a[$k])>$n||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$a[$k]))gai_fail('Invalid answer text.',502);
    if(!is_array($a['steps'])||!array_is_list($a['steps'])||count($a['steps'])<1||count($a['steps'])>5)gai_fail('Invalid answer steps.',502);
    foreach($a['steps'] as $v)if(!is_string($v)||strlen($v)>500||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$v))gai_fail('Invalid answer step.',502);
    return $a;
}
function gai_transport_ready(): bool { return extension_loaded('curl')&&(curl_version()['features']&CURL_VERSION_SSL)!==0; }
function gai_http(array $request): array {
    if(!gai_transport_ready())gai_fail('Private worker HTTPS transport is unavailable.',503);
    $key=getenv('LCCT_AI_WORKER_KEY');if(!$key||strlen($key)>4096||preg_match('/[^\x21-\x7e]/',$key))gai_fail('Worker credential is unavailable.',503);
    $body=json_encode($request,JSON_THROW_ON_ERROR);if(strlen($body)>16000||str_contains($body,$key))gai_fail('Unsafe outbound request refused.',503);
    $raw='';$ch=curl_init('https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent');
    if(!$ch)gai_fail('Worker HTTPS transport could not start.',503);
    $options=[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_HTTPHEADER=>['Content-Type: application/json','x-goog-api-key: '.$key],CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>35,CURLOPT_NOSIGNAL=>true,CURLOPT_WRITEFUNCTION=>function($handle,string $chunk)use(&$raw):int{if(strlen($raw)+strlen($chunk)>131072)return 0;$raw.=$chunk;return strlen($chunk);}];
    $ca=getenv('LCCT_AI_CA_FILE');if($ca){if(!is_file($ca)||is_link($ca))gai_fail('Worker CA bundle unavailable.',503);$options[CURLOPT_CAINFO]=$ca;}
    try{if(!curl_setopt_array($ch,$options))gai_fail('Worker HTTPS settings unavailable.',503);$ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
        if(in_array($status,[400,401,403,404,429],true))gai_fail('Provider rejected the request. No retry was made.',$status);
        if($ok===false||$status!==200)gai_fail('Provider request failed or exceeded its bounds; it may have been processed. No retry was made.',502);
        try{$r=json_decode($raw,true,32,JSON_THROW_ON_ERROR);}catch(Throwable $e){gai_fail('Provider response was invalid.',502);}if(!is_array($r))gai_fail('Provider response was invalid.',502);return $r;
    }finally{curl_close($ch);}
}
function gai_tick(?callable $transport=null): bool {
    $policy=gai_config();$job=gai_state(function(&$s)use($policy){$s['heartbeat']=time();$s['heartbeatInstance']=gai_instance();if(!$policy['approved']||$s['paused']||($s['cooldown']??0)>time())return null;
        foreach($s['jobs'] as &$j)if($j['state']==='working'&&time()-($j['started']??0)>60){$j['state']='failed';$j['reason']='Worker was interrupted; no automatic retry.';}
        foreach($s['jobs'] as &$j)if($j['state']==='queued'&&time()-$j['created']>120){$j['state']='failed';$j['reason']='Queued request expired without an API call.';}
        foreach($s['jobs'] as &$j)if($j['state']==='queued'){$j['state']='working';$j['started']=time();return $j;}return null;});
    if(!$job)return false;
    $answer=null;$reason=null;$limited=false;
    try{if(($job['instance']??'')!==gai_instance())gai_fail('Wrong worker instance.',503);$request=gai_request($job['role'],$job['topic']);$answer=gai_answer(($transport??'gai_http')($request));}
    catch(Throwable $e){$limited=in_array($e->getCode(),[401,403,429],true);$reason=match($e->getCode()){429=>'Provider quota/rate limit reached. Gemini calls are paused locally for one hour; use the local guide.',401,403=>'Authentication or access was rejected. Calls are paused locally for one hour; ask the operator to review access without sharing the key.',400=>'Provider request configuration was rejected. Contact the maintainer; no retry was made.',404=>'Configured model unavailable. No fallback model was called.',default=>'Gemini could not provide a validated answer. Use the local guide; no automatic retry was made.'};}
    gai_state(function(&$s)use($job,$answer,$reason,$limited){$s['heartbeat']=time();if($limited)$s['cooldown']=time()+3600;
        if(!isset($s['jobs'][$job['id']])||$s['jobs'][$job['id']]['state']!=='working'||$s['paused'])return;
        $j=&$s['jobs'][$job['id']];$j['state']=$answer?'done':'failed';if($answer){$j['answer']=$answer;$s['cache'][$job['instance'].':'.$job['role'].':'.$job['topic']]=['answer'=>$answer,'expires'=>time()+21600];}else{$j['reason']=$reason;}
        $s['lastEvent']=['time'=>time(),'kind'=>$answer?'answer_ready':'provider_failed'];
    });return true;
}
