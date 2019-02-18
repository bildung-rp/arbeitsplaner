YUI.add('moodle-local_aclmodules-sectioncollapse', function (Y, NAME) {

M.local_aclmodules = M.local_aclmodules || {};
M.local_aclmodules.sectioncollapse = function (data) {

    function submitUserPreference(pref, value) {

        var url = M.cfg.wwwroot + '/lib/ajax/setuserpref.php';

        var params = {};
        params.sesskey = M.cfg.sesskey;
        params.pref = pref;
        params.value = value;

        Y.io(url, {
            data :params
        });
    }

    function onClickSectionHide(link){

        var sectionid = link.get('id').split('_')[1];

        if (link.hasClass('collapsed')) {
            link.removeClass('collapsed');
            Y.all('tr[id^="modulerow_' + sectionid + '"]').removeClass('collapsed');
            submitUserPreference('aclsectioncollapsed-' + sectionid, 0);

        } else {
            link.addClass('collapsed');
            Y.all('tr[id^="modulerow_' + sectionid + '"]').addClass('collapsed');
            submitUserPreference('aclsectioncollapsed-' + sectionid, 1);
        }
    }

    function initialize() {
        // ... inflate sections.
        Y.all('td[id^="acl-table-section"]').on('click', function(e) {
            onClickSectionHide(e.target);
        });
    }

    initialize();
};

}, '@VERSION@', {"requires": ["node", "io"]});
