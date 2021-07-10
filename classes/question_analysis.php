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


class question_analysis {
 var $question_id = null;
 
 function __construct($id) {
  $this->question_id = $id;
  $this->attempts = array();
  $this->attempts_by_id = array();
 }

 function add_data($x) {
  if (isset($this->attempts_by_id[$x->attempt_id])) {
   $a = $this->attempts_by_id[$x->attempt_id];
  } else {
   if ($this->attempts) {
    $a0 = last_entry($this->attempts);
    $a0->finalise();
   }
   $a = new question_attempt_analysis($x->attempt_id);
   $this->attempts[] = $a;
   $this->attempts_by_id[$x->attempt_id] = $a;
  }

  $a->add_data($x);
 }

 function finalise() {
  if ($this->attempts) {
   $a0 = last_entry($this->attempts);
   $a0->finalise();
  }  
 }

 function collate() {
  $this->all_submissions   = array();
  $this->first_submissions = array();
  $this->last_submissions  = array();

  foreach ($this->attempts as $a) {
   foreach ($a->submissions as $s) {
    $this->all_submissions[] = $s;
    if ($s->is_first_submission) {
     $this->first_submissions[] = $s;
    }
    if ($s->is_last_submission) {
     $this->last_submissions[] = $s;
    }
   }
  }

  $this->all_submissions_sorted   = $this->all_submissions;
  $this->first_submissions_sorted = $this->first_submissions;
  $this->last_submissions_sorted  = $this->last_submissions;

  $comp = array('question_analysis','compare_submissions');
  
  usort($this->all_submissions_sorted  ,$comp);
  usort($this->first_submissions_sorted,$comp);
  usort($this->last_submissions_sorted ,$comp);
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

 static function compare_submissions($a,$b) {
  $x = strcmp($a->raw_fraction,$b->raw_fraction);
  if ($x) { return $x; }
  
  $x = strcmp($a->note,$b->note);
  if ($x) { return $x; }
 
  $x = strcmp($a->answer,$b->answer);
  if ($x) { return $x; }
  
  return intval($a->step_id) - intval($b->step_id);
 }
}
