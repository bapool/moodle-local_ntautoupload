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
 * Thin wrapper around the core user upload process.
 *
 * Adds three things to the core "Upload users" engine:
 * - a default email address (username@domain) for rows whose email is blank or invalid;
 * - language codes such as EN_US are matched to the installed language (en_us);
 * - access to the result counters so they can be reported by email.
 *
 * @package    local_ntautoupload
 * @copyright  2026 Brian Pool, National Trail Local Schools
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class uploader extends \tool_uploaduser\process {
    /** @var string Domain used to build default email addresses, without the @. Empty means disabled. */
    protected $defaultdomain = '';

    /** @var array|null Languages users may choose, keyed by code. Loaded on first use. */
    protected $languages = null;

    /**
     * Set the domain used for default email addresses.
     *
     * @param string $domain Domain such as example.org (a leading @ is ignored). Empty disables the feature.
     * @return void
     */
    public function set_default_email_domain(string $domain): void {
        $this->defaultdomain = ltrim(trim($domain), '@');
    }

    /**
     * Prepare one CSV line, then fill in a default email address where needed.
     *
     * For an existing account with a blank or invalid email in the file, the email
     * column is ignored so the account keeps its current address. For a new account,
     * the address is set to username@domain.
     *
     * @param array $line
     * @return \stdClass|null
     */
    protected function prepare_user_record(array $line): ?\stdClass {
        global $DB;

        $user = parent::prepare_user_record($line);
        if ($user === null) {
            return null;
        }
        $this->normalise_lang($user);
        if ($this->defaultdomain === '' || $user->username === '') {
            return $user;
        }
        if (isset($user->email) && $user->email !== '' && validate_email($user->email)) {
            return $user;
        }

        $exists = $DB->record_exists('user', [
            'username' => $user->username,
            'mnethostid' => $user->mnethostid,
            'deleted' => 0,
        ]);
        if ($exists) {
            // Keep the address already stored on the account.
            unset($user->email);
        } else {
            $user->email = $user->username . '@' . $this->defaultdomain;
        }
        return $user;
    }

    /**
     * Make the language code match an allowed Moodle language, or drop it.
     *
     * Student information systems often send codes such as EN_US or en-US, which
     * Moodle does not recognise as the installed language en_us. An unrecognised
     * code made every existing account look changed on every run, so it is
     * lower-cased and, if Moodle still does not offer that language, ignored.
     *
     * @param \stdClass $user
     * @return void
     */
    protected function normalise_lang(\stdClass $user): void {
        if (!isset($user->lang) || $user->lang === '') {
            return;
        }
        if ($this->languages === null) {
            $this->languages = get_string_manager()->get_list_of_translations();
        }
        $lang = \core_text::strtolower(str_replace('-', '_', $user->lang));
        if (isset($this->languages[$lang])) {
            $user->lang = $lang;
        } else {
            unset($user->lang);
        }
    }

    /**
     * Result counters for the run.
     *
     * @return array<string, int>
     */
    public function get_counts(): array {
        return [
            'created' => (int)$this->usersnew,
            'updated' => (int)$this->usersupdated,
            'uptodate' => (int)$this->usersuptodate,
            'skipped' => (int)$this->usersskipped,
            'errors' => (int)$this->userserrors,
            'weakpasswords' => (int)$this->weakpasswords,
        ];
    }
}
