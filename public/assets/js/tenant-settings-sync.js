(function(w){
  'use strict';
  var config=w.TenantSettingsSyncConfig||{},checking=false,current=Number(config.revision||0);
  function removeOffline(){
    var revoke=w.OfflinePOS&&OfflinePOS.revokeOfflineAccess?OfflinePOS.revokeOfflineAccess():Promise.resolve();
    if('serviceWorker'in navigator)navigator.serviceWorker.getRegistrations().then(function(rows){rows.forEach(function(row){row.unregister();});});
    if(w.caches)caches.keys().then(function(keys){keys.filter(function(key){return key.indexOf('shop-pos-')===0;}).forEach(function(key){caches.delete(key);});});
    return revoke;
  }
  function check(){
    if(checking||!navigator.onLine||!config.url)return Promise.resolve();
    checking=true;
    return fetch(config.url,{credentials:'same-origin',cache:'no-store'}).then(function(r){if(!r.ok)throw new Error('settings');return r.json();}).then(function(data){
      if(!data.ok)return;
      if(data.status!=='active'){removeOffline().finally(function(){location.href=config.loginUrl||'/auth/login.php?locked=1';});return;}
      if(!data.offline_enabled)removeOffline();
      if(Number(data.revision||0)!==current){current=Number(data.revision||0);location.reload();}
    }).catch(function(){}).finally(function(){checking=false;});
  }
  w.TenantSettingsSync={check:check};
  w.addEventListener('online',check);w.addEventListener('load',check);setInterval(check,60000);
})(window);
