M.local_aclmodules = M.local_aclmodules || {};

M.local_aclmodules.stateeditdialog = function () {

    var parentnode = Y.Node.create('<div id="filesskin" class="local_aclmodules-tag-dialog"></div>');
    var contentnode = Y.Node.create('<div id="stateedit-content"></div>');
    parentnode.append(contentnode);

    var panel;
    var statusnode;

    function initDialog() {
        Y.one(document.body).appendChild(parentnode);

        panel = new Y.Panel({
            srcNode      : parentnode,
            headerContent: M.str.local_aclmodules.stateedit,
            width        : 360,
            zIndex       : 5,
            centered     : true,
            modal        : true,
            visible      : false,
            render       : true,
            plugins      : [Y.Plugin.Drag]
        });
    }

    function showDialog(modid, userid) {

        var url = M.cfg.wwwroot + '/local/aclmodules/pages/stateedit_ajax.php?cmid=' + modid + '&userid=' + userid;

        Y.io(url, {
            data : {},
            on: {
                success: function (id, resp) {
                    try {

                        contentnode.setHTML(resp.responseText);
                        Y.one('#id_submitbutton').on('click', function(e) {
                            e.preventDefault();
                            onClickSubmit();
                        });

                        Y.one('#id_cancel').on('click', function(e) {
                            e.preventDefault();
                            panel.hide();
                        });

                        statusnode = Y.one('#filesskin #id_status');
                        panel.show();

                    } catch (e) {

                        contentnode.setHTML("parsefailed");
                    }
                }
            }
        });
    }

    function updateUserState(cmid, userid, reportsdata) {

        // ...update modstate.
        var statusdiv = Y.one('#statusdiv_' + userid + '_' + cmid);
        statusdiv.removeAttribute('class');
        statusdiv.addClass('statusdiv');
        statusdiv.addClass('state_' + reportsdata.state);

        var checkbox = Y.one('#moduseravail_' + userid + '_' + cmid);
        // ...lock checkbox, when state > 70;
        if (reportsdata.state > 70) {
            checkbox.set('disabled', 'disabled');
        } else {
            checkbox.removeAttribute('disabled');
        }

        // ...update sectionstate.
        var sstatusdiv = Y.one('#sstatusdiv_' + userid + '_' + reportsdata.section);
        sstatusdiv.removeAttribute('class');
        sstatusdiv.addClass('statusdiv');
        sstatusdiv.addClass('state_' + reportsdata.sectionstate);
    }

    function onClickSubmit() {

        var url = M.cfg.wwwroot + '/local/aclmodules/pages/stateedit_ajax.php';
        var spinner = M.util.add_spinner(Y, statusnode);

        // ... get params.
        var params = {};
        params.cmid = Y.one('input[name="cmid"]').get('value');
        params.courseid = Y.one('input[name="courseid"]').get('value');
        params.userid = Y.one('input[name="userid"]').get('value');
        params.useractivitystate = Y.one('#id_useractivitystate').get('value');

        var messageinput = Y.one('#id_useractivitymessage');
        if (messageinput) {
            params.useractivitymessage = Y.one('#id_useractivitymessage').get('value');
        }

        params.action = "update";
        params.sesskey = M.cfg.sesskey;

        Y.io(url, {
            data : params,
            on: {
                start : function() {

                    spinner.show();
                },

                success: function (id, resp) {
                    try {

                        var responsetext = Y.JSON.parse(resp.responseText);
                        if (responsetext.error == 1) {
                            new M.core.ajaxException(responsetext.message);
                        }

                        // ...set Message,
                        statusnode.addClass('alert');
                        statusnode.setHTML(responsetext.message);

                        // ... update the usersstate.
                        if (responsetext.reportsdata) {
                            updateUserState(params.cmid, params.userid, responsetext.reportsdata);
                        }

                        // ...rename cancel button.
                        panel.hide();

                    } catch (e) {

                        contentnode.setHTML("parsefailed");
                    }
                },
                failure: function() {
                    spinner.hide();
                }
            }
        });
    }

    function onClickEdit(divnode) {
        var modid = divnode.get('id').split('_')[2];
        var userid = divnode.get('id').split('_')[1];
        showDialog(modid, userid);
    }

    function initialize() {

        Y.all('div[id^="statusdiv_"]').each(function (node) {

            node.on('click', function(e) {
                e.preventDefault();
                onClickEdit(node);
            });
        });

        initDialog();
    }

    initialize();
};
