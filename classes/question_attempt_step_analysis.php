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
 var $step_id = null;
 var $is_submission = null;
 var $is_first_submission = null;
 var $is_last_submission = null;
 var $answer = null;
 var $note = null;
 
 function __construct($id) {
  $this->step_id = $id;
  $this->data = array();
  $this->data_index = array();
  $this->answers = array();
  $this->notes = array();
  $this->raw_fractions = array();
  $this->fractions = array();
  $this->notes_by_prt = array();
  $this->raw_fractions_by_prt = array();
  $this->fractions_by_prt = array();
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
    $this->answers[] = $v;
   } else {
    $k = question_analysis::strip_prefix($n,'-_note_');
    if ($k !== false) {
     $this->notes[] = $v;
     $this->notes_by_prt[$k] = $v;
    }
    $k = question_analysis::strip_prefix($n,'-_fraction_');
    if ($k !== false) {
     $this->fractions[] = $v;
     $this->fractions_by_prt[$k] = $v;
    }
    $k = question_analysis::strip_prefix($n,'-_rawfraction_');
    if ($k !== false) {
     $this->raw_fractions[] = $v;
     $this->raw_fractions_by_prt[$k] = $v;
    }
   }
  }
 }

 function finalise() {
  $this->answer       = implode(' | ',$this->answers);
  $this->note         = implode(' | ',$this->notes);
  $this->fraction     = implode(' | ',$this->fractions);
  $this->raw_fraction = implode(' | ',$this->raw_fractions);
 }
}
