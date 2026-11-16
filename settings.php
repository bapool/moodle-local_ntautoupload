<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Admin settings for local_ntautoupload.
 *
 * @package    local_ntautoupload
 * @copyright  2026 Brian Pool, National Trail Local Schools
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_ntautoupload', new lang_string('pluginname', 'local_ntautoupload'));
    $ADMIN->add('localplugins', $settings);

    if ($ADMIN->fulltree) {
        require_once($CFG->libdir . '/csvlib.class.php');
        require_once($CFG->dirroot . '/admin/tool/uploaduser/locallib.php');
        $component = 'local_ntautoupload';

        // On/off and seasonal pause.
        $settings->add(new admin_setting_heading(
            "{$component}/generalheading",
            new lang_string('generalheading', $component),
            new lang_string('generalheading_desc', $component)
        ));

        $settings->add(new admin_setting_configcheckbox(
            "{$component}/enabled",
            new lang_string('enabled', $component),
            new lang_string('enabled_desc', $component),
            1
        ));

        $settings->add(new admin_setting_configcheckbox(
            "{$component}/autopause",
            new lang_string('autopause', $component),
            new lang_string('autopause_desc', $component),
            1
        ));

        $months = \core_calendar\type_factory::get_calendar_instance()->get_months();
        $settings->add(new admin_setting_configselect(
            "{$component}/autopausemonth",
            new lang_string('autopausemonth', $component),
            '',
            12,
            $months
        ));

        $days = array_combine(range(1, 31), range(1, 31));
        $settings->add(new admin_setting_configselect(
            "{$component}/autopauseday",
            new lang_string('autopauseday', $component),
            new lang_string('autopauseday_desc', $component),
            15,
            $days
        ));

        // Reports.
        $settings->add(new admin_setting_heading(
            "{$component}/reportheading",
            new lang_string('reportheading', $component),
            ''
        ));

        $settings->add(new admin_setting_configtext(
            "{$component}/notifyemails",
            new lang_string('notifyemails', $component),
            new lang_string('notifyemails_desc', $component),
            '',
            PARAM_RAW_TRIMMED
        ));

        $hours = [];
        for ($h = 0; $h < 24; $h++) {
            $hours[$h] = sprintf('%02d:00', $h);
        }
        $settings->add(new admin_setting_configselect(
            "{$component}/dailyreporthour",
            new lang_string('dailyreporthour', $component),
            new lang_string('dailyreporthour_desc', $component),
            7,
            $hours
        ));

        $settings->add(new \local_ntautoupload\admin\setting_regex(
            "{$component}/unknowncoursepattern",
            new lang_string('unknowncoursepattern', $component),
            new lang_string('unknowncoursepattern_desc', $component),
            ''
        ));

        $settings->add(new admin_setting_configtext(
            "{$component}/unknowncourseminrows",
            new lang_string('unknowncourseminrows', $component),
            new lang_string('unknowncourseminrows_desc', $component),
            1,
            PARAM_INT
        ));

        // Upload options shared by every import.
        $settings->add(new admin_setting_heading(
            "{$component}/optionsheading",
            new lang_string('optionsheading', $component),
            new lang_string('optionsheading_desc', $component)
        ));

        $settings->add(new admin_setting_configselect(
            "{$component}/encoding",
            new lang_string('encoding', 'tool_uploaduser'),
            '',
            'UTF-8',
            core_text::get_encodings()
        ));

        $settings->add(new admin_setting_configselect(
            "{$component}/delimiter",
            new lang_string('csvdelimiter', 'tool_uploaduser'),
            '',
            'comma',
            csv_import_reader::get_delimiter_list()
        ));

        $settings->add(new admin_setting_configselect(
            "{$component}/uploadtype",
            new lang_string('uuoptype', 'tool_uploaduser'),
            '',
            UU_USER_ADD_UPDATE,
            [
                UU_USER_ADDNEW => new lang_string('uuoptype_addnew', 'tool_uploaduser'),
                UU_USER_ADDINC => new lang_string('uuoptype_addinc', 'tool_uploaduser'),
                UU_USER_ADD_UPDATE => new lang_string('uuoptype_addupdate', 'tool_uploaduser'),
                UU_USER_UPDATE => new lang_string('uuoptype_update', 'tool_uploaduser'),
            ]
        ));

        $settings->add(new admin_setting_configselect(
            "{$component}/updatetype",
            new lang_string('uuupdatetype', 'tool_uploaduser'),
            '',
            UU_UPDATE_FILEOVERRIDE,
            [
                UU_UPDATE_NOCHANGES => new lang_string('nochanges', 'tool_uploaduser'),
                UU_UPDATE_FILEOVERRIDE => new lang_string('uuupdatefromfile', 'tool_uploaduser'),
                UU_UPDATE_ALLOVERRIDE => new lang_string('uuupdateall', 'tool_uploaduser'),
                UU_UPDATE_MISSING => new lang_string('uuupdatemissing', 'tool_uploaduser'),
            ]
        ));

        $settings->add(new admin_setting_configselect(
            "{$component}/createpasswords",
            new lang_string('uupasswordnew', 'tool_uploaduser'),
            '',
            1,
            [
                0 => new lang_string('infilefield', 'auth'),
                1 => new lang_string('createpasswordifneeded', 'auth'),
            ]
        ));

        $settings->add(new admin_setting_configcheckbox(
            "{$component}/noemailduplicates",
            new lang_string('uunoemailduplicates', 'tool_uploaduser'),
            '',
            1
        ));

        $settings->add(new admin_setting_configcheckbox(
            "{$component}/standardusernames",
            new lang_string('uustandardusernames', 'tool_uploaduser'),
            '',
            0
        ));

        $settings->add(new admin_setting_configcheckbox(
            "{$component}/allowsuspends",
            new lang_string('allowsuspends', 'tool_uploaduser'),
            '',
            0
        ));

        $settings->add(new admin_setting_configtext(
            "{$component}/defaultemaildomain",
            new lang_string('defaultemaildomain', $component),
            new lang_string('defaultemaildomain_desc', $component),
            '',
            PARAM_HOST
        ));

        // Import slots.
        $counts = array_combine(
            range(1, \local_ntautoupload\manager::MAX_IMPORTS),
            range(1, \local_ntautoupload\manager::MAX_IMPORTS)
        );
        $settings->add(new admin_setting_configselect(
            "{$component}/numimports",
            new lang_string('numimports', $component),
            new lang_string('numimports_desc', $component),
            3,
            $counts
        ));

        for ($i = 1; $i <= \local_ntautoupload\manager::get_import_count(); $i++) {
            $settings->add(new admin_setting_heading(
                "{$component}/importheading{$i}",
                new lang_string('importn', $component, $i),
                ''
            ));

            $settings->add(new admin_setting_configtext(
                "{$component}/name{$i}",
                new lang_string('importname', $component),
                new lang_string('importname_desc', $component),
                '',
                PARAM_TEXT
            ));

            $settings->add(new admin_setting_configtext(
                "{$component}/filepath{$i}",
                new lang_string('filepath', $component),
                new lang_string('filepath_desc', $component, $CFG->dataroot),
                '',
                PARAM_PATH
            ));

            $settings->add(new admin_setting_configcheckbox(
                "{$component}/notifynofile{$i}",
                new lang_string('notifynofile', $component),
                new lang_string('notifynofile_desc', $component),
                0
            ));

            $settings->add(new admin_setting_configcheckbox(
                "{$component}/renameafter{$i}",
                new lang_string('renameafter', $component),
                new lang_string('renameafter_desc', $component),
                0
            ));
        }
    }
}
