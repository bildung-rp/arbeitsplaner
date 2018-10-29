M.coursereport_modreview = M.coursereport_modreview || {};
M.coursereport_modreview.reportuser = function (data) {

    var waitimg = '<img src="' + M.util.image_url('i/ajaxloader', 'moodle') + '" />';

    function getUserreport(headernode, userid) {

        data.userid = userid;
        data.action = 'getuserreport';

        var statusnode = headernode.one('.status');
        statusnode.setHTML(waitimg);

        // ...validation of values!
        var url = M.cfg.wwwroot + '/course/report/modreview/ajax.php';

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

                    if (details.result == 'userreport') {
                        var contentdiv = Y.Node.create('<div id="userreport-' + userid + '"></div>');
                        contentdiv.append(details.userreport);
                        headernode.ancestor().append(contentdiv);
                    }
                }
            }
        });
    }

    function toggleCollapse(node, userid) {

        var contentnode = Y.one('#userreport-' + userid);
        var link = node.one('a');

        if (!contentnode) {
            getUserreport(node, userid);
            link.replaceClass('modreview-img-collapsed', 'modreview-img-expanded');
            return true;
        }

        if (link.hasClass('modreview-img-collapsed')) {
            link.replaceClass('modreview-img-collapsed', 'modreview-img-expanded');
        } else {
            link.replaceClass('modreview-img-expanded', 'modreview-img-collapsed');
        }

        contentnode.toggleView();
        return true;
    }

    function initialize() {

        Y.all('div[id^="userreport-header-"]').each (

            function (node, index) {

                var userid = Number(node.get('id').split('-')[2]);

                node.on('click', function (e) {
                    e.preventDefault();
                    toggleCollapse(node, userid);
                });
            }
            );
    }

    initialize();
};