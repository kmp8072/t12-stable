/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2010 onwards Totara Learning Solutions LTD
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @author Brian Barnes <brian.barnes@totaralearning.com>
 * @package totara
 * @subpackage reportbuilder
 */

/**
 * Javascript file containing JQuery bindings for hierarchy dialog filters.
 */

define(['jquery', 'core/config', 'core/str'], function ($, mdlcfg, mdlstrings) {
    var handler = {

        // Holds items that need to be initialised.
        waitingitems: [],

        /**
         * Module initialisation method called by php js_init_call().
         *
         * @param string    The filter to apply (hierarchy, badge, hierarchy_multi, cohort, category, course_multi)
         * @param string    The current value (may be HTML) - only used by hierarchy and badge
         * @param string    The type of the hierarchy to load - only used by hierarchy type
         */
        init: function(filter, value, type) {
            handler.waitingitems.push({
                filter: filter,
                value: value,
                hierarchytype: type
            });

            if (window.dialogsInited) {
                this.rb_init_filter_dialogs();
            } else {
                // Queue it up.
                if (!$.isArray(window.dialoginits)) {
                    window.dialoginits = [];
                }

                // Only need need to add the function once as it goes through all current ones.
                if (this.waitingitems.length === 1) {
                    window.dialoginits.push(this.rb_init_filter_dialogs);
                }
            }
        },

        rb_init_filter_dialogs: function() {
            $.each(handler.waitingitems, function () {
                switch (this.filter) {
                    case "hierarchy":
                        handler.rb_load_hierarchy_filters(this);
                        break;
                    case "badge":
                        handler.rb_load_badge_filters(this);
                        break;
                    case "hierarchy_multi":
                        handler.rb_load_hierarchy_multi_filters();
                        break;
                    case "cohort":
                        handler.rb_load_cohort_filters();
                        break;
                    case "category":
                        handler.rb_load_category_filters();
                        break;
                    case "course_multi":
                        handler.rb_load_course_multi_filters();
                        break;
                }
            });

            // Activate the 'delete' option next to any selected items in filters.
            $(document).on('click', '.multiselect-selected-item a', function(event) {
                event.preventDefault();

                var container = $(this).parents('div.multiselect-selected-item');
                var filtername = container.data('filtername');
                var id = container.data('id');
                var hiddenfield = $('input[name='+filtername+']');

                // Take this element's ID out of the hidden form field.
                var ids = hiddenfield.val();
                var id_array = ids.split(',');
                var new_id_array = $.grep(id_array, function(index) { return index != id; });
                var new_ids = new_id_array.join(',');
                hiddenfield.val(new_ids);

                // Remove this element from the DOM.
                container.remove();
            });
        },

        rb_load_hierarchy_filters: function(filter) {
            switch (filter.hierarchytype) {
                case 'org':
                    $('input.rb-filter-choose-org').each(function() {
                        var id = $(this).attr('id');
                        // Remove 'show-' and '-dialog' from ID.
                        id = id.substr(5, id.length - 12);

                        ///
                        /// Organisation dialog.
                        ///
                        var url = mdlcfg.wwwroot + '/totara/hierarchy/prefix/organisation/assign/';

                        mdlstrings.get_string('chooseorganisation', 'totara_hierarchy').done(function (chooseorganisation) {
                            totaraSingleSelectDialog(
                                id,
                                chooseorganisation + filter.value,
                                url + 'find.php?',
                                id,
                                id + 'title'
                            );
                        });

                        // Disable popup buttons if first pulldown is set to 'any value'.
                        if ($('select[name=' + id + '_op]').val() === '0') {
                            $('input[name=' + id + '_rec]').prop('disabled', true);
                            $('#show-' + id + '-dialog').prop('disabled', true);
                        }
                    });

                    break;

                case 'pos':
                    $('input.rb-filter-choose-pos').each(function() {
                        var id = $(this).attr('id');
                        // Remove 'show-' and '-dialog' from ID.
                        id = id.substr(5, id.length - 12);

                        ///
                        /// Position dialog.
                        ///
                        var url = mdlcfg.wwwroot + '/totara/hierarchy/prefix/position/assign/';

                        mdlstrings.get_string('chooseposition', 'totara_hierarchy').done(function (chooseposition) {
                            totaraSingleSelectDialog(
                                id,
                                chooseposition + filter.value,
                                url + 'position.php?',
                                id,
                                id + 'title'
                            );
                        });

                        // Disable popup buttons if first pulldown is set to 'any value'.
                        if ($('select[name=' + id + '_op]').val() === '0') {
                            $('input[name=' + id + '_rec]').prop('disabled',true);
                            $('#show-' + id + '-dialog').prop('disabled',true);
                        }
                    });

                    break;

                case 'comp':
                    $('input.rb-filter-choose-comp').each(function() {
                        var id = $(this).attr('id');
                        // Remove 'show-' and '-dialog' from ID.
                        id = id.substr(5, id.length - 12);

                        ///
                        /// Competency dialog.
                        ///
                        var url = mdlcfg.wwwroot + '/totara/hierarchy/prefix/competency/assign/';

                        mdlstrings.get_string('selectcompetency', 'totara_hierarchy').done(function (selectecomptency) {
                            totaraSingleSelectDialog(
                                id,
                                selectecomptency + filter.value,
                                url + 'find.php?',
                                id,
                                id + 'title'
                            );
                        });

                        // Disable popup buttons if first pulldown is set to 'any value'.
                        if ($('select[name=' + id + '_op]').val() === '0') {
                            $('input[name=' + id + '_rec]').prop('disabled',true);
                            $('#show-' + id + '-dialog').prop('disabled',true);
                        }
                    });

                    break;
            }

        },

        rb_load_hierarchy_multi_filters: function() {

            // Bind multi-organisation report filter.
            $('div.rb-org-add-link a').each(function() {
                var id = $(this).attr('id');
                // Remove 'show-' and '-dialog' from ID.
                id = id.substr(5, id.length - 12);

                var url = mdlcfg.wwwroot + '/totara/hierarchy/prefix/organisation/assignfilter/';

                mdlstrings.get_string('chooseorgplural', 'totara_reportbuilder').done(function (chooseorgplural) {
                    totaraMultiSelectDialogRbFilter(
                        id,
                        chooseorgplural,
                        url + 'find.php?',
                        url + 'save.php?filtername=' + id + '&ids='
                    );
                });
            });

            // Bind multi-position report filter.
            $('div.rb-pos-add-link a').each(function() {
                var id = $(this).attr('id');
                // Remove 'show-' and '-dialog' from ID.
                id = id.substr(5, id.length - 12);

                var url = mdlcfg.wwwroot + '/totara/hierarchy/prefix/position/assignfilter/';

                mdlstrings.get_string('chooseposplural', 'totara_reportbuilder').done(function (chooseposplural) {
                    totaraMultiSelectDialogRbFilter(
                        id,
                        chooseposplural,
                        url + 'find.php?',
                        url + 'save.php?filtername=' + id + '&ids='
                    );
                });
            });


            // Bind multi-competency report filter.
            $('div.rb-comp-add-link a').each(function() {
                var id = $(this).attr('id');
                // Remove 'show-' and '-dialog' from ID.
                id = id.substr(5, id.length - 12);

                var url = mdlcfg.wwwroot + '/totara/hierarchy/prefix/competency/assignfilter/';

                mdlstrings.get_string('choosecompplural', 'totara_reportbuilder').done(function (choosecompplural) {
                    totaraMultiSelectDialogRbFilter(
                        id,
                        choosecompplural,
                        url + 'find.php?',
                        url + 'save.php?filtername=' + id + '&ids='
                    );
                });
            });
        },

        rb_load_cohort_filters: function() {
            // Loop through every 'add cohort' link binding to a dialog.
            $('div.rb-cohort-add-link a').each(function() {
                var id = $(this).attr('id');
                // Remove 'show-' and '-dialog' from ID.
                id = id.substr(5, id.length - 12);

                var url = mdlcfg.wwwroot + '/totara/reportbuilder/ajax/';

                mdlstrings.get_string('choosecohorts', 'totara_cohort').done(function (choosecohorts) {
                    totaraMultiSelectDialogRbFilter(
                        id,
                        choosecohorts,
                        url + 'find_cohort.php?sesskey=' + mdlcfg.sesskey,
                        url + 'save_cohort.php?sesskey=' + mdlcfg.sesskey + '&filtername=' + id + '&ids='
                    );
                });
            });
        },

        rb_load_badge_filters: function(filter) {
            // Loop through every 'add badge' link binding to a dialog.
            $('div.rb-badge-add-link a').each(function() {
                var id = $(this).attr('id');
                // Remove 'show-' and '-dialog' from ID.
                id = id.substr(5, id.length - 12);

                var url = mdlcfg.wwwroot + '/totara/reportbuilder/ajax/';

                mdlstrings.get_string('choosebadges', 'badges').done(function (choosebadges) {
                    totaraMultiSelectDialogRbFilter(
                        id,
                        choosebadges,
                        url + 'find_badge.php?reportid=' + filter.value + '&sesskey=' + mdlcfg.sesskey,
                        url + 'save_badge.php?filtername=' + id + '&sesskey=' + mdlcfg.sesskey + '&ids='
                    );
                });
            });
        },

        rb_load_category_filters: function() {
            $(document).on('change', '#id_course_category-path_op', function(event) {
                event.preventDefault();
                var name = $(this).attr('name');
                name = name.substr(0, name.length - 3);// Remove _op.

                if ($(this).val() === '0') {
                    $('input[name='+name+'_rec]').prop('disabled', true);
                    $('#show-'+name+'-dialog').prop('disabled', true);
                } else {
                    $('input[name='+name+'_rec]').prop('disabled', false);
                    $('#show-'+name+'-dialog').prop('disabled', false);
                }
            });

            $('input.rb-filter-choose-category').each(function() {
                var id = $(this).attr('id');
                // Remove 'show-' and '-dialog' from ID.
                id = id.substr(5, id.length - 12);

                var url = mdlcfg.wwwroot + '/totara/reportbuilder/ajax/filter/category/';

                mdlstrings.get_string('choosecatplural', 'totara_reportbuilder').done(function (choosecatplural) {
                    totaraMultiSelectDialogRbFilter(
                        id,
                        choosecatplural,
                        url + 'find.php?sesskey=' + mdlcfg.sesskey,
                        url + 'save.php?filtername=' + id + '&sesskey=' + mdlcfg.sesskey +'&ids='
                    );
                });

                // Disable popup buttons if first pulldown is set to 'any value'.
                if ($('select[name='+id+'_op]').val() === '0') {
                    $('input[name='+id+'_rec]').prop('disabled',true);
                    $('#show-'+id+'-dialog').prop('disabled',true);
                }
            });
        },

        rb_load_course_multi_filters: function() {
            $(document).on('change', '#id_course-id_op', function(event) {
                event.preventDefault();
                var name = $(this).attr('name');
                name = name.substr(0, name.length - 3);// Remove _op.

                if ($(this).val() === '0') {
                    $('input[name='+name+'_rec]').prop('disabled', true);
                    $('#show-'+name+'-dialog').prop('disabled', true);
                } else {
                    $('input[name='+name+'_rec]').prop('disabled', false);
                    $('#show-'+name+'-dialog').prop('disabled', false);
                }
            });

            $('input.rb-filter-choose-course').each(function() {
                var id = $(this).attr('id');
                // Remove 'show-' and '-dialog' from ID.
                id = id.substr(5, id.length - 12);

                var url = mdlcfg.wwwroot + '/totara/reportbuilder/ajax/filter/course_multi/';
                mdlstrings.get_string('coursemultiitemchoose', 'totara_reportbuilder').done(function (coursemultiitemchoose) {
                    totaraMultiSelectDialogRbFilter(
                        id,
                        coursemultiitemchoose,
                        url + 'find.php?sesskey=' + mdlcfg.sesskey,
                        url + 'save.php?filtername=' + id + '&sesskey=' + mdlcfg.sesskey +'&ids='
                    );
                });

                // Disable popup buttons if first pulldown is set to 'any value'.
                if ($('select[name='+id+'_op]').val() === '0') {
                    $('input[name='+id+'_rec]').prop('disabled',true);
                    $('#show-'+id+'-dialog').prop('disabled',true);
                }
            });
        }
    };

    return handler;
});