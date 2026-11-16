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
 * Tests for the automatic user upload manager.
 *
 * @package    local_ntautoupload
 * @category   test
 * @copyright  2026 Brian Pool, National Trail Local Schools
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_ntautoupload\manager
 * @covers     \local_ntautoupload\uploader
 * @covers     \local_ntautoupload\progress_tracker
 */
final class manager_test extends \advanced_testcase {
    /** @var string Folder inside dataroot used by the tests. */
    protected $folder;

    /**
     * Common setup: one import slot pointing at a test file, emails captured.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        \core_date::set_default_server_timezone();
        $this->folder = $CFG->dataroot . '/ntautoupload_test';
        check_dir_exists($this->folder);

        set_config('enabled', 1, 'local_ntautoupload');
        set_config('autopause', 1, 'local_ntautoupload');
        set_config('autopausemonth', 12, 'local_ntautoupload');
        set_config('autopauseday', 15, 'local_ntautoupload');
        set_config('numimports', 3, 'local_ntautoupload');
        set_config('dailyreporthour', 7, 'local_ntautoupload');
        set_config('defaultemaildomain', 'example.org', 'local_ntautoupload');
        set_config('notifyemails', 'reports@example.com', 'local_ntautoupload');
        set_config('name1', 'Users', 'local_ntautoupload');
        set_config('filepath1', 'ntautoupload_test/users.csv', 'local_ntautoupload');
    }

    /**
     * Run the manager, swallowing the task log output.
     *
     * @param mixed ...$args Arguments for manager::run().
     * @return \stdClass[]
     */
    protected function run_quietly(...$args): array {
        ob_start();
        try {
            return manager::run(...$args);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * Write a CSV file into the test folder with an old modification time.
     *
     * @param string $name
     * @param string $content
     * @return string Absolute path.
     */
    protected function write_csv(string $name, string $content): string {
        $path = $this->folder . '/' . $name;
        file_put_contents($path, $content);
        touch($path, time() - 600);
        clearstatcache(true, $path);
        return $path;
    }

    /**
     * Timestamp for a local date and time in the server timezone.
     *
     * @param string $datetime
     * @return int
     */
    protected function local_time(string $datetime): int {
        return (new \DateTime($datetime, \core_date::get_server_timezone_object()))->getTimestamp();
    }

    public function test_autopause_happens_once_per_year(): void {
        $this->expectOutputRegex('/pause date was reached/');
        $this->assertFalse(manager::check_autopause($this->local_time('2026-12-14 23:59:00')));
        $this->assertTrue(manager::is_enabled());

        $this->assertTrue(manager::check_autopause($this->local_time('2026-12-15 00:05:00')));
        $this->assertFalse(manager::is_enabled());

        // Turned back on in late December: not paused again that year.
        set_config('enabled', 1, 'local_ntautoupload');
        $this->assertFalse(manager::check_autopause($this->local_time('2026-12-20 10:00:00')));
        $this->assertFalse(manager::check_autopause($this->local_time('2027-01-05 10:00:00')));
        $this->assertTrue(manager::is_enabled());

        // Next year it pauses again.
        $this->assertTrue(manager::check_autopause($this->local_time('2027-12-15 03:00:00')));
        $this->assertFalse(manager::is_enabled());
    }

    public function test_autopause_can_be_turned_off(): void {
        set_config('autopause', 0, 'local_ntautoupload');
        $this->assertFalse(manager::check_autopause($this->local_time('2026-12-20 10:00:00')));
        $this->assertTrue(manager::is_enabled());
    }

    public function test_autopause_day_is_clamped_to_month_length(): void {
        set_config('autopausemonth', 2, 'local_ntautoupload');
        set_config('autopauseday', 31, 'local_ntautoupload');
        $this->assertEquals($this->local_time('2027-02-28 00:00:00'), manager::get_autopause_timestamp(2027));
        $this->assertEquals($this->local_time('2028-02-29 00:00:00'), manager::get_autopause_timestamp(2028));
    }

    public function test_resolve_path_stays_inside_dataroot(): void {
        global $CFG;
        $this->assertEquals($CFG->dataroot . '/imports/users.csv', manager::resolve_path('imports/users.csv'));
        $this->assertNull(manager::resolve_path('/etc/passwd'));
        $this->assertNull(manager::resolve_path('../outside.csv'));
        $this->assertNull(manager::resolve_path('imports/../../outside.csv'));
        $this->assertNull(manager::resolve_path(''));
    }

    public function test_import_creates_users_enrols_and_reports(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course(['shortname' => 'MATH101']);
        $this->write_csv('users.csv', "username,firstname,lastname,email,course1,group1\n" .
            "student1,Amy,Adams,,MATH101,Period 1\n" .
            "student2,Ben,Brown,ben@example.net,MATH101,Period 2\n");

        $sink = $this->redirectEmails();
        $results = $this->run_quietly();

        $this->assertCount(1, $results);
        $this->assertEquals(manager::STATUS_PROCESSED, $results[0]->status);
        $this->assertEquals(2, $results[0]->counts['created']);

        $user1 = $DB->get_record('user', ['username' => 'student1'], '*', MUST_EXIST);
        $this->assertEquals('student1@example.org', $user1->email);
        $user2 = $DB->get_record('user', ['username' => 'student2'], '*', MUST_EXIST);
        $this->assertEquals('ben@example.net', $user2->email);

        $context = \context_course::instance($course->id);
        $this->assertTrue(is_enrolled($context, $user1->id));
        $this->assertTrue(groups_is_member(groups_get_group_by_name($course->id, 'Period 1'), $user1->id));

        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertEquals('reports@example.com', $messages[0]->to);
        $this->assertStringContainsString('Users created: 2', quoted_printable_decode($messages[0]->body));
    }

    public function test_existing_user_keeps_email_when_file_has_none(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user(['username' => 'teacher1', 'email' => 'real@example.net']);
        $this->write_csv('users.csv', "username,firstname,lastname,email\nteacher1,Tia,Turner,\n");

        $this->redirectEmails();
        $results = $this->run_quietly();

        $this->assertEquals(manager::STATUS_PROCESSED, $results[0]->status);
        $this->assertEquals('real@example.net', $DB->get_field('user', 'email', ['id' => $user->id]));
        $this->assertEquals('Tia', $DB->get_field('user', 'firstname', ['id' => $user->id]));
    }

    public function test_unchanged_file_is_not_imported_again(): void {
        $this->write_csv('users.csv', "username,firstname,lastname\nstudent1,Amy,Adams\n");
        $sink = $this->redirectEmails();

        $this->assertCount(1, $this->run_quietly());
        $this->assertCount(0, $this->run_quietly(), 'Same content must be skipped.');
        $this->assertCount(1, $sink->get_messages());

        // Changed content is imported.
        $this->write_csv('users.csv', "username,firstname,lastname\nstudent1,Amy,Adams\nstudent2,Ben,Brown\n");
        $this->assertCount(1, $this->run_quietly());

        // The --force option imports unchanged content.
        $this->assertCount(1, $this->run_quietly(null, false, true, true));
    }

    public function test_recently_changed_file_waits(): void {
        $path = $this->write_csv('users.csv', "username,firstname,lastname\nstudent1,Amy,Adams\n");
        touch($path, time());
        clearstatcache(true, $path);
        $this->redirectEmails();
        $this->assertCount(0, $this->run_quietly());
    }

    public function test_renamed_files_are_imported_once_each(): void {
        set_config('name2', 'Classes', 'local_ntautoupload');
        set_config('filepath2', 'ntautoupload_test/classes.csv', 'local_ntautoupload');
        set_config('renameafter2', 1, 'local_ntautoupload');
        $content = "username,firstname,lastname\nstudent9,Cal,Cole\n";
        $path = $this->write_csv('classes.csv', $content);

        $sink = $this->redirectEmails();
        $results = $this->run_quietly();
        $this->assertCount(1, $results);
        $this->assertFileDoesNotExist($path);
        $this->assertFileExists($this->folder . '/classes-processed.csv');

        // Nothing waiting: no email for a slot without the daily report.
        $this->assertCount(0, $this->run_quietly());

        // The same content made again by hand is imported again.
        $path = $this->write_csv('classes.csv', $content);
        touch($path, time() - 120);
        clearstatcache(true, $path);
        $this->assertCount(1, $this->run_quietly());
        $this->assertCount(2, $sink->get_messages());
    }

    public function test_daily_no_new_file_notice(): void {
        set_config('notifynofile1', 1, 'local_ntautoupload');
        $sink = $this->redirectEmails();

        // Before the report hour: nothing.
        $this->run_quietly(null, false, true, false, $this->local_time('2026-11-17 06:05:00'));
        $this->assertCount(0, $sink->get_messages());

        // At the report hour: one notice, then no more that day.
        $this->run_quietly(null, false, true, false, $this->local_time('2026-11-17 07:05:00'));
        $this->run_quietly(null, false, true, false, $this->local_time('2026-11-17 08:05:00'));
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('no new file today', $messages[0]->subject);

        // Next day: another notice.
        $this->run_quietly(null, false, true, false, $this->local_time('2026-11-18 07:05:00'));
        $this->assertCount(2, $sink->get_messages());
    }

    public function test_no_notice_when_file_was_imported_today(): void {
        set_config('notifynofile1', 1, 'local_ntautoupload');
        set_config('lastimport1', $this->local_time('2026-11-17 01:05:00'), 'local_ntautoupload');
        $sink = $this->redirectEmails();
        $this->run_quietly(null, false, true, false, $this->local_time('2026-11-17 07:05:00'));
        $this->assertCount(0, $sink->get_messages());
    }

    public function test_paused_does_nothing_and_sends_nothing(): void {
        set_config('enabled', 0, 'local_ntautoupload');
        set_config('notifynofile1', 1, 'local_ntautoupload');
        $this->write_csv('users.csv', "username,firstname,lastname\nstudent1,Amy,Adams\n");
        $sink = $this->redirectEmails();

        $this->assertCount(0, $this->run_quietly());
        $this->assertCount(0, $sink->get_messages());

        // The CLI option can still run it by hand.
        $this->assertCount(1, $this->run_quietly(null, true, false));
    }

    public function test_failed_file_is_reported_once(): void {
        $this->write_csv('users.csv', "onlyonecolumn\nx\n");
        $sink = $this->redirectEmails();

        $results = $this->run_quietly();
        $this->assertEquals(manager::STATUS_FAILED, $results[0]->status);
        $this->assertCount(0, $this->run_quietly(), 'The same broken file must not be retried every hour.');
        $this->assertCount(1, $sink->get_messages());
        $this->assertStringContainsString('FAILED', $sink->get_messages()[0]->subject);
    }

    public function test_row_errors_are_listed_in_the_report(): void {
        $this->getDataGenerator()->create_user(['username' => 'taken', 'email' => 'dupe@example.net']);
        $this->write_csv('users.csv', "username,firstname,lastname,email\nnewbie,Nia,Nash,dupe@example.net\n");
        $sink = $this->redirectEmails();

        $results = $this->run_quietly();
        $this->assertEquals(1, $results[0]->counts['errors']);
        $this->assertCount(1, $results[0]->problems);
        $this->assertStringContainsString('newbie', $results[0]->problems[0]);
        $this->assertStringContainsString('newbie', quoted_printable_decode($sink->get_messages()[0]->body));
    }

    public function test_file_that_cannot_be_renamed_is_imported_once(): void {
        set_config('name2', 'Classes', 'local_ntautoupload');
        set_config('filepath2', 'ntautoupload_test/readonly/classes.csv', 'local_ntautoupload');
        set_config('renameafter2', 1, 'local_ntautoupload');
        check_dir_exists($this->folder . '/readonly');
        $this->write_csv('readonly/classes.csv', "username,firstname,lastname\nstudent9,Cal,Cole\n");
        chmod($this->folder . '/readonly', 0555);

        try {
            $this->redirectEmails();
            $results = $this->run_quietly();
            $this->assertCount(1, $results);
            if ($results[0]->renamedto !== '') {
                $this->markTestSkipped('Running as a user that can rename in a read-only folder (root).');
            }
            $this->assertNotEmpty($results[0]->message);
            $this->assertCount(0, $this->run_quietly(), 'A file that could not be renamed must not be imported every hour.');
        } finally {
            chmod($this->folder . '/readonly', 0777);
        }
    }

    public function test_uppercase_language_code_does_not_update_every_row(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user(['username' => 'student7', 'firstname' => 'Sam',
            'lastname' => 'Stone', 'lang' => 'en']);
        $this->write_csv('users.csv', "username,firstname,lastname,lang\nstudent7,Sam,Stone,EN\nstudent7,Sam,Stone,EN\n");
        $this->redirectEmails();

        ob_start();
        $results = manager::run();
        $log = ob_get_clean();

        $this->assertEquals(0, $results[0]->counts['updated']);
        $this->assertEquals(2, $results[0]->counts['uptodate']);
        $this->assertEquals('en', $DB->get_field('user', 'lang', ['id' => $user->id]));
        $this->assertStringNotContainsString('lang', $log);
    }

    public function test_unknown_courses_are_summarised(): void {
        $this->getDataGenerator()->create_course(['shortname' => 'REAL1']);
        $this->write_csv('users.csv', "username,firstname,lastname,course1\n" .
            "s1,Amy,Adams,LUNCH A\ns2,Ben,Brown,LUNCH A\ns3,Cal,Cole,STUDY HALL\ns4,Dee,Dunn,REAL1\n");
        $sink = $this->redirectEmails();

        $results = $this->run_quietly();
        $this->assertEquals(['LUNCH A' => 2, 'STUDY HALL' => 1], $results[0]->unknowncourses);
        $this->assertSame([], $results[0]->problems);
        $body = quoted_printable_decode($sink->get_messages()[0]->body);
        $this->assertStringContainsString('LUNCH A (2), STUDY HALL (1)', str_replace(["=\r\n", "\r\n"], ['', ' '], $body));
    }

    public function test_build_report_matches_email(): void {
        $this->write_csv('users.csv', "username,firstname,lastname\nstudent1,Amy,Adams\n");
        $sink = $this->redirectEmails();
        $results = $this->run_quietly();

        [$subject, $body] = manager::build_report($results[0]);
        $this->assertEquals($sink->get_messages()[0]->subject, $subject);
        $this->assertStringContainsString('Users created: 1', $body);
        $this->assertStringContainsString('Import: Users', $body);
    }

    public function test_unknown_course_report_filter(): void {
        $courses = ['0013DC' => 15, '0013BO' => 6, '0013KX' => 5, '010CH' => 18, 'ASL 121EE' => 10,
            '050152SS' => 9, '1401AJS' => 7, '0013dc' => 9];

        $this->assertEquals($courses, manager::filter_unknown_courses($courses), 'No filter: list everything.');

        set_config('unknowncoursepattern', '^[0-9]{4}[A-Z]{2}$', 'local_ntautoupload');
        set_config('unknowncourseminrows', 6, 'local_ntautoupload');
        $this->assertEquals(['0013DC' => 15, '0013BO' => 6], manager::filter_unknown_courses($courses));

        // An invalid pattern is ignored rather than hiding everything.
        set_config('unknowncoursepattern', '^[0-9', 'local_ntautoupload');
        $this->assertFalse(manager::is_valid_pattern('^[0-9'));
        $this->assertCount(7, manager::filter_unknown_courses($courses));
    }
}
