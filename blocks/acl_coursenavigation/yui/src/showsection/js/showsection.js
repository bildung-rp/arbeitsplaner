M.block_acl_coursenavigation = M.block_acl_coursenavigation || {};
M.block_acl_coursenavigation.section = function(data) {

    function onCloseClicked() {
        parent.Y.one('#overlay').setStyle('height', '0px');
    }
 
    function initialize() {

        Y.one('html').setStyle('background', 'transparent');

        Y.one('#gridshadebox_close').on('click', function (e) {
            onCloseClicked();
        });
        
        Y.on('esc', function (e) {
            parent.Y.one('#overlay').setStyle('height', '0px');
        });
   
        parent.Y.all('a[id^="gridsection-"]').each(

            function (node, index) {
                node.on('click', function (e) {
                    e.preventDefault();
                    var section = Number(node.get('id').split('-')[1]);
                    M.block_acl_coursenavigation.showsection(section);
                });
            }

            );
                
        M.block_acl_coursenavigation.showsection(data.section);        
            
    }

    initialize();
};

M.block_acl_coursenavigation.showsection = function(section) {

    Y.one('a[id="gridsection-' + section + '"]').simulate('click');
    parent.Y.one('#overlay').setStyle('height', parent.Y.one("body").get("docHeight"));
    Y.one('#gridshadebox').show();
    window.focus();
};