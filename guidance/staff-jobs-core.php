<?php
if(!function_exists('ad_role')){http_response_code(404);exit;}
function ad_dispatch(): int {
    global $pdo;gx($pdo->inTransaction(),'Run staff reminders through the locked transaction.',500);gx(ad_ready(),'Staff extension is not ready.',503);
    gx((gone("SELECT setting_value FROM gd_settings WHERE setting_key='staff_reminders'")['setting_value']??'0')==='1','Staff reminders are disabled. Head approval is required.',409);
    $heads=gall("SELECT u.* FROM users u JOIN gd_staff s ON s.user_id=u.id WHERE s.guidance_role='head' AND s.active=1 AND u.status='active' AND u.role='admin' AND u.office_code='guide'");$items=[];
    foreach(gall("SELECT * FROM gd_adv_tasks WHERE status='open' AND due_at<=DATE_ADD(NOW(),INTERVAL 1 DAY) ORDER BY due_at LIMIT 501") as $r){
        gx(count($items)<500,'More than 500 due items. Review the backlog; no partial dispatch was committed.',422);$person=gone('SELECT * FROM users WHERE id=?',[$r['owner_id']]);$valid=false;
        if($r['ticket_id']){$t=gone("SELECT * FROM tickets WHERE id=? AND office_code='guide'",[$r['ticket_id']]);$valid=$person&&$t&&in_array(g_staff($person),['head','counselor'],true)&&g_case($person,$t);}
        else $valid=$person&&in_array(g_staff($person),['head','administrator'],true);
        foreach($valid?[$person]:$heads as $who)$items[]=['task',(int)$r['id'],(int)$who['id'],'Guidance staff follow-up due'];
    }
    foreach(gall("SELECT * FROM gd_adv_urgent WHERE status='open' ORDER BY due_at LIMIT 501") as $r){
        gx(count($items)<500,'More than 500 pending notices. Review urgent staffing; no partial dispatch was committed.',422);
        $person=gone('SELECT * FROM users WHERE id=?',[$r['owner_id']]);$t=gone("SELECT * FROM tickets WHERE id=? AND office_code='guide'",[$r['ticket_id']]);
        $valid=$person&&$t&&in_array(g_staff($person),['head','counselor'],true)&&g_case($person,$t);foreach($valid?[$person]:$heads as $who)$items[]=['urgent_owner',(int)$r['id'],(int)$who['id'],'Guidance urgent staff review pending'];
        if($r['due_at']<=date('Y-m-d H:i:s')){ $fallback=gone('SELECT * FROM users WHERE id=?',[$r['fallback_id']]);foreach($fallback&&g_head($fallback)?[$fallback]:$heads as $who)$items[]=['urgent_fallback',(int)$r['id'],(int)$who['id'],'Guidance urgent review needs Head acknowledgment']; }
    }
    gx(count($items)<=500,'More than 500 notices. Review the backlog before dispatch.',422);$sent=0;
    foreach($items as [$type,$id,$recipient,$title]){
        if(gone('SELECT id FROM gd_adv_dispatch WHERE item_type=? AND item_id=? AND recipient_id=? AND day_key=?',[$type,$id,$recipient,date('Y-m-d')]))continue;
        gq("INSERT INTO notifications(user_id,title,body) VALUES(?,?,'Open the Guidance staff workspace. This message contains no student or case details.')",[$recipient,$title]);
        gq('INSERT INTO gd_adv_dispatch(item_type,item_id,recipient_id,day_key,delivered_at) VALUES(?,?,?,?,NOW())',[$type,$id,$recipient,date('Y-m-d')]);$sent++;
    }
    gq("INSERT INTO gd_settings(setting_key,setting_value) VALUES('staff_job_last_success',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)",[date('Y-m-d H:i:s')]);return $sent;
}
