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

namespace local_ntautoupload;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/csvlib.class.php');
require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->dirroot . '/cohort/lib.php');
require_once($CFG->dirroot . '/admin/tool/uploaduser/locallib.php');

/**
 * Runs the configured automatic user uploads, handles the seasonal pause and sends reports.
 *
 * @package    local_ntautoupload
 * @copyright  2026 Brian Pool, National Trail Local Schools
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /** @var string Plugin component name. */
    public const COMPONENT = 'local_ntautoupload';

    /** @var int Largest number of import slots that can be configured. */
    public const MAX_IMPORTS = 10;

    /** @var string Import finished processing a file. */
    public const STATUS_PROCESSED = 'processed';

    /** @var int Seconds a file must be left unchanged before it is imported, so half-written files are skipped. */
    public const MIN_FILE_AGE = 60;

    /** @var string The import failed. */
    public const STATUS_FAILED = 'failed';

    /**
     * Number of import slots shown in the settings.
     *
     * @return int
     */
    public static function get_import_count(): int {
        $count = (int)get_config(self::COMPONENT, 'numimports');
        if ($count < 1) {
            $count = 3;
        }
        return min($count, self::MAX_IMPORTS);
    }

    /**
     * Whether automatic uploads are switched on.
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        return !empty(get_config(self::COMPONENT, 'enabled'));
    }

    /**
     * Timestamp of the automatic pause date in the given year, in the server timezone.
     *
     * A day that does not exist in the chosen month (for example 31 November) is moved
     * back to the last day of that month.
     *
     * @param int $year
     * @return int
     */
    public static function get_autopause_timestamp(int $year): int {
        $month = (int)get_config(self::COMPONENT, 'autopausemonth');
        $day = (int)get_config(self::COMPONENT, 'autopauseday');
        $month = ($month >= 1 && $month <= 12) ? $month : 12;
        $day = ($day >= 1 && $day <= 31) ? $day : 15;
        while ($day > 28 && !checkdate($month, $day, $year)) {
            $day--;
        }
        $date = new \DateTime('now', \core_date::get_server_timezone_object());
        $date->setDate($year, $month, $day);
        $date->setTime(0, 0, 0);
        return $date->getTimestamp();
    }

    /**
     * Switch uploads off if the yearly pause date has been reached.
     *
     * The pause happens at most once per calendar year, so an administrator who
     * turns uploads back on after the pause date is not overruled the next night.
     *
     * @param int $now Current timestamp.
     * @return bool True if uploads were paused by this call.
     */
    public static function check_autopause(int $now): bool {
        if (empty(get_config(self::COMPONENT, 'autopause')) || !self::is_enabled()) {
            return false;
        }
        $date = new \DateTime('@' . $now);
        $date->setTimezone(\core_date::get_server_timezone_object());
        $year = (int)$date->format('Y');

        if ((int)get_config(self::COMPONENT, 'lastautopauseyear') >= $year) {
            return false;
        }
        if ($now < self::get_autopause_timestamp($year)) {
            return false;
        }

        set_config('enabled', 0, self::COMPONENT);
        set_config('lastautopauseyear', $year, self::COMPONENT);
        mtrace(get_string('logautopaused', self::COMPONENT));
        return true;
    }

    /**
     * Run one or all configured imports.
     *
     * An import only runs when its file is new or has changed since the last
     * successful import, so the task can run every hour and pick up files soon
     * after they appear.
     *
     * @param int|null $slot Import number to run, or null for every configured import in order.
     * @param bool $ignorepause Run even when uploads are switched off.
     * @param bool $sendemail Send the email reports.
     * @param bool $force Import the file even if it has not changed since the last import.
     * @param int|null $now Current time, for testing.
     * @return \stdClass[] Results, one per import that was attempted.
     */
    public static function run(
        ?int $slot = null,
        bool $ignorepause = false,
        bool $sendemail = true,
        bool $force = false,
        ?int $now = null
    ): array {
        $now = $now ?? time();
        if (self::check_autopause($now) && $sendemail) {
            self::send_autopause_notice();
        }

        if (!$ignorepause && !self::is_enabled()) {
            mtrace(get_string('logpaused', self::COMPONENT));
            return [];
        }

        $lockfactory = \core\lock\lock_config::get_lock_factory(self::COMPONENT);
        $lock = $lockfactory->get_lock('run', 0);
        if (!$lock) {
            mtrace(get_string('logalreadyrunning', self::COMPONENT));
            return [];
        }

        \core_php_time_limit::raise();
        raise_memory_limit(MEMORY_HUGE);

        $results = [];
        try {
            $slots = ($slot === null) ? range(1, self::get_import_count()) : [$slot];
            foreach ($slots as $i) {
                $config = self::get_import_config($i);
                if ($config->filepath === '') {
                    continue;
                }

                $check = self::check_file($config, $force, $now);
                if ($check->state !== 'ready') {
                    mtrace(get_string('log' . $check->state, self::COMPONENT, $config->name));
                    if ($sendemail && $config->notifynofile && self::is_daily_notice_due($config, $now)) {
                        self::send_no_new_file_notice($config, $now);
                    }
                    continue;
                }

                mtrace(get_string('logstarting', self::COMPONENT, $config->name));
                $result = self::run_import($config, $check->path);
                $result->hash = $check->hash;
                $results[] = $result;
                mtrace(get_string('logfinished', self::COMPONENT, [
                    'name' => $config->name,
                    'status' => get_string('status_' . $result->status, self::COMPONENT),
                ]));

                if ($result->status === self::STATUS_PROCESSED) {
                    set_config('lasthash' . $i, $check->hash, self::COMPONENT);
                    set_config('lastmtime' . $i, $check->mtime, self::COMPONENT);
                    set_config('lastimport' . $i, $now, self::COMPONENT);
                    unset_config('lastfailedhash' . $i, self::COMPONENT);
                } else {
                    set_config('lastfailedhash' . $i, $check->hash, self::COMPONENT);
                }

                if ($sendemail) {
                    self::send_report($result);
                }
            }
        } finally {
            $lock->release();
        }
        return $results;
    }

    /**
     * Decide whether an import's file should be imported now.
     *
     * States: 'nofile' (no file), 'unchanged' (same content as the last successful
     * import), 'failedbefore' (same content as the last failed import), 'tooyoung'
     * (changed in the last minute, so it may still be being written) or 'ready'.
     * For files that are renamed after importing, a file saved again after the last
     * import counts as new even if its content is the same.
     *
     * @param \stdClass $config From {@see get_import_config()}.
     * @param bool $force Ignore the change check.
     * @param int $now Current time.
     * @return \stdClass With state, path and hash.
     */
    public static function check_file(\stdClass $config, bool $force, int $now): \stdClass {
        $check = (object)['state' => 'nofile', 'path' => null, 'hash' => '', 'mtime' => 0];
        $path = self::resolve_path($config->filepath);
        if ($path === null) {
            // Let run_import() report the invalid path.
            $check->state = 'ready';
            return $check;
        }
        clearstatcache(true, $path);
        if (!is_file($path)) {
            return $check;
        }
        $check->path = $path;
        if (!is_readable($path)) {
            $check->state = 'ready';
            return $check;
        }
        if (!$force && ($now - filemtime($path)) < self::MIN_FILE_AGE) {
            $check->state = 'tooyoung';
            return $check;
        }
        $check->hash = sha1_file($path);
        $check->mtime = filemtime($path);
        // A renamed-after file with the same content is still new if it was saved again
        // after the last import (for example a class list made again by hand). If the
        // rename failed, the untouched file is not imported again every hour.
        $same = ($check->hash === $config->lasthash);
        if ($config->renameafter) {
            $same = $same && $check->mtime <= $config->lastmtime;
        }
        if (!$force && $same) {
            $check->state = 'unchanged';
        } else if (!$force && $check->hash === $config->lastfailedhash) {
            $check->state = 'failedbefore';
        } else {
            $check->state = 'ready';
        }
        return $check;
    }

    /**
     * Whether today's "no new file" notice should be sent for an import.
     *
     * The notice goes out once a day, at or after the configured hour, and only if
     * the import has not run since midnight.
     *
     * @param \stdClass $config From {@see get_import_config()}.
     * @param int $now Current time.
     * @return bool
     */
    public static function is_daily_notice_due(\stdClass $config, int $now): bool {
        $date = new \DateTime('@' . $now);
        $date->setTimezone(\core_date::get_server_timezone_object());
        $hour = (int)$date->format('G');
        $date->setTime(0, 0, 0);
        $midnight = $date->getTimestamp();

        $reporthour = (int)get_config(self::COMPONENT, 'dailyreporthour');
        return $hour >= $reporthour && $config->lastnotice < $midnight && $config->lastimport < $midnight;
    }

    /**
     * Settings and state for one import slot.
     *
     * @param int $slot
     * @return \stdClass
     */
    public static function get_import_config(int $slot): \stdClass {
        $config = get_config(self::COMPONENT);
        $name = trim($config->{'name' . $slot} ?? '');
        return (object)[
            'slot' => $slot,
            'name' => ($name !== '') ? $name : get_string('importn', self::COMPONENT, $slot),
            'filepath' => trim($config->{'filepath' . $slot} ?? ''),
            'notifynofile' => !empty($config->{'notifynofile' . $slot}),
            'renameafter' => !empty($config->{'renameafter' . $slot}),
            'lasthash' => (string)($config->{'lasthash' . $slot} ?? ''),
            'lastfailedhash' => (string)($config->{'lastfailedhash' . $slot} ?? ''),
            'lastimport' => (int)($config->{'lastimport' . $slot} ?? 0),
            'lastmtime' => (int)($config->{'lastmtime' . $slot} ?? 0),
            'lastnotice' => (int)($config->{'lastnotice' . $slot} ?? 0),
        ];
    }

    /**
     * Import one file.
     *
     * @param \stdClass $config From {@see get_import_config()}.
     * @param string|null $path Absolute path from {@see check_file()}.
     * @return \stdClass Result with status, counts, problems and message.
     */
    public static function run_import(\stdClass $config, ?string $path): \stdClass {
        $start = time();
        $result = (object)[
            'name' => $config->name,
            'filepath' => $config->filepath,
            'status' => self::STATUS_FAILED,
            'counts' => [],
            'problems' => [],
            'unknowncourses' => [],
            'message' => '',
            'renamedto' => '',
            'started' => $start,
            'duration' => 0,
        ];

        try {
            if ($path === null) {
                throw new \moodle_exception('errorinvalidpath', self::COMPONENT, '', $config->filepath);
            }
            if (!is_readable($path)) {
                throw new \moodle_exception('errorunreadable', self::COMPONENT, '', $config->filepath);
            }

            $content = file_get_contents($path);
            $iid = \csv_import_reader::get_new_iid('uploaduser');
            $cir = new \csv_import_reader($iid, 'uploaduser');
            $settings = get_config(self::COMPONENT);
            $cir->load_csv_content($content, $settings->encoding ?? 'UTF-8', $settings->delimiter ?? 'comma');
            unset($content);
            if (($error = $cir->get_error()) !== null) {
                $cir->cleanup(true);
                throw new \moodle_exception('errorcsv', self::COMPONENT, '', $error);
            }

            progress_tracker::reset_problems();
            $uploader = new uploader($cir, progress_tracker::class);
            $uploader->set_form_data(self::get_form_data());
            $uploader->set_default_email_domain($settings->defaultemaildomain ?? '');
            $uploader->process();

            $result->status = self::STATUS_PROCESSED;
            $result->counts = $uploader->get_counts();
            $result->problems = progress_tracker::get_problems();
            $result->unknowncourses = progress_tracker::get_unknown_courses();

            if ($config->renameafter) {
                $target = self::get_processed_path($path);
                if (@rename($path, $target)) {
                    $result->renamedto = $target;
                } else {
                    $result->message = get_string('errorrename', self::COMPONENT, $target);
                    mtrace($result->message);
                }
            }
        } catch (\Throwable $e) {
            $result->status = self::STATUS_FAILED;
            $result->message = $e->getMessage();
            $result->problems = progress_tracker::get_problems();
            mtrace($result->message);
        }

        $result->duration = time() - $start;
        return $result;
    }

    /**
     * Turn a configured path into an absolute path inside the Moodle data directory.
     *
     * Paths are relative to $CFG->dataroot. Absolute paths and paths containing ".."
     * are rejected so the plugin can only read files from the data directory.
     *
     * @param string $relativepath
     * @return string|null Absolute path, or null if the path is not allowed.
     */
    public static function resolve_path(string $relativepath): ?string {
        global $CFG;
        $relativepath = trim(str_replace('\\', '/', $relativepath));
        if ($relativepath === '' || strpos($relativepath, '/') === 0 || preg_match('~(^|/)\.\.(/|$)~', $relativepath)) {
            return null;
        }
        return rtrim($CFG->dataroot, '/') . '/' . $relativepath;
    }

    /**
     * Name a processed file is renamed to: name-processed.ext in the same folder.
     *
     * @param string $path
     * @return string
     */
    public static function get_processed_path(string $path): string {
        $info = pathinfo($path);
        $extension = isset($info['extension']) ? '.' . $info['extension'] : '';
        return $info['dirname'] . '/' . $info['filename'] . '-processed' . $extension;
    }

    /**
     * Options for the core upload process, taken from the plugin settings.
     *
     * @return \stdClass
     */
    public static function get_form_data(): \stdClass {
        $config = get_config(self::COMPONENT);
        return (object)[
            'uutype' => (int)($config->uploadtype ?? UU_USER_ADD_UPDATE),
            'uuupdatetype' => (int)($config->updatetype ?? UU_UPDATE_FILEOVERRIDE),
            'uupasswordnew' => (int)($config->createpasswords ?? 1),
            'uupasswordold' => 0,
            'uunoemailduplicates' => (int)($config->noemailduplicates ?? 1),
            'uustandardusernames' => (int)($config->standardusernames ?? 0),
            'uuallowsuspends' => (int)($config->allowsuspends ?? 0),
            'uuallowrenames' => 0,
            'uuallowdeletes' => 0,
            'uumatchemail' => 0,
            'uubulk' => UU_BULK_NONE,
            'uuforcepasswordchange' => UU_PWRESET_NONE,
        ];
    }

    /**
     * Email addresses that receive reports. Falls back to the main administrator.
     *
     * @return string[]
     */
    public static function get_recipients(): array {
        $setting = (string)get_config(self::COMPONENT, 'notifyemails');
        $emails = [];
        foreach (preg_split('/[\s,;]+/', $setting, -1, PREG_SPLIT_NO_EMPTY) as $email) {
            if (validate_email($email)) {
                $emails[] = $email;
            }
        }
        if (!$emails && ($admin = get_admin())) {
            $emails[] = $admin->email;
        }
        return array_values(array_unique($emails));
    }

    /**
     * Email the report for one import.
     *
     * @param \stdClass $result From {@see run_import()}.
     * @return bool True if every email was sent.
     */
    public static function send_report(\stdClass $result): bool {
        [$subject, $body] = self::build_report($result);
        return self::send_email($subject, $body);
    }

    /**
     * Build the subject and plain text body of the report for one import.
     *
     * @param \stdClass $result From {@see run_import()}.
     * @return string[] Subject and body.
     */
    public static function build_report(\stdClass $result): array {
        $site = get_site();
        $status = get_string('status_' . $result->status, self::COMPONENT);
        $subject = get_string('reportsubject', self::COMPONENT, [
            'site' => format_string($site->shortname),
            'name' => $result->name,
            'status' => $status,
        ]);

        $a = [
            'site' => format_string($site->fullname),
            'name' => $result->name,
            'file' => $result->filepath,
            'status' => $status,
            'started' => userdate($result->started, get_string('strftimedatetime', 'langconfig')),
            'duration' => format_time($result->duration),
        ];
        $lines = [get_string('reportheader', self::COMPONENT, $a), ''];

        if ($result->status === self::STATUS_PROCESSED) {
            $lines[] = get_string('reportcounts', self::COMPONENT, $result->counts);
        }
        if ($result->renamedto !== '') {
            $lines[] = '';
            $lines[] = get_string('reportrenamed', self::COMPONENT, $result->renamedto);
        }
        if ($result->message !== '') {
            $lines[] = '';
            $lines[] = get_string('reportmessage', self::COMPONENT, $result->message);
        }
        $unknown = self::filter_unknown_courses($result->unknowncourses);
        if ($unknown) {
            $lines[] = '';
            $lines[] = get_string('reportunknowncourses', self::COMPONENT, count($unknown));
            $courses = [];
            foreach ($unknown as $course => $rows) {
                $courses[] = get_string('reportunknowncourse', self::COMPONENT, ['course' => $course, 'rows' => $rows]);
            }
            $lines[] = implode(', ', $courses);
        }
        if ($result->problems) {
            $lines[] = '';
            $lines[] = get_string('reportproblems', self::COMPONENT, count($result->problems));
            $shown = array_slice($result->problems, 0, 200);
            foreach ($shown as $problem) {
                $lines[] = '- ' . $problem;
            }
            if (count($result->problems) > count($shown)) {
                $lines[] = get_string('reportmoreproblems', self::COMPONENT, count($result->problems) - count($shown));
            }
        }

        return [$subject, implode("\n", $lines)];
    }

    /**
     * Keep only the unknown courses the report settings ask to see.
     *
     * A course is reported when its short name matches the "Unknown courses to report"
     * pattern (if one is set) and it appears on at least the minimum number of rows.
     *
     * @param int[] $courses Rows per course short name.
     * @return int[]
     */
    public static function filter_unknown_courses(array $courses): array {
        $pattern = trim((string)get_config(self::COMPONENT, 'unknowncoursepattern'));
        $minrows = max(1, (int)get_config(self::COMPONENT, 'unknowncourseminrows'));
        $usepattern = ($pattern !== '' && self::is_valid_pattern($pattern));
        $kept = [];
        foreach ($courses as $course => $rows) {
            if ($rows < $minrows) {
                continue;
            }
            if ($usepattern && !preg_match(self::wrap_pattern($pattern), (string)$course)) {
                continue;
            }
            $kept[$course] = $rows;
        }
        return $kept;
    }

    /**
     * Whether a pattern from the settings is a valid regular expression.
     *
     * @param string $pattern Pattern without delimiters.
     * @return bool
     */
    public static function is_valid_pattern(string $pattern): bool {
        return @preg_match(self::wrap_pattern($pattern), '') !== false;
    }

    /**
     * Add delimiters to a pattern from the settings.
     *
     * @param string $pattern Pattern without delimiters.
     * @return string
     */
    protected static function wrap_pattern(string $pattern): string {
        return '~' . str_replace('~', '\\~', $pattern) . '~u';
    }

    /**
     * Email the daily notice that no new file was imported today.
     *
     * @param \stdClass $config From {@see get_import_config()}.
     * @param int $now Current time.
     * @return bool True if every email was sent.
     */
    public static function send_no_new_file_notice(\stdClass $config, int $now): bool {
        $site = get_site();
        $subject = get_string('reportsubject', self::COMPONENT, [
            'site' => format_string($site->shortname),
            'name' => $config->name,
            'status' => get_string('status_nonewfile', self::COMPONENT),
        ]);
        $body = get_string('nonewfilebody', self::COMPONENT, [
            'site' => format_string($site->fullname),
            'name' => $config->name,
            'file' => $config->filepath,
            'lastimport' => $config->lastimport
                ? userdate($config->lastimport, get_string('strftimedatetime', 'langconfig'))
                : get_string('never'),
        ]);
        set_config('lastnotice' . $config->slot, $now, self::COMPONENT);
        return self::send_email($subject, $body);
    }

    /**
     * Email a one-time notice that uploads were paused automatically.
     *
     * @return bool True if every email was sent.
     */
    public static function send_autopause_notice(): bool {
        $site = get_site();
        $subject = get_string('autopausesubject', self::COMPONENT, format_string($site->shortname));
        $url = new \moodle_url('/admin/settings.php', ['section' => self::COMPONENT]);
        $body = get_string('autopausebody', self::COMPONENT, [
            'site' => format_string($site->fullname),
            'url' => $url->out(false),
        ]);
        return self::send_email($subject, $body);
    }

    /**
     * Send a plain text email to every recipient.
     *
     * @param string $subject
     * @param string $body
     * @return bool True if every email was sent.
     */
    protected static function send_email(string $subject, string $body): bool {
        $from = \core_user::get_noreply_user();
        $ok = true;
        foreach (self::get_recipients() as $email) {
            $to = \core_user::get_noreply_user();
            $to->email = $email;
            $to->firstname = $email;
            $to->lastname = '';
            $to->mailformat = 0; // Plain text.
            $to->emailstop = 0;
            if (!email_to_user($to, $from, $subject, $body)) {
                mtrace(get_string('logemailfailed', self::COMPONENT, $email));
                $ok = false;
            }
        }
        return $ok;
    }
}
