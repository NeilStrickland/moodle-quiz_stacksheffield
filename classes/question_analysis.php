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

//defined('MOODLE_INTERNAL') || die();

/**
 *
 * @package    quiz_stacksheffield
 * @copyright  2021 Neil Strickland
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once($CFG->dirroot .
             '/mod/quiz/report/stacksheffield/classes/' .
             'chain_link.php');

class question_analysis {
 var $question = null;
 var $question_id = null;
 
 function __construct($question) {
  $this->question = $question;
  $this->question_id = $question->id;
  $this->attempts = array();
  $this->attempts_by_id = array();
  
  self::prepare_question($this->question);
 }

 function add_data($x) {
  if (isset($this->attempts_by_id[$x->attempt_id])) {
   $a = $this->attempts_by_id[$x->attempt_id];
  } else {
   if ($this->attempts) {
    $a0 = self::last_entry($this->attempts);
    $a0->finalise();
   }
   $a = new question_attempt_analysis($this->question,
                                      $x->attempt_id,
                                      $x->question_note,
                                      $x->quiz_attempt_id,
                                      $x->slot);
   $this->attempts[] = $a;
   $this->attempts_by_id[$x->attempt_id] = $a;
  }

  $a->add_data($x);
 }

 function finalise() {
  if ($this->attempts) {
   $a0 = self::last_entry($this->attempts);
   $a0->finalise();
  }
 }

 function uses_standard_mr_notes() {
  foreach ($this->attempts as $a) {
   foreach ($a->submissions as $s) {
    $n = $s->note;
    if ($n && ! ($n == 'correct' || preg_match('/^[YNyn!?]+$/',$n))) {
     return false;
    }
   }
  }  

  return true;
 }

 function convert_standard_mr_notes() {
  foreach ($this->attempts as $a) {
   foreach ($a->submissions as $s) {
    $s->corrected_option = null;
    if ($s->note) {
     for ($i = 0; $i < strlen($s->note); $i++) {
      $c = substr($s->note,$i,1);
      if ($c == '!' || $c == '?') {
       $s->corrected_option = $i;
       break;
      }
     }
     $s->note = strtr($s->note,'?!','yn');
    }
   }
  }
 }
 
 function collate() {
  if ($this->uses_standard_mr_notes()) {
   $this->convert_standard_mr_notes();
  }
  
  $this->all_submissions   = array();
  $this->initial_submissions = array();
  $this->final_submissions  = array();

  foreach ($this->attempts as $a) {
   foreach ($a->submissions as $s) {
    $this->all_submissions[] = $s;
    if ($s->is_initial_submission) {
     $this->initial_submissions[] = $s;
    }
    if ($s->is_final_submission) {
     $this->final_submissions[] = $s;
    }
   }
  }

  $this->all_submissions_sorted   = $this->all_submissions;
  $this->initial_submissions_sorted = $this->initial_submissions;
  $this->final_submissions_sorted  = $this->final_submissions;

  $comp = array('\quiz_stacksheffield\question_analysis','compare_submissions');
  
  usort($this->all_submissions_sorted  ,$comp);
  usort($this->initial_submissions_sorted,$comp);
  usort($this->final_submissions_sorted ,$comp);

  $keys = array('raw_fraction','note','answer');
  $this->all_submissions_tree = self::make_tree($keys,$this->all_submissions_sorted);
  $this->all_submissions_flat = self::flatten_tree($keys,$this->all_submissions_tree);

  $this->note_chains = new chain_link();

  foreach($this->attempts as $a) {
   $this->note_chains->add_attempt($a);
  }

  $this->note_chains->collate();
 }

 static function strip_prefix($s,$prefix) {
  $n = strlen($prefix);
  if (strlen($s) >= $n && substr($s,0,$n) === $prefix) {
   return substr($s,$n);
  } else {
   return false;
  }
 }

 static function last_entry($a) {
  $n = count($a);
  if ($n) {
   return $a[$n-1];
  } else {
   return false;
  }
 }

 static function prepare_question($question) {
  $question->is_randomised = 0;
  $question->has_note = trim($question->options->questionnote) ? 1 : 0;
  
  $mc_inputs = array();
  
  foreach($question->inputs as $i) {
   $i->is_mc = 0;
   
   if ($i->type == 'checkbox' ||
       $i->type == 'radio' ||
       $i->type == 'dropdown') {
    $i->is_mc = 1;
    $i->num_options = 0;
    $i->num_options_used = 0;
    $i->is_permuted = 0;
    $mc_inputs[] = $i;
   }
  }

  $question->is_permuted = 0;
  
  if (trim($question->options->questionnote) == '{#perm#}') {
   $question->is_permuted = 1;
   if (count($mc_inputs) == 1) {
    $mc_inputs[0]->is_permuted = 1;
   }
  }
 }
 
 static function compare_submissions($a,$b) {
  $x = strcmp($a->raw_fraction,$b->raw_fraction);
  if ($x) { return $x; }
  
  $x = strcmp($a->note,$b->note);
  if ($x) { return $x; }
 
  $x = strcmp($a->answer,$b->answer);
  if ($x) { return $x; }
  
  return intval($a->step_id) - intval($b->step_id);
 }

 static function tree_count($x) {
  if (is_array($x)) {
   $n = 0;
   foreach ($x as $y) { $n += self::tree_count($y); }
  } else {
   return 1;
  }
 }

 static function make_tree($keys,$xx) {
  if ($keys) {
   $keys0 = $keys;
   $key = array_shift($keys0);
   $xx0 = array();
   foreach($xx as $x) {
    if (! isset($xx0[$x->$key])) {
     $xx0[$x->$key] = array();
    }
    $xx0[$x->$key][] = $x;
   }
   $xx1 = array();
   foreach($xx0 as $k => $yy) {
    $xx1[$k] = self::make_tree($keys0,$yy);
   }
   return($xx1);
  } else {
   return $xx;
  }
 }

 static function flatten_tree($keys,$xx) {
  if ($keys) {
   $tt = array();
   $keys0 = $keys;
   $key = array_shift($keys0);
   foreach($xx as $k => $yy) {
    $f = function($v) use ($k) { return array_merge(array($k),$v); };
    $tt = array_merge($tt,array_map($f,self::flatten_tree($keys0,$xx[$k])));
   }
   return $tt;
  } else {
   return array(array($xx));
  }
 }

 static function compare_count($x,$y) {
  $n = count($x);
  $m = count($y);
  $a = count($x[$n-1]);
  $b = count($y[$m-1]);
  return $b - $a;
 }

 static function sort_count(&$xx) {
  usort($xx,array('\quiz_stacksheffield\question_analysis','compare_count'));
 }
}
