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


class question_attempt_step_analysis {
 var $question = null;
 var $step_id = null;
 var $sequence_number = null;
 var $question_note = null;
 var $perm = null;
 var $is_submission = null;
 var $is_marked = null;
 var $is_first_submission = null;
 var $is_last_submission = null;
 var $answer = null;
 var $note = null;
 var $seed = null;
 
 function __construct($question,$id,$seq,$question_note='',$perm = null) {
  $this->question = $question;
  $this->question_note = $question_note;
  $this->perm = $perm;
  $this->step_id = $id;
  $this->sequence_number = $seq;
  $this->data = array();
  $this->data_index = array();
  $this->answers_by_input = array();
  $this->notes_by_prt = array();
  $this->raw_fractions_by_prt = array();
  $this->fractions_by_prt = array();

  foreach($question->inputs as $i) {
   $n = $i->name;
   $this->answers_by_input[$n] = null;
  }

  foreach($question->prts as $p) {
   $n = $p->name;
   $this->notes_by_prt[$n] = null;
   $this->raw_fractions_by_prt[$n] = null;
   $this->fractions_by_prt[$n] = null;
  }  
 }
  
 function add_data($x) {
  $n = $x->name;
  $v = $x->value;
  $this->data[] = $x;
  $this->data_index[$n] = $v;
  if (question_analysis::strip_prefix($n,'_') === false) {
   if ($n == '-submit' && $v) {
    $this->is_submission = 1;
   }

   if (question_analysis::strip_prefix($n,'-_') === false) {
    if (array_key_exists($n,$this->answers_by_input)) {
     $this->answers_by_input[$n] = $v;
    }
   } else {
    $k = question_analysis::strip_prefix($n,'-_note_');
    if ($k !== false) {
     $this->notes_by_prt[$k] = $v;
    }
    $k = question_analysis::strip_prefix($n,'-_fraction_');
    if ($k !== false) {
     $this->fractions_by_prt[$k] = $v;
    }
    $k = question_analysis::strip_prefix($n,'-_rawfraction_');
    if ($k !== false) {
     $this->raw_fractions_by_prt[$k] = $v;
    }
   }
  }
 }

 function finalise() {
  $q = $this->question;
  $di = $this->data_index;
  
  $this->is_submission = array_key_exists('-submit',$di);   

  if (array_key_exists('_seed',$di)) {
   $this->seed = $di['_seed'];
  }
  
  $this->is_marked = true;
  
  foreach($q->prts as $prt) {
   $k = $prt->name;
   $kn = '-_note_' . $k;
   $kf = '-_fraction_' . $k;
   $kr = '-_rawfraction_' . $k;
   
   if (array_key_exists($kn,$di)) { $this->notes_by_prt[$k] = $di[$kn]; }
   if (array_key_exists($kf,$di)) { $this->fractions_by_prt[$k] = $di[$kf]; }
   if (array_key_exists($kr,$di)) {
    $this->raw_fractions_by_prt[$k] = $di[$kr];
   } else {
    $this->is_marked = false;
   }
  }

  foreach($q->inputs as $i) {
   if ($i->is_mc) {
    $answers = array();
    foreach($di as $k => $v) {
     if ($k == $i->name . '_' . $v) {
      $vi = (int) $v;
      if ($this->perm) { $vi = $this->perm[$vi]; }
      $i->num_options = max($i->num_options,$vi);
      $answers[] = $vi;
     }
    }
    sort($answers);
    $answer = implode(',',$answers);
    if ($i->type == 'checkbox') {
     $answer = '{' . $answer . '}';
    }
    $this->answers_by_input[$i->name] = $answer;
   } else {
    if (array_key_exists($i->name,$di)) {
     $this->answers_by_input[$i->name] = $di[$i->name];
    }
   }
  }
  
  $this->answer       = implode(' | ',$this->answers_by_input);
  $this->note         = implode(' | ',$this->notes_by_prt);
  $this->fraction     = implode(' | ',$this->fractions_by_prt);
  $this->raw_fraction = implode(' | ',$this->raw_fractions_by_prt);
 }
}
