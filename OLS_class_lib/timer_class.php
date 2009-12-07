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


/** \brief Stopwatch for code timing
*
* $watch = new stopwatch();
* $watch->format('perl');
*
* $watch->start('a');
* $watch->start('w');
*
* $watch->stop('a');
* #$watch->start('a');
*
* echo $watch->dump();
* echo "...\n";
* $watch->format('screen');
* echo $watch->dump();
* echo "...\n";
*
* $watch->log("foo.log");
*
*/

class stopwatch {
  var $timers;				// Currently running timers
  var $sums;				// Sums of completed timers
  var $prefix;				// Prefix of Output
  var $delim;				// Delimitor of Output
  var $postfix;				// Postfix of Output
  var $format;				// Format of Output

  /**
   * \brief constructor
   * @param $prefix		Output prefix
   * @param $delim			Output delimitor
   * @param    $postfix		Output postfix remember newline
   * @param    $format		Output format ("%s => %01.6f")
   *************/
  function stopwatch($prefix = null, $delim = null, $postfix = null, $format = null) {
    $this->prefix  = $prefix;
    $this->delim   = $delim;
    $this->postfix = $postfix;
    $this->format  = $format;
    $this->timers  = Array();
    $this->sums    = Array();
    $this->start('Total');
  }

  /**
   * 	\brief start a timer
   *  @param   $s		Name of timer to start
   *  @param   $ignore (bool)	Ignore already started timer (default false)
   *************/
  function start($s, $ignore = 1) {
    if($ignore == 0 && $this->timers[$s])
      die("FATAL: Cannot start timer $s... already running");
    $this->timers[$s] = microtime();
  }

  /**
  * \brief stop a timer
   * @param    $s		Name of timer to stop
   * @param    $ignore (bool)	Ignore not running timer (default false)
   *************/
  function stop($s, $ignore = 1) {
    if($ignore == 0 && !$this->timers[$s])
      die("FATAL: Cannot stop timer $s... not running");
    list($usec_stop,  $sec_stop) = explode(" ", microtime());
    list($usec_start, $sec_start) = explode(" ", $this->timers[$s]);
    $this->timers[$s] = null;
    $this->sums[$s] += ((float)$usec_stop - (float)$usec_start) + (float)($sec_stop - $sec_start);
  }


  /**
  *  \brief splittime
   * @param    $s		Name of timer
	 * @return splittime
   *************/
  function splittime($s) {
    $add = 0;
    if($this->timers[$s]) {
      list($usec_stop,  $sec_stop) = explode(" ", microtime());
      list($usec_start, $sec_start) = explode(" ", $this->timers[$s]);
      $add = ((float)$usec_stop - (float)$usec_start) + (float)($sec_stop - $sec_start);
    }
    return($this->sums[$s] + $add);
  }

  /**
   * \brief format
   * @param    $format	name of default format (file, screen or perl);
   *************/
  function format($format) {
    if($format == "perl") {
      $this->prefix  = "{ 'url' => '" . urlencode($_SERVER["PHP_SELF"]) . "', 'ts' => " . time() .  ", ";
      $this->delim   = ", ";
      $this->postfix = " }";
      $this->format  = "'%s' => %0.6f";
    } else if($format == "file") {
      $this->prefix  = urlencode($_SERVER["REQUEST_URI"]) . ": ";
      $this->delim   = " ";
      $this->postfix = "";
      $this->format  = "%s => %0.6f";
    } else if($format == "screen") {
      $this->prefix  = "<pre>\nTimings for: " . urlencode($_SERVER["REQUEST_URI"]) . ":\n";
      $this->delim   = "\n";
      $this->postfix = "\n</pre>";
      $this->format  = "%20s => %0.6f";
    } else {
      die("FATAL: Unknown format in stopwatch");
    }
  }

  /**
   *  \brief dump all stoptimers
   *  @param $delim		delimitor
   *	@return Dump of timers;
   *************/
  function dump($delim = null) {
    foreach($this->timers as $k => $v)
      if(!is_null($v))
	$this->stop($k);

    $prefix  = $this->prefix;
    $postfix = $this->postfix;
    $format  = $this->format;
    if(is_null($delim))   $delim   = $this->delim;				// Get delimitor or constructor delimitor
    // If unset: get defalut values
    if(is_null($delim))   $delim   = "\n\t";
    if(is_null($format))  $format  = "%s => %01.6f";
    if(is_null($prefix))  $prefix  = "Timings for: " . $_SERVER['REQUEST_URI'] . (preg_match("/\n/", $delim) ? $delim : " ");
    if(is_null($postfix)) $postfix = "\n";
    if(!preg_match("/\n\$/", $postfix)) $postfix .= "\n";				// Make sure postfix ends in a newline

    $ret = Array();
    //natcasesort($keys = array_keys($this->sums));
    $keys = array_keys($this->sums);
    foreach($keys as $k)
      array_push($ret, sprintf($format, $k, $this->sums[$k]));

    return $prefix . join($delim, $ret) . $postfix;
  }

  /**
   * \brief log
   *  @param   $file		filename to log in
   *  @param   $logformat	format to use for log
   *  @return	 BOOL;
   *************/
  function log($file, $logformat = "perl") {
    $prefix  = $this->prefix;							// Backup format
    $postfix = $this->postfix;
    $format  = $this->format;
    $delim   = $this->delim;

    if(! is_null($logformat))
      $this->format($logformat);
    if($fd = fopen($file, "a")) {
      fwrite($fd, $this->dump());
      fclose($fd);
    }

    $this->prefix  = $prefix;							// Restore format
    $this->postfix = $postfix;
    $this->format  = $format;
    $this->delim   = $delim;
    return ! !$fd;								// Boolize $fd
  }
};

?>
