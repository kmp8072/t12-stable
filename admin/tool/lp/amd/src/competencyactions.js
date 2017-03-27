// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Handle selection changes and actions on the competency tree.
 *
 * @module     tool_lp/competencyactions
 * @package    tool_lp
 * @copyright  2015 Damyon Wiese <damyon@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery',
        'core/url',
        'core/templates',
        'core/notification',
        'core/str',
        'core/ajax',
        'tool_lp/dragdrop-reorder',
        'tool_lp/tree',
        'tool_lp/dialogue',
        'tool_lp/menubar'],
       function($, url, templates, notification, str, ajax, dragdrop, Ariatree, Dialogue, menubar) {

    // Private variables and functions.
    /** @var {Object} treeModel - This is an object representing the nodes in the tree. */
    var treeModel = null;
    /** @var {Node} moveSource - The start of a drag operation */
    var moveSource = null;
    /** @var {Node} moveTarget - The end of a drag operation */
    var moveTarget = null;

    /**
     * Respond to choosing the "Add" menu item for the selected node in the tree.
     * @method addHandler
     */
    var addHandler = function() {
        var parent = $('[data-region="competencyactions"]').data('competency');

        var params = {
            competencyframeworkid : treeModel.getCompetencyFrameworkId()
        };

        if (parent !== null) {
            // We are adding at a sub node.
            params.parentid = parent.id;
        }
        var queryparams = $.param(params);
        window.location = url.relativeUrl('/admin/tool/lp/editcompetency.php?' + queryparams);
    };

    /**
     * A source and destination has been chosen - so time to complete a move.
     * @method doMove
     */
    var doMove = function() {
        if (typeof (moveTarget) === "undefined") {
            // This is a top level node.
            moveTarget = 0;
        }

        if (moveTarget == moveSource) {
            return;
        }
        var frameworkid = $('[data-region="filtercompetencies"]').data('frameworkid');
        var requests = ajax.call([{
            methodname: 'tool_lp_set_parent_competency',
            args: { competencyid: moveSource, parentid: moveTarget }
        }, {
            methodname: 'tool_lp_data_for_competencies_manage_page',
            args: { competencyframeworkid: frameworkid,
                    search: $('[data-region="filtercompetencies"] input').val() }
        }]);
        requests[1].done(reloadPage).fail(notification.exception);
    };

    /**
     * A move competency popup was opened - initialise the aria tree in it.
     * @method initMovePopup
     * @param {dialogue} popup The tool_lp/dialogue that was created.
     */
    var initMovePopup = function(popup) {
        new Ariatree('[data-enhance=movetree]', function(target) {
            moveTarget = $(target).data('id');
        });

        var body = $(popup.getContent());
        body.on('click', '[data-action="move"]', function() { popup.close(); doMove(); });
        body.on('click', '[data-action="cancel"]', function() { popup.close(); });
    };

    /**
     * Turn a flat list of competencies into a tree structure (recursive).
     * @method addCompetencyChildren
     * @param {Object} parent The current parent node in the tree
     * @param {Object[]} competencies The flat list of competencies
     */
    var addCompetencyChildren = function(parent, competencies) {
        var i;

        for (i = 0; i < competencies.length; i++) {
            if (competencies[i].parentid == parent.id) {
                parent.haschildren = true;
                competencies[i].children = [];
                competencies[i].haschildren = false;
                parent.children[parent.children.length] = competencies[i];
                addCompetencyChildren(competencies[i], competencies);
            }
        }
    };

    /**
     * A node was chosen and "Move" was selected from the menu. Open a popup to select the target.
     * @method moveHandler
     */
    var moveHandler = function() {
        var competency = $('[data-region="competencyactions"]').data('competency');

        // Remember what we are moving.
        moveSource = competency.id;

        // Load data for the template.
        var requests = ajax.call([
            {
                methodname: 'tool_lp_search_competencies',
                args: {
                    competencyframeworkid: competency.competencyframeworkid,
                    searchtext: ''
                }
            },{
                methodname: 'tool_lp_read_competency_framework',
                args: {
                    id: competency.competencyframeworkid
                }
            }
        ]);

        // When all data has arrived, continue.
        $.when.apply(null, requests).done(function(competencies, framework) {

            // Expand the list of competencies into a tree.
            var i, competenciestree = [];
            for (i = 0; i < competencies.length; i++) {
                var onecompetency = competencies[i];
                if (onecompetency.parentid === "0") {
                    onecompetency.children = [];
                    onecompetency.haschildren = 0;
                    competenciestree[competenciestree.length] = onecompetency;
                    addCompetencyChildren(onecompetency, competencies);
                }
            }

            str.get_strings([
                { key: 'movecompetency', component: 'tool_lp', param: competency.shortname },
                { key: 'move', component: 'tool_lp' },
                { key: 'cancel', component: 'moodle' }
            ]).done(function (strings) {

                var context = {
                    framework: framework,
                    competencies: competenciestree
                };

                templates.render('tool_lp/competencies_move_tree', context)
                   .done(function(tree) {
                       new Dialogue(
                           strings[0], // Move competency x.
                           tree, // The move tree.
                           initMovePopup
                       );

                   }).fail(notification.exception);

           }).fail(notification.exception);

        }).fail(notification.exception);

    };

    /**
     * Edit the selected competency.
     * @method editHandler
     */
    var editHandler = function() {
        var competency = $('[data-region="competencyactions"]').data('competency');

        var params = {
            competencyframeworkid : treeModel.getCompetencyFrameworkId(),
            id : competency.id,
            parentid: competency.parentid
        };

        var queryparams = $.param(params);
        window.location = url.relativeUrl('/admin/tool/lp/editcompetency.php?' + queryparams);
    };

    /**
     * Re-render the page with the latest data.
     * @method reloadPage
     */
    var reloadPage = function(context) {
        templates.render('tool_lp/manage_competencies_page', context)
            .done(function(newhtml, newjs) {
                $('[data-region="managecompetencies"]').replaceWith(newhtml);
                templates.runTemplateJS(newjs);
            })
           .fail(notification.exception);
    };

    /**
     * Perform a search and render the page with the new search results.
     * @method updateSearchHandler
     */
    var updateSearchHandler = function(e) {
        e.preventDefault();

        var frameworkid = $('[data-region="filtercompetencies"]').data('frameworkid');

        var requests = ajax.call([{
            methodname: 'tool_lp_data_for_competencies_manage_page',
            args: { competencyframeworkid: frameworkid,
                    search: $('[data-region="filtercompetencies"] input').val() }
        }]);
        requests[0].done(reloadPage).fail(notification.exception);
    };

    /**
     * Move a competency "up". This only affects the sort order within the same branch of the tree.
     * @method moveUpHandler
     */
    var moveUpHandler = function() {
        // We are chaining ajax requests here.
        var competency = $('[data-region="competencyactions"]').data('competency');
        var requests = ajax.call([{
            methodname: 'tool_lp_move_up_competency',
            args: { id: competency.id }
        }, {
            methodname: 'tool_lp_data_for_competencies_manage_page',
            args: { competencyframeworkid: competency.competencyframeworkid,
                    search: $('[data-region="filtercompetencies"] input').val() }
        }]);
        requests[1].done(reloadPage).fail(notification.exception);
    };

    /**
     * Move a competency "down". This only affects the sort order within the same branch of the tree.
     * @method moveDownHandler
     */
    var moveDownHandler = function() {
        // We are chaining ajax requests here.
        var competency = $('[data-region="competencyactions"]').data('competency');
        var requests = ajax.call([{
            methodname: 'tool_lp_move_down_competency',
            args: { id: competency.id }
        }, {
            methodname: 'tool_lp_data_for_competencies_manage_page',
            args: { competencyframeworkid: competency.competencyframeworkid,
                    search: $('[data-region="filtercompetencies"] input').val() }
        }]);
        requests[1].done(reloadPage).fail(notification.exception);
    };

    /**
     * Open a dialogue to show all the courses using the selected competency.
     * @method seeCoursesHandler
     */
    var seeCoursesHandler = function() {
        var competency = $('[data-region="competencyactions"]').data('competency');

        var requests = ajax.call([{
            methodname: 'tool_lp_list_courses_using_competency',
            args: { id: competency.id }
        }]);

        requests[0].done(function(courses) {
            var context = {
                courseviewurl: url.relativeUrl('/course/view.php'),
                courses: courses
            };
            templates.render('tool_lp/linked_courses_summary', context).done(function(html) {
                str.get_string('linkedcourses', 'tool_lp').done(function (linkedcourses) {
                    new Dialogue(
                        linkedcourses, // Title.
                        html, // The linked courses.
                        initMovePopup
                    );
                }).fail(notification.exception);
            }).fail(notification.exception);
        }).fail(notification.exception);
    };

    /**
     * Delete a competency.
     * @method doDelete
     */
    var doDelete = function() {
        // We are chaining ajax requests here.
        var competency = $('[data-region="competencyactions"]').data('competency');
        var requests = ajax.call([{
            methodname: 'tool_lp_delete_competency',
            args: { id: competency.id }
        }, {
            methodname: 'tool_lp_data_for_competencies_manage_page',
            args: { competencyframeworkid: competency.competencyframeworkid,
                    search: $('[data-region="filtercompetencies"] input').val() }
        }]);
        requests[1].done(reloadPage).fail(notification.exception);
    };

    /**
     * Show a confirm dialogue before deleting a competency.
     * @method deleteHandler
     */
    var deleteHandler = function() {
        var competency = $('[data-region="competencyactions"]').data('competency');

        templates.render('tool_lp/competency_summary', competency)
           .done(function(html) {

               str.get_strings([
                   { key: 'confirm', component: 'moodle' },
                   { key: 'deletecompetency', component: 'tool_lp', param: html },
                   { key: 'delete', component: 'moodle' },
                   { key: 'cancel', component: 'moodle' }
               ]).done(function (strings) {
                    notification.confirm(
                       strings[0], // Confirm.
                       strings[1], // Delete competency X?
                       strings[2], // Delete.
                       strings[3], // Cancel.
                       doDelete
                    );
               }).fail(notification.exception);
           }).fail(notification.exception);

    };

    /**
     * HTML5 implementation of drag/drop (there is an accesible alternative in the menus).
     * @method dragStart
     */
    var dragStart = function(e) {
        e.originalEvent.dataTransfer.setData('text', $(e.target).data('id'));
    };

    /**
     * HTML5 implementation of drag/drop (there is an accesible alternative in the menus).
     * @method allowDrop
     */
    var allowDrop = function(e) {
        e.originalEvent.dataTransfer.dropEffect = 'move';
        e.preventDefault();
    };

    /**
     * HTML5 implementation of drag/drop (there is an accesible alternative in the menus).
     * @method dragEnter
     */
    var dragEnter = function(e) {
        e.preventDefault();
        $(this).addClass('currentdragtarget');
    };

    /**
     * HTML5 implementation of drag/drop (there is an accesible alternative in the menus).
     * @method dragLeave
     */
    var dragLeave = function(e) {
        e.preventDefault();
        $(this).removeClass('currentdragtarget');
    };

    /**
     * HTML5 implementation of drag/drop (there is an accesible alternative in the menus).
     * @method dropOver
     */
    var dropOver = function(e) {
        e.preventDefault();
        moveSource = e.originalEvent.dataTransfer.getData('text');
        moveTarget = $(e.target).data('id');
        $(this).removeClass('currentdragtarget');

        doMove();
    };

    return {
        /**
         * Initialise this page (attach event handlers etc).
         *
         * @method init
         * @param {Object} model The tree model provides some useful functions for loading and searching competencies.
         */
        init: function(model) {
            treeModel = model;

            $('[data-region="competencyactions"]').on('click', addHandler);

            menubar.enhance('.competencyactionsmenu', {
                '[data-action="edit"]': editHandler,
                '[data-action="delete"]': deleteHandler,
                '[data-action="move"]': moveHandler,
                '[data-action="moveup"]': moveUpHandler,
                '[data-action="movedown"]': moveDownHandler,
                '[data-action="linkedcourses"]': seeCoursesHandler
            });
            $('[data-region="competencyactionsmenu"]').hide();

            $('[data-region="filtercompetencies"]').on('submit', updateSearchHandler);
            // Simple html5 drag drop because we already added an accessible alternative.
            $('[data-region="managecompetencies"] li').on('dragstart', dragStart);
            $('[data-region="managecompetencies"] li').on('dragover', allowDrop);
            $('[data-region="managecompetencies"] li').on('dragenter', dragEnter);
            $('[data-region="managecompetencies"] li').on('dragleave', dragLeave);
            $('[data-region="managecompetencies"] li').on('drop', dropOver);
        },

        /**
         * Handler when a node in the aria tree is selected.
         * @method selectionChanged
         */
        selectionChanged: function(node) {
            var id = $(node).data('id');
            menubar.closeAll();
            if (typeof id === "undefined") {
                // Assume this is the root of the tree.
                // Here we are only getting the text from the top of the tree, to do it we clone the tree,
                // remove all children and then call text on the result.
                $('[data-region="competencyinfo"]').html(node.clone().children().remove().end().text());
                $('[data-region="competencyactions"]').data('competency', null);
                $('[data-region="competencyactionsmenu"]').hide();
                $('[data-region="competencyactions"] [data-action="add"]').removeAttr("disabled");
            } else {
                var competency = treeModel.getCompetency(id);

                $('[data-region="competencyactionsmenu"]').show();
                templates.render('tool_lp/competency_summary', competency)
                   .done(function(html) {
                        $('[data-region="competencyinfo"]').html(html);
                   }).fail(notification.exception);

                $('[data-region="competencyactions"]').data('competency', competency);
                $('[data-region="competencyactions"] [data-action="add"]').removeAttr("disabled");

            }
        }
    };
});
