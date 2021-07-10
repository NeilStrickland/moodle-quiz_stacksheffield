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
 var $attempt_id = null;
 var $seed = null;
 var $question_summary = null;
 
 function __construct($id) {
  $this->attempt_id = $id;
  $this->steps = array();
  $this->steps_by_id = array();
  $this->submissions = array();
 }

 function add_data($x) {
  if (isset($this->steps_by_id[$x->step_id])) {
   $s = $this->steps_by_id[$x->step_id];
  } else {
   if ($this->steps) {
    $s0 = end($this->steps);
    $s0->finalise();
   }
   $s = new question_attempt_step_analysis($x->step_id);
   $this->steps[] = $s;
   $this->steps_by_id[$x->step_id] = $s;
   $s->sequence_number = $x->sequencenumber;
   $s->state = $x->state;
  }

  if ($x->name == '_seed') {
   $this->seed = $x->value;
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
