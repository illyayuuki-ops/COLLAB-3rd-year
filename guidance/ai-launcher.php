<?php
if(!isset($u)||!function_exists('g_staff')||!function_exists('url')){http_response_code(404);exit;}
if(in_array(g_staff($u),['head','counselor','administrator'],true)){
    $map=['reports'=>'reports','events'=>'activities','programs'=>'programs','appointments'=>'schedule','knowledge'=>'resources','settings'=>'settings','register'=>'certificates'];
    $staffMap=['desk'=>'followthrough','care'=>'support','consent'=>'consent','referrals'=>'referrals','urgent'=>'urgent','schedule'=>'schedule','programs'=>'programs','planning'=>'planning','knowledge'=>'resources','governance'=>'governance'];
    $topic=isset($tab)&&!isset($view)?($staffMap[$tab]??'welcome'):($map[$view??'']??'welcome');
    echo '<link rel="stylesheet" href="'.e(url('guidance/assets/assistant.css')).'?v=1"><a class="gai-launcher" href="'.e(url('guidance/assistant.php?topic='.$topic)).'"><span aria-hidden="true">✦</span> Staff assistant</a>';
}
