YUI.add("moodle-block_acl_coursenavigation-module",function(e,t){M.block_acl_coursenavigation=M.block_acl_coursenavigation||{},M.block_acl_coursenavigation.init=function(t){function i(e){n.setStyle("top",window.pageYOffset);var i=Number(e.get("id").split("-")[1]);r||(n.set("data",M.cfg.wwwroot+"/blocks/acl_coursenavigation/gridsections.php?courseid="+t.courseid+"&"+"section="+i),r=!0)}function s(){n=e.Node.create('<object id="overlay" type="text/html" style="position:absolute;width:100%;height:0;top:0px;z-index:2"></object>'),e.one(document.body).append(n),e.all('a[id^="gridsection-"]').each(function(e,t){e.on("click",function(e){e.preventDefault(),i(e.currentTarget)})})}var n,r=!1;s()}},"@VERSION@",{requires:["node"]});
