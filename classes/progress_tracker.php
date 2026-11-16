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

/**
 * Progress tracker that keeps the task log short and collects problems for the report.
 *
 * Only rows that have a warning or an error are written to the task log, and
 * rows with an error are also collected so they can be listed in the email.
 * Enrolments in courses that do not exist are counted per course instead, so
 * the email shows one line per course rather than one per row.
 * The core upload process creates the tracker itself from a class name, so the
 * collected problems are kept in a static property.
 *
 * @package    local_ntautoupload
 * @copyright  2026 Brian Pool, National Trail Local Schools
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class progress_tracker extends \tool_uploaduser\local\text_progress_tracker {
    /** @var string[] Problems collected during the current run. */
    protected static $problems = [];

    /** @var int[] Rows per course short name that does not exist in Moodle, collected during the current run. */
    protected static $unknowncourses = [];

    /** @var string|null Regular expression matching the core "unknown course" message. */
    protected static $unknowncoursepattern = null;

    /**
     * Clear the collected problems before a new run.
     *
     * @return void
     */
    public static function reset_problems(): void {
        self::$problems = [];
        self::$unknowncourses = [];
    }

    /**
     * Courses named in the file that do not exist in Moodle, with the number of rows for each.
     *
     * @return int[] Keyed by course short name, sorted by name.
     */
    public static function get_unknown_courses(): array {
        $courses = self::$unknowncourses;
        ksort($courses, SORT_NATURAL | SORT_FLAG_CASE);
        return $courses;
    }

    /**
     * If a message is the core "unknown course" error, return the course name.
     *
     * @param string $message Plain text message.
     * @return string|null
     */
    protected static function match_unknown_course(string $message): ?string {
        if (self::$unknowncoursepattern === null) {
            $marker = 'NTAUTOUPLOADCOURSE';
            $template = self::to_plain_text(get_string('unknowncourse', 'error', $marker));
            self::$unknowncoursepattern = '/^' . str_replace(preg_quote($marker, '/'), '(.+)', preg_quote($template, '/')) . '$/u';
        }
        return preg_match(self::$unknowncoursepattern, $message, $matches) ? $matches[1] : null;
    }

    /**
     * Problems collected during the current run.
     *
     * @return string[]
     */
    public static function get_problems(): array {
        return self::$problems;
    }

    /**
     * Write one line to the task log.
     *
     * @param string $line
     * @return void
     */
    protected function output_line(string $line): void {
        mtrace(self::to_plain_text($line));
    }

    /**
     * Flush the current row, logging it only when it has a warning or an error.
     *
     * @return void
     */
    public function flush() {
        if (empty($this->_row) || empty($this->_row['line']['normal'])) {
            parent::flush();
            return;
        }

        $haswarning = false;
        $errors = [];
        foreach ($this->_row as $key => $field) {
            if (!empty($field['warning'])) {
                $haswarning = true;
            }
            if (!empty($field['error'])) {
                $messages = [];
                foreach (explode("\n", $field['error']) as $message) {
                    $message = self::to_plain_text($message);
                    if ($message === '') {
                        continue;
                    }
                    if ($key === 'enrolments' && ($course = self::match_unknown_course($message)) !== null) {
                        self::$unknowncourses[$course] = (self::$unknowncourses[$course] ?? 0) + 1;
                        continue;
                    }
                    $messages[] = $message;
                }
                if ($messages) {
                    $label = ($key === 'status') ? '' : $this->headers[$key] . ': ';
                    $errors[] = $label . implode('; ', $messages);
                }
                $haswarning = true;
            }
        }

        if ($errors) {
            $username = self::to_plain_text($this->_row['username']['normal'] ?? '');
            $line = get_string('linex', 'tool_uploaduser', $this->_row['line']['normal']);
            if ($username !== '') {
                $line .= ' (' . $username . ')';
            }
            self::$problems[] = $line . ': ' . implode('; ', $errors);
        }

        if ($errors || $haswarning) {
            parent::flush();
            return;
        }

        // Nothing worth logging: start a fresh row without output.
        foreach ($this->columns as $col) {
            $this->_row[$col] = ['normal' => '', 'info' => '', 'warning' => '', 'error' => ''];
        }
    }

    /**
     * Strip markup the core process adds for its web page output.
     *
     * @param string $text
     * @return string
     */
    protected static function to_plain_text(string $text): string {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s*\n\s*/', '; ', $text));
    }
}
