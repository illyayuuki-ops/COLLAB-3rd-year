(()=>{'use strict';
const bootNode=document.getElementById('gai-boot');if(!bootNode)return;
const boot=JSON.parse(bootNode.textContent),$=id=>document.getElementById(id),log=$('gai-messages');
let context='welcome',generation=0,busy=false,status={ready:false,approved:false,paused:true};
const el=(tag,text,cls)=>{const n=document.createElement(tag);if(text!==undefined)n.textContent=text;if(cls)n.className=cls;return n;};
const scroll=()=>{log.scrollTop=log.scrollHeight;};
async function api(action,fields={}){const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),9000);
 try{const r=await fetch(boot.endpoint,{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:new URLSearchParams({csrf:boot.csrf,action,...fields}),signal:controller.signal});
 const type=r.headers.get('content-type')||'';if(!type.includes('application/json'))throw Error('Your session may have expired. Refresh and sign in again.');
 const j=await r.json();if(!r.ok||!j.ok)throw Error(j.error||'The assistant could not complete this request.');return j.data;
 }catch(e){if(e.name==='AbortError')throw Error('The local request timed out. Refresh availability; do not repeatedly resend.');throw e;}finally{clearTimeout(timer);}}
function bubble(user=false){const row=el('article',undefined,'gai-message'+(user?' user':''));if(!user)row.append(el('span','✦','gai-avatar'));const body=el('div',undefined,'gai-bubble');row.append(body);log.append(row);while(log.children.length>30)log.firstElementChild.remove();return body;}
function message(text,error=false){const body=bubble();body.append(el('p',text,error?'gai-error':''));scroll();return body;}
function safeLink(label,path){const u=new URL(path,location.origin);if(u.origin!==location.origin||!u.pathname.includes('/guidance/'))throw Error('Unexpected report/navigation URL.');const a=el('a',label);a.href=u.href;a.target='_blank';a.rel='noopener noreferrer';return a;}
function steps(body,answer,tag){body.append(el('span',tag,'gai-tag'),el('h3',answer.title));const ol=el('ol');for(const s of answer.steps)ol.append(el('li',s));body.append(ol,el('p',answer.note,'gai-note'));}
function active(topic){for(const b of document.querySelectorAll('#gai-topics button'))b.classList.toggle('active',b.dataset.topic===topic);}
function panel(report){$('gai-report-panel').hidden=!report;$('gai-chat-panel').hidden=report;for(const [id,on]of[['gai-chat-tab',!report],['gai-report-tab',report]]){$(id).classList.toggle('selected',on);$(id).setAttribute('aria-pressed',String(on));}}
function updateStatus(s){status=s;const pill=$('gai-status');pill.textContent=s.ready?'Gemini available · protected topics':'Local guide · Gemini '+(!s.approved?'not enabled':s.paused?'paused':'offline / limited');pill.classList.toggle('ready',s.ready);
 const p=$('gai-pause');if(p){p.disabled=!s.approved;p.textContent=s.paused?'Resume Gemini':'Pause Gemini';p.title='Stops new requests; cannot recall requests already sent to Google.';}}
async function refresh(){try{updateStatus(await api('status'));}catch(e){updateStatus({ready:false,approved:false,paused:true});}}
function aiAnswer(answer,cached=false){const b=bubble();steps(b,answer,cached?'Cached Gemini explanation · verify against the built-in guide':'Gemini explanation · verify against the built-in guide');b.append(el('p','AI text can be mistaken. Only the local buttons and existing application forms perform actions.','gai-small'));scroll();}
async function poll(job,placeholder,turn){const until=Date.now()+125000;while(turn===generation&&Date.now()<until){await new Promise(r=>setTimeout(r,2200));if(turn!==generation)return;
 try{const r=await api('poll',{job});if(turn!==generation)return;if(r.state==='done'){placeholder.remove();aiAnswer(r.answer);return;}if(['failed','cancelled'].includes(r.state)){placeholder.textContent=r.reason||'The request was cancelled. Use the built-in guide.';return;}}
 catch(e){if(turn===generation)placeholder.textContent=e.message;return;}}
 if(turn===generation)placeholder.textContent='The background request did not finish in time. Use the built-in guide; do not immediately resend.';}
