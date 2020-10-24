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
 * Admin settings for the patternessay question type.
 *
 * @package   qtype_patternessay
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/patternessay/classes/admin/admin_setting_spell_checker.php');
require_once($CFG->dirroot . '/question/type/patternessay/classes/admin/admin_setting_environment_check.php');
require_once($CFG->dirroot . '/question/type/patternessay/classes/admin/admin_setting_spell_check_languages.php');

$settings->add(new \qtype_patternessay\admin\qtype_patternessay_admin_setting_spell_checker('qtype_patternessay/spellchecker',
        get_string('spellcheckertype', 'qtype_patternessay'),
        get_string('spellcheckertype_desc', 'qtype_patternessay'), null, null));

$settings->add(new \qtype_patternessay\admin\qtype_patternessay_admin_setting_environment_check('qtype_patternessay_environment_check',
        get_string('environmentcheck', 'qtype_patternessay'), null));

$settings->add(new admin_setting_configtext('qtype_patternessay/amatiwsurl',
        get_string('amatiwsurl', 'qtype_patternessay'),
        get_string('amatiwsurl_desc', 'qtype_patternessay'), '', PARAM_URL));

$settings->add(new admin_setting_configtext('qtype_patternessay/minresponses',
        get_string('minresponses', 'qtype_patternessay'),
        get_string('minresponses_desc', 'qtype_patternessay'), 10, PARAM_INT));

$settings->add(new \qtype_patternessay\admin\qtype_patternessay_admin_setting_spell_check_languages('qtype_patternessay/spellcheck_languages',
        get_string('setting_installed_spell_check_dictionaries', 'qtype_patternessay'),
        get_string('setting_installed_spell_check_dictionaries_des', 'qtype_patternessay'), null, null));
