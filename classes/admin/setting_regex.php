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

namespace local_ntautoupload\admin;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/adminlib.php');

/**
 * Admin setting for a regular expression, checked when the settings are saved.
 *
 * The value is a pattern without delimiters, for example ^[0-9]{4}[A-Z]{2}$.
 *
 * @package    local_ntautoupload
 * @copyright  2026 Brian Pool, National Trail Local Schools
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_regex extends \admin_setting_configtext {
    /**
     * Create the setting.
     *
     * @param string $name
     * @param string $visiblename
     * @param string $description
     * @param string $defaultsetting
     */
    public function __construct($name, $visiblename, $description, $defaultsetting) {
        parent::__construct($name, $visiblename, $description, $defaultsetting, PARAM_RAW_TRIMMED);
    }

    /**
     * Accept an empty value or a valid regular expression.
     *
     * @param string $data
     * @return bool|string True if valid, otherwise an error message.
     */
    public function validate($data) {
        $data = trim((string)$data);
        if ($data === '' || \local_ntautoupload\manager::is_valid_pattern($data)) {
            return true;
        }
        return get_string('errorpattern', 'local_ntautoupload');
    }
}
