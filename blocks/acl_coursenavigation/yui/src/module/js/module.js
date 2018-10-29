M.block_acl_coursenavigation = M.block_acl_coursenavigation || {};
M.block_acl_coursenavigation.init = function(data) {

    var objectnode;
    var loaded = false;

    function onClick(li) {

        objectnode.setStyle('top', window.pageYOffset);

        var section = Number(li.get('id').split('-')[1]);

        if (!loaded) {

            objectnode.set('data', M.cfg.wwwroot + '/blocks/acl_coursenavigation/gridsections.php?courseid=' + data.courseid + '&' + 'section=' + section);
            loaded = true;
        }
    }

    function initialize() {

        objectnode = Y.Node.create('<object id="overlay" type="text/html" style="position:absolute;width:100%;height:0;top:0px;z-index:2"></object>');
        Y.one(document.body).append(objectnode);

        Y.all('a[id^="gridsection-"]').each(

            function (node, index) {
                node.on('click', function (e) {
                    e.preventDefault();
                    onClick(e.currentTarget);
                });
            }

            );
    }

    initialize();
};