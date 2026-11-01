<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/config/app.php';require __DIR__.'/domain.php';require_once __DIR__.'/staff-core.php';require __DIR__.'/staff-jobs-core.php';
if(!ad_ready())throw new RuntimeException('Staff extension is not enabled.');
$enabled=(gone("SELECT setting_value FROM gd_settings WHERE setting_key='staff_reminders'")['setting_value']??'0')==='1';
if(!in_array('--run',$argv,true)){echo 'READ ONLY: generic staff reminders '.($enabled?'enabled':'disabled').". Run with --run only after Head approval. This is not an emergency monitoring service.\n";exit;}
if(!$enabled){echo "Disabled; no notifications dispatched.\n";exit;}
try{g_transaction(fn()=>ad_dispatch());echo "Staff reminder pass completed. No student notifications, external email/SMS or clinical content were sent.\n";}catch(Throwable $e){try{gq("INSERT INTO gd_settings(setting_key,setting_value) VALUES('staff_job_last_failure',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)",[date('Y-m-d H:i:s')]);}catch(Throwable $ignored){}fwrite(STDERR,'Staff reminder pass failed ('.get_class($e).'). Check local availability and Head monitoring. No partial dispatch was committed.'.PHP_EOL);exit(1);}
