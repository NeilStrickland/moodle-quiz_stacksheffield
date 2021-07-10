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
 * This file defines the report class for STACK questions.
 *
 * @copyright  2021 Neil Strickland
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/report/attemptsreport.php');
require_once($CFG->dirroot . '/mod/quiz/report/statistics/report.php');
require_once($CFG->dirroot . '/question/type/stack/locallib.php');

require_once($CFG->dirroot . '/mod/quiz/report/stacksheffield/classes/question_analysis.php');

/**
 * Report subclass for the responses report to individual stack questions.
 *
 * @copyright 2021 Neil Strickland
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_stacksheffield_report extends quiz_attempts_report {

    /** @var The quiz context. */
    protected $context;

    /** @var qubaid_condition used to select the attempts to include in SQL queries. */
    protected $qubaids;

    /** @array The names of all inputs for this question.*/
    protected $inputs;

    /** @array The names of all prts for this question.*/
    protected $prts;

    /** @array The deployed questionnotes for this question.*/
    protected $qnotes;

    /** @array The attempts at this question.*/
    protected $attempts;

    public function display($quiz, $cm, $course) {
        global $CFG, $DB, $OUTPUT;

        // Initialise the required data.
        $this->mode = 'stacksheffield';
        $this->context = context_module::instance($cm->id);

        list($currentgroup, $studentsjoins, $groupstudentsjoins, $allowedjoins) =
                $this->get_students_joins($cm, $course);

        // QUIZ_GRADEAVERAGE includes all attempts, irrespective of which one determines the final grade.
        $this->qubaids = quiz_statistics_qubaids_condition($quiz->id, $allowedjoins, QUIZ_GRADEAVERAGE, true);

        $questionsused = $this->get_stack_questions_used_in_attempt($this->qubaids);

        $questionid = optional_param('questionid', 0, PARAM_INT);

        // Display the appropriate page.
        $this->print_header_and_tabs($cm, $course, $quiz);
        if (!$questionsused) {
            $this->display_no_stack_questions();

        } else if (!$questionid) {
            $this->display_index($questionsused);

        } else if (array_key_exists($questionid, $questionsused)) {
            $this->display_analysis($questionsused[$questionid]);

        } else {
            $this->display_unknown_question();
        }
    }

    /**
     * Get all the STACK questions used in all the attempts at a quiz. (Note that
     * Moodle random questions may be being used.)
     * @param qubaid_condition $qubaids the attempts of interest.
     * @return array of rows from the question table.
     */
    protected function get_stack_questions_used_in_attempt(qubaid_condition $qubaids) {
        global $DB;

        return $DB->get_records_sql("
                SELECT q.*
                  FROM {question} q
                  JOIN (
                        SELECT qa.questionid, MIN(qa.slot) AS firstslot
                          FROM {$qubaids->from_question_attempts('qa')}
                         WHERE {$qubaids->where()}
                      GROUP BY qa.questionid
                       ) usedquestionids ON q.id = usedquestionids.questionid
                 WHERE q.qtype = 'stack'
              ORDER BY usedquestionids.firstslot
                ", $qubaids->from_where_params());
    }

    /**
     * Display a message saying there are no STACK questions in this quiz.
     */
    public function display_no_stack_questions() {
        global $OUTPUT;

        echo $OUTPUT->heading(get_string('nostackquestions', 'quiz_stacksheffield'));
    }

    /**
     * Display an error if the question id is unrecognised.
     */
    public function display_unknown_question() {
        print_error('questiondoesnotexist', 'question');
    }

    /**
     * Display an index page listing all the STACK questions in the quiz,
     * with a link to get a detailed analysis of each one.
     * @param array $questionsused the STACK questions used in this quiz.
     */
    public function display_index($questionsused) {
        global $OUTPUT;

        $baseurl = $this->get_base_url();
        echo $OUTPUT->heading(get_string('stackquestionsinthisquiz', 'quiz_stacksheffield'));
        echo html_writer::tag('p', get_string('stackquestionsinthisquiz_descript', 'quiz_stacksheffield'));

        echo html_writer::start_tag('ul');
        foreach ($questionsused as $question) {
            echo html_writer::tag('li', html_writer::link(
                    new moodle_url($baseurl, array('questionid' => $question->id)),
                    format_string($question->name)));
        }
        echo html_writer::end_tag('ul');
    }

    public function analyse_data($question) {
        $A = new \quiz_stacksheffield\question_analysis($question->id);
        $this->question_analysis = $A;

        $sql = <<<SQL
SELECT 
 d.id AS data_id,
 d.name,
 d.value,
 s.id AS step_id,
 s.sequencenumber,
 s.state,
 b.id AS attempt_id
FROM mdl_question_attempt_step_data d
LEFT JOIN mdl_question_attempt_steps s ON d.attemptstepid=s.id
LEFT JOIN mdl_question_attempts b ON s.questionattemptid=b.id
WHERE b.questionid=:question_id
SQL;

        $dd = $DB->get_records_sql($sql,array('question_id' => $question_id));

        foreach($dd as $d) {
            $A->add_data($d);
        }

        $A->collate();

        return $A;
    }
    
    /**
     * Display analysis of a particular question in this quiz.
     * @param object $question the row from the question table for the question to analyse.
     */
    public function display_analysis($question) {
        get_question_options($question);
        $this->display_question_information($question);

        // Setup useful internal arrays for report generation.
        $this->inputs = array_keys($question->inputs);
        $this->prts = array_keys($question->prts);

        $this->qnotes = array();

        $a = $this->analyse_data($question);

        echo "<pre>"; var_dump($a); echo "</pre>";
        
        // Maxima analysis.
        $maxheader = array();
        $maxheader[] = "STACK input data for the question '". $question->name."'";
        $maxheader[] = new moodle_url($this->get_base_url(), array('questionid' => $question->id));
        $maxheader[] = "Data generated: ".date("Y-m-d H:i:s");
        $maximacode = $this->maxima_comment($maxheader);
        $maximacode .= "\ndisplay2d:true$\nload(\"stackreporting\")$\n";
        $maximacode .= "stackdata:[]$\n";
        $variants = array();
        foreach ($this->qnotes as $qnote) {
            $variants[] = '"'.$qnote.'"';
        }
        $inputs = array();
        foreach ($this->inputs as $input) {
            $inputs[] = $input;
        }
        $anymaximadata = false;


        // Maxima analysis at the end.
        if ($anymaximadata) {
            $maximacode .= "\n/* Reset input names */\nkill(" . implode(',', $inputs) . ")$\n";
            $maximacode .= $this->maxima_list_create($variants, 'variants');
            $maximacode .= $this->maxima_list_create($inputs, 'inputs');
            $maximacode .= "\n/* Perform the analysis. */\nstack_analysis(stackdata)$\n";
            echo html_writer::tag('h3', get_string('maximacode', 'quiz_stacksheffield'));
            echo html_writer::tag('p', get_string('offlineanalysis', 'quiz_stacksheffield'));
            $rows = count(explode("\n", $maximacode)) + 2;
            echo html_writer::tag('textarea', $maximacode,
                    array('readonly' => 'readonly', 'wrap' => 'virtual', 'rows' => $rows, 'cols' => '160'));
        }
    }


    /*
     * This function simply prints out some useful information about the question.
     */
    private function display_question_information($question) {
        global $OUTPUT;
        $opts = $question->options;

        echo $OUTPUT->heading($question->name, 3);

        // Display the question variables.
        echo $OUTPUT->heading(stack_string('questionvariables'), 3);
        echo html_writer::start_tag('div', array('class' => 'questionvariables'));
        echo  html_writer::tag('pre', htmlspecialchars($opts->questionvariables));
        echo html_writer::end_tag('div');

        echo $OUTPUT->heading(stack_string('questiontext'), 3);
        echo html_writer::tag('div', html_writer::tag('div', stack_ouput_castext($question->questiontext),
        array('class' => 'outcome generalfeedback')), array('class' => 'que'));

        echo $OUTPUT->heading(stack_string('generalfeedback'), 3);
        echo html_writer::tag('div', html_writer::tag('div', stack_ouput_castext($question->generalfeedback),
        array('class' => 'outcome generalfeedback')), array('class' => 'que'));

        echo $OUTPUT->heading(stack_string('questionnote'), 3);
        echo html_writer::tag('div', html_writer::tag('div', stack_ouput_castext($opts->questionnote),
        array('class' => 'outcome generalfeedback')), array('class' => 'que'));

        echo $OUTPUT->heading(get_string('pluginname', 'quiz_stacksheffield'), 3);
    }

    /*
     * Take an array of numbers and create an array containing %s for each column.
     */
    private function column_stats($data) {
        $rdata = array();
        foreach ($data as $anote => $a) {
            $rdata[$anote] = array_merge(array_values($a), array(array_sum($a)));
        }
        reset($data);
        $coltotal = array_fill(0, count(current($data)) + 1, 0);
        foreach ($rdata as $anote => $row) {
            foreach ($row as $key => $col) {
                $coltotal[$key] += $col;
            }
        }
        foreach ($rdata as $anote => $row) {
            foreach ($row as $key => $col) {
                if (0 != $coltotal[$key]) {
                    $rdata[$anote][$key] = round(100 * $col / $coltotal[$key], 1);
                }
            }
        }
        return $rdata;
    }


    /**
     * Takes an array of $data and a $listname and creates maxima code for a list assigned to the name $listname.
     * This splits up very long lists into reasonable size lists so as not to overflow maxima input.
     */
    private function maxima_list_create($data, $listname) {
        if (empty($data)) {
            return '';
        }

        $concatarray = array();
        $toolong = false;
        $maximacode = '';
        foreach ($data as $val) {
            $concatarray[] = $val;
            $cct = implode(',',$concatarray);
            // This ensures we don't have one entry for each differenet input, leading to impossibly long sessions.
            if (strlen($cct) > 100) {
                $toolong = true;
                $maximacode .= $listname.':append('.$listname.',['.$cct."])$\n";
                $concatarray = array();
            }
        }
        if ($toolong) {
            if (empty($concatarray)) {
                $maximacode = $listname.":[]$\n".$maximacode;
            } else {
                $maximacode = $listname.":[]$\n".$maximacode.$listname.':append('.$listname.',['.$cct."])$\n";
            }
        } else {
            $maximacode = $listname.':['.$cct."]$\n";
        }
        return $maximacode;
    }

    /**
     * Takes an array of strings and generates a formatted Maxima comment block.
     */
    private function maxima_comment($data) {
        if (empty($data)) {
            return '';
        }

        $l = 0;
        foreach ($data as $k => $h) {
            $l = max(strlen($h), $l);
        }
        $comment = str_pad('/**', $l + 3, '*') . "**/\n";
        $maximacode = $comment;
        foreach ($data as $k => $h) {
            // Warning: pad_str doesn't work here.
            $offset = substr_count($h, '&') * 4;
            $maximacode .= '/* '.$h.str_repeat(' ', $l - strlen($h) + $offset)." */\n";
        }
        $maximacode .= $comment;
        return $maximacode;
    }
}
