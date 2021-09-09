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

namespace quiz_stacksheffield;

defined('MOODLE_INTERNAL') || die();

/**
 *
 * @package    quiz_stacksheffield
 * @copyright  2021 Neil Strickland
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


class question_attempt_analysis {
 var $question = null;
 var $attempt_id = null;
 var $question_note = null;
 var $quiz_attempt_id = null;
 var $slot = null;
 var $perm = array();
 var $seed = null;
 
 function __construct($question,$id,$question_note,$quiz_attempt_id,$slot) {
  $this->question = $question;
  $this->attempt_id = $id;
  $this->question_note = $question_note;
  $this->quiz_attempt_id = $quiz_attempt_id;
  $this->slot = $slot;
  $this->steps = array();
  $this->steps_by_id = array();
  $this->submissions = array();
  $this->perm = null;
  
  if ($question->is_permuted) {
   $perm0 = explode(',',trim($question_note,' []'));
   $n = count($perm0);
   $m = 0;
   $this->perm = array();
   for ($i = 0; $i < $n; $i++) {
    $this->perm[$i+1] = $perm0[$i];
    $m = max($m,$perm0[$i]);
   }

   foreach($question->inputs as $i) {
    if ($i->is_mc && $i->is_permuted) {
     $i->num_options_used = $n;
     $i->num_options = max($i->num_options,$m);
    }
   }
  }
 }

 function add_data($x) {
  if (isset($this->steps_by_id[$x->step_id])) {
   $s = $this->steps_by_id[$x->step_id];
  } else {
   if ($this->steps) {
    $s0 = end($this->steps);
    $s0->finalise();
   }
   $s = new question_attempt_step_analysis($this->question,
                                           (int) $x->step_id,
                                           $this->question_note,
                                           $this->perm,
                                           $this->attempt_id,
                                           $this->quiz_attempt_id,
                                           $this->slot);
   $this->steps[] = $s;
   $this->steps_by_id[$x->step_id] = $s;
   $s->sequence_number = (int) $x->sequencenumber;
   $s->state = $x->state;
  }

  if ($x->name == '_seed') {
   $this->seed = (int) $x->value;
   if ($this->seed > 1) {
    $this->question->is_randomised = 1;
   }
  }
  
  $s->add_data($x);
 }

 function finalise() {
  if ($this->steps) {
   $s0 = question_analysis::last_entry($this->steps);
   $s0->finalise();
  }

  $this->submissions = array();
  foreach($this->steps as $s) {
   if ($s->is_submission) {
    $this->submissions[] = $s;
   }
  }

  if ($this->submissions) {
   $s = $this->submissions[0];
   $s->is_first_submission = true;
   $this->first_submission = $s;
   
   $s = question_analysis::last_entry($this->submissions);
   $s->is_last_submission = true;
   $this->last_submission = $s;
  } else {
   $this->first_submission = null;
   $this->last_submission = null;
  }
 }
}
