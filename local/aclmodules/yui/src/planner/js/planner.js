M.local_aclmodules = M.local_aclmodules || {};
M.local_aclmodules.planner = function (data) {

    var aclmodactive;
    var modtosection = {};
    var userslider; // horizontal Slider
    var rowslider;  // vertical Slider
    var warning = false;

    function activateRow(moduleid) {

        var rowelementsusers = Y.all('input[id$="_' + moduleid + '"]:not([name^="aclmodactive"])');

        rowelementsusers.each(
        function(node) {
            node.getDOMNode().disabled = false;
        }
    );
    }

    function deactivateRow(moduleid) {

        var rowelementsusers = Y.all('input[id$="_' + moduleid + '"]:not([name^="aclmodactive"])');

        rowelementsusers.each(
        function(node) {
            node.getDOMNode().disabled = true;
        }
    );
    }

    function onClickModActive(node) {

        var moduleid = Number(node.get('id').split('_')[1]);

        var checkbox = node.getDOMNode();
        if (checkbox.checked) {
            activateRow(moduleid);
        } else {
            deactivateRow(moduleid);
        }
    }

    /** dont' remove check, when users state is above 70 */
    function setSafeChecked(userid, modid, node, checked) {

        // get state.statusdiv_{$participant->id}_{$mod->id}
        var statediv = Y.one('#statusdiv_' + userid + '_' + modid);
        var classstr = statediv.get('className');

        var state = classstr.split('_');

        // don't uncheck inputs, when state is above 70.
        if ((!checked) && (state[1]) && (state[1] > 70)) {
            return;
        }
        node.getDOMNode().checked = checked;
    }

    function setModAvailUsers(modid, levelid, checked) {

        if (data.leveltousers[levelid]) {

            for (var userid in data.leveltousers[levelid]) {
                var node = Y.one('#moduseravail_' + userid + '_' + modid);
                setSafeChecked(userid, modid, node, checked);
            }

        } else {

            if (levelid === 0) {

                Y.all('input[id^="moduseravail"][id$="_' + modid + '"]').each(
                function (node) {
                    var userid = node.get('id').split('_')[1];
                    setSafeChecked(userid, modid, node, checked);
                }
                );
            } else {
                alert(M.str.local_aclmodules.nouserassignedtolevel);
            }
        }
    }

    function setSectionAvailUsers(link, checked) {

        var levelid = Number(link.ancestor().get('id').split('_')[2]);
        var sectionid = Number(link.ancestor().get('id').split('_')[1]);

        if (data.sectioncmids[sectionid]) {

            for (var i in data.sectioncmids[sectionid]) {

                var modid = data.sectioncmids[sectionid][i];
                setModAvailUsers(modid, levelid, checked);
            }

            // Now check state for mods in that level per user and set section state properly
            if (data.leveltousers[levelid]) {

                for (var userid in data.leveltousers[levelid]) {

                    setSectionUserAvail(sectionid, userid);
                }

            } else {

                if (levelid === 0) {

                    Y.all('input[id^="sectionuseravail"][id$="_' + sectionid + '"]').each(
                    function (node) {
                        var userid = node.get('id').split('_')[1];
                        setSectionUserAvail(sectionid, userid);
                    }
                );
                }
            }
        }
    }

    function onClickLevelAdd(link) {
        var levelid = Number(link.ancestor().get('id').split('_')[2]);
        var modid = Number(link.ancestor().get('id').split('_')[1]);
        setModAvailUsers(modid, levelid, true);
    }

    function onClickLevelLess(link) {
        var levelid = Number(link.ancestor().get('id').split('_')[2]);
        var modid = Number(link.ancestor().get('id').split('_')[1]);
        setModAvailUsers(modid, levelid, false);
    }

    function onClickSectionLevelAdd(sectionaddlink) {
        setSectionAvailUsers(sectionaddlink, true);
    }

    function onClickSectionLevelLess(sectionlesslink) {
        setSectionAvailUsers(sectionlesslink, false);
    }

    function onClickSectionUserAvail(checkbox) {
        var sectionid = checkbox.get('id').split('_')[2];
        var userid = checkbox.get('id').split('_')[1];
        var checked = checkbox.get('checked');

        if (data.sectioncmids[sectionid]) {

            for (var i in data.sectioncmids[sectionid]) {

                var modid = data.sectioncmids[sectionid][i];
                var node = Y.one('#moduseravail_' + userid + '_' + modid);
                setSafeChecked(userid, modid, node, checked);
            }
        }
    }

    function setSectionUserAvail(sectionid, userid) {

        if (data.sectioncmids[sectionid]) {

            var countchecked = 0;

            for (var i in data.sectioncmids[sectionid]) {

                var modid = data.sectioncmids[sectionid][i];
                if (Y.one('#moduseravail_' + userid + '_' + modid).get('checked')) {
                    countchecked++;
                }
            }

            var checked = (countchecked > 0);
            Y.one('#sectionuseravail_' + userid + '_' + sectionid).set('checked', checked);

        }
    }

    function onClickModUserAvail(checkbox) {

        var cmodid = Number(checkbox.get('id').split('_')[2]);
        var sectionid = Number(modtosection[cmodid]);
        var userid = Number(checkbox.get('id').split('_')[1]);

        setSectionUserAvail(sectionid, userid);
    }

    function onClickSectionConfigOptions(checkbox) {
        var sectionid = Number(checkbox.get('id').split('_')[2]);
        var configoption = checkbox.get('id').split('_')[1];
        var checked = checkbox.get('checked');

        if (data.sectioncmids[sectionid]) {

            for (var i in data.sectioncmids[sectionid]) {

                var modid = data.sectioncmids[sectionid][i];
                Y.all('#configoptions_' + configoption + '_' + modid).set('checked', checked);

            }
        }
    }

    function onClickConfigOptions(checkbox) {

        var cmodid = Number(checkbox.get('id').split('_')[2]);
        var sectionid = Number(modtosection[cmodid]);
        var configoption = checkbox.get('id').split('_')[1];

        if (data.sectioncmids[sectionid]) {

            var countchecked = 0;

            for (var i in data.sectioncmids[sectionid]) {

                var modid = data.sectioncmids[sectionid][i];
                if (Y.one('#configoptions_' + configoption + '_' + modid).get('checked')) {
                    countchecked++;
                }
            }

            var checked = (countchecked === data.sectioncmids[sectionid].length);
            Y.one('#sectionconfigoptions_' + configoption + '_' + sectionid).set('checked', checked);

        }
    }

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

    function onClickConfigColHide(col) {

        if (col.hasClass('collapsed')) {
            col.removeClass('collapsed');
            col.set('title', M.str.local_aclmodules.hidecfgcolumns);
            Y.all('.cfgcell').show();
            submitUserPreference('aclcfgcolcollapsed_' + data.courseid, 0);
        } else {
            col.addClass('collapsed');
            col.set('title', M.str.local_aclmodules.showcfgcolumns);
            Y.all('.cfgcell').hide();
            submitUserPreference('aclcfgcolcollapsed_' + data.courseid, 1);
        }

    }

    function columsSetVisible(number) {

        // set all columns visible with col > number
        for (var i = number; i < data.userslidervals.max; i++) {
            Y.all('#acl-table .c' + i).show();
        }

        // set all columns invisible with col < number
        for (var j = userslider.get('min'); j < number; j++) {
            Y.all('#acl-table .c' + j).hide();
        }
    }

    function rowsSetVisible(number) {

        // set all row visible with col > number
        for (var i = number; i < data.rowslidervals.max; i++) {
            var row1 = Y.one('#acl-table .rc' + i);
            if (row1) {
                row1.show();
            }
        }

        // set all rows invisible with col < number
        for (var j = rowslider.get('min'); j < number; j++) {
            var row2 = Y.one('#acl-table .rc' + j);
            if (row2) {
                row2.hide();
            }
        }
    }

    function initSlider() {

        // Create a horizontal Slider.
        userslider = new Y.Slider({
            min : data.userslidervals.min,
            max: data.userslidervals.max,
            value: Number(data.userslidervals.value),
            length : '200'
        });

        userslider.after("valueChange", function() {
            columsSetVisible(userslider.get('value'));
        });

        userslider.after("slideEnd", function () {
            submitUserPreference('aclcolvisible_' + data.courseid, userslider.get('value'));
        });
        userslider.render('.horiz_slider');

        // Create a vertical Slider.
        rowslider = new Y.Slider({
            axis : 'y',
            min : data.rowslidervals.min,
            max: data.rowslidervals.max,
            value: Number(data.rowslidervals.value),
            length : '200'
        });

        rowslider.after( "valueChange", function() {
            rowsSetVisible(rowslider.get('value'));
        });

        rowslider.after("slideEnd", function () {
            submitUserPreference('aclrowvisible_' + data.courseid, rowslider.get('value'));
        });
        rowslider.render('.vert_slider');
    }

    function onInputChanged() {

        if (!warning) {
            warning = true;
            window.onbeforeunload = function () {
                return M.str.local_aclmodules.notsavedwarning;
            };
        }

        var alert = Y.one('.alert');
        if (alert) {
            alert.hide();
        }
    }

    function initialize() {

        initSlider();

        // prepare a array modid => sectionid.
        for (var sectionid in data.sectioncmids) {

            for (var i in data.sectioncmids[sectionid]) {
                modtosection[data.sectioncmids[sectionid][i]] = sectionid;
            }
        }

        // from here on all events stuff.
        aclmodactive = Y.all('input[name^="aclmodactive"]');
        aclmodactive.on('click', function(e) {
            onClickModActive(e.target);
        });

        aclmodactive.each(

        function (node) {
            onClickModActive(node);
        });

        // ... Level Handling.
        Y.all('a[id^="add"]').on('click', function(e) {
            onInputChanged();
            e.preventDefault();
            onClickLevelAdd(e.target);
        });

        Y.all('a[id^="less"]').on('click', function(e) {
            onInputChanged();
            e.preventDefault();
            onClickLevelLess(e.target);
        });

        Y.all('a[id^="sectionadd"]').on('click', function(e) {
            onInputChanged();
            e.preventDefault();
            onClickSectionLevelAdd(e.target);
        });

        Y.all('a[id^="sectionless"]').on('click', function(e) {
            onInputChanged();
            e.preventDefault();
            onClickSectionLevelLess(e.target);
        });

        // ... User ACL Handling
        Y.all('input[id^="sectionuseravail"]').on('click', function(e) {
            onInputChanged();
            onClickSectionUserAvail(e.target);
        });

        Y.all('input[id^="moduseravail"]').on('click', function(e) {
            onInputChanged();
            // check if all are deselected.
            onClickModUserAvail(e.target);
        });

        // ... config options handling
        Y.all('input[id^="sectionconfigoptions"]').on('click', function(e) {
            onInputChanged();
            onClickSectionConfigOptions(e.target);
        });

        Y.all('input[id^="configoptions"]').on('click', function(e) {
            onInputChanged();
            onClickConfigOptions(e.target);
        });

        Y.one('#cfgcolcollapse').on('click', function (e) {
            onClickConfigColHide(e.target);
        });

        Y.one('#plannerform').on('submit', function() {
            window.onbeforeunload = null;
        });
    }

    initialize();
};