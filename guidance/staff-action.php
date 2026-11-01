<?php
require dirname(__DIR__).'/config/app.php';
require __DIR__.'/domain.php';
require_once __DIR__.'/staff-core.php';
try {
    $u=g_actor();ad_role($u);gx($_SERVER['REQUEST_METHOD']==='POST','Use POST.',405);gx(g_rate('staff-advanced-write:'.$u['id'],300),'Too many requests. Try later.',429);
    $action=gt($_POST,'action',1,50);
    if(in_array($action,['policy.publish','policy.retire','handover','reminders.configure'],true)||($action==='referral.create'&&($_POST['basis']??'')==='approved_exception')){gx(g_head($u));gx(g_rate('advanced-sensitive:'.$u['id'],20),'Too many sensitive-action attempts. Try later.',429);}
    $result=g_transaction(fn()=>ad_action($u,$action,$_POST));
    flash($result['message']);header('Location: '.url('guidance/staff.php?'.http_build_query(['tab'=>$result['tab'],'ticket'=>$result['ticket']])) ,true,303);exit;
} catch(Throwable $e) {
    if(($action??'')==='reminders.run'&&isset($u)){try{if(g_head($u))gq("INSERT INTO gd_settings(setting_key,setting_value) VALUES('staff_job_last_failure',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)",[date('Y-m-d H:i:s')]);}catch(Throwable $ignored){}}
    $expected=$e instanceof RuntimeException&&!($e instanceof PDOException)&&in_array($e->getCode(),[400,403,404,405,409,422,429,503],true);
    if(!$expected)error_log('Staff workspace failure: '.get_class($e));
    g_denied($expected?$e->getMessage():'Unable to save. No partial database changes were committed. Contact the Guidance Head.',$expected?$e->getCode():500);
}
