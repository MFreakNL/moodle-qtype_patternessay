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
 * Pattern-match question renderer class.
 *
 * @package    qtype_patternessay
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * Generates the output for pattern-match questions.
 *
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_patternessay_renderer extends qtype_renderer {
    public function formulation_and_controls(question_attempt $qa,
                                                            question_display_options $options) {

        $question = $qa->get_question();
        $currentanswer = $qa->get_last_qt_var('answer');

        if(empty($currentanswer)){
            $currentanswer = strip_tags($question->responsetemplate);
        }

        $inputname = $qa->get_qt_field_name('answer');
        $attributes = array(
            'class' => 'answerinputfield',
            'name' => $inputname,
            'id' => $inputname,
            'aria-labelledby' => $inputname . '-label'
        );

        if ($options->readonly) {
            $attributes['readonly'] = 'readonly';
        }

        $feedbackimg = '';
        if ($options->correctness) {
            $answer = $question->get_matching_answer(array('answer' => $currentanswer));
            if ($answer) {
                $fraction = $answer->fraction;
            } else {
                $fraction = 0;
            }
            $attributes['class'] .= ' '.$this->feedback_class($fraction);
            $feedbackimg = $this->feedback_image($fraction);
        }

        $htmlresponse = $question->allowsubscript || $question->allowsuperscript;
        if ($htmlresponse) {
            $editor = get_texteditor('ousupsub');
            if ($editor === false) {
                $htmlresponse = false;
            }
        }
        $questiontext = $question->format_questiontext($qa);
        $rows = $question->responsefieldlines;
        $cols = 50;
        $placeholder = false;
        if (preg_match('/__([0-9]+)x([0-9]+)__/i', $questiontext, $matches)) {
            $placeholder = $matches[0];
            $cols = $matches[1];
            $rows = $matches[2];
        } else {
            if (preg_match('/__([0-9]+)__/', $questiontext, $matches)) {
                $placeholder = $matches[0];
                $cols = $matches[1];
            } else if (preg_match('/_____+/', $questiontext, $matches)) {
                $placeholder = $matches[0];
                $cols = strlen($placeholder);
            }
        }
        $rows = round($rows * 1.1);
        $cols = round($cols * 1.1);

        if ($htmlresponse && $options->readonly) {
            $input = html_writer::tag('span', $currentanswer, $attributes) . $feedbackimg;
        } else if ($htmlresponse) {
            $attributes['rows'] = 2;
            $attributes['cols'] = $cols;
            $input = html_writer::tag('textarea', $currentanswer, $attributes) . $feedbackimg;
        } else if ($rows > 1) {
            $attributes['rows'] = $rows;
            $attributes['cols'] = $cols;
            $input = html_writer::tag('textarea', $currentanswer, $attributes) . $feedbackimg;
        } else {
            $inputattributes = array(
                'type' => 'text',
                'value' => $currentanswer
            );
            $inputattributes['size'] = $cols;
            $input = html_writer::empty_tag('input', $inputattributes + $attributes) . $feedbackimg;
        }
        if ($placeholder) {
            $inputinplace = html_writer::tag('label', get_string('answer'),
                    array('for' => $attributes['id'], 'class' => 'accesshide'));
            $inputinplace .= $input;
            $questiontext = substr_replace($questiontext, $inputinplace,
                     strpos($questiontext, $placeholder), strlen($placeholder));
        }

        $result = $this->question_tests_link($question, $options);
        $result .= html_writer::tag('div', $questiontext, array('class' => 'qtext'));

        if (!$placeholder) {
            $result .= html_writer::start_tag('div', array('class' => 'ablock', 'id' => $inputname . '-label'));
            $result .= html_writer::tag('label', get_string('answercolon', 'qtype_numerical'), array('for' => $attributes['id']));
            $result .= html_writer::tag('div', $input, array('class' => 'answer'));
            $result .= html_writer::end_tag('div');
        }

        if ($htmlresponse && !$options->readonly) {
            if ($question->allowsubscript && $question->allowsuperscript) {
                $supsub = 'both';
            } else if ($question->allowsuperscript) {
                $supsub = 'sup';
            } else if ($question->allowsubscript) {
                $supsub = 'sub';
            }
            $options = array(
                'supsub' => $supsub
            );
            $editor->use_editor($attributes['id'], $options);
        }

        if ($qa->get_state() == question_state::$invalid) {
            $result .= html_writer::nonempty_tag('div',
                    $question->get_validation_error(array('answer' => $currentanswer)),
                    array('class' => 'validationerror'));
        }

        return $result;
    }

    public function specific_feedback(question_attempt $qa) {
        $question = $qa->get_question();

        $answer = $question->get_matching_answer(array('answer' => $qa->get_last_qt_var('answer')));
        if (!$answer || !$answer->feedback) {
            return '';
        }

        return $question->format_text($answer->feedback, $answer->feedbackformat,
                $qa, 'question', 'answerfeedback', $answer->id);
    }

    public function correct_response(question_attempt $qa) {
        return '';
    }

    /**
     * Displays a link to run the question tests, if applicable.
     * @param qtype_patternessay_question $question
     * @param question_display_options $options
     * @return string HTML fragment.
     */
    protected function question_tests_link(qtype_patternessay_question $question, question_display_options $options) {
        if (!empty($options->suppressruntestslink)) {
            return '';
        }
        if (!$question->user_can_view()) {
            return '';
        }

        $link = html_writer::link(new moodle_url(
                '/question/type/patternessay/testquestion.php', array('id' => $question->id)),
                get_string('testthisquestion', 'qtype_patternessay'));

        return html_writer::tag('div', $link, array('class' => 'questiontestslink'));
    }

    public function back_to_test_question_link($qid) {
        return html_writer::tag('p', html_writer::link(
                new moodle_url('/question/type/patternessay/testquestion.php', array('id' => $qid)),
                get_string('testquestionbacklink', 'qtype_patternessay')));
    }

    public function display_feedback($feedback) {
        $html = html_writer::div(get_string('savedxresponses', 'qtype_patternessay', ($feedback->saved)));
        if (count($feedback->duplicates)) {
            $html .= html_writer::div(get_string('xresponsesduplicated', 'qtype_patternessay',
                    (count($feedback->duplicates))));
            $html .= html_writer::alist($feedback->duplicates);
        }
        if (count($feedback->problems)) {
            $html .= html_writer::div(get_string('xresponsesproblems', 'qtype_patternessay',
                    (count($feedback->problems))));
            $html .= html_writer::alist($feedback->problems);
        }
        return $html;
    }
}
