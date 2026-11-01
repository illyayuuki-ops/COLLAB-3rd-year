<?php
if (!function_exists('g_staff')) { http_response_code(404); exit; }
function ad_ready(): bool { return g_ready() && (bool) gone("SELECT setting_key FROM gd_settings WHERE setting_key='staff_advanced' AND setting_value='1'"); }
function ad_role(array $u): string { $r=g_staff($u);gx(in_array($r,['head','counselor','administrator'],true));return $r; }
function ad_clinical(array $u): void { gx(in_array(ad_role($u),['head','counselor'],true),'Clinical staff access required.',403); }
function ad_ops(array $u): void { gx(in_array(ad_role($u),['head','administrator'],true),'Activity operations access required.',403); }
function ad_case(array $u,int $id): array {
    global $pdo;ad_clinical($u);$args=[$id];$where="office_code='guide'";
    if(!g_head($u)){$where.=' AND assigned_to=?';$args[]=$u['id'];}
    $t=gone('SELECT * FROM tickets WHERE id=? AND '.$where.($pdo->inTransaction()?' FOR UPDATE':''),$args);gx($t!==null,'Request not found.',404);return $t;
}
function ad_staff(int $id, bool $headOnly=false): array {
    $u=gone('SELECT * FROM users WHERE id=?',[$id]);gx($u && in_array(g_staff($u),$headOnly?['head']:['head','counselor'],true),'Choose an active authorized clinical staff member.',400);return $u;
}
function ad_password(array $u,array $p): void {
    gx(g_head($u)); // HTTP rate gate is outside the transaction so failures remain counted.
    $fresh=gone('SELECT password FROM users WHERE id=?',[$u['id']]);gx($fresh && password_verify(gt($p,'current_password',1,512),$fresh['password']),'Current Head password required.',403);
}
function ad_date(array $p,string $key): string {
    $s=gt($p,$key,10,10);gx((bool)preg_match('/^\d{4}-\d{2}-\d{2}$/D',$s),'Invalid date.',400);
    $d=DateTimeImmutable::createFromFormat('!Y-m-d',$s);gx($d && $d->format('Y-m-d')===$s && $s>='2000-01-01' && $s<='2100-12-31','Invalid date.',400);return $s;
}
function ad_datetime(array $p,string $key): string { $v=gt($p,$key,16,16);gx((bool)preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/D',$v),'Use a valid local date and time.',400);return gdtime($p,$key); }
function ad_clock(array $p,string $key): string { $s=gt($p,$key,5,5);gx((bool)preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$s),'Use a valid 24-hour time.',400);return $s; }
function ad_row(array $u,string $table,int $id): array {
    gx(in_array($table,['plans','tasks','consents','referrals','outcomes','rules','blocks','waitlist','urgent','programs','policies'],true),'Invalid staff entity.',400);
    $role=ad_role($u);$args=[$id];$where='r.id=?';
    if(in_array($table,['plans','consents','referrals','outcomes','waitlist','urgent'],true)){
        $scope=ad_scope($u,'t',$args);$where.=' AND EXISTS(SELECT 1 FROM tickets t WHERE t.id=r.ticket_id AND '.$scope.')';
    }elseif($table==='tasks'){
        if($role==='administrator')$where.=' AND r.ticket_id IS NULL AND r.event_id IS NOT NULL';
        else{$scope=ad_scope($u,'t',$args);$case='EXISTS(SELECT 1 FROM tickets t WHERE t.id=r.ticket_id AND '.$scope.')';$where.=' AND '.($role==='head'?'('.$case.' OR (r.ticket_id IS NULL AND r.event_id IS NOT NULL))':$case);}
    }elseif(in_array($table,['rules','blocks'],true)){ad_clinical($u);if(!g_head($u)){$where.=' AND r.counselor_id=?';$args[]=$u['id'];}}
    elseif($table==='programs')ad_ops($u);else gx(g_head($u));
    $r=gone('SELECT r.* FROM gd_adv_'.$table.' r WHERE '.$where,$args);gx($r!==null,'Record not found.',404);
    if(!empty($r['ticket_id']))ad_case($u,(int)$r['ticket_id']);return $r;
}
function ad_version(array $row,array $p): void { gx((int)$row['version']===gi($p,'version',1),'This record changed. Refresh before saving; your previous submission was not applied.',409); }
function ad_update(string $table,array $r,array $values): void {
    gx(in_array($table,['plans','tasks','consents','referrals','rules','blocks','waitlist','urgent','programs'],true));
    $set=implode(',',array_map(fn($k)=>$k.'=?',array_keys($values)));
    $q=gq('UPDATE gd_adv_'.$table.' SET '.$set.',version=version+1 WHERE id=? AND version=?',array_merge(array_values($values),[$r['id'],$r['version']]));gx($q->rowCount()===1,'Concurrent change. Refresh and retry.',409);
}
function ad_insert(string $table,array $values): int {
    global $pdo;gx(in_array($table,['plans','plan_entries','tasks','consents','referrals','outcomes','rules','blocks','waitlist','urgent','programs','reviews','policies','checks'],true));
    gq('INSERT INTO gd_adv_'.$table.' ('.implode(',',array_keys($values)).') VALUES('.implode(',',array_fill(0,count($values),'?')).')',array_values($values));return (int)$pdo->lastInsertId();
}
function ad_log(array $u,string $action,int $id,?int $ticket=null,?int $event=null,string $detail=''): void {
    gq('INSERT INTO gd_adv_log(ticket_id,event_id,action,entity_id,actor_id,detail,created_at) VALUES(?,?,?,?,?,?,NOW())',[$ticket,$event,$action,$id,$u['id'],$detail]);
    g_audit($u,'staff_'.$action,'Record '.$id.'; contents withheld.');
}
function ad_policy(int $id,string $purpose): array {
    $r=gone('SELECT * FROM gd_adv_policies WHERE id=? AND purpose=? AND retired_at IS NULL',[$id,$purpose]);gx($r!==null,'Choose a current institution-approved policy version.',400);return $r;
}
function ad_consent(int $id,int $ticket): array {
    $r=gone("SELECT c.* FROM gd_adv_consents c JOIN gd_adv_policies p ON p.id=c.policy_id WHERE c.id=? AND c.ticket_id=? AND c.state='granted' AND c.expires_on>=? AND p.purpose='referral' AND p.retired_at IS NULL",[$id,$ticket,date('Y-m-d')]);gx($r!==null,'Current, unwithdrawn referral consent under an active policy is required. No sharing should occur.',409);return $r;
}
function ad_schedule(int $counselor,int $student,string $start,string $end,int $exclude=0,bool $requireRule=true): int {
    ad_staff($counselor);gx($start>date('Y-m-d H:i:s') && $end>$start && strtotime($end)-strtotime($start)<=7200 && substr($start,0,10)===substr($end,0,10),'Use a future same-day appointment of at most two hours.',400);
    $date=substr($start,0,10);$rules=gall('SELECT * FROM gd_adv_rules WHERE counselor_id=? AND active=1 AND weekday=? AND effective_from<=? AND effective_to>=?',[$counselor,(int)date('N',strtotime($start)),$date,$date]);$match=null;
    foreach($rules as $r){$anchor=$date.' '.$r['starts_time'].':00';$limit=$date.' '.$r['ends_time'].':00';$duration=(strtotime($end)-strtotime($start))/60;$step=((int)$r['slot_minutes']+(int)$r['buffer_minutes'])*60;
        if($start>=$anchor && $end<=$limit && $duration===(int)$r['slot_minutes'] && (strtotime($start)-strtotime($anchor))%$step===0){$match=$r;break;}}
    $hasPolicy=(bool)gone('SELECT id FROM gd_adv_rules WHERE counselor_id=? LIMIT 1',[$counselor]);
    gx($match || (!$requireRule&&!$hasPolicy),'Choose a published availability slot (duration and buffer grid must match).',409);
    $buffer=(int)($match['buffer_minutes']??0);$left=date('Y-m-d H:i:s',strtotime($start)-$buffer*60);$right=date('Y-m-d H:i:s',strtotime($end)+$buffer*60);
    gx(!gone('SELECT id FROM gd_adv_blocks WHERE counselor_id=? AND active=1 AND starts_at<? AND ends_at>?',[$counselor,$right,$left]),'This slot conflicts with blocked time or its buffer.',409);
    gx(!gone("SELECT a.id FROM gd_appointments a LEFT JOIN gd_adv_appointments m ON m.appointment_id=a.id WHERE a.id<>? AND a.status IN ('requested','confirmed') AND ((a.counselor_id=? AND ((a.starts_at<? AND a.ends_at>?) OR (DATE_SUB(a.starts_at,INTERVAL COALESCE(m.buffer_minutes,0) MINUTE)<? AND DATE_ADD(a.ends_at,INTERVAL COALESCE(m.buffer_minutes,0) MINUTE)>?))) OR (a.user_id=? AND a.starts_at<? AND a.ends_at>?))",[$exclude,$counselor,$right,$left,$end,$start,$student,$end,$start]),'This slot overlaps an existing reservation or its buffer.',409);
    return $buffer;
}
function ad_scope(array $u,string $alias,array &$params): string {
    ad_clinical($u);$s=$alias.".office_code='guide'";if(!g_head($u)){$s.=' AND '.$alias.'.assigned_to=?';$params[]=$u['id'];}return $s;
}
function ad_transition(string $old,string $next,array $map): void { gx(in_array($next,$map[$old]??[],true),'This transition is not allowed from the current state.',409); }
function ad_appointment_stamp(array $a): string { $canonical=[];foreach(['id','user_id','counselor_id','starts_at','ends_at','concern','status','updated_at'] as $k)$canonical[$k]=(string)($a[$k]??'');return hash('sha256',json_encode($canonical,JSON_THROW_ON_ERROR)); }
function ad_action(array $u,string $action,array $p): array {
    global $pdo;ad_role($u);gx(ad_ready(),'Apply the staff extension migration first.',503);gx($pdo->inTransaction(),'Use the transaction-protected endpoint.',500);
    $token=gt($p,'request_token',32,32);gx((bool)preg_match('/^[a-f0-9]{32}$/D',$token),'Invalid request token.',400);
    gx(!gone('SELECT id FROM gd_adv_requests WHERE user_id=? AND token=?',[$u['id'],$token]),'This form was already submitted. Refresh before making another change.',409);
    gq('INSERT INTO gd_adv_requests(user_id,token,created_at) VALUES(?,?,NOW())',[$u['id'],$token]);
    $uid=(int)$u['id'];$id=gi($p,'id');$ticket=null;$event=null;$tab='desk';$detail='';$now=date('Y-m-d H:i:s');
    switch($action){
    case 'plan.save':
        $t=ad_case($u,gi($p,'ticket_id',1));$ticket=(int)$t['id'];$r=gone('SELECT * FROM gd_adv_plans WHERE ticket_id=?',[$ticket]);
        $v=['goal'=>gt($p,'goal',5,3000),'actions'=>gt($p,'actions',5,3000),'target_on'=>ad_date($p,'target_on'),'status'=>ge($p,'status',['active','completed','archived']),'progress'=>gi($p,'progress',0,100),'updated_by'=>$uid,'updated_at'=>$now];
        gx($v['status']!=='completed'||$v['progress']===100,'Complete all agreed progress or keep the plan active.',400);
        if($r){ad_version($r,$p);ad_update('plans',$r,$v);$id=(int)$r['id'];}else{$id=ad_insert('plans',['ticket_id'=>$ticket,'created_by'=>$uid,'created_at'=>$now]+$v);}
        ad_insert('plan_entries',['plan_id'=>$id,'progress'=>$v['progress'],'note'=>gt($p,'note',5,2000),'goal_snapshot'=>$v['goal'],'actions_snapshot'=>$v['actions'],'recorded_by'=>$uid,'created_at'=>$now]);$detail='Plan state '.$v['status'].'; target '.$v['target_on'].'; agreed progress '.$v['progress'].'%.';$tab='care';break;
    case 'task.create':
        $ticket=gi($p,'ticket_id');$event=gi($p,'event_id');gx(($ticket>0)!==($event>0),'Choose one case OR one activity.',400);
        if($ticket){$t=ad_case($u,$ticket);$owner=g_head($u)?gi($p,'owner_id',1):$uid;$person=ad_staff($owner);gx(g_case($person,$t),'The follow-up owner must be authorized for this case.',403);$kind=ge($p,'kind',['followup','contact','referral_check']);$event=null;}
        else{ad_ops($u);g_event($u,$event);$ticket=null;$owner=g_head($u)?gi($p,'owner_id',1):$uid;$person=gone('SELECT * FROM users WHERE id=?',[$owner]);gx($person&&in_array(g_staff($person),['head','administrator'],true),'Choose active activity staff.',400);$kind=ge($p,'kind',['preparation','attendance_check','certificate_check']);}
        $due=ad_datetime($p,'due_at');gx($due>$now,'Choose a future due time.',400);$id=ad_insert('tasks',['ticket_id'=>$ticket,'event_id'=>$event,'owner_id'=>$owner,'kind'=>$kind,'due_at'=>$due,'status'=>'open','note'=>gt($p,'note',5,2000),'created_by'=>$uid,'created_at'=>$now,'updated_at'=>$now]);break;
    case 'task.finish':
        $r=ad_row($u,'tasks',$id);ad_version($r,$p);gx($r['status']==='open','Task already finished.',409);$status=ge($p,'status',['done','cancelled']);$completion=gt($p,'completion_note',5,2000);$detail='Task '.$status.': '.$completion;ad_update('tasks',$r,['status'=>$status,'completion_note'=>$completion,'completed_at'=>$now,'updated_at'=>$now]);$ticket=$r['ticket_id'];$event=$r['event_id'];break;
    case 'task.assign':
        gx(g_head($u));$r=ad_row($u,'tasks',$id);ad_version($r,$p);gx($r['status']==='open','Task is finished.',409);$owner=gi($p,'owner_id',1);$person=gone('SELECT * FROM users WHERE id=?',[$owner]);
        if($r['ticket_id']){ad_staff($owner);gx(g_case($person,ad_case($u,(int)$r['ticket_id'])),'Owner must have current case authorization.',403);}
        else gx($person&&in_array(g_staff($person),['head','administrator'],true),'Choose active activity staff.',400);
        $detail='Follow-up owner changed from staff '.$r['owner_id'].' to staff '.$owner.'.';ad_update('tasks',$r,['owner_id'=>$owner,'updated_at'=>$now]);$ticket=$r['ticket_id'];$event=$r['event_id'];break;
    case 'consent.record':
        $t=ad_case($u,gi($p,'ticket_id',1));$ticket=(int)$t['id'];$policy=gone("SELECT * FROM gd_adv_policies WHERE id=? AND retired_at IS NULL AND purpose IN ('care','referral')",[gi($p,'policy_id',1)]);gx($policy!==null,'Choose an active consent policy.',400);
        $expires=ad_date($p,'expires_on');gx($expires>=date('Y-m-d'),'Consent must have a current expiry date.',400);
        $id=ad_insert('consents',['ticket_id'=>$ticket,'policy_id'=>$policy['id'],'authority'=>ge($p,'authority',['student','guardian','authorized_representative']),'state'=>ge($p,'state',['granted','declined']),'evidence_ref'=>gt($p,'evidence_ref',5,200),'expires_on'=>$expires,'recorded_by'=>$uid,'recorded_at'=>$now]);$tab='consent';break;
    case 'consent.withdraw':
        $r=ad_row($u,'consents',$id);ad_version($r,$p);gx($r['state']==='granted','Only a granted consent can be withdrawn.',409);$detail='Withdrawal evidence: '.gt($p,'withdrawal_ref',5,200);ad_update('consents',$r,['state'=>'withdrawn','withdrawn_by'=>$uid,'withdrawn_at'=>$now]);$ticket=$r['ticket_id'];$tab='consent';break;
    case 'referral.create':
        $t=ad_case($u,gi($p,'ticket_id',1));$ticket=(int)$t['id'];$basis=ge($p,'basis',['consent','approved_exception']);$consent=null;$exception=null;$why='';
        if($basis==='consent'){$consent=gi($p,'consent_id',1);ad_consent($consent,$ticket);}else{ad_password($u,$p);$exception=(int)ad_policy(gi($p,'exception_policy_id',1),'referral')['id'];$why=gt($p,'justification',20,2000);}
        $id=ad_insert('referrals',['ticket_id'=>$ticket,'destination'=>gt($p,'destination',3,160),'destination_type'=>ge($p,'destination_type',['guidance','school_office','external_professional']),'consent_id'=>$consent,'exception_policy_id'=>$exception,'basis'=>$basis,'justification'=>$why,'minimum_summary'=>gt($p,'minimum_summary',5,1500),'status'=>'requested','evidence'=>gt($p,'evidence',5,1000),'created_by'=>$uid,'created_at'=>$now,'updated_at'=>$now]);$tab='referrals';break;
    case 'referral.update':
        $r=ad_row($u,'referrals',$id);ad_version($r,$p);$next=ge($p,'status',['accepted','scheduled','completed','declined','cancelled']);ad_transition($r['status'],$next,['requested'=>['accepted','declined','cancelled'],'accepted'=>['scheduled','completed','cancelled'],'scheduled'=>['completed','cancelled']]);
        if(!in_array($next,['cancelled','declined'],true)&&$r['basis']==='consent')ad_consent((int)$r['consent_id'],(int)$r['ticket_id']);
        if(!in_array($next,['cancelled','declined'],true)&&$r['basis']==='approved_exception')ad_policy((int)$r['exception_policy_id'],'referral');
        $scheduled=$next==='scheduled'?ad_datetime($p,'scheduled_at'):$r['scheduled_at'];if($next==='scheduled')gx($scheduled>$now,'Choose a future referral appointment.',400);
        $detail='Referral state '.$r['status'].' to '.$next.'. Evidence: '.gt($p,'evidence',5,2000);
        ad_update('referrals',$r,['status'=>$next,'scheduled_at'=>$scheduled,'evidence'=>gt($p,'evidence',5,2000),'updated_at'=>$now]);$ticket=$r['ticket_id'];$tab='referrals';break;
    case 'outcome.record':
        $t=ad_case($u,gi($p,'ticket_id',1));$ticket=(int)$t['id'];$measure=ge($p,'measure',['goal_progress','helpfulness']);$source=ge($p,'source',['student_reported','professional_review','declined']);$score=$source==='declined'?null:gi($p,'score',$measure==='helpfulness'?1:0,$measure==='helpfulness'?5:10);$date=ad_date($p,'observed_on');gx($date<=date('Y-m-d'),'Cannot record a future observation.',400);
        $id=ad_insert('outcomes',['ticket_id'=>$ticket,'measure'=>$measure,'score'=>$score,'source'=>$source,'note'=>gt($p,'note',0,2000),'recorded_by'=>$uid,'observed_on'=>$date,'created_at'=>$now]);$tab='care';break;
    case 'handover':
        ad_password($u,$p);$t=ad_case($u,gi($p,'ticket_id',1));$ticket=(int)$t['id'];gx((string)$t['assigned_to']===(string)gi($p,'expected_owner'),'Assignment changed. Refresh.',409);$to=gi($p,'counselor_id',1);ad_staff($to);gx($to!==(int)$t['assigned_to'],'Choose a different counselor.',400);gt($p,'reason',10,1000);
        if(gone("SELECT id FROM gd_adv_urgent WHERE ticket_id=? AND status<>'resolved'",[$ticket])){$fallback=gi($p,'fallback_id',1);ad_staff($fallback,true);gx($fallback!==$to,'An independent Head fallback is required.',400);gq("UPDATE gd_adv_urgent SET owner_id=?,fallback_id=?,version=version+1,updated_at=NOW() WHERE ticket_id=? AND status<>'resolved'",[$to,$fallback,$ticket]);}
        gq('UPDATE tickets SET assigned_to=?,updated_at=NOW() WHERE id=?',[$to,$ticket]);gq("UPDATE gd_adv_tasks SET owner_id=?,version=version+1,updated_at=NOW() WHERE ticket_id=? AND status='open'",[$to,$ticket]);gq("UPDATE gd_adv_waitlist SET counselor_id=?,version=version+1,updated_at=NOW() WHERE ticket_id=? AND status IN ('waiting','offered')",[$to,$ticket]);
        gq('INSERT INTO gd_plus_case_log(ticket_id,actor_id,action,reason,created_at) VALUES(?,?,?, ?,NOW())',[$ticket,$uid,'staff_handover','From staff '.($t['assigned_to']?:'unassigned').' to staff '.$to.'. '.gt($p,'reason',10,1000)]);$detail='Assigned from staff '.($t['assigned_to']?:'unassigned').' to staff '.$to.'. '.gt($p,'reason',10,1000);$id=$ticket;$tab='care';break;
    case 'rule.create':
        ad_clinical($u);$c=g_head($u)?gi($p,'counselor_id',1):$uid;ad_staff($c);$day=gi($p,'weekday',1,7);$start=ad_clock($p,'starts_time');$end=ad_clock($p,'ends_time');$from=ad_date($p,'effective_from');$to=ad_date($p,'effective_to');$slot=gi($p,'slot_minutes',15,120);$buffer=gi($p,'buffer_minutes',0,60);
        gx($end>$start&&$to>=$from&&(strtotime($to)-strtotime($from))<=366*86400&&$to>=date('Y-m-d'),'Invalid availability interval.',400);gx((strtotime('2000-01-01 '.$end)-strtotime('2000-01-01 '.$start))/60 >= $slot,'Availability is shorter than the slot duration.',400);
        gx(!gone('SELECT id FROM gd_adv_rules WHERE counselor_id=? AND active=1 AND weekday=? AND effective_from<=? AND effective_to>=? AND starts_time<? AND ends_time>?',[$c,$day,$to,$from,$end,$start]),'Overlapping recurring availability. Retire the old rule first.',409);
        $id=ad_insert('rules',['counselor_id'=>$c,'weekday'=>$day,'starts_time'=>$start,'ends_time'=>$end,'slot_minutes'=>$slot,'buffer_minutes'=>$buffer,'effective_from'=>$from,'effective_to'=>$to,'created_by'=>$uid,'created_at'=>$now]);$tab='schedule';break;
    case 'rule.retire': case 'block.retire':
        $table=$action==='rule.retire'?'rules':'blocks';$r=ad_row($u,$table,$id);ad_version($r,$p);gx((int)$r['active']===1,'Already inactive.',409);ad_update($table,$r,['active'=>0]);$tab='schedule';break;
    case 'block.create':
        ad_clinical($u);$c=g_head($u)?gi($p,'counselor_id',1):$uid;ad_staff($c);$start=ad_datetime($p,'starts_at');$end=ad_datetime($p,'ends_at');gx($end>$start&&$end>$now,'Invalid blocked interval.',400);
        gx(!gone("SELECT a.id FROM gd_appointments a LEFT JOIN gd_adv_appointments m ON m.appointment_id=a.id WHERE a.counselor_id=? AND a.status IN ('requested','confirmed') AND DATE_SUB(a.starts_at,INTERVAL COALESCE(m.buffer_minutes,0) MINUTE)<? AND DATE_ADD(a.ends_at,INTERVAL COALESCE(m.buffer_minutes,0) MINUTE)>?",[$c,$end,$start]),'Reschedule or cancel overlapping reservations before blocking this time.',409);
        $id=ad_insert('blocks',['counselor_id'=>$c,'starts_at'=>$start,'ends_at'=>$end,'reason'=>gt($p,'reason',3,160),'created_by'=>$uid,'created_at'=>$now]);$tab='schedule';break;
    case 'waitlist.create':
        $t=ad_case($u,gi($p,'ticket_id',1));$ticket=(int)$t['id'];$c=g_head($u)?gi($p,'counselor_id',1):$uid;ad_staff($c);gx(g_head($u)||$c===(int)$t['assigned_to']);$from=ad_date($p,'earliest_on');$to=ad_date($p,'latest_on');gx($from>=date('Y-m-d')&&$to>=$from,'Choose a future appointment window.',400);
        gx(!gone("SELECT id FROM gd_adv_waitlist WHERE ticket_id=? AND status IN ('waiting','offered')",[$ticket]),'This case already has a waiting appointment request.',409);
        $id=ad_insert('waitlist',['ticket_id'=>$ticket,'counselor_id'=>$c,'earliest_on'=>$from,'latest_on'=>$to,'priority'=>ge($p,'priority',['routine','soon','urgent']),'status'=>'waiting','created_by'=>$uid,'created_at'=>$now,'updated_at'=>$now]);$tab='schedule';break;
    case 'waitlist.update':
        $r=ad_row($u,'waitlist',$id);ad_version($r,$p);$next=ge($p,'status',['offered','withdrawn']);ad_transition($r['status'],$next,['waiting'=>['offered','withdrawn'],'offered'=>['withdrawn']]);ad_update('waitlist',$r,['status'=>$next,'updated_at'=>$now]);$ticket=$r['ticket_id'];$tab='schedule';break;
    case 'appointment.save':
        $t=ad_case($u,gi($p,'ticket_id',1));$ticket=(int)$t['id'];$c=g_head($u)?gi($p,'counselor_id',1):$uid;$start=ad_datetime($p,'starts_at');$end=ad_datetime($p,'ends_at');$waiting=gi($p,'waitlist_id');$w=null;
        if($waiting){$w=ad_row($u,'waitlist',$waiting);ad_version($w,$p);gx((int)$w['ticket_id']===$ticket&&(int)$w['counselor_id']===$c&&in_array($w['status'],['waiting','offered'],true),'Waitlist request does not match.',409);gx(substr($start,0,10)>=$w['earliest_on']&&substr($start,0,10)<=$w['latest_on'],'Outside the waiting request window.',400);}
        if($id){$old=gone('SELECT * FROM gd_appointments WHERE id=?',[$id]);gx($old&&(int)$old['user_id']===(int)$t['user_id']&&(g_head($u)||(int)$old['counselor_id']===$uid),'Appointment not found.',404);gx(in_array($old['status'],['requested','confirmed'],true)&&hash_equals(ad_appointment_stamp($old),gt($p,'expected_stamp',64,64)),'Appointment changed or is finished.',409);}
        $buffer=ad_schedule($c,(int)$t['user_id'],$start,$end,$id,true);$concern=gt($p,'concern',5,4000);
        if($id)gq("UPDATE gd_appointments SET counselor_id=?,starts_at=?,ends_at=?,concern=?,status='confirmed',updated_at=NOW() WHERE id=?",[$c,$start,$end,$concern,$id]);
        else{gq("INSERT INTO gd_appointments(user_id,counselor_id,starts_at,ends_at,concern,status,updated_at) VALUES(?,?,?,?,?,'confirmed',NOW())",[$t['user_id'],$c,$start,$end,$concern]);$id=(int)$pdo->lastInsertId();}
        gq("INSERT INTO gd_adv_appointments(appointment_id,ticket_id,buffer_minutes,reason,recorded_by,updated_at) VALUES(?,?,?,'',?,NOW()) ON DUPLICATE KEY UPDATE ticket_id=VALUES(ticket_id),buffer_minutes=VALUES(buffer_minutes),version=version+1,updated_at=NOW()",[$id,$ticket,$buffer,$uid]);
        $detail='Appointment #'.$id.(isset($old)?' rescheduled from '.$old['starts_at'].' to '.$old['ends_at'].' (staff '.$old['counselor_id'].')':' booked').'; new slot '.$start.' to '.$end.' (staff '.$c.').';
        if($w)ad_update('waitlist',$w,['status'=>'booked','appointment_id'=>$id,'updated_at'=>$now]);$tab='schedule';break;
    case 'appointment.attendance':
        ad_clinical($u);$a=gone('SELECT * FROM gd_appointments WHERE id=?',[$id]);gx($a&&(g_head($u)||(int)$a['counselor_id']===$uid),'Appointment not found.',404);
        gx($a['status']==='confirmed'&&$a['ends_at']<=$now&&hash_equals(ad_appointment_stamp($a),gt($p,'expected_stamp',64,64)),'Only an unchanged, ended confirmed appointment can be marked.',409);
        $state=ge($p,'attendance_state',['attended','no_show']);$reason=gt($p,'reason',5,1000);$meta=gone('SELECT * FROM gd_adv_appointments WHERE appointment_id=?',[$id]);$ticket=$meta['ticket_id']??null;
        gq("UPDATE gd_appointments SET status=?,updated_at=NOW() WHERE id=?",[$state==='attended'?'completed':'cancelled',$id]);
        gq("INSERT INTO gd_adv_appointments(appointment_id,attendance_state,reason,recorded_by,updated_at) VALUES(?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE attendance_state=VALUES(attendance_state),reason=VALUES(reason),recorded_by=VALUES(recorded_by),version=version+1,updated_at=NOW()",[$id,$state,$reason,$uid]);$tab='schedule';break;
    case 'urgent.create':
        $t=ad_case($u,gi($p,'ticket_id',1));$ticket=(int)$t['id'];$owner=(int)($t['assigned_to']?:$uid);ad_staff($owner);$fallback=gi($p,'fallback_id',1);ad_staff($fallback,true);gx($fallback!==$owner,'Use an independent Head fallback; assign a responding counselor first.',400);$policy=ad_policy(gi($p,'policy_id',1),'urgent');$due=ad_datetime($p,'due_at');gx($due>$now&&strtotime($due)-time()<=86400,'Acknowledgment deadline must be within the next 24 hours.',400);
        gx(!gone("SELECT id FROM gd_adv_urgent WHERE ticket_id=? AND status<>'resolved'",[$ticket]),'An urgent review is already open.',409);
        $id=ad_insert('urgent',['ticket_id'=>$ticket,'policy_id'=>$policy['id'],'owner_id'=>$owner,'fallback_id'=>$fallback,'due_at'=>$due,'reason'=>gt($p,'reason',10,2000),'status'=>'open','resolution'=>'','created_by'=>$uid,'created_at'=>$now,'updated_at'=>$now]);$tab='urgent';break;
    case 'urgent.ack': case 'urgent.resolve':
        $r=ad_row($u,'urgent',$id);ad_version($r,$p);$ticket=$r['ticket_id'];
        if($action==='urgent.ack'){gx($r['status']==='open','This review is already acknowledged or resolved.',409);ad_update('urgent',$r,['status'=>'acknowledged','acknowledged_by'=>$uid,'acknowledged_at'=>$now,'updated_at'=>$now]);}
        else{gx($r['status']==='acknowledged','Acknowledge and review before resolving.',409);ad_update('urgent',$r,['status'=>'resolved','resolution'=>gt($p,'resolution',10,3000),'updated_at'=>$now]);}$tab='urgent';break;
    case 'program.save':
        ad_ops($u);$program=gi($p,'program_id',1);gx((bool)gone('SELECT id FROM gd_programs WHERE id=?',[$program]),'Program not found.',404);$r=gone('SELECT * FROM gd_adv_programs WHERE program_id=?',[$program]);gx(!$r||!gb($p,'initial'),'A plan already exists. Use its current revision form below.',409);$from=ad_date($p,'period_from');$to=ad_date($p,'period_to');gx($to>=$from&&(strtotime($to)-strtotime($from))<=365*86400,'Use a program period of up to 366 days.',400);$budget=gt($p,'budget',1,12);gx((bool)preg_match('/^\d{1,7}(?:\.\d{1,2})?$/D',$budget),'Use a nonnegative PHP planning estimate.',400);
        $v=['objectives'=>gt($p,'objectives',10,3000),'target_group'=>gt($p,'target_group',3,200),'resources'=>gt($p,'resources',3,3000),'budget'=>$budget,'expected_participation'=>gi($p,'expected_participation',0,1000000),'period_from'=>$from,'period_to'=>$to,'updated_by'=>$uid,'updated_at'=>$now];
        if($r){ad_version($r,$p);ad_insert('reviews',['program_id'=>$program,'objectives_snapshot'=>$r['objectives'],'resources_snapshot'=>$r['resources'],'result_note'=>'Prior plan snapshot before revision.','lessons'=>'','rating'=>0,'recorded_by'=>$uid,'created_at'=>$now]);ad_update('programs',$r,$v);$id=(int)$r['id'];}else $id=ad_insert('programs',['program_id'=>$program]+$v);$tab='programs';break;
    case 'program.review':
        ad_ops($u);$r=ad_row($u,'programs',$id);$id=ad_insert('reviews',['program_id'=>$r['program_id'],'objectives_snapshot'=>$r['objectives'],'resources_snapshot'=>$r['resources'],'result_note'=>gt($p,'result_note',10,3000),'lessons'=>gt($p,'lessons',10,3000),'rating'=>gi($p,'rating',1,5),'recorded_by'=>$uid,'created_at'=>$now]);$tab='programs';break;
    case 'policy.publish':
        ad_password($u,$p);gx(gb($p,'approved')===1,'Record actual institutional approval before publishing.',400);$purpose=ge($p,'purpose',['care','referral','urgent','retention']);$version=gt($p,'version_label',1,80);gx(!gone('SELECT id FROM gd_adv_policies WHERE purpose=? AND version_label=?',[$purpose,$version]),'This policy version already exists; publish a new version label.',409);
        $id=ad_insert('policies',['purpose'=>$purpose,'version_label'=>$version,'title'=>gt($p,'title',5,160),'body'=>gt($p,'body',30,10000),'approval_ref'=>gt($p,'approval_ref',5,200),'approved_by'=>$uid,'approved_at'=>$now]);$tab='governance';break;
    case 'policy.retire':
        ad_password($u,$p);$r=ad_row($u,'policies',$id);gx($r['retired_at']===null,'Already retired.',409);gq('UPDATE gd_adv_policies SET retired_at=NOW() WHERE id=?',[$id]);$tab='governance';break;
    case 'check.record':
        gx(g_head($u));$id=ad_insert('checks',['kind'=>ge($p,'kind',['restore_drill','backup_verification','access_review','retention_review','reminder_test']),'result'=>ge($p,'result',['passed','failed','needs_action']),'evidence_ref'=>gt($p,'evidence_ref',5,200),'details'=>gt($p,'details',10,3000),'next_due'=>ad_date($p,'next_due'),'recorded_by'=>$uid,'created_at'=>$now]);$tab='governance';break;
    case 'reminders.run':
        gx(g_head($u));require_once __DIR__.'/staff-jobs-core.php';ad_dispatch();$tab='governance';break;
    case 'reminders.configure':
        ad_password($u,$p);$enabled=gb($p,'enabled');gq("INSERT INTO gd_settings(setting_key,setting_value) VALUES('staff_reminders',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)",[(string)$enabled]);$tab='governance';break;
    default: gx(false,'Unknown staff action.',400);
    }
    ad_log($u,$action,$id,$ticket?:null,$event?:null,$detail);
    return ['id'=>$id,'ticket'=>$ticket?:0,'tab'=>$tab,'message'=>'Saved. Staff-only records remain confidential.'];
}
