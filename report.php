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

require_once($CFG->dirroot .
             '/mod/quiz/report/stacksheffield/classes/' .
             'question_analysis.php');

require_once($CFG->dirroot .
             '/mod/quiz/report/stacksheffield/classes/' .
             'question_attempt_analysis.php');

require_once($CFG->dirroot .
             '/mod/quiz/report/stacksheffield/classes/' .
             'question_attempt_step_analysis.php');

require_once($CFG->dirroot .
             '/mod/quiz/report/stacksheffield/classes/' .
             'chain_link.php');

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
        $this->quiz = $quiz;
        $this->cm = $cm;
        $this->course = $course;
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

        $sql = <<<SQL
SELECT q.*
FROM {question} q
JOIN (
 SELECT
  qa.questionid,
  MIN(qa.slot) AS firstslot
  FROM {$qubaids->from_question_attempts('qa')}
  WHERE {$qubaids->where()}
  GROUP BY qa.questionid
) usedquestionids 
ON q.id = usedquestionids.questionid
WHERE q.qtype = 'stack'
ORDER BY usedquestionids.firstslot

SQL;
        
        $this->stack_questions_used_in_attempt = 
          $DB->get_records_sql($sql, $qubaids->from_where_params());

        return $this->stack_questions_used_in_attempt;
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
    public function display_index() {
        global $OUTPUT;

        $baseurl = $this->get_base_url();
        echo $OUTPUT->heading(get_string('stackquestionsinthisquiz',
                                         'quiz_stacksheffield'));
        echo html_writer::tag('p', get_string('stackquestionsinthisquiz_descript',
                                              'quiz_stacksheffield'));

        echo html_writer::start_tag('ul');
        foreach ($this->stack_questions_used_in_attempt as $question) {
            echo html_writer::tag('li', html_writer::link(
                    new moodle_url($baseurl, array('questionid' => $question->id)),
                    format_string($question->name)));
        }
        echo html_writer::end_tag('ul');
    }

    /**
     * Display a bar listing all the STACK questions in the quiz.
     * This is to be used in a page giving detailed analysis of one
     * question, providing links to similar pages for the other questions.
     * @param array $questionsused the STACK questions used in this quiz.
     */

    public function display_index_bar() {
        global $OUTPUT,$PAGE;

        $currentid = $this->question->id;
        $baseurl = $this->get_base_url();
        $data = new stdClass();
        $data->question = array();

        $i = 0;
        foreach ($this->stack_questions_used_in_attempt as $question) {
            $i++;
            $x = new stdClass();
            $x->code = 'Q' . $i;
            $x->name = $question->name;
            $x->active = ($question->id == $currentid);
            $x->url = new moodle_url($baseurl, array('questionid' => $question->id));
            $data->question[] = $x;
        }

        $html = $OUTPUT->render_from_template(
            'quiz_stacksheffield/questionbar',$data);
        echo $html;
    }

    /**
     * Display a form to control various display options
     */

    public function display_table_options() {
        global $OUTPUT;

        $html = $OUTPUT->render_from_template(
            'quiz_stacksheffield/tableoptions',$this->table_options);
        echo $html;
    }

    public function display_timeline_options() {
        global $OUTPUT;

        $html = $OUTPUT->render_from_template(
            'quiz_stacksheffield/timelineoptions',$this->timeline_options);
        echo $html;
    }

    public function analyse_code() {
        $q = $this->question;
        $o = $q->options;
        $code = array();
        if (trim($o->questionvariables)) {
            $code[] = html_writer::tag('pre', htmlspecialchars($o->questionvariables));
        }
        foreach($q->prts as $p) {
            if (trim($p->feedbackvariables)) {
                $code[] = html_writer::tag('pre', htmlspecialchars($p->feedbackvariables));
            }
        }
        $this->questioncode = implode("<hr/>",$code);
    }
    
    public function analyse_data() {
        global $DB;
        $question = $this->question;
        $A = new \quiz_stacksheffield\question_analysis($question);
        $this->question_analysis = $A;

        $sql = <<<SQL
SELECT 
 d.id AS data_id,
 d.name,
 d.value,
 s.id AS step_id,
 s.sequencenumber,
 s.state,
 s.timecreated,
 b.id AS attempt_id,
 b.questionsummary AS question_note,
 b.slot,
 b.questionusageid AS question_usage_id,
 a.id AS quiz_attempt_id 
FROM mdl_question_attempt_step_data d
LEFT JOIN mdl_question_attempt_steps s ON d.attemptstepid=s.id
LEFT JOIN mdl_question_attempts b ON s.questionattemptid=b.id
LEFT JOIN mdl_quiz_attempts a ON a.uniqueid = b.questionusageid
WHERE b.questionid=:question_id
SQL;

        $dd = $DB->get_records_sql($sql,array('question_id' => $question->id));

        foreach($dd as $d) {
            $A->add_data($d);
        }

        $A->collate();

        return $A;
    }

    private function get_options() {
        $O = new stdClass();
        $O->cmid = $this->cm->id;
        $O->question_id = $this->question->id;
        
        $roworder = optional_param('roworder','',PARAM_RAW);

        if ($roworder == '') {
            $O->splitbymark = true;
            $O->splitbyanswernote = true;
            $O->splitbyanswer = false;
            $O->orderbyfreq = true;
            $O->orderbymark = false;
        } else {
            $O->splitbymark = optional_param('splitbymark',false,PARAM_BOOL);
            $O->splitbyanswernote = optional_param('splitbyanswernote',false,PARAM_BOOL);
            $O->splitbyanswer = optional_param('splitbyanswer',false,PARAM_BOOL);
            if ($roworder == 'orderbyfreq') {
                $O->orderbyfreq = 1;
                $O->orderbymark = 0;
            } else {
                $O->orderbyfreq = 0;
                $O->orderbymark = 1;
            }
        }
        
        $O->include_all     = 0;
        $O->include_initial = 0;
        $O->include_final   = 0;

        $include = optional_param('include','',PARAM_RAW);
        if ($include == 'initial') {
            $O->include = 'initial';
            $O->include_initial = 1;
        } elseif ($include == 'final') {
            $O->include = 'final';
            $O->include_final = 1;
        } else {
            $O->include = 'all';
            $O->include_all = 1;
        }
        
        $this->table_options = $O;

        $P = new stdClass();
        
        $timelinebase = optional_param('timelinebase','',PARAM_RAW);
        if ($timelinebase == 'attempt') {
            $P->base_view    = 0;
            $P->base_attempt = 1;            
        } else {
            $P->base_view    = 1;
            $P->base_attempt = 0;            
        }

        $this->timeline_options = $P;
    }
    
    /**
     * Display analysis of a particular question in this quiz.
     * @param object $question the row from the question table for the question to analyse.
     */
    public function display_analysis($question) {
        global $OUTPUT;
     
        $this->question = $question;
        get_question_options($question);
        $this->analyse_code();
        $A = $this->analyse_data();
        $this->get_options();
        
        $this->display_index_bar();

        $this->display_question_information();

        echo html_writer::start_tag('form',array('name' => 'optionsform'));

        echo $OUTPUT->heading('Response analysis', 3);

        $this->display_table_options();
            
        // Setup useful internal arrays for report generation.
        $this->inputs = array_keys($this->question->inputs);
        $this->prts = array_keys($this->question->prts);
        $this->qnotes = array();

        $table = new html_table();
        $table->attributes['class'] = 'generaltable';

        $keys = array();
        $head = array();
        if ($this->table_options->splitbymark) {
            $keys[] = 'raw_fraction';
            $head[] = 'Mark';
        }

        if ($this->table_options->splitbyanswernote) {
            $keys[] = 'note';
            $head[] = 'Answer note';
        }

        if ($this->table_options->splitbyanswer) {
            if ($this->question->is_randomised &&
                $this->question->has_note &&
                ! $this->question->is_permuted) {
                $keys[] = 'question_note';
                $head[] = 'Question note';
            }
            $keys[] = 'answer';
            $head[] = 'Answer';
        }

        $head[] = 'Examples';
        $head[] = 'Count';
        
        $table->head = $head;
        
        if ($this->table_options->include == 'initial') {
         $submissions = $A->initial_submissions_sorted;
        } elseif ($this->table_options->include == 'final') {
         $submissions = $A->final_submissions_sorted;
        } else {
         $submissions = $A->all_submissions_sorted;
        }

        // There used to be a checkbox for this.  Perhaps it should
        // be reinstated.
        if (true) {
            $submissions = array_filter($submissions, fn($s) => $s->is_marked);
        }
        
        $tree = \quiz_stacksheffield\question_analysis::make_tree($keys,$submissions);

        $flat = \quiz_stacksheffield\question_analysis::flatten_tree($keys,$tree);

        if ($this->table_options->orderbyfreq) {
            \quiz_stacksheffield\question_analysis::sort_count($flat);
        }

        $n = count($keys);

        $clickhandler = <<<JS
event.target.style.color = "#88EE88";
JS
                      ;

        $full_count = 0;
        foreach($flat as $s) { $full_count += count($s[$n]); }
 
        foreach($flat as $s) {
            $r = array();
            for ($i = 0; $i < $n; $i++) {
                $r[] = $s[$i];
            }
            $ss = $s[$n];
            $m0 = count($ss);
            
            $r[] = $this->preview_bar($ss);
            $m1 = round((100 * $m0) / $full_count);
     
            $r[] = '' . $m0 . '/' . $full_count . '=' . $m1 . '%';
            $table->data[] = $r;
        }

        echo html_writer::table($table);

        echo $OUTPUT->heading('Note chains', 3);

        $this->chain_tree($A);

        echo $OUTPUT->heading('Timelines', 3);

        $this->display_timeline_options();

        $this->timeline_div($A);

        echo html_writer::end_tag('form');  //optionsform

    }

    function chain_node_contents($x) {
        if (! $x) { return ''; }

        $n = $x->note . '<br/>';
        if ($x->seq == 1) {
            $n .= sprintf('%1.2f',$x->proportion);
        } else {
            $n .= '*' . sprintf('%1.2f',$x->immediate_proportion) .
               '=' . sprintf('%1.2f',$x->proportion);
        }

        $ss = $x->final_submissions + $x->nonfinal_submissions;
        $n .= '<br/>' . $this->preview_bar($ss);

        return $n;
    }
    
    function chain_table($A) {
        echo html_writer::table($table);        

        $table = new html_table();
        $table->attributes['class'] = 'quiv-stacksheffield-chaintable';

        $C = $A->note_chains->rows();
        
        foreach($C as $c) {
            $r = array();
            foreach($c as $x) {
                if ($x) {
                    $n = $this->chain_node_contents($x);
                    $cell = new html_table_cell($n);
                    $r[] = $cell;
                } else {
                    $cell = new html_table_cell('');
                    $cell->attributes['class'] = 'empty';
                    $r[] = $cell;
                }
            }
            $table->data[] = $r;
        }

        echo html_writer::table($table);
    }
    
    function chain_tree($A) {
        $box_width = 150;
        $box_height = 80;
        $gap_width = 50;
        $gap_height = 10;

        $A->note_chains->set_position(0,0);
        $f = $A->note_chains->flatten();
        $h = "";
        $x_max = 0;
        $y_max = 0;
        foreach($f as $u) {
            $x_max = max($x_max,$u->x);
            $y_max = max($y_max,$u->y);
            $n = $this->chain_node_contents($u);
            $u->x0 = $u->x * ($box_width + $gap_width) - $box_width;
            $u->y0 = $u->y * ($box_height + $gap_height);
            if ($u->x > 0) {
                $h .= <<<SVG
 <foreignObject x="{$u->x0}" y="{$u->y0}" width="{$box_width}" height="{$box_height}">
  <div class="quiv-stacksheffield-chaintreenode">$n</div>  
 </foreignObject>

SVG;
            }
        }

        foreach($f as $u) {
            if (! $u->sorted_children) { continue; }
            $x0 = $u->x0 + $box_width;
            $y0 = $u->y0 + $box_height / 2;
            $h .= <<<SVG
 <path d="M {$x0} {$y0} h {$gap_width}" stroke="black"/>

SVG;
            $nc = count($u->sorted_children);
            if ($nc == 1) { continue; }
            $lc = $u->sorted_children[$nc-1];
            $x0 = $u->x0 + $box_width + $gap_width/2;
            $y0 = $u->y0 + $box_height / 2;
            $y1 = $lc->y0 + $box_height / 2;
            $dy = $y1 - $y0;
            $h .= <<<SVG
 <path d="M {$x0} {$y0} v {$dy}" stroke="black"/>

SVG;
            $dx = $gap_width / 2;
            for ($i = 1; $i < $nc; $i++) {
                $c = $u->sorted_children[$i];
                $y1 = $c->y0 + $box_height / 2;
            $h .= <<<SVG
 <path d="M {$x0} {$y1} h {$dx}" stroke="black"/>

SVG;                
            }
        }

        $width  = $x_max * $box_width  + $x_max * $gap_width;
        $height = ($y_max + 1) * $box_height + $y_max * $gap_height;
        $h = <<<HTML
<svg width="$width" height="$height" class="quiv-stacksheffield-chaintree">
$h
</svg>

HTML;

        echo $h;
    }

    function timeline_div($A) {
        global $PAGE,$OUTPUT;

        $ttt = array();

        $this->get_options();

        if ($this->timeline_options->base_view) {
            $attempts = $A->attempts_by_duration;
        } else {
            $attempts = $A->attempts_by_duration_alt;
        }
        
        foreach($attempts as $a) {
            if (! $a->submissions) { continue; }
            $base = 0;
            if ($this->timeline_options->base_attempt) {
                $base = $a->initial_submission->time_offset;
            }

            $tt = array();
            foreach($a->submissions as $s) {
                $s0 = [$s->time_offset - $base,
                       (int) $s->quiz_attempt_id,
                       (int) $s->slot,
                       $s->sequence_number,
                       (float) $s->raw_fraction];
                $tt[] = $s0;
            }
            if ($tt) { $ttt[] = $tt; }
        }
        $ttts = json_encode($ttt);

        // The prefix quiz_ is appropriate here because that is what
        // is configured in mod/quiz/db/subplugins.json
        $PAGE->requires->js_call_amd(
            'quiz_stacksheffield/timeline_viewer','init');
        echo <<<HTML
  <div id="timelines_div"
   style="width:850px; height: 400px; overflow: auto;"
   data-timelines="{$ttts}">
   <svg id="timelines_svg" width="100" height="100">
   </svg>
  </div>
  <div id="timelines_msg" style="width:850px; min-height:30px;"></div>

HTML;
    }
    
    function preview_bar($ss) {
        global $OUTPUT;

        $clickhandler = <<<JS
event.target.style.color = "#88EE88";
JS
                      ;

        $t = '';
        $m0 = count($ss);
        $m = min(9,$m0);
        for ($j = 0; $j < $m; $j++) {
            $x = $ss[$j];
            $url = new \moodle_url('/mod/quiz/reviewquestion.php',
                                   array('attempt' => $x->quiz_attempt_id,
                                         'slot' => $x->slot,
                                         'step' => $x->sequence_number));
            $action = new \popup_action('click', $url, 'reviewquestion',
                                        array('height' => 450,
                                              'width' => 650));
            $link = $OUTPUT->action_link($url,$j+1,$action,
                                         array('onclick' => $clickhandler));
            
            $t .= $link . ' ';
        }

        return $t;
    }

    /*
     * This function simply prints out some useful information about the question.
     */
    private function display_question_information() {
        global $OUTPUT;

        $question = $this->question;
        $opts = $question->options;

        $edit_url = new \moodle_url('/question/question.php', array(
                'cmid' => $this->cm->id, 'id' => $question->id));
        $edit_icon = $OUTPUT->pix_icon('t/edit', '', 'moodle', array('title' => ''));
        $edit_link = html_writer::link($edit_url,$edit_icon);

        $preview_url = quiz_question_preview_url($this->quiz,$this->question);
        $preview_icon = $OUTPUT->pix_icon('t/preview',get_string('previewquestion', 'quiz'));
        $preview_action = new \popup_action('click', $preview_url, 'questionpreview',
                                            question_preview_popup_params());
        $preview_link = $OUTPUT->action_link($preview_url,$preview_icon,$preview_action);
                      
        echo $OUTPUT->heading($edit_link . ' ' .
                              $preview_link . ' ' .
                              $question->name, 3);

        
        // Display the question variables.
        echo $OUTPUT->heading(get_string('questioncode','quiz_stacksheffield'), 3);
        echo html_writer::start_tag('div', array('class' => 'questionvariables'));
        echo $this->questioncode;
        echo html_writer::end_tag('div');

        echo $OUTPUT->heading(stack_string('questiontext'), 3);
        echo html_writer::tag('div',
                              html_writer::tag('div',
                                               stack_ouput_castext($question->questiontext),
        array('class' => 'outcome generalfeedback')), array('class' => 'que'));

        if (trim($question->generalfeedback)) {
            echo $OUTPUT->heading(stack_string('generalfeedback'), 3);
            echo html_writer::tag('div',
                                  html_writer::tag('div',
                                                   stack_ouput_castext($question->generalfeedback),
                                                   array('class' => 'outcome generalfeedback')),
                                  array('class' => 'que'));
        }

        echo $OUTPUT->heading(stack_string('questionnote'), 3);
        echo html_writer::tag('div',
                              html_writer::tag('div',
                                               stack_ouput_castext($opts->questionnote),
                                               array('class' => 'outcome generalfeedback')),
                              array('class' => 'que'));

    }
}
