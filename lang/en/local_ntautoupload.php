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
 * English language strings for local_ntautoupload.
 *
 * @package    local_ntautoupload
 * @copyright  2026 Brian Pool, National Trail Local Schools
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['autopause'] = 'Pause automatically each year';
$string['autopause_desc'] = 'Switch automatic uploads off on the date below, once a year. Uploads stay off until you turn them back on with the setting above, for example after the new semester\'s courses are ready.';
$string['autopausebody'] = 'Automatic user uploads on {$a->site} were switched off today because the yearly pause date was reached.

No uploads will run until you turn them back on:
{$a->url}';
$string['autopauseday'] = 'Pause day';
$string['autopauseday_desc'] = 'If the month has fewer days, the last day of the month is used.';
$string['autopausemonth'] = 'Pause month';
$string['autopausesubject'] = '[{$a}] Automatic user uploads paused';
$string['clihelp'] = 'Runs the automatic user uploads now.

Options:
--import=N        Run only import number N (default: every configured import, in order)
--force           Import the file even if it has not changed since the last import
--ignore-pause    Run even if automatic uploads are switched off
--no-email        Do not send the email reports
--print-report    Show each report on screen, as it would be emailed
-h, --help        Print this help

Example:
$ sudo -u www-data /usr/bin/php local/ntautoupload/cli/run.php --import=1';
$string['clinoimport'] = 'Import {$a} is not configured.';
$string['clireportsubject'] = 'Subject: {$a}';
$string['dailyreporthour'] = 'Daily report time';
$string['dailyreporthour_desc'] = 'For imports with "Daily report when no new file" ticked: if no new file has been imported by this time, one email a day says so.';
$string['defaultemaildomain'] = 'Default email domain';
$string['defaultemaildomain_desc'] = 'If a row has a blank or invalid email address, new accounts get username@this domain (for example example.org, without the @). Existing accounts keep their current address. Leave empty to turn this off.';
$string['enabled'] = 'Automatic uploads enabled';
$string['enabled_desc'] = 'Untick to stop every automatic upload. Nothing is imported and no reports are sent until this is ticked again.';
$string['errorcsv'] = 'The CSV file could not be read: {$a}';
$string['errorinvalidpath'] = 'The file path "{$a}" is not allowed. Use a path relative to the Moodle data directory, without "..".';
$string['errorpattern'] = 'This is not a valid regular expression.';
$string['errorrename'] = 'The file was imported but could not be renamed to {$a}. It may be imported again on the next run.';
$string['errorunreadable'] = 'The file "{$a}" exists but cannot be read by the web server user.';
$string['filepath'] = 'CSV file';
$string['filepath_desc'] = 'Path of the CSV file, relative to the Moodle data directory ({$a}). For example: imports/users.csv. Leave empty to turn this import off. The file uses the same columns as Site administration > Users > Upload users.';
$string['generalheading'] = 'Automatic uploads';
$string['generalheading_desc'] = 'Imports CSV files from the Moodle data directory with the core Upload users tool. The scheduled task "Run automatic user uploads" checks every hour by default. An import runs only when its file is new or has changed since the last successful import. Imports run one after another in the order below.';
$string['importn'] = 'Import {$a}';
$string['importname'] = 'Name';
$string['importname_desc'] = 'Name used in the email report, for example "Users" or "New class enrolments".';
$string['logalreadyrunning'] = 'Automatic user uploads are already running. Skipping this run.';
$string['logautopaused'] = 'The yearly pause date was reached. Automatic user uploads have been switched off.';
$string['logemailfailed'] = 'Could not send the report email to {$a}.';
$string['logfailedbefore'] = '{$a}: the file is the same one that failed last time. Fix or replace it, or run the CLI script with --force.';
$string['logfinished'] = 'Finished {$a->name}: {$a->status}';
$string['lognofile'] = '{$a}: no file found.';
$string['logpaused'] = 'Automatic user uploads are switched off. Nothing was imported.';
$string['logstarting'] = 'Starting {$a}';
$string['logtooyoung'] = '{$a}: the file changed in the last minute and may still be being written. It will be checked again next run.';
$string['logunchanged'] = '{$a}: the file has not changed since the last import.';
$string['nonewfilebody'] = 'Site: {$a->site}
Import: {$a->name}
File: {$a->file}

No new or changed file has been imported today.
Last successful import: {$a->lastimport}';
$string['notifyemails'] = 'Send reports to';
$string['notifyemails_desc'] = 'Email addresses that receive the reports, separated by commas. Leave empty to use the main administrator\'s address.';
$string['notifynofile'] = 'Daily report when no new file';
$string['notifynofile_desc'] = 'Send one email a day, at the daily report time, if no new file was imported since midnight. A report is always sent whenever a file is imported or an import fails.';
$string['numimports'] = 'Number of imports';
$string['numimports_desc'] = 'How many import slots to show. Save the page to see the new slots.';
$string['optionsheading'] = 'Upload options';
$string['optionsheading_desc'] = 'These options apply to every import and match the options on the Upload users page.';
$string['pluginname'] = 'Automatic user uploads';
$string['privacy:metadata'] = 'The Automatic user uploads plugin does not store any personal data. Accounts, enrolments and groups it creates are stored by Moodle core.';
$string['renameafter'] = 'Rename the file after importing';
$string['renameafter_desc'] = 'After a successful import, rename the file to name-processed.csv in the same folder. Use this for files you create by hand: each new file is imported once, even if its content matches an earlier file. Leave unticked for files that another system replaces regularly; those are imported only when their content changes.';
$string['reportcounts'] = 'Users created: {$a->created}
Users updated: {$a->updated}
Users unchanged: {$a->uptodate}
Users skipped: {$a->skipped}
Weak passwords: {$a->weakpasswords}
Errors: {$a->errors}';
$string['reportheader'] = 'Site: {$a->site}
Import: {$a->name}
File: {$a->file}
Result: {$a->status}
Started: {$a->started}
Run time: {$a->duration}';
$string['reportheading'] = 'Reports';
$string['reportmessage'] = 'Message: {$a}';
$string['reportmoreproblems'] = '...and {$a} more. See the task log for the full list.';
$string['reportproblems'] = 'Rows with errors ({$a}):';
$string['reportrenamed'] = 'The file was renamed to {$a}';
$string['reportsubject'] = '[{$a->site}] Automatic upload "{$a->name}": {$a->status}';
$string['reportunknowncourse'] = '{$a->course} ({$a->rows})';
$string['reportunknowncourses'] = 'Courses in the file that do not exist in Moodle ({$a}), with the number of rows for each:';
$string['status_failed'] = 'FAILED';
$string['status_nonewfile'] = 'no new file today';
$string['status_processed'] = 'completed';
$string['taskrunimports'] = 'Run automatic user uploads';
$string['unknowncourseminrows'] = 'Minimum rows for an unknown course';
$string['unknowncourseminrows_desc'] = 'List an unknown course only if at least this many rows in the file name it. Use 1 to list them all.';
$string['unknowncoursepattern'] = 'Unknown courses to report';
$string['unknowncoursepattern_desc'] = 'A regular expression (without delimiters) that a course short name must match to be listed under "Courses in the file that do not exist in Moodle". Leave empty to list every unknown course. For example, ^[0-9]{4}[A-Z]{2}$ lists only names made of four digits followed by two capital letters, such as 0013DC.';
