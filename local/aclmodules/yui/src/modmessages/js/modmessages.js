M.local_aclmodules = M.local_aclmodules || {};
M.local_aclmodules.message = function (data) {

    var waitimg = '<img src="' + M.util.image_url('i/ajaxloader', 'moodle') + '" />';

    function onSendClicked(modid, form) {

        var data = {};
        data.useridfrom = form.one('input[name="useridfrom"]').get('value');
        data.useridto = form.one('[name="useridto"]').get('value');
        data.message = form.one('textarea[name="message"]').get('value');
        data.modid = modid;
        data.action = 'sendmessage';

        // ...validation of values!

        var statusnode = Y.one('#modmessages-status-' + modid);
        var contentnode = Y.one('#modmessages-' + modid);
        var url = M.cfg.wwwroot + '/local/aclmodules/ajax/modmessage.php';

        statusnode.setHTML(waitimg);

        Y.io(url, {
            data: data,
            on: {
                success: function (id, resp) {
                    var details;

                    try {
                        details = Y.JSON.parse(resp.responseText);
                        statusnode.setHTML("");

                    } catch (e) {

                        statusnode.setHTML("parsefailed");
                    }

                    if (details.result == "sendmessage") {
                        var newnode = Y.Node.create(details.modmessages);
                        contentnode.replace(newnode);
                        addEventButtonListener();
                    }
                }
            }
        });
    }

    function onUnreadClicked(modid, messageid, linknode) {

        var data = {};
        data.messageid = messageid;
        data.modid = modid;
        data.action = 'setreadmessage';

        var contentnode = Y.one('#modmessages-header-' + modid + ' .modmessage-header-info');
        var url = M.cfg.wwwroot + '/local/aclmodules/ajax/modmessage.php';

        Y.io(url, {
            data: data,
            on: {
                success: function (id, resp) {
                    var details;

                    try {
                        details = Y.JSON.parse(resp.responseText);

                    } catch (e) {
                        alert('parseerror');
                    }

                    if (details.result == "setreadmessage") {
                        if (contentnode && details.modmessagesheaderinfo) {
                            contentnode.setHTML(details.modmessagesheaderinfo);
                        }
                        if (details.modmessagesreadinfo) {
                            linknode.replace(details.modmessagesreadinfo);
                        }
                    }
                }
            }
        });
    }

    function addEventButtonListener() {

        Y.all('.modmessages-unread').each (

            function (node, index) {

                var form = node.ancestor('form');
                var modid = node.get('id').split('-')[2];
                var messageid = node.get('id').split('-')[3];
                node.on('click', function (e) {
                    e.preventDefault();
                    onUnreadClicked(modid, messageid, node);
                });
            }
            );

        Y.all('button[id^="btnsendmessage_"]').each (

            function (node, index) {

                var form = node.ancestor('form');
                var modid = node.get('id').split('_')[1];
                node.on('click', function (e) {
                    e.preventDefault();
                    onSendClicked(modid, form);
                });
            }
            );

        Y.all('button[id^="btnsendnewmessage_"]').each (

            function (node, index) {

                var form = node.ancestor('form');
                var modid = node.get('id').split('_')[1];
                node.on('click', function (e) {
                    e.preventDefault();
                    onSendClicked(modid, form);
                });
            }
            );

        Y.all('.modmessagesold-header').each (

            function (node, index) {

                var contentnode = node.next('.modmessagesold-content');
                node.on('click', function (e) {

                    e.preventDefault();

                    var link = node.one('a');

                    if (link.hasClass('modmessages-img-collapsed')) {

                        link.replaceClass('modmessages-img-collapsed', 'modmessages-img-expanded');

                    } else {

                        link.replaceClass('modmessages-img-expanded', 'modmessages-img-collapsed');
                    }

                    contentnode.toggleView();
                });
            }
            );
    }

    function getModMessages(headernode, modid) {

        var data = {};
        data.modid = modid;
        data.action = 'getmessages';

        var statusnode = headernode.one('.status');
        statusnode.setHTML(waitimg);

        // ...validation of values!
        var url = M.cfg.wwwroot + '/local/aclmodules/ajax/modmessage.php';

        Y.io(url, {
            data: data,
            on: {
                success: function (id, resp) {
                    var details;

                    try {
                        details = Y.JSON.parse(resp.responseText);
                        statusnode.setHTML('');
                    } catch (e) {
                        statusnode.setHTML('parsefailed');
                    }

                    if (details.result == "getmessages") {
                        headernode.ancestor().append(details.modmessages);
                        addEventButtonListener();
                    }
                }
            }
        });
    }

    function toggleCollapse(node, modid) {

        var contentnode = Y.one('#modmessages-' + modid);
        var link = node.one('a');

        if (!contentnode) {
            getModMessages(node, modid);
            link.replaceClass('modmessages-img-collapsed', 'modmessages-img-expanded');
            return true;
        }

        if (link.hasClass('modmessages-img-collapsed')) {
            link.replaceClass('modmessages-img-collapsed', 'modmessages-img-expanded');
        } else {
            link.replaceClass('modmessages-img-expanded', 'modmessages-img-collapsed');
        }

        contentnode.toggleView();
        return true;
    }

    function initialize() {

        addEventButtonListener();

        Y.all('div[id^="modmessages-header-"]').each (

            function (node, index) {

                var modid = node.get('id').split('-')[2];

                node.on('click', function (e) {
                    e.preventDefault();
                    toggleCollapse(node, modid);
                });
            }
            );
    }
    initialize();
};