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

namespace local_ntautoupload\task;

use local_ntautoupload\manager;

/**
 * Scheduled task that runs every configured automatic user upload in order.
 *
 * @package    local_ntautoupload
 * @copyright  2026 Brian Pool, National Trail Local Schools
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class run_imports extends \core\task\scheduled_task {
    /**
     * Name shown on the scheduled tasks admin page.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskrunimports', 'local_ntautoupload');
    }

    /**
     * Run the imports.
     *
     * @return void
     */
    public function execute(): void {
        manager::run(null, false, true);
    }
}
