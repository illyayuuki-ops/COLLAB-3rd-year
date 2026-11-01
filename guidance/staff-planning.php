<?php
if(!function_exists('ad_role')){http_response_code(404);exit;}
require_once __DIR__.'/reports.php';
function ad_mean(string $label,array $rows,float $value,string $suffix,string $note): array {
    $m=rp_measure($label,$rows,$note,$value,'score');$m['unit']='number';if($m['value']!==null)$m['display']=number_format($value,1).$suffix;return $m;
}
function ad_planning(array $u,array $input): array {
 global $pdo;$own=!$pdo->inTransaction();if($own)$pdo->beginTransaction();try{$r=ad_build_planning($u,$input);if($own)$pdo->commit();return $r;}catch(Throwable $e){if($own&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function ad_build_planning(array $u,array $input): array {
    ad_role($u);$out=rp_report($u,$input);$f=$out['filters'];$out['title']='Staff continuity & semester planning';
    $keep=$f['mode']==='clinical'?['Selected-period intake','Monthly intake trend','Current workload snapshot','Response and resolution']:['Activity portfolio','Portfolio comparison','Registration capacity','Attendance coverage','Certificate follow-through'];
    $out['sections']=array_values(array_filter($out['sections'],fn($s)=>in_array($s['title'],$keep,true)));
    if($f['mode']==='clinical'){
        $args=[];$where=ad_scope($u,'t',$args);if($f['category']){$where.=' AND t.category_id=?';$args[]=$f['category'];}if(g_head($u)&&$f['counselor']){$where.=' AND t.assigned_to=?';$args[]=$f['counselor'];}$cohort=rp_cohort_where($f,'t.user_id',$args);if($cohort)$where.=' AND '.$cohort;
        $plans=rp_fetch('SELECT p.id AS _key,t.user_id AS student,p.status FROM gd_adv_plans p JOIN tickets t ON t.id=p.ticket_id WHERE '.$where,$args);
        $out['sections'][]=rp_breakdown('Current support plans',rp_partition($plans,'status',['active'=>'Active','completed'=>'Completed','archived'=>'Archived']),'Current plan states across all creation dates, within current case/academic/assignment filters. Not plans created during the selected period.');
        $tasks=rp_fetch("SELECT a.id AS _key,t.user_id AS student,a.due_at FROM gd_adv_tasks a JOIN tickets t ON t.id=a.ticket_id WHERE $where AND a.status='open'",$args);
        $overdue=array_values(array_filter($tasks,fn($r)=>$r['due_at']<date('Y-m-d H:i:s')));
        $out['sections'][]=['title'=>'Current clinical follow-through','note'=>'All-date current open tasks within the same current case filters. Overdue does not indicate student risk.','items'=>rp_relation($tasks,$overdue,'Open follow-up tasks','Overdue follow-up tasks','Task records, not unique students.')];
        $ref=rp_fetch('SELECT a.id AS _key,t.user_id AS student,a.status FROM gd_adv_referrals a JOIN tickets t ON t.id=a.ticket_id WHERE '.$where,$args);
        $out['sections'][]=rp_breakdown('Current referral states',rp_partition($ref,'status',['requested'=>'Requested','accepted'=>'Accepted','scheduled'=>'Scheduled','completed'=>'Completed','declined'=>'Declined','cancelled'=>'Cancelled']),'All-date referral records, manually tracked. No claim that the system delivered clinical information.');
        $o=rp_fetch('SELECT o.id,o.ticket_id AS _key,t.user_id AS student,o.measure,o.source,o.score,o.observed_on FROM gd_adv_outcomes o JOIN tickets t ON t.id=o.ticket_id WHERE '.$where.' AND o.observed_on>=? AND o.observed_on<=? AND o.score IS NOT NULL ORDER BY o.observed_on,o.id',array_merge($args,[$f['from'],$f['to']]));
        $items=[];
        foreach(['goal_progress'=>10,'helpfulness'=>5] as $measure=>$max)foreach(['student_reported','professional_review'] as $source){
            $first=[];$latest=[];foreach($o as $r)if($r['measure']===$measure&&$r['source']===$source){$first[$r['_key']]??=$r;$latest[$r['_key']]=$r;}
            $sample=array_values($latest);$label=ucwords(str_replace('_',' ',$source.' '.$measure));$items[]=ad_mean($label.' · latest per case',$sample,$sample?array_sum(array_column($sample,'score'))/count($sample):0,' / '.$max,'Latest recorded value per case within the selected observation dates; sources/scales never mixed.');
            if($measure==='goal_progress'){$pairs=[];foreach($latest as $key=>$r)if($r['observed_on']>$first[$key]['observed_on'])$pairs[]=['student'=>$r['student'],'change'=>(int)$r['score']-(int)$first[$key]['score']];$items[]=ad_mean($label.' · paired change',$pairs,$pairs?array_sum(array_column($pairs,'change'))/count($pairs):0,' points','First to latest on different dates within the selected window, same source and 0–10 scale. Not a causal effectiveness score.');}
        }
        $out['sections'][]=['title'=>'Recorded support outcomes','note'=>'Voluntary staff-entered observations, not diagnoses or validated screening results. Declined/missing scores are excluded. At least five distinct students are required per released sample.','items'=>$items];
        if(!$f['category']){
            $aa=[$f['from'].' 00:00:00',(new DateTimeImmutable($f['to']))->modify('+1 day')->format('Y-m-d').' 00:00:00'];$aw='a.starts_at>=? AND a.starts_at<?';if($f['counselor']){$aw.=' AND a.counselor_id=?';$aa[]=$f['counselor'];}$ac=rp_cohort_where($f,'a.user_id',$aa);if($ac)$aw.=' AND '.$ac;
            $appointments=rp_fetch("SELECT a.id AS _key,a.user_id AS student,CASE WHEN m.attendance_state IN ('attended','no_show') THEN m.attendance_state WHEN a.status='completed' THEN 'completed_legacy' WHEN a.status='cancelled' THEN 'cancelled_other' ELSE a.status END AS outcome FROM gd_appointments a LEFT JOIN gd_adv_appointments m ON m.appointment_id=a.id WHERE ".$aw,$aa);
            $out['sections'][]=rp_breakdown('Appointment follow-through',rp_partition($appointments,'outcome',['requested'=>'Requested','confirmed'=>'Confirmed / no recorded outcome','attended'=>'Staff-verified attended','no_show'=>'Staff-verified no-show','completed_legacy'=>'Completed through other workflow','cancelled_other'=>'Other cancellation']),'Appointment start dates and appointment-owner/cohort scope. Only explicitly recorded no-shows count as no-shows. Past appointments are never automatically marked missed. Native completed status alone is not staff-verified attendance.');
        }else{$out['definitions'][]='Appointment outcomes are omitted under a service filter because legacy appointments lack a reliable service link.';}
        $out['definitions'][]='Planning guidance: use intake volume and recurring patterns alongside published appointment capacity, referral delays and follow-up backlogs. Do not rank students or infer risk from these metrics. Averages/changes are not evidence that counseling caused an outcome.';
    }else{
        $args=[$f['from'].' 00:00:00',(new DateTimeImmutable($f['to']))->modify('+1 day')->format('Y-m-d').' 00:00:00'];$where='e.starts_at>=? AND e.starts_at<?';if($f['program']){$where.=' AND e.program_id=?';$args[]=$f['program'];}
        $rows=gall('SELECT a.status,COUNT(*) n FROM gd_adv_tasks a JOIN gd_events e ON e.id=a.event_id WHERE a.ticket_id IS NULL AND '.$where.' GROUP BY a.status',$args);$items=[];foreach($rows as $r)$items[]=rp_public(ucfirst($r['status']).' activity tasks',(int)$r['n']);
        $out['sections'][]=['title'=>'Activity follow-through','note'=>'Administrative task records attached to activities starting in the selected period. No participant identities or task text.','items'=>$items];
        $pa=$f['program']?[$f['program']]:[];$pw=$f['program']?' WHERE program_id=?':'';
        $plans=(int)gq('SELECT COUNT(*) FROM gd_adv_programs'.$pw,$pa)->fetchColumn();$reviews=(int)gq('SELECT COUNT(*) FROM gd_adv_reviews'.($pw?$pw.' AND':' WHERE').' rating>0',$pa)->fetchColumn();
        $out['sections'][]=['title'=>'Program planning coverage','note'=>'Current configured program plans and all-date staff evaluation records. Not student feedback, and not limited by activity dates.','items'=>[rp_public('Documented program plans',$plans),rp_public('Recorded staff evaluations',$reviews)]];
        $out['definitions'][]='Planning guidance: compare participation and pending attendance/certificate work against each program’s documented objectives and resources. Staff delivery evaluations are not student satisfaction or clinical outcomes.';
    }
    return $out;
}
