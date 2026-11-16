# Automatic user uploads (local_ntautoupload)

Imports user CSV files that another system (or a person) drops into the Moodle
data directory, using Moodle's own **Site administration > Users > Upload users**
engine. It runs as a Moodle scheduled task, emails a report after each import,
and can pause itself every year so student records are not loaded into the
wrong semester's courses.

Written by Brian Pool for National Trail Local Schools, and made generic so any
school can use it.

## Why this plugin exists

National Trail used three copies of a hacked `admin/tool/uploaduser` page
(`autouploaduser.php`, `autouploadclass.php`, `autouploadIEP.php`), opened every
night by a Windows scheduled task in Internet Explorer and by broken crontab
lines. Problems with that setup:

- The pages were public: anyone who knew the URL could start an import.
- They ran inside a web request, so the browser or web server could cut them
  off partway through, and nobody was told.
- On days with no file they crashed with a PHP error.
- They lived inside a core folder, so a Moodle upgrade would delete them.
- There was no simple way to stop uploads between semesters. If uploads ran
  after December 15 but before the spring courses were set up, last semester's
  students ended up in the new courses.

This plugin replaces all three pages.

## What it does

- **Up to 10 import slots.** Each slot is one CSV file, relative to the Moodle
  data directory (for example `moodleimport/NTmoodle.csv`). Slots run one after
  another, in order, so put the file that creates accounts first.
- **Checks every hour, imports only what is new.** The scheduled task
  "Run automatic user uploads" runs at five past every hour by default. A slot
  is imported only when its file is new or its content has changed since the
  last successful import (checked with a SHA-1 hash of the file). Files changed
  in the last minute are left for the next run in case they are still being
  written.
- **Optional rename after import.** For files you create by hand (new class
  enrolments, IEP groups), tick "Rename the file after importing". The file is
  renamed to `name-processed.csv`, so each file you drop in is imported exactly
  once, even if its content matches an earlier one.
- **Uses the core upload engine** (`tool_uploaduser\process`), so CSV columns,
  enrolments (`course1`, `role1`, `group1`, ...), cohorts and system roles all
  work exactly as on the Upload users page.
- **Default email addresses.** If a row has a blank or invalid email, a new
  account gets `username@your-domain`. An existing account keeps its current
  address.
- **Email reports**, sent to the addresses in "Send reports to" (or to the main
  administrator if that is empty):
  - after every import, with users created, updated, unchanged, skipped and
    errors, plus a list of rows that had errors;
  - when an import fails (a broken file is reported once, not every hour);
  - for slots with "Daily report when no new file" ticked: one email a day at
    the daily report time if nothing new was imported since midnight.
- **Short reports.** Rows naming a course that does not exist in Moodle are
  summarised as one line per course with a row count, not listed row by row.
  "Unknown courses to report" (a regular expression) and "Minimum rows for an
  unknown course" hide the ones you never put in Moodle, such as lunch or study
  hall sections.
- **Language codes cleaned up.** A code such as `EN_US` is matched to the
  installed language `en_us`. Before this, every row looked changed and was
  "updated" on every run. A code Moodle does not offer is ignored.
- **Master switch and yearly pause.** Untick "Automatic uploads enabled" to
  stop everything: nothing is imported and no reports are sent. With "Pause
  automatically each year" on, the plugin switches itself off on the chosen date
  (default **December 15**) and sends one email saying so. It stays off until
  you tick the switch again, for example once the spring courses are ready. It
  pauses at most once per calendar year, so turning it back on in late December
  is not overruled the next night.
- **Locking.** Only one run can happen at a time, whether it was started by the
  scheduled task or from the command line.

## Requirements

- Moodle 4.5 (tested on 4.5.16 with PostgreSQL 16).
- The core Upload users tool (`tool_uploaduser`), which is always present.
- Moodle cron running normally (`admin/cli/cron.php` every minute).

## Installing

1. Copy this folder to `local/ntautoupload` in your Moodle code, so the files sit
   at `local/ntautoupload/version.php`, `local/ntautoupload/settings.php`, etc.
2. Visit **Site administration > Notifications**, or run
   `sudo -u www-data php admin/cli/upgrade.php`.
3. Configure it at **Site administration > Plugins > Local plugins > Automatic
   user uploads**.

## Example: National Trail settings

| Setting | Value |
| --- | --- |
| Send reports to | `bpool@nationaltrail.us` |
| Daily report time | 09:00 |
| Unknown courses to report | `^[0-9]{4}[A-Z]{2}$` (our course standard: 4 digits + 2 capital teacher initials, e.g. 0013DC) |
| Minimum rows for an unknown course | 6 |
| Default email domain | `nationaltrail.us` |
| Upload type | Add new and update existing users |
| Existing user details | Override with file |
| Import 1 | Name `Users`, file `moodleimport/NTmoodle.csv`, daily report ticked, rename **unticked** (the SIS replaces this file every night, and the nightly backup script copies it) |
| Import 2 | Name `Classes`, file `moodleimport/classlists.csv`, rename unticked (the share is read-only for www-data) |
| Import 3 | Name `IEP`, file `moodleimport/NTmoodleIEP.csv`, rename unticked |

These are stored in `/root/ntautoupload-settings.sh` on the servers. Running
that script applies them all, so a rebuilt server needs no manual setup.

With these settings the nightly user file (written at about 00:45) is imported
at 01:05 and you get its report then. On days when the SIS sends no new file
you get one "no new file today" email at the daily report time. A class or IEP
file you make at 8:30 is imported at 9:05. Because the share is read-only for
the web server, those files are not renamed; each is imported once and then
again only if its content changes. To pick files up faster, change the
task's schedule (for example minute `*/15`) under **Site administration >
Server > Scheduled tasks**.

## Running an import by hand

From **Site administration > Server > Scheduled tasks**, use "Run now" next to
"Run automatic user uploads". This respects the on/off switch and only imports
new or changed files.

Or from the command line, in the Moodle folder:

```
sudo -u www-data /usr/bin/php local/ntautoupload/cli/run.php --help
sudo -u www-data /usr/bin/php local/ntautoupload/cli/run.php              # every import, new files only
sudo -u www-data /usr/bin/php local/ntautoupload/cli/run.php --import=1   # only import 1
sudo -u www-data /usr/bin/php local/ntautoupload/cli/run.php --import=1 --force         # even if unchanged
sudo -u www-data /usr/bin/php local/ntautoupload/cli/run.php --ignore-pause --no-email  # while paused, no emails
sudo -u www-data /usr/bin/php local/ntautoupload/cli/run.php --no-email --print-report  # see the report on screen
```

The script exits with code 1 if any import failed.

## Where to look when something goes wrong

- The email report lists the rows with errors (up to 200).
- **Site administration > Server > Tasks > Task logs** shows every run. Rows
  with warnings or errors are logged; rows without problems are not, to keep
  the log short.

## Security notes

- There is no web page that runs an import. Only the scheduled task, "Run now"
  (site administrators) and the CLI script (server access) can start one.
- File paths must be inside the Moodle data directory. Absolute paths and
  paths containing `..` are refused.

## Privacy

The plugin stores no personal data of its own (it implements the null privacy
provider). Accounts, enrolments and groups it creates are stored by Moodle core.
Email reports can contain usernames of rows with errors, so send them only to
staff who are allowed to see that information.

## Running the tests

```
vendor/bin/phpunit local/ntautoupload/tests/manager_test.php
```

## License

GNU GPL v3 or later. See https://www.gnu.org/copyleft/gpl.html.
