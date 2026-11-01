<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/config/app.php';
require __DIR__.'/staff-schema.php';
if(!g_ready()||!gr_ready())throw new RuntimeException('Install the verified Guidance/Restoration baseline first.');
$plan=gad_plan($pdo,__DIR__.'/migrations/003_staff_advanced.sql');
if(!in_array('--apply',$argv,true)){echo "PLAN ONLY: 18 additive gd_adv tables; no writes, imports, deletions or shared-table alterations.\n";foreach($plan as $p)echo $p['table'].' : '.($p['exists']?'compatible':'would create')."\n";echo "After a verified database/uploads backup: add --apply --backup-confirmed.\n";exit;}
if(!in_array('--backup-confirmed',$argv,true))throw new RuntimeException('Stop: verify a database/uploads backup and key recovery before acknowledging --backup-confirmed.');
$lock='guidance-write:'.DB_NAME;
if((int)gq('SELECT GET_LOCK(?,10)',[$lock])->fetchColumn()!==1)throw new RuntimeException('Guidance is busy. Stop the server first.');
try{
 $plan=gad_plan($pdo,__DIR__.'/migrations/003_staff_advanced.sql');
 foreach($plan as $p)$pdo->exec($p['sql']);
 gad_plan($pdo,__DIR__.'/migrations/003_staff_advanced.sql');
 gq("INSERT INTO gd_settings(setting_key,setting_value) VALUES('staff_advanced','1') ON DUPLICATE KEY UPDATE setting_value='1'");
 gq("INSERT IGNORE INTO gd_settings(setting_key,setting_value) VALUES('staff_reminders','0')");
 echo "Staff extension enabled. 18 additive tables; student portal/shared schema unchanged. Reminders default OFF.\nDDL is not transactional. Retain backups; no automated downgrade or table deletion is provided.\n";
}finally{gq('SELECT RELEASE_LOCK(?)',[$lock]);}
