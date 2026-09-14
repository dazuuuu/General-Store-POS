(function(w){
  'use strict';
  var key='developer-support-pending-v1';
  function read(){try{return JSON.parse(localStorage.getItem(key)||'[]');}catch(e){return [];}}
  function write(rows){localStorage.setItem(key,JSON.stringify(rows));}
  function notice(text,type){var box=document.getElementById('supportOfflineNotice');if(!box){box=document.createElement('div');box.id='supportOfflineNotice';box.className='alert position-fixed bottom-0 end-0 m-3 shadow';box.style.zIndex=2000;document.body.appendChild(box);}box.className='alert alert-'+(type||'info')+' position-fixed bottom-0 end-0 m-3 shadow';box.textContent=text;}
  function flush(){
    if(!navigator.onLine)return Promise.resolve();
    var rows=read(),had=rows.length>0;
    return rows.reduce(function(chain,row){return chain.then(function(){
      return fetch(row.url,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:row.body}).then(function(r){if(!r.ok)throw new Error('Support update needs you to sign in again.');rows=rows.filter(function(x){return x.id!==row.id;});write(rows);});
    });},Promise.resolve()).then(function(){if(!had)return;notice('Offline support changes synchronized.','success');location.reload();}).catch(function(e){notice(e.message,'warning');});
  }
  document.addEventListener('submit',function(e){var form=e.target.closest('form[data-support-sync]');if(!form||navigator.onLine)return;e.preventDefault();var rows=read();rows.push({id:Date.now()+'-'+Math.random(),url:form.action||location.href,body:new URLSearchParams(new FormData(form)).toString()});write(rows);notice('Saved offline. This support change will apply automatically when you reconnect.','warning');});
  w.addEventListener('online',flush);w.addEventListener('load',flush);
})(window);
