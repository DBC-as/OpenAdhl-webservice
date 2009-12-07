<?php
/**
 *
 * This file is part of Open Library System.
 * Copyright © 2009, Dansk Bibliotekscenter a/s,
 * Tempovej 7-11, DK-2750 Ballerup, Denmark. CVR: 15149043
 *
 * Open Library System is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Open Library System is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Open Library System.  If not, see <http://www.gnu.org/licenses/>.
*/


class z3950 {

  private $target;
  private $database;
  private $syntax;
  private $element;
  private $schema;
  private $start;
  private $step;
  private $rpn;
  private $error;
  private $errno;
  private $addinfo;
  private $hits;
  private $z_id;

	public function __construct() {
	}

	/** \brief do a z3950 rpn search
 	*
 	*/
	public function z3950_search($wait_seconds = 15) {
    $connect_opt = array();
    if ($this->z_id = yaz_connect($this->target)) {
      if ($this->databas)
        yaz_database($this->z_id, $this->database);
      yaz_sort($this->z_id, "");
      yaz_range($this->z_id, 1, 0);
      yaz_search($this->z_id, "rpn", $this->rpn);
      $wait = array("timeout" => $wait_seconds);
      yaz_wait($this->z_id, $wait);
      $this->set_error($this->z_id);
      if ($this->hits = yaz_hits($this->z_id)) {
        // 2do yaz_sort($this->z_id, $this->sort);
        yaz_syntax($this->z_id, $this->syntax);
        yaz_element($this->z_id, $this->element);
        $start = max($this->start, 1);	// need to be at least 1
        $step = min($this->step, $this->hits - $this->start + 1);	// cannot excede hits
        yaz_range($this->z_id, $start, $step);
        yaz_schema($this->z_id, $this->schema);
        yaz_present($this->z_id);
        yaz_wait($this->z_id, $wait);
        $this->set_error($this->z_id);
      }
    } else
      $this->set_error($this->z_id);
    return $this->hits;
	}

	/** \brief do a z3950 rpn search
 	*
 	*/
	public function z3950_record($no=1) {
    if (is_resource($this->z_id)) {
      return(yaz_record($this->z_id, $no, "raw"));
    } else
      return FALSE;
  }

	/** \brief set target
 	*
 	*/
	private function set_error(&$z_id) {
    $this->error = yaz_error($z_id);
    $this->errno = yaz_errno($z_id);
    $this->addinfo = yaz_addinfo($z_id);
	}

  
	/** \brief get error
 	*
 	*/
	public function get_error() {
    return $this->error;
	}

	/** \brief get error
 	*
 	*/
	public function get_errno() {
    return $this->errno;
	}

	/** \brief get addinfo
 	*
 	*/
	public function get_addinfo() {
    return $this->addinfo;
	}

	/** \brief get error_string
 	*
 	*/
	public function get_error_string() {
    return $this->error . '(' . $this->errno . ') - ' . $this->addinfo;
	}

	/** \brief set target
 	*
 	*/
	public function set_target($target) {
    $this->target = $target;
	}

	/** \brief set database
 	*
 	*/
	public function set_database($database) {
    $this->database = $database;
	}

	/** \brief set syntax
 	*
 	*/
	public function set_syntax($syntax) {
    $this->syntax = $syntax;
	}

	/** \brief set element set name
 	*
 	*/
	public function set_element($element) {
    $this->element = $element;
	}

	/** \brief set schema
 	*
 	*/
	public function set_schema($schema) {
    $this->schema = $schema;
	}

	/** \brief set start
 	*
 	*/
	public function set_start($start) {
    $this->start = $start;
	}

	/** \brief set step
 	*
 	*/
	public function set_step($step) {
    $this->step = $step;
	}

	/** \brief set rpn
 	*
 	*/
	public function set_rpn($rpn) {
    $this->rpn = $rpn;
	}

}



?>
