<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Pattern-match question type installation code.
 *
 * @package   qtype_patternessay
 * @copyright 2013 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use qtype_patternessay\local\spell\qtype_patternessay_spell_checker;

function xmldb_qtype_patternessay_install() {
    global $CFG;

    $backends = qtype_patternessay_spell_checker::get_installed_backends();
    end($backends);
    set_config('spellchecker', key($backends), 'qtype_patternessay');
    set_config('spellcheck_languages', 'en', 'qtype_patternessay');
}
