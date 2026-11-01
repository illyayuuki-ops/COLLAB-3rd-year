<?php
define('LCCT_AI_INTERNAL',true);require dirname(__DIR__).'/config/app.php';require dirname(__DIR__).'/includes/layout.php';require __DIR__.'/ai-core.php';
$u=g_actor();try{$role=gai_role(g_staff($u));}catch(Throwable $e){g_denied('This assistant is for authorized Guidance staff only.',403);}
header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');
$labels=['head'=>'Guidance Head','counselor'=>'Guidance Counselor','administrator'=>'Guidance Operations'];
$initial=is_string($_GET['topic']??null)?$_GET['topic']:'welcome';try{gai_topic($role,$initial);}catch(Throwable $e){$initial='welcome';}
$topics=gai_topics($role);$boot=['endpoint'=>url('guidance/ai-api.php'),'csrf'=>csrf_token(),'role'=>$role,'initial'=>$initial];
page_start('Guidance assistant','Staff-only help · Reports stay in the application');
?>
<link rel="stylesheet" href="<?=e(url('guidance/assets/assistant.css'))?>?v=1">
<section id="gai" class="gai-shell" aria-label="Guidance staff assistant">
 <aside class="gai-library">
  <a class="gai-back" href="<?=e(url('guidance/staff.php'))?>">← Staff workspace</a>
  <div class="gai-brand"><span class="gai-mark" aria-hidden="true">✦</span><div><strong>Guidance assistant</strong><small>Help for the work you do</small></div></div>
  <div class="gai-role"><span>YOUR ACCESS PROFILE</span><strong><?=e($labels[$role])?></strong><small>Permissions are enforced by the server.</small></div>
  <label class="gai-search">Find a help topic<input id="gai-search" type="search" placeholder="Reports, scheduling, resources…" autocomplete="off"></label>
  <nav id="gai-topics" aria-label="Assistant help topics">
  <?php foreach($topics as $t):?><button type="button" data-topic="<?=e($t['id'])?>"><span><?=e($t['title'])?></span><?php if(!$t['cloud']):?><small>Local guide</small><?php endif;?></button><?php endforeach;?>
  </nav>
  <p class="gai-library-note">No local AI model is installed. Optional explanations use the Gemini API through a private worker.</p>
 </aside>
 <div class="gai-main">
  <header class="gai-header"><div><span class="gai-eyebrow">LCCT · STAFF WORKSPACE</span><h1>A little help, right here.</h1><p>Understand a feature. Find the next step. Prepare an authorized report.</p></div><div class="gai-header-actions"><span id="gai-status" class="gai-status" role="status">Checking availability…</span><button id="gai-clear" class="gai-button gai-quiet" type="button">Clear conversation</button></div></header>
  <div class="gai-privacy"><strong>Keep personal details out of chat.</strong> Messages are matched to topics locally. Only approved generic help instructions can go to Gemini—not your original message, school records or report results. This module does not save raw chat text.</div>
  <div class="gai-tabs"><button id="gai-chat-tab" type="button" class="selected" aria-pressed="true">Ask & learn</button><button id="gai-report-tab" type="button" aria-pressed="false">Prepare a report <span>↗</span></button></div>
  <section id="gai-report-panel" class="gai-report-panel" hidden>
   <div><span class="gai-eyebrow">LOCAL REPORT BUILDER</span><h2>Real data. Your existing permissions.</h2><p>Choose the scope, then open the existing report engine. No report data is sent to Gemini.</p></div>
   <form id="gai-report-form">
    <label>Report type<select name="kind"><option value="summary">Service / activity summary</option><option value="planning">Staff continuity &amp; semester planning</option></select></label>
    <label>Report area<select name="mode"><?php if($role!=='counselor'):?><option value="activities">Activity participation & delivery</option><?php endif;?><?php if($role!=='administrator'):?><option value="clinical">Authorized clinical-service aggregates</option><?php endif;?></select></label>
    <label>Reporting period<select name="preset" id="gai-preset"><option value="month">This month to date</option><option value="30">Last 30 days</option><option value="90">Last 90 days</option><option value="year">Year to date</option><option value="custom">Custom dates</option></select></label>
    <div id="gai-dates" class="gai-dates" hidden><label>From<input type="date" name="from" min="1971-01-01" max="9998-12-31"></label><label>Through<input type="date" name="to" min="1971-01-01" max="9998-12-31"></label></div>
    <button class="gai-button gai-primary" type="submit">Prepare report options →</button>
   </form><div id="gai-report-result" aria-live="polite"></div>
   <p class="gai-small">Counselor reports remain assignment-scoped. Operations cannot request clinical reports. Small-group suppression and export limits remain active.</p>
  </section>
  <section id="gai-chat-panel" class="gai-chat-panel">
   <div id="gai-messages" class="gai-messages" role="log" aria-live="polite" aria-relevant="additions text" aria-label="Assistant conversation"></div>
   <form id="gai-compose" class="gai-compose">
    <label for="gai-question" class="gai-compose-label">Ask about a feature—not a person or case</label>
    <div class="gai-input-row"><textarea id="gai-question" rows="2" maxlength="1800" placeholder="How do I prepare this month's report?" autocomplete="off" spellcheck="true"></textarea><button id="gai-send" class="gai-button gai-primary" type="submit">Send <span aria-hidden="true">↑</span></button></div>
    <div class="gai-compose-foot"><label><input id="gai-enhance" type="checkbox" checked> Add a Gemini explanation when available</label><span>Enter to send · Shift+Enter for a new line</span></div>
   </form>
  </section>
  <footer class="gai-footer"><span>AI explains; PHP authorizes. No autonomous record changes or clinical decisions.</span><?php if($role==='head'):?><button id="gai-pause" class="gai-link-button" type="button" disabled>Pause Gemini</button><?php endif;?></footer>
 </div>
</section>
<script type="application/json" id="gai-boot"><?=json_encode($boot,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR)?></script>
<script src="<?=e(url('guidance/assets/assistant.js'))?>?v=1" defer></script>
<?php page_end(); ?>
