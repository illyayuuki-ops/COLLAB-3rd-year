<?php
require dirname(__DIR__).'/config/app.php';require dirname(__DIR__).'/includes/layout.php';require __DIR__.'/domain.php';
$u=g_actor();
try{
 $role=ad_role($u);gx(ad_ready(),'The staff extension is installed but not enabled. Ask the coordinator to review the additive migration plan and verify a backup first.',503);
 $tabs=['desk'=>'Follow-through'];
 if(in_array($role,['head','counselor'],true))$tabs+=['care'=>'Support plans','consent'=>'Consent records','referrals'=>'Referrals','urgent'=>'Urgent review','schedule'=>'Scheduling'];
 if(in_array($role,['head','administrator'],true))$tabs+=['programs'=>'Program planning'];
 $tabs+=['planning'=>'Planning report','knowledge'=>'Published resources'];if($role==='head')$tabs+=['governance'=>'Governance & health'];
 $tab=gt($_GET,'tab',0,30,'desk');gx(isset($tabs[$tab]),'This staff area is not available for your role.',403);$ticket=gi($_GET,'ticket');$search=gt($_GET,'q',0,80);if($ticket)ad_case($u,$ticket);
 foreach($_GET as $key=>$value)if(str_starts_with((string)$key,'p_'))gi($_GET,$key,1,10000,1);
 $format=gt($_GET,'format',0,10);gx($format===''||($tab==='planning'&&in_array($format,['csv','print'],true)),'Unknown staff report format.',400);
 if($tab==='planning'){
  require __DIR__.'/staff-planning.php';require_once __DIR__.'/reports-view.php';$planning=ad_planning($u,$_GET);
  if($format){gx(g_rate('guidance_reports_export_'.$u['id'],30),'Too many report exports. Try later.',429);g_audit($u,'staff_planning_export','Aggregate '.$format.'; no clinical content');
   if($format==='csv'){header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="guidance-staff-planning.csv"');echo "\xEF\xBB\xBF".g_csv(array_merge([['Section','Measure','Result','Basis / definition']],rp_export_rows($planning)));}
   else{header('Content-Type: text/html; charset=utf-8');echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Guidance staff planning</title><style>'.file_get_contents(__DIR__.'/assets/reports.css').'</style></head><body class="rp-print"><div class="rp-print-toolbar"><button onclick="window.print()">Print / save as PDF</button><span>Restricted staff aggregate report.</span></div><main>'.rp_render($planning).'</main></body></html>';}
   exit;
  }
 }
}catch(Throwable $e){$expected=$e instanceof RuntimeException&&!($e instanceof PDOException)&&in_array($e->getCode(),[400,403,404,422,429,503],true);if(!$expected)error_log('Staff workspace read failure: '.get_class($e));g_denied($expected?$e->getMessage():'The staff workspace could not be loaded. Contact the Guidance Head.',$expected?$e->getCode():500);}
page_start('Guidance staff workspace','Staff-only · Philippine local time');
?>
<link rel="stylesheet" href="<?=e(url('guidance/assets/guidance.css'))?>">
<link rel="stylesheet" href="<?=e(url('guidance/assets/layout-pass.css'))?>">
<link rel="stylesheet" href="<?=e(url('guidance/assets/staff.css'))?>?v=<?=(int)filemtime(__DIR__.'/assets/staff.css')?>">
<div class="ad-workspace" id="guidance">
<?php if(getenv('LCCT_SYNTHETIC_TEST')==='1')echo '<div class="alert warn">Synthetic isolated test data. No school records or external message delivery.</div>';
require __DIR__.'/staff-view.php';
if($tab==='planning'){
 // Reuse the same controls/model without sending filters to the older report route.
 if(!function_exists('gh')){function gh(string $n,$v):string{return ah($n,$v);}function gf(string $n,string $l,string $t='text',$v='',string $a=''):string{return af($n,$l,$t,$v,$a);}function gs(string $n,string $l,array $i,$v='',string $a=''):string{return ase($n,$l,$i,$v);}}
 $controls=rp_controls($planning);$controls=str_replace(['name="view" value="reports"',e(url('guidance/index.php?view=reports')),e(url('guidance/reports-export.php?'))],['name="tab" value="planning"',e(url('guidance/staff.php?tab=planning')),e(url('guidance/staff.php?tab=planning&'))],$controls);
 echo '<link rel="stylesheet" href="'.e(url('guidance/assets/reports.css')).'">'.$controls.rp_render($planning);
}
?>
<footer class="ad-footer">Staff-only extension · Existing student portal and shared login are unchanged. Clinical notes are not student-visible replies.</footer>
</div>
<?php require __DIR__.'/ai-launcher.php'; page_end(); ?>
