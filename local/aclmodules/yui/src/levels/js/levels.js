M.local_aclmodules = M.local_aclmodules || {};

M.local_aclmodules.editform = function(divnode, onDelete) {

    var label = divnode.one('span');
    var editlink = divnode.one('.editlevellink');
    var deletelink = divnode.one('.deletelevellink');
    var input = divnode.one('input');

    var listenevents = [];
    var thisevent;

    var editinstructions = Y.Node.create('<span />')
    .addClass('editinstructions')
    .setAttrs({
        'id' : 'id_editinstructions'
    })
    .set('innerHTML', M.str.local_aclmodules.edittitleinstructions);

    function addEvents() {

        thisevent = Y.one('document').on('keydown', function(e) {
            if (e.keyCode === 27) {
                e.preventDefault();
                onBlurInput(false);
            }
            if (e.keyCode === 13) {
                e.preventDefault();
                onBlurInput(true);
            }

        });
        listenevents.push(thisevent);
    }

    function removeEvents() {
        while (thisevent = listenevents.shift()) {
            thisevent.detach();
        }
    }

    function onEditClicked() {
        label.hide();
        editlink.hide();
        deletelink.hide();
        editinstructions.show();
        addEvents();
        input.set("type", "text");
        input.focus().select();
    }

    function onBlurInput(save) {
        label.show();
        editlink.show();
        deletelink.show();
        if (save) {
            label.setContent(input.get('value'));
        } else {
            input.set('value', label.getContent());
        }
        input.set("type", "hidden");
        editinstructions.hide();
        removeEvents();

        // add title to all new radioinputs.
        var val = input.get('name').replace('[', '_').replace(']','');
        var radio = Y.all('input[value="' + val + '"]');
        radio.setAttribute('title', input.get('value'));
    // ... check whether it was a new input.
    }

    function onDeleteClicked() {
        // calling callback function.
        onDelete(divnode.get('id'));
    }

    function initialize() {

        editlink.on('click', function(e) {
            e.preventDefault();
            onEditClicked();
        });

        deletelink.on('click', function(e) {
            e.preventDefault();
            onDeleteClicked();
        });

        input.on('blur', function(e) {
            e.preventDefault();
            onBlurInput();
        });

        editinstructions.hide();
        divnode.append(editinstructions);
    }

    initialize();
};

M.local_aclmodules.editlevels = function (data) {

    // note the additional user column!
    var colcount = data.levelcount + 2;
    var userids = data.userids;
    var newlevelid = 0;
    var rows;
    var warning = false;

    function createLevelForm(column) {

        var div = Y.Node.create('<div id="newlevel_' + newlevelid + '" class="editleveldiv"></div>');
        div.appendChild(Y.Node.create('<span></span>'));
        div.appendChild(Y.Node.create('<input type="hidden" name="newlevel[' + newlevelid + ']" value="" />'));
        div.appendChild(Y.Node.create('<a href="#" class="editlevellink">' + data.icons.edit + '</a>'));
        div.appendChild(Y.Node.create('<a href="#" class="deletelevellink">' + data.icons.del + '</a>'));
        new M.local_aclmodules.editform(div, function (column) {
            onDeleteLevel(column);
        }, true);

        return div;
    }

    function addColumn(column) {

        var div;

        rows.each(

            function(node, index) {

                var domnode = node.getDOMNode();
                var cell = Y.one(domnode.insertCell(column));
                cell.addClass("cell");

                if (index === 0) {

                    div = createLevelForm(column);
                    cell.append(div);

                } else {

                    var radio = Y.Node.create('<input type="radio" />');
                    radio.set('name', 'userlevel[' + userids[index - 1] + ']');
                    radio.set('value', 'newlevel_' + newlevelid);
                    radio.on('click',
                        function (e) {
                            onUserLevelClicked(e.target);
                        });
                    cell.setContent(radio);
                }
            }
            );

        newlevelid++;
        div.one('.editlevellink').simulate('click');
    }

    function deleteColumn(column) {

        rows.each(

            function(node, index) {

                var row = node.getDOMNode();
                row.deleteCell(column);
            }
            );

        colcount--;
        checkAssigned();
    }

    /** event handler for add button*/
    function onAddLevelClicked() {

        addColumn(colcount);
        colcount++;
    }

    /** callback function from M.local_aclmodules.editform*/
    function onDeleteLevel(id) {

        var cells = rows.item(0).all('td div');

        cells.some(

            function (node, index) {

                if (id == node.get('id')) {
                    deleteColumn(index + 2);
                    return true;
                }
            }
            );
    }

    function checkAssigned() {

        var users = Y.all('span[id^="u_"]');

        users.each(

            function (node, index) {

                var userid = node.get('id').split('_')[1];
                var checked = Y.one("input[name='userlevel[" + userid + "]']:checked");

                if ((checked) && (checked.get('value') != 'level_0')) {
                    node.removeClass('unassigned');
                    node.addClass('assigned');
                } else {
                    node.removeClass('assigned');
                    node.addClass('unassigned');
                }
            }
            );
    }

    function onUserLevelClicked(radio) {
        var radionode = Y.one(radio);
        var name = radionode.get('name');
        var match = name.match(/userlevel\[(.*)\]/);
        var userid = match[1];
        var node = Y.one("#u_" + userid);

        if (radionode.get('value') != 'level_0') {
            node.removeClass('unassigned');
            node.addClass('assigned');
        } else {
            node.removeClass('assigned');
            node.addClass('unassigned');
        }
    }

    function onInputChanged() {

        if (!warning) {
            warning = true;
            window.onbeforeunload = function () {
                return 'notsavedwarning';
            };
        }
    }

    function initialize() {

        // ... get all rows.
        rows = Y.all('#acl-levels tr');

        Y.all("input[name^='userlevel']").on('click',
            function (e) {
                onUserLevelClicked(e.target);
                onInputChanged();
            });

        checkAssigned();

        Y.one('#levelsform').on('submit', function() {
            window.onbeforeunload = null;
        });

        // ... wrap the existing forms to M.local_aclmodules.editform
        var editleveldivs = Y.all(".editleveldiv");
        editleveldivs.each(

            function(node, index) {

                new M.local_aclmodules.editform(node,
                    function (column) {
                        onDeleteLevel(column);
                        onInputChanged();
                    },
                    false);

            }
            );

        // ... Eventhandling for the add button.
        var addleveldiv = Y.one("#addlevel");

        if (addleveldiv) {
            addleveldiv.on('click',function(e) {
                e.preventDefault();
                onAddLevelClicked();
                onInputChanged();
            }
            );
        }
    }

    initialize();
};