async function ask(topic,raw,allowAI=true){if(busy)return;const turn=generation;busy=true;$('gai-send').disabled=true;panel(false);
 if(raw){const b=bubble(true);b.append(el('p',raw));}scroll();
 try{const fields={message:raw||'',context,enhance:allowAI&&$('gai-enhance').checked?'1':'0'};if(topic)fields.topic=topic;
 const r=await api('chat',fields);if(turn!==generation)return;context=r.topic;active(context);
 const b=bubble();steps(b,r,'Built-in guide · permission-checked');const actions=el('div',undefined,'gai-actions');actions.append(safeLink('Open this workspace ↗',r.route));
 if(r.report){const info=el('p','Review these locally interpreted options: '+r.report.filters.kind+' / '+r.report.filters.mode+' / '+(r.report.filters.preset==='custom'?r.report.filters.from+' to '+r.report.filters.to:r.report.filters.preset)+'. Names and IDs in messages are not used as filters.','gai-note');b.append(info);actions.append(safeLink('Preview report ↗',r.report.preview),safeLink('CSV',r.report.csv),safeLink('Print / PDF ↗',r.report.print));}
 if(r.topic==='reports'||r.topic==='planning'){const btn=el('button','Prepare report options','gai-button');btn.type='button';btn.addEventListener('click',()=>{if(r.report){for(const [k,v]of Object.entries(r.report.filters)){const input=$('gai-report-form').elements.namedItem(k);if(input)input.value=v;}$('gai-preset').dispatchEvent(new Event('change'));}panel(true);});actions.append(btn);}
 b.append(actions);if(r.topic==='welcome'&&raw)b.append(el('p','I match software questions to approved topics locally. If this missed your intent, choose a specific topic from the library.','gai-note'));
 if(r.ai.state==='done')aiAnswer(r.ai.answer,Boolean(r.ai.cached));else if(r.ai.state==='queued'){const pending=el('p','Gemini is preparing a generic explanation in the background. Your original message was not sent.','gai-pending');b.append(pending);void poll(r.ai.job,pending,turn);}else b.append(el('p',r.ai.reason||'Local guide only.','gai-small'));scroll();
 }catch(e){if(turn===generation)message(e.message,true);}finally{busy=false;$('gai-send').disabled=false;}}
$('gai-compose').addEventListener('submit',e=>{e.preventDefault();const q=$('gai-question').value.trim();if(!q||busy)return;if(new TextEncoder().encode(q).length>1800){message('Please shorten the question to 1,800 bytes or fewer.',true);return;}$('gai-question').value='';void ask(null,q);});
$('gai-question').addEventListener('keydown',e=>{if(e.key==='Enter'&&!e.shiftKey&&!e.isComposing){e.preventDefault();$('gai-compose').requestSubmit();}});
for(const b of document.querySelectorAll('#gai-topics button'))b.addEventListener('click',()=>void ask(b.dataset.topic,''));
$('gai-search').addEventListener('input',e=>{const q=e.target.value.toLowerCase();for(const b of document.querySelectorAll('#gai-topics button'))b.hidden=!b.textContent.toLowerCase().includes(q);});
$('gai-chat-tab').addEventListener('click',()=>panel(false));$('gai-report-tab').addEventListener('click',()=>panel(true));
$('gai-preset').addEventListener('change',()=>{$('gai-dates').hidden=$('gai-preset').value!=='custom';for(const d of $('gai-dates').querySelectorAll('input'))d.required=!$('gai-dates').hidden;});
$('gai-report-form').addEventListener('submit',async e=>{e.preventDefault();const form=e.currentTarget,button=form.querySelector('button');button.disabled=true;$('gai-report-result').replaceChildren();
 try{const fields=Object.fromEntries(new FormData(form));const r=await api('report',fields);const card=el('div',undefined,'gai-bubble');card.append(el('span','READY TO OPEN LOCALLY','gai-tag'),el('h3','Your report options are prepared'),el('p','No report has been fetched by the assistant. Choose a local preview or export; the existing report engine rechecks your access.'));
 const actions=el('div',undefined,'gai-actions');actions.append(safeLink('Preview report ↗',r.preview),safeLink('Download CSV',r.csv),safeLink('Print / save PDF ↗',r.print));card.append(actions);$('gai-report-result').append(card);
 }catch(err){$('gai-report-result').append(el('p',err.message,'gai-error'));}finally{button.disabled=false;}});
$('gai-clear').addEventListener('click',async()=>{generation++;log.replaceChildren();$('gai-question').value='';context='welcome';active(context);
 try{await api('clear');message('Conversation cleared and pending requests cancelled. Raw chat text is not saved by this module. A request already received by Google cannot be recalled.');}catch(e){message('The local view is cleared, but server cancellation was not confirmed. '+e.message,true);}});
if($('gai-pause'))$('gai-pause').addEventListener('click',async()=>{const b=$('gai-pause');b.disabled=true;try{updateStatus(await api('pause',{paused:status.paused?'0':'1'}));message(status.paused?'Gemini is paused. Built-in help and local reports remain available.':'Gemini resumed for approved generic topics.');}catch(e){message(e.message,true);await refresh();}});
window.addEventListener('pageshow',e=>{if(e.persisted)location.reload();});
window.addEventListener('pagehide',()=>{generation++;log.replaceChildren();$('gai-question').value='';});
void (async()=>{await refresh();await ask(boot.initial,'',false);})();
})();
