YUI.add("moodle-local_aclmodules-stateeditdialog",function(e,t){M.local_aclmodules=M.local_aclmodules||{},M.local_aclmodules.stateeditdialog=function(){function s(){e.one(document.body).appendChild(t),r=new e.Panel({srcNode:t,headerContent:M.str.local_aclmodules.stateedit,width:360,zIndex:5,centered:!0,modal:!0,visible:!1,render:!0,plugins:[e.Plugin.Drag]})}function o(t,s){var o=M.cfg.wwwroot+"/local/aclmodules/pages/stateedit_ajax.php?cmid="+t+"&userid="+s;e.io(o,{data:{},on:{success:function(t,s){try{n.setHTML(s.responseText),e.one("#id_submitbutton").on("click",function(e){e.preventDefault(),a()}),e.one("#id_cancel").on("click",function(e){e.preventDefault(),r.hide()}),i=e.one("#filesskin #id_status"),r.show()}catch(o){n.setHTML("parsefailed")}}}})}function u(t,n,r){var i=e.one("#statusdiv_"+n+"_"+t);i.removeAttribute("class"),i.addClass("statusdiv"),i.addClass("state_"+r.state);var s=e.one("#moduseravail_"+n+"_"+t);r.state>70?s.set("disabled","disabled"):s.removeAttribute("disabled");var o=e.one("#sstatusdiv_"+n+"_"+r.section);o.removeAttribute("class"),o.addClass("statusdiv"),o.addClass("state_"+r.sectionstate)}function a(){var t=M.cfg.wwwroot+"/local/aclmodules/pages/stateedit_ajax.php",s=M.util.add_spinner(e,i),o={};o.cmid=e.one('input[name="cmid"]').get("value"),o.courseid=e.one('input[name="courseid"]').get("value"),o.userid=e.one('input[name="userid"]').get("value"),o.useractivitystate=e.one("#id_useractivitystate").get("value");var a=e.one("#id_useractivitymessage");a&&(o.useractivitymessage=e.one("#id_useractivitymessage").get("value")),o.action="update",o.sesskey=M.cfg.sesskey,e.io(t,{data:o,on:{start:function(){s.show()},success:function(t,s){try{var a=e.JSON.parse(s.responseText);a.error==1&&new M.core.ajaxException(a.message),i.addClass("alert"),i.setHTML(a.message),a.reportsdata&&u(o.cmid,o.userid,a.reportsdata),r.hide()}catch(f){n.setHTML("parsefailed")}},failure:function(){s.hide()}}})}function f(e){var t=e.get("id").split("_")[2],n=e.get("id").split("_")[1];o(t,n)}function l(){e.all('div[id^="statusdiv_"]').each(function(e){e.on("click",function(t){t.preventDefault(),f(e)})}),s()}var t=e.Node.create('<div id="filesskin" class="local_aclmodules-tag-dialog"></div>'),n=e.Node.create('<div id="stateedit-content"></div>');t.append(n);var r,i;l()}},"@VERSION@",{requires:["base","node","io","dom","panel","dd"]});
