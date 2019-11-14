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
 * Defines the \qtype_patternessay\test responses class.
 *
 * @package   qtype_patternessay
 * @copyright 2016 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_patternessay;
defined('MOODLE_INTERNAL') || die();
global $CFG;

require_once($CFG->dirroot . '/question/type/patternessay/question.php');

/**
 * Question type: Pattern match: Test responses class.
 *
 * Manages the test responses associated with a given question.
 *
 * @copyright 2016 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class testquestion_responses {

    /** @var \question the question the test responses relate to. */
    protected $questionobj = null;

    /**
     * @var \testquestion_response[] placeholder for the test responses associated with the
     * give question when \testquestion_controller stores an instance of \testquestion_responses
     * a handler for the question it is working wth.
     * @see testquestion_controller::__construct()
     */
    protected $responses = null;

    /** @var array lookup array linking responses to rules that match them. */
    public $rulematches = null;

    /** SQL query fragment that determines which responses should be graded and part of the
     * rule and question summary counts. */
    const SQLGRADED = 'AND expectedfraction IS NOT NULL AND gradedfraction IS NOT NULL';

    /**
     * Create an instance of this class representing a question with no saved test responses.
     * @return \testquestion_responses
     */
    public static function create() {
        return new self();
    }

    /**
     * Create an instance of this class representing the saved test responses of a given question.
     * @param \question $questionobj the quiz.
     * @see testquestion_controller::__construct()
     * @param \question the question to prepare test responses for.
     * @return \testquestion_responses
     */
    public static function create_for_question($questionobj) {
        $handler = self::create();
        $handler->questionobj = $questionobj;
        $handler->responses = self::get_responses_by_questionid($handler->questionobj->id);
        $responseids = array_keys($handler->responses);
        $handler->rulematches = self::get_rule_matches_for_responses($responseids, $handler->questionobj->id);
        return $handler;
    }

    /**
     * Get all saved test responses for a question.
     * @param int $questionid id of the question to get responses for.
     * @return \testquestion_response[]
     */
    public static function get_responses_by_questionid($questionid) {
        global $DB;
        $responses = $DB->get_records('qtype_patternessay_responses', array('questionid' => $questionid), 'id ASC');
        return self::data_to_responses($responses);
    }

    /**
     * Get only the graded test responses for a question.
     * @param int $questionid id of the question to get responses for.
     * @return \testquestion_response[]
     */
    public static function get_graded_responses_by_questionid($questionid) {
        global $DB;
        $sqlgraded = "SELECT * FROM {qtype_patternessay_responses} WHERE questionid = ? " .
                self::SQLGRADED . " ORDER BY id ASC";
        $responses = $DB->get_records_sql($sqlgraded, array('questionid' => $questionid));
        return self::data_to_responses($responses);
    }

    /**
     * Get the test responses matching the given response ids from the DB.
     * @param int[] $responseids ids of the \test_response items.
     * @return \testquestion_response[]
     */
    public static function get_responses_by_ids($responseids) {
        global $DB;
        $responses = $DB->get_records_list('qtype_patternessay_responses', 'id', $responseids, 'id ASC');
        return self::data_to_responses($responses);
    }

    /**
     * Convert the passed data to test responses.
     * @param array $data data records to convert
     * @return test_response[] array of convert records as test_response objects
     */
    public static function data_to_responses($data) {
        $responses = array();
        foreach ($data as $datarow) {
            $response = \qtype_patternessay\testquestion_response::create($datarow);
            $responses[$response->id] = $response;
        }
        return $responses;
    }

    /**
     * Convert the passed test_response objects to a data array.
     * @param array $responses test_response objects convert
     * @return array[]
     */
    public static function responses_to_data($responses) {
        $data = array();
        foreach ($responses as $response) {
            $datarow = array($response->id, $response->response, $response->expectedfraction);
            $data[] = $datarow;
        }
        return $data;
    }

    /**
     * Save given responses to the database and return feedback on number saved and duplicates.
     * @param array $responses test_response objects convert
     * @return \stdClass
     */
    public static function add_responses($responses) {
        global $DB;

        $feedback = new \stdClass();
        $feedback->duplicates = array();
        $feedback->saved = 0;
        $count = 0;
        // Loop the responses.
        foreach ($responses as $response) {
            $count++;
            // There could be matching responses in the DB. Ugly to have a DB call in a for loop.
            // but seemed best compromise since this is a rare function.
            $id = $DB->get_field_select('qtype_patternessay_responses', 'id', 'response=? AND questionid=?',
                    array($response->response, $response->questionid));
            // Check for duplicates.
            if ($id) {
                // Record duplicate response against it's number in the saved array.
                $feedback->duplicates[$count] = $response->response;
                continue;
            }

            // Save unique response.
            $DB->insert_record('qtype_patternessay_responses', $response);
            $feedback->saved++;
        }
        return $feedback;
    }

    /**
     * Helper method to update the database with the given test_response data
     * @param \test_response $response updated response object
     * @return bool
     */
    public static function update_response($response) {
        global $DB;
        return $DB->update_record('qtype_patternessay_responses', $response);
    }

    /**
     * Delete test_responses from the database with the given ids
     * @param array $responseids ids of responses to delete
     * @return bool
     */
    public static function delete_responses_by_ids ($responseids) {
        global $DB;
        $DB->delete_records_list('qtype_patternessay_r_matches', 'testresponseid', $responseids);
        return $DB->delete_records_list('qtype_patternessay_responses', 'id', $responseids);
    }

    /**
     * Calculate and return the AMATI grading statistic for the given question.
     *
     * With the AMATI approach We calculate how good a question is at grading its given human marked
     * response set correctly. The human mark tells us how many of the responses should be given
     * a mark. The computer mark tells us what the question would give each response.
     *
     * By comparing the two we can determine how many responses were:
     * 1) matched: the computer gives a mark a human would give
     * 2) missed positive: The human marked it correct, the computer marked it incorrect
     * 3) missed negative: The human marked it incorrect, the computer marked it correct
     * 4) Ungraded: The computed mark is not available because the response has not been tested.
     *
     * To calculate these statistics we query the database. There are more DB calls than we would like
     * and given that this is calculated each time the edit question form or the testquestion form is
     * shown we need the calls to be as fast and light weight as possible.
     *
     * We tried to reduce the number of calls but couldn't find a way that would actually improve
     * performance.
     *
     * To establish which grades to include in our calculations we exclude responses with either
     * a null expectedfracttion or graded fraction. This is not ideal but for a pilot and for the
     * current feature requirements this was acceptable.
     *
     * We use the constant self::SQLGRADED to keep this check in one place and easy to manage.
     * @return $counts \stdClass
     */
    public static function get_question_grade_summary_counts($question) {
        global $DB;
        $counts = new \stdClass();

        // Get graded count.
        $sqlgraded = "SELECT COUNT(1) FROM {qtype_patternessay_responses}
                WHERE questionid = ? " . self::SQLGRADED;
        $params = array('questionid' => $question->id);
        $counts->graded = $DB->count_records_sql($sqlgraded, $params);

        // Get total responses.
        $counts->total = $DB->count_records('qtype_patternessay_responses', $params);
        $counts->ungraded = $counts->total - $counts->graded;

        $params['expectedfraction'] = 1;
        $params['gradedfraction'] = 1;
        $counts->correctlymarkedright = $DB->count_records('qtype_patternessay_responses', $params);

        $params['expectedfraction'] = 0;
        $params['gradedfraction'] = 0;
        $counts->correctlymarkedwrong = $DB->count_records('qtype_patternessay_responses', $params);

        $counts->correct = $counts->correctlymarkedright + $counts->correctlymarkedwrong;

        // Get human marks v computer marks.
        // Remove expectedfraction and gradedfraction as we are using count_records_sql and excluding
        // null values.
        unset($params['expectedfraction']);
        unset($params['gradedfraction']);
        $sqlhumanmarkedwrong = $sqlgraded . " AND expectedfraction = 0 AND gradedfraction IS NOT NULL";
        $counts->humanmarkedwrong = $DB->count_records_sql($sqlhumanmarkedwrong, $params);

        $sqlhumanmarkedright = $sqlgraded . " AND expectedfraction = 1 AND gradedfraction IS NOT NULL";
        $counts->humanmarkedright = $DB->count_records_sql($sqlhumanmarkedright, $params);

        $counts->accuracy = 0;
        if ($counts->correct && $counts->graded) {
            $counts->accuracy = round($counts->correct / $counts->graded * 100);
        }
        return $counts;
    }

    /**
     * Does the given question have linked test responses?
     *
     * This method is called several times. I understand that
     * moodle db handling will cache the result and not query the
     * db excessively.
     * @return bool
     */
    public static function has_responses($question) {
        global $DB;
        if (!isset($question->id)) {
            return false;
        }

        // Get correct count.
        $params = array('questionid' => $question->id);

        // Get total responses.
        return $DB->record_exists('qtype_patternessay_responses', $params);
    }

    /**
     * Grade the given response with the given question.
     * @param test_response $response response object to grade
     * @param qtype_patternessay_question question to do the grading
     */
    public static function grade_response($response, $question) {
        list($actualmark, $notused) = $question->grade_response(array('answer' => $response->response));
        $response->set_gradedfraction($actualmark);
        self::update_response($response);
    }

    /**
     * Grade the responses with the given rule and question.
     * @param \testquestion_response[] $responses response objects to grade
     * @param \question_answer $rule Answer object containing the rule to grade with
     * @param qtype_patternessay_question question to do the grading
     */
    public static function grade_responses_by_rule($responses, $rule, $question) {
        foreach ($responses as $response) {
            $match = $question->compare_response_with_answer(array('answer' => $response->response), $rule);
            if ($match && !in_array($rule->id, $response->ruleids)) {
                $response->ruleids[] = $rule->id;
            }
        }
    }

    /**
     * Return accuracy statistics for the given rule.
     *
     * Amati uses the terms pos and neg differently for individual rules
     * compared to the statistics for the whole question.
     *
     * Amati is focused entirely on creating rules that match correct reponses. Therefore
     * amati doesn't like rules matching incorrect answers. So the statistics for the rule
     * reflect this.
     *
     * For rule pos and neg mean:
     * Pos: how many correct matches the rule makes
     * Neg: how many incorrect matches the rule makes
     * e.g. Pos = 15 Neg = 2
     *
     * The rule statistics are displayed on the question edit form along with a show
     * coverage tool. The show coverage tool lets the author see the responses the
     * statistics refer to.
     *
     * THese statistics show the author exactly how accurate each rule is so they can
     * determine how it impacts the overall question accuracy.
     * @param \test_response[]  $responses response objects to grade
     * @param int $ruleid Id of rule
     * @param array $matches lookup array matching ruleids to response ids from
     *            self::get_rule_matches_for_responses
     */
    public static function get_rule_accuracy_counts($responses, $ruleid, $matches) {
        $accuracy = array('positive' => 0, 'negative' => 0);

        $responseids = array();
        // The matches array lists the responseids that match each rule.
        // This is how we quickly determine which responses to use for
        // the calculation.
        if (array_key_exists($ruleid, $matches['ruleidstoresponseids'])) {
            $responseids = $matches['ruleidstoresponseids'][$ruleid];
        }
        foreach ($responseids as $responseid) {
            if (!array_key_exists($responseid, $responses)) {
                continue;
            }

            $response = $responses[$responseid];
            if ($response->expectedfraction) {
                // Matches human expectation.
                $accuracy['positive']++;
            } else {
                // Does not match human expectation.
                $accuracy['negative']++;
            }
        }

        return $accuracy;
    }

    /**
     * Save a record of of each match between a rule and a graded test response.
     * @param qtype_patternessay_question question to do the grading
     * @param array $responseids an array of response ids that need rule matching.
     */
    public static function save_rule_matches($question, $responseids=array()) {
        global $DB;

        $rules = $question->get_answers();
        if (empty($responseids)) {
            self::delete_rule_matches($question);
            $responses = self::get_graded_responses_by_questionid($question->id);
        } else {
            self::delete_rule_matches($question, $responseids);
            $responses = self::get_responses_by_ids($responseids);
        }
        // Grade a response and save results to the qtype_patternessay_r_matches table.
        foreach ($responses as $response) {
            // Do not re-grade responses that have not already been graded.
            if (!is_double($response->gradedfraction) || !is_double($response->expectedfraction)) {
                continue;
            }
            foreach ($rules as $aid => $rule) {
                // Do not grade responses for answers that are 'catch all' (any other answer).
                if ($rule->answer == '*') {
                    continue;
                }
                // Prevent rule matches being saved if the rule grade is 0. Amati has no
                // equivalent to a grade of 0, and saving these makes the show coverage confusing.
                if ($rule->fraction == 0) {
                    continue;
                }
                $match = false;
                $match = $question->compare_response_with_answer(
                                                    array('answer' => $response->response), $rule);
                if ($match) {
                    $rulematch = array();
                    $rulematch['answerid'] = $rule->id;
                    $rulematch['testresponseid'] = $response->id;
                    $rulematch['questionid'] = $question->id;
                    $DB->insert_record('qtype_patternessay_r_matches', (object)$rulematch);
                }
            }
        }
    }

    /**
     * Grade all responses and save rule matches for a question.
     * @param qtype_patternessay_question $question
     */
    public static function grade_responses_and_save_matches($question) {
        $responses = self::get_responses_by_questionid($question->id);
        foreach ($responses as $response) {
            self::grade_response($response, $question);
        }
        self::save_rule_matches($question);
    }

    /**
     * Method providing results for trying a rule on a response set for a question,
     * without storing anything back into the database.
     * @param qtype_patternessay_question $question
     * @param string $ruletxt
     * @param number $fraction 1 or 0.
     * @return string
     */
    public static function try_rule($question, $ruletxt, $fraction) {
        $id = 0;
        $feedback = '';
        $feedbackformat = 1;
        $answer = new \question_answer($id, $ruletxt, $fraction, '', 1);
        $expression = new \patternessay_expression($answer->answer);
        if ($expression->is_valid()) {
            $answer->answer = $expression->get_formatted_expression_string();
        } else {
            return \html_writer::div(get_string('tryrulenovalidrule', 'qtype_patternessay'));
        }
        $responses = self::get_graded_responses_by_questionid($question->id);
        if (empty($responses)) {
            return \html_writer::div(get_string('tryrulenogradedresponses', 'qtype_patternessay'));
        }
        $accuracy = array('positive' => 0, 'negative' => 0);
        $responsematches = array();
        foreach ($responses as $response) {
            if (!$question->compare_response_with_answer(array('answer' => $response->response), $answer)) {
                // Only responses that are matched by the rule need be considered further.
                continue;
            }
            // Do not grade using $question->grade_response() as this returns the grade from the
            // database. Rather use the fact that we have a match and rely on the grade from the
            // form - that is how grade_response works anyway, but without a db hit.
            if ($response->expectedfraction) {
                $accuracy['positive']++;
            } else {
                $accuracy['negative']++;
            }
            if ($response->expectedfraction == $fraction) {
                if ($response->expectedfraction) {
                    $responsematches[] = '<span>' .
                            $response->id . ': ' . $response->response .
                            '</span>';
                } else {
                    $responsematches[] = '<span>' .
                            $response->id . ': ' . $response->response .
                            '</span>';
                }
            } else {
                if ($response->expectedfraction) {
                    $responsematches[] = '<span class="qtype_patternessay-selftest-missed-negative">' .
                            $response->id . ': ' . $response->response .
                            '</span>';
                } else {
                    $responsematches[] = '<span class="qtype_patternessay-selftest-missed-positive">' .
                            $response->id . ': ' . $response->response .
                            '</span>';
                }
            }
        }
        // Prepare output.
        if (empty($responsematches)) {
            return \html_writer::div(get_string('tryrulenomatch', 'qtype_patternessay'));
        } else {
            $out = \html_writer::div(get_string('ruleaccuracylabel', 'qtype_patternessay'));
            $out .= \html_writer::div(get_string('ruleaccuracy', 'qtype_patternessay', $accuracy));
            $out .= \html_writer::div(get_string('tryrulecoverage', 'qtype_patternessay'));
            $out .= \html_writer::start_div();
            $out .= \html_writer::alist($responsematches);
            $out .= \html_writer::end_div();
            return $out;
        }
    }

    /**
     * Delete the record of each match between a rule and test response for a given question.s
     * @param qtype_patternessay_question question
     * @param array $responseids Optional array of response ids
     */
    public static function delete_rule_matches($question, $responseids=array()) {
        global $DB;
        if (empty($responseids)) {
            $DB->delete_records('qtype_patternessay_r_matches', array('questionid' => $question->id));
        } else {
            list ($sql, $params) = $DB->get_in_or_equal($responseids);
            $params[] = $question->id;
            $DB->delete_records_select('qtype_patternessay_r_matches',
                    "testresponseid $sql AND questionid = ?", $params);
        }
    }

    /**
     * Return a look up array linking the id of each response with the ids of the rules
     * that match it, and an opposite version linking each rule with the response it matches.
     *
     * @param array[] test_response $responses response objects to grade
     * @param \question_answer $rule Answer object containing the rule to grade with
     * @param qtype_patternessay_question question to do the grading
     * @return array lookup array linking responses to rules that match them
     */
    public static function get_rule_matches_for_responses($responseids, $questionid) {
        global $DB;
        $matchresponseidstoruleids = array();
        $matchruleidstoresponseids = array();
        $matches = array('responseidstoruleids' => $matchresponseidstoruleids,
                'ruleidstoresponseids' => $matchruleidstoresponseids);

        // If there are no responses return an empty matches object.
        if (!count($responseids)) {
            return $matches;
        }

        // Get the response ids for the question.
        $sql = "SELECT id, testresponseid, answerid FROM {qtype_patternessay_r_matches}
                    WHERE questionid='" . $questionid . "'
                    AND testresponseid IN(". implode(',', $responseids) . ")
                    ORDER BY testresponseid ASC";
        $data = $DB->get_records_sql($sql);

        foreach ($data as $record) {
            // Match responses to rules.
            // if the matching array hasn't be created, create it.
            if (!array_key_exists($record->testresponseid, $matchresponseidstoruleids)) {
                $matchresponseidstoruleids[$record->testresponseid] = array();
            }
            $matchresponseidtoruleid = $matchresponseidstoruleids[$record->testresponseid];
            if (!in_array($record->answerid, $matchresponseidtoruleid)) {
                $matchresponseidtoruleid[] = $record->answerid;
            }
            $matchresponseidstoruleids[$record->testresponseid] = $matchresponseidtoruleid;

            // Match rules to responses.

            // If the matching array hasn't be created, create it.
            if (!array_key_exists($record->answerid, $matchruleidstoresponseids)) {
                $matchruleidstoresponseids[$record->answerid] = array();
            }
            $matchruleidtoresponseid = $matchruleidstoresponseids[$record->answerid];
            if (!in_array($record->testresponseid, $matchruleidtoresponseid)) {
                $matchruleidtoresponseid[] = $record->testresponseid;
            }
            $matchruleidstoresponseids[$record->answerid] = $matchruleidtoresponseid;

        }

        $matches = array('responseidstoruleids' => $matchresponseidstoruleids,
                'ruleidstoresponseids' => $matchruleidstoresponseids);
        return $matches;
    }

    /**
     * Return a look up array linking the id of each response with the ids of the rules
     * that match it, and an opposite version linking each rule with the response it matches.
     *
     * @param array[] test_response $responses response objects to grade
     * @param \question_answer $rule Answer object containing the rule to grade with
     * @param qtype_patternessay_question question to do the grading
     */
    public static function get_rule_matches_from_responses($responses) {
        global $DB;
        $matchresponseidstoruleids = array();
        $matchruleidstoresponseids = array();
        $matches = array('responseidstoruleids' => $matchresponseidstoruleids,
                'ruleidstoresponseids' => $matchruleidstoresponseids);

        // If there are no responses return an empty matches object.
        if (!count($responses)) {
            return $matches;
        }

        foreach ($responses as $response) {
            if (empty($response->ruleids)) {
                continue;
            }

            // Match responses to rules.
            // if the matching array hasn't be created, create it.
            if (!array_key_exists($response->id, $matchresponseidstoruleids)) {
                $matchresponseidstoruleids[$response->id] = array();
            }

            foreach ($response->ruleids as $ruleid) {
                $matchresponseidtoruleid = $matchresponseidstoruleids[$response->id];
                if (!in_array($ruleid, $matchresponseidtoruleid)) {
                    $matchresponseidtoruleid[] = $ruleid;
                }
                $matchresponseidstoruleids[$response->id] = $matchresponseidtoruleid;

                // Match rules to responses.

                // If the matching array hasn't be created, create it.
                if (!array_key_exists($ruleid, $matchruleidstoresponseids)) {
                    $matchruleidstoresponseids[$ruleid] = array();
                }
                $matchruleidtoresponseid = $matchruleidstoresponseids[$ruleid];
                if (!in_array($response->id, $matchruleidtoresponseid)) {
                    $matchruleidtoresponseid[] = $response->id;
                }
                $matchruleidstoresponseids[$ruleid] = $matchruleidtoresponseid;
            }

        }

        $matches = array('responseidstoruleids' => $matchresponseidstoruleids,
                'ruleidstoresponseids' => $matchruleidstoresponseids);
        return $matches;
    }

    /**
     * Do any rules match a given response. Use the lookup array to find out.
     *
     * @param $rulematches array[] lookup array of response ids to rule ids.
     * @param $responseid id of the response to find matching rules for.
     * @return bool
     */
    public static function has_rule_match_for_response($rulematches, $responseid) {
        return array_key_exists($responseid, $rulematches['responseidstoruleids']);
    }

    /**
     * Link each rule that matches the given response to it's order in its related question.
     *
     * @param \qtype_patternessay\testquestion_responses $testresponsehandler object the testresponses handler
     * @param int $responseid id of the test response the rules much match
     * @retun array
     */
    public static function get_matching_rule_indexes_for_response($testresponsehandler, $responseid) {
        $ruleids = array_keys($testresponsehandler->questionobj->get_answers());
        $rulematch = $testresponsehandler->rulematches['responseidstoruleids'][$responseid];

        $matches = array();
        foreach ($rulematch as $matchid) {
            $index = array_search($matchid, $ruleids) + 1;
            if ($index != null) {
                $matches[] = $index;
            }
        }

        // Order values from low to high.
        asort($matches);
        return $matches;
    }

    /**
     * Update the ruleids field of the given responses using the matches look up
     * array
     *
     * @param array[] test_response $responses response objects to grade
     * @param \question_answer $rule Answer object containing the rule to grade with
     * @param qtype_patternessay_question question to do the grading
     */
    public static function update_responses_with_ruleids($responses, $matches) {
        $matchresponseidstoruleids = $matches['responseidstoruleids'];
        foreach ($matchresponseidstoruleids as $responseid => $ruleids) {
            if (!array_key_exists($responseid, $responses)) {
                continue;
            }
            $responses[$responseid]->ruleids = $ruleids;
        }
    }

    /**
     * Rerieve and return valid test responses from a given csv file and feedback
     * and problems that occurred.
     *
     * This is a helper method to quickly retrieve responses ready to save to the
     * database and also provide feedback to users the problems that occurred.
     *
     * This was initially developed for uploadresponses.php as part of the
     * process of uploading responses. Later the unit and behat test classes used
     * it to reduce duplication and ensure a consistent approach to loading
     * responses from csv files.
     *
     * @param string $filepath path to the file
     * @param object $question question object
     * @param int $count the number of responses to load (optional)
     * @throws coding_exception
     * @return \qtype_patternessay\testquestion_response[] string[]
     */
    public static function load_responses_from_file($filepath, $question, $count=0) {
        if (!$contents = file_get_contents($filepath)) {
            throw new coding_exception('Could not open testquestionresponses CSV file.');
        }
        $lines = str_getcsv($contents, "\n");
        $responses = array();
        $problems = array();
        $row = 0;
        foreach ($lines as $line) {
            $row += 1;
            $problem = false;
            if ($row == 1) {
                continue; // Skipping header row or comment.
            }
            if (empty($line)) {
                continue;
            }
            $data = str_getcsv($line, ',');

            if (!is_numeric($data[0])) {
                // The first column of the uploaded file should contain a human mark
                // for the response text in the next column.
                // There has been a request to allow unmarked responses within the upload file.
                // Previously this check would have placed an entry in the problems array.
                $score = null;
            } else {
                $score = (float)$data[0];
            }

            if (is_null($score) && empty($data[1])) {
                // Ignore blank rows.
                continue;
            }

            if (count($data) !== 2) {
                // We only want a human mark and an answer string. Any more elements in this
                // csv file are not wanted. Often a comma will exist within a students answer,
                // and tutors are expected to wrap those answers within speech marks for this
                // upload. See e.g. in fixtures/shortanswerquestion_webserviceresponses.csv.
                $problems[] = get_string('testquestionuploadrowhastwoitems', 'qtype_patternessay',
                                    array('row' => $row, 'items' => count($data)));
                $problem = true;
            }

            if (!$problem) {
                // Data needs to be in utf8 format. Authors using Excel will often have
                // xA0 or #160 (non-breaking spaces) present in the data, as well as other
                // extended characters allowed by iso-8859-1 encoding.
                if (function_exists('mb_detect_encoding') && !mb_detect_encoding($data[1], 'UTF-8', true)) {
                    // Convert to utf8.
                    $data[1] = \core_text::convert($data[1], mb_detect_encoding($data[1]));
                }
                $response = new \qtype_patternessay\testquestion_response();
                $response->questionid = $question->id;
                $response->response = trim($data[1]);
                $response->expectedfraction = $score;
                $responses[] = $response;
                // If we have loaded the right number of responses stop.
                if ($count && $row > $count) {
                    break;
                }
            }
        }

        return array($responses, $problems);
    }

    /**
     * Check duplicate response in test question.
     *
     * @param int $questionid The question id.
     * @param string $response The response.
     *
     * @return bool true if exist, otherwise false.
     */
    public static function check_duplicate_response($questionid, $response) {
        global $DB;

        return $DB->record_exists_select('qtype_patternessay_responses', 'response = ? AND questionid = ?',
                ['response' => $response, 'questionid' => $questionid]);
    }
}
