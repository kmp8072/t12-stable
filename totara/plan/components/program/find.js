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
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @author Aaron Barnes <aaron.barnes@totaralms.com>
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package totara
 * @subpackage totara_core
 */
M.totara_plan_program_find = M.totara_plan_program_find || {

    Y: null,
    // optional php params and defaults defined here, args passed to init method
    // below will override these values
    config: {},

    /**
     * module initialisation method called by php js_init_call()
     *
     * @param object    YUI instance
     * @param string    args supplied in JSON format
     */
    init: function(Y, args){
        // save a reference to the Y instance (all of its dependencies included)
        this.Y = Y;

        if (args) {
            var jargs = Y.JSON.parse(args);
            for (var a in jargs) {
                if (Y.Object.owns(jargs, a)) {
                    this.config[a] = jargs[a];
                }
            }
        }
        // check jQuery dependency is available
        if (typeof $ === 'undefined') {
            throw new Error('M.totara_plan_program_find.init()-> jQuery dependency required for this module to function.');
        }

        var url = M.cfg.wwwroot + '/totara/plan/components/program/';
        var saveurl = url + 'update.php?id='+this.config.plan_id+'&update=';

        require(['totara_plan/component'], function (component) {
            component.init(M.totara_plan_program_find.config);

            var handler = new component.totaraDialog_handler_preRequisite();
            handler.baseurl = url;
            var buttonsObj = {};
            buttonsObj[M.util.get_string('save','totara_core')] = function() { handler._save(saveurl) }
            buttonsObj[M.util.get_string('cancel','moodle')] = function() { handler._cancel() }

            totaraDialogs['evidence'] = new totaraDialog(
                'assignprograms',
                'show-program-dialog',
                {
                    buttons: buttonsObj,
                    title: '<h2>' + M.util.get_string('addprograms', 'totara_plan') + '</h2>'
                },
                url + 'find.php?id=' + M.totara_plan_program_find.config.plan_id,
                handler
            );
        });
    }
};
