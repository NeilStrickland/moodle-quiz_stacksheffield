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

/**
 *
 * @package    quiz_stacksheffield
 * @copyright  2021 Neil Strickland
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class chain_link {
 function __construct() {
  $this->seq = 0;
  $this->count = 0;
  $this->immediate_proportion = 1;
  $this->proportion = 1;
  $this->note = null;
  $this->notes = array();
  $this->submissions = array();
  $this->final_submissions = array();
  $this->nonfinal_submissions = array();
  $this->children = array();
 }

 function add_submission($s) {
  $n = $s->note;
  if (! $n) { $n = 'no note'; }
  if (isset($this->children[$n])) {
   $c = $this->children[$n];
  } else {
   $c = new chain_link();
   $c->seq = $this->seq + 1;
   $c->note = $n;
   $c->notes = $this->notes;
   $c->notes[] = $n;
   $this->children[$n] = $c;
  }

  $c->count++;
  $c->submissions[] = $s;
  if ($s->is_final_submission) {
   $c->final_submissions[] = $s;
  } else {
   $c->nonfinal_submissions[] = $s;
  }
  
  if ($this->seq == 0) {
   $this->count++;
  }
      
  return $c;
 }

 function add_attempt($a) {
  $c = $this;
  foreach($a->submissions as $s) {
   if ($s->is_marked) {
    $c = $c->add_submission($s);
   }
  }
 }

 function collate() {
  $cc = array();

  foreach ($this->children as $c) {
   $cc[] = $c;
   $c->immediate_proportion = $c->count / $this->count;
   $c->proportion = $this->proportion * $c->immediate_proportion;
   $c->collate();
  }

  usort($cc,array('\quiz_stacksheffield\chain_link','compare_count'));
  $this->sorted_children = $cc;
 }

 function rows() {
  if ($this->sorted_children) {
   $r = array();
   $h = $this;
   foreach($this->sorted_children as $c) {
    $u = $c->rows();
    foreach($u as $v) {
     if ($this->seq > 0) { array_unshift($v,$h); }
     $h = null;
     $r[] = $v;
    }
   }

   return $r;
  } else {
   return array(array($this));
  }
 }
 
 function set_position($x,$y) {
  $this->x = $x;
  $this->y = $y;
  $this->y_max = $y;
  $first = 1;
  foreach ($this->sorted_children as $c) {
   $this->y_max = $c->set_position($x + 1, $first ? $y : $this->y_max + 1);
   $first = 0;
  }

  return $this->y_max;
 }

 function flatten() {
  $f = array($this);
  foreach ($this->sorted_children as $c) {
   $f = array_merge($f,$c->flatten());
  }
  return $f;
 }
 
 static function compare_count($a,$b) {
  return $b->count - $a->count;
 }
}
