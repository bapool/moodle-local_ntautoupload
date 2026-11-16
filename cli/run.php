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
 * Run the automatic user uploads from the command line.
 *
 * @package    local_ntautoupload
 * @copyright  2026 Brian Pool, National Trail Local Schools
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_ntautoupload\manager;

[$options, $unrecognized] = cli_get_params([
    'help' => false,
    'import' => null,
    'ignore-pause' => false,
    'no-email' => false,
    'force' => false,
    'print-report' => false,
], [
    'h' => 'help',
]);

if ($unrecognized) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognized)));
}

if ($options['help']) {
    cli_writeln(get_string('clihelp', 'local_ntautoupload'));
    exit(0);
}

$slot = null;
if ($options['import'] !== null) {
    $slot = (int)$options['import'];
    if ($slot < 1 || $slot > manager::get_import_count() || manager::get_import_config($slot)->filepath === '') {
        cli_error(get_string('clinoimport', 'local_ntautoupload', $options['import']));
    }
}

\core\cron::setup_user();

$results = manager::run(
    $slot,
    !empty($options['ignore-pause']),
    empty($options['no-email']),
    !empty($options['force'])
);

$failed = false;
foreach ($results as $result) {
    if (!empty($options['print-report'])) {
        [$subject, $body] = manager::build_report($result);
        cli_writeln('');
        cli_writeln(str_repeat('=', 79));
        cli_writeln(get_string('clireportsubject', 'local_ntautoupload', $subject));
        cli_writeln(str_repeat('=', 79));
        cli_writeln($body);
        cli_writeln(str_repeat('=', 79));
    }
    if ($result->status === manager::STATUS_FAILED) {
        $failed = true;
    }
}
exit($failed ? 1 : 0);
