YUI.add("moodle-local_aclmodules-planner",function(e,t){M.local_aclmodules=M.local_aclmodules||{},M.local_aclmodules.planner=function(t){function u(t){var n=e.all('input[id$="_'+t+'"]:not([name^="aclmodactive"])');n.each(function(e){e.getDOMNode().disabled=!1})}function a(t){var n=e.all('input[id$="_'+t+'"]:not([name^="aclmodactive"])');n.each(function(e){e.getDOMNode().disabled=!0})}function f(e){var t=Number(e.get("id").split("_")[1]),n=e.getDOMNode();n.checked?u(t):a(t)}function l(t,n,r,i){var s=e.one("#statusdiv_"+t+"_"+n),o=s.get("className"),u=o.split("_");if(!i&&u[1]&&u[1]>70)return;r.getDOMNode().checked=i}function c(n,r,i){if(t.leveltousers[r])for(var s in t.leveltousers[r]){var o=e.one("#moduseravail_"+s+"_"+n);l(s,n,o,i)}else r===0?e.all('input[id^="moduseravail"][id$="_'+n+'"]').each(function(e){var t=e.get("id").split("_")[1];l(t,n,e,i)}):alert(M.str.local_aclmodules.nouserassignedtolevel)}function h(n,r){var i=Number(n.ancestor().get("id").split("_")[2]),s=Number(n.ancestor().get("id").split("_")[1]);if(t.sectioncmids[s]){for(var o in t.sectioncmids[s]){var u=t.sectioncmids[s][o];c(u,i,r)}if(t.leveltousers[i])for(var a in t.leveltousers[i])y(s,a);else i===0&&e.all('input[id^="sectionuseravail"][id$="_'+s+'"]').each(function(e){var t=e.get("id").split("_")[1];y(s,t)})}}function p(e){var t=Number(e.ancestor().get("id").split("_")[2]),n=Number(e.ancestor().get("id").split("_")[1]);c(n,t,!0)}function d(e){var t=Number(e.ancestor().get("id").split("_")[2]),n=Number(e.ancestor().get("id").split("_")[1]);c(n,t,!1)}function v(e){h(e,!0)}function m(e){h(e,!1)}function g(n){var r=n.get("id").split("_")[2],i=n.get("id").split("_")[1],s=n.get("checked");if(t.sectioncmids[r])for(var o in t.sectioncmids[r]){var u=t.sectioncmids[r][o],a=e.one("#moduseravail_"+i+"_"+u);l(i,u,a,s)}}function y(n,r){if(t.sectioncmids[n]){var i=0;for(var s in t.sectioncmids[n]){var o=t.sectioncmids[n][s];e.one("#moduseravail_"+r+"_"+o).get("checked")&&i++}var u=i>0;e.one("#sectionuseravail_"+r+"_"+n).set("checked",u)}}function b(e){var t=Number(e.get("id").split("_")[2]),n=Number(r[t]),i=Number(e.get("id").split("_")[1]);y(n,i)}function w(n){var r=Number(n.get("id").split("_")[2]),i=n.get("id").split("_")[1],s=n.get("checked");if(t.sectioncmids[r])for(var o in t.sectioncmids[r]){var u=t.sectioncmids[r][o];e.all("#configoptions_"+i+"_"+u).set("checked",s)}}function E(n){var i=Number(n.get("id").split("_")[2]),s=Number(r[i]),o=n.get("id").split("_")[1];if(t.sectioncmids[s]){var u=0;for(var a in t.sectioncmids[s]){var f=t.sectioncmids[s][a];e.one("#configoptions_"+o+"_"+f).get("checked")&&u++}var l=u===t.sectioncmids[s].length;e.one("#sectionconfigoptions_"+o+"_"+s).set("checked",l)}}function S(t,n){var r=M.cfg.wwwroot+"/lib/ajax/setuserpref.php",i={};i.sesskey=M.cfg.sesskey,i.pref=t,i.value=n,e.io(r,{data:i})}function x(n){n.hasClass("collapsed")?(n.removeClass("collapsed"),n.set("title",M.str.local_aclmodules.hidecfgcolumns),e.all(".cfgcell").show(),S("aclcfgcolcollapsed_"+t.courseid,0)):(n.addClass("collapsed"),n.set("title",M.str.local_aclmodules.showcfgcolumns),e.all(".cfgcell").hide(),S("aclcfgcolcollapsed_"+t.courseid,1))}function T(n){for(var r=n;r<t.userslidervals.max;r++)e.all("#acl-table .c"+r).show();for(var s=i.get("min");s<n;s++)e.all("#acl-table .c"+s).hide()}function N(n){for(var r=n;r<t.rowslidervals.max;r++){var i=e.one("#acl-table .rc"+r);i&&i.show()}for(var o=s.get("min");o<n;o++){var u=e.one("#acl-table .rc"+o);u&&u.hide()}}function C(){i=new e.Slider({min:t.userslidervals.min,max:t.userslidervals.max,value:Number(t.userslidervals.value),length:"200"}),i.after("valueChange",function(){T(i.get("value"))}),i.after("slideEnd",function(){S("aclcolvisible_"+t.courseid,i.get("value"))}),i.render(".horiz_slider"),s=new e.Slider({axis:"y",min:t.rowslidervals.min,max:t.rowslidervals.max,value:Number(t.rowslidervals.value),length:"200"}),s.after("valueChange",function(){N(s.get("value"))}),s.after("slideEnd",function(){S("aclrowvisible_"+t.courseid,s.get("value"))}),s.render(".vert_slider")}function k(){o||(o=!0,window.onbeforeunload=function(){return M.str.local_aclmodules.notsavedwarning});var t=e.one(".alert");t&&t.hide()}function L(){C();for(var i in t.sectioncmids)for(var s in t.sectioncmids[i])r[t.sectioncmids[i][s]]=i;n=e.all('input[name^="aclmodactive"]'),n.on("click",function(e){f(e.target)}),n.each(function(e){f(e)}),e.all('a[id^="add"]').on("click",function(e){k(),e.preventDefault(),p(e.target)}),e.all('a[id^="less"]').on("click",function(e){k(),e.preventDefault(),d(e.target)}),e.all('a[id^="sectionadd"]').on("click",function(e){k(),e.preventDefault(),v(e.target)}),e.all('a[id^="sectionless"]').on("click",function(e){k(),e.preventDefault(),m(e.target)}),e.all('input[id^="sectionuseravail"]').on("click",function(e){k(),g(e.target)}),e.all('input[id^="moduseravail"]').on("click",function(e){k(),b(e.target)}),e.all('input[id^="sectionconfigoptions"]').on("click",function(e){k(),w(e.target)}),e.all('input[id^="configoptions"]').on("click",function(e){k(),E(e.target)}),e.one("#cfgcolcollapse").on("click",function(e){x(e.target)}),e.one("#plannerform").on("submit",function(){window.onbeforeunload=null})}var n,r={},i,s,o=!1;L()}},"@VERSION@",{requires:["base","node","io","dom","slider"]});
