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


require_once("verbose_class.php");

/**
 * \brief Class for handling OCI
 *
 * Example usage:
 *
 * <?
 * require('oci_class.php');
 *
 * define(VIP_US,"user");
 * define(VIP_PW,"passwd");
 * define(VIP_DB,"dbname");
 *
 * $oci = new Oci(VIP_US, VIP_PW, VIP_DB);
 *
 * $oci->connect();
 *
 * $oci->set_query("SELECT * FROM sdi_user where EMAIL like '%dbc.dk'");
 *
 * echo "<PRE>";
 * while($data=$oci->fetch_into_assoc()) {
 *   print_r($data);
 * }

 * $oci->disconnect();
 *
 * ?>
 *
 */

class oci {

  ///////////////////////////////////////
  // PRIVATE VARIABLES DO NOT CHANGE!!!//
  ///////////////////////////////////////

  /// Value for successful connection <bool>
  var $connect;

  /// Oci statement <string>
  var $statement;

  // Bind list
  var $bind_list;

  /// SQL query <string>
  var $query;

  /// Iterator for number of rows fetched. <int>
  var $num_fetched_rows;

  /// Username for database connection <string>
  var $username;

  /// Password for database connection <string>
  var $password;

  /// Tnsname for database connection <string>
  var $database;

  /// Determines wether connection is persistent. <bool>
  var $persistent_connect = false;

  /// Contains error string. Empty if no error. <string>
  var $error;

  /// Pagination enable flag <bool>
  var $enable_pagination=false;

  /// Pagination begin <int>
  var $begin;

  /// Pagination end <int>
  var $end;

  /// Default value for end <int>
  var $end_default_val=25;

  /// Commit enabled <bool>
  var $commit_enabled=false;

  /// Set max connect retries
  var $num_connect_attempts=1;

  /// Stores updated rows number <int>
  var $num_rows;

	/// verbose object
	var $verbose;

  ////////////////////
  // PUBLIC METHODS //
  ////////////////////

 /**
  * \brief  constructor
  * @param username username for db connection OR credentials in format: user@dbname/passwd
  * @param password password for db connection
  * @param database database name (from tnsnames.ora)
  */

  function oci($username,$password="",$database="") {
	global $TRACEFILE;

	$this->verbose = new verbose($TRACEFILE, "WARNING+ERROR+FATAL");

    if($password=="" && $database=="") {


      $expl=explode("/", $username);
      $this->username=$expl[0];
      $expl=explode("@", $expl[1]);
      $this->password=$expl[0];
      $this->database=$expl[1];

    } else {
      $this->username=$username;
      $this->password=$password;
      $this->database=$database;
    }
    if (defined("OCI_NUM_CONNECT_ATTEMTS")
     && is_numeric(OCI_NUM_CONNECT_ATTEMTS)
     && OCI_NUM_CONNECT_ATTEMTS < 20)
      $this->num_connect_attempts = OCI_NUM_CONNECT_ATTEMTS;

    $this->charset = NULL;
  }

	function destructor() {
		$this->disconnect();
	}



  function cursor_open() {
    if (version_compare(PHP_VERSION,'5','>='))
       return oci_new_cursor($this->connect);
    else
       return ocinewcursor($this->connect);
  }

 /**
  * \brief Set's pagination start and end values and enables pagination flag
  * @param begin pagination (int)
  * @param end pagination (int)
  */

  function set_pagination($begin,$end) {

    if(empty($begin))
      $begin=0;

    if(empty($end))
      $end=$this->end_default_val;

    if(!is_numeric($begin) || !is_numeric($end)) {
      Die("Validation error: Only integers allowed for pagination");
    }

    $this->begin=$begin;
    $this->end=$end;

    $this->enable_pagination=true;
  }


 /**
  * \brief Sets number of attempts for connect
  * @param num_connect_attempts
  */

  function set_num_connect_attempts($num_connect_attempts) {
    return $this->num_connect_attempts=$num_connect_attempts;
  }

 /**
  * \brief sets charset
  */

  function set_charset($charset) {
    return $this->charset = $charset;
  }

 /**
  * \brief Returns number of updated rows.
  * @return int
  */

  function get_num_rows() {
    return $this->num_rows;
  }


 /**
  * \brief Check if connection is persistent
  * @return bool
  */

  function is_persistent_connect() {
    return $this->persistent_connect;
  }

 /**
  * \brief Get OCI error
  * @return string.
  */

  function get_error() {
    return $this->error;
  }

 /**
  * \brief Return OCI error-string
  */

  function get_error_string() {
    if ($this->error && is_array($this->error))
      return $this->error["code"] . ": " . $this->error["message"];
    else
      return FALSE;
  }


 /**
  * \brief Get OCI connector
  *
  * Returns OCI connecter in case the user would like to work with it (i.e. for OCI functions not supported by this wrapper class).
  *
  * @return object.
  */

  function get_connector() {
    return $this->connect;
  }


 /**
  * \brief Open new OCI connection
  * @return bool.
  */

  function connect($connect_count=-1) {

    $this->clear_OCI_error();

    if (is_resource($this->connect)) {
      // $this->verbose->log(OCI, "oci_pconnect:: " . $this->username . "@" . $this->database . " reuse connection");
      return true;
    }

    if($connect_count==-1)
      $connect_count=$this->num_connect_attempts;

    if (version_compare(PHP_VERSION,'5','>='))
       $this->connect=@oci_pconnect($this->username, $this->password, $this->database, $this->charset );
    else
       $this->connect=@ociplogon($this->username, $this->password, $this->database, $this->charset );

    if (!is_resource($this->connect)) {
      if($connect_count>1) {
        $this->verbose->log(WARNING, "oci_pconnect:: " . $this->username . "@" . $this->database . " reconnect (" . $connect_count . ") with error: " . $this->get_error_string());
        return $this->connect($connect_count-1);
      }

      $this->set_OCI_error(ocierror());
      $this->verbose->log(ERROR, "oci_pconnect:: " . $this->username . "@" . $this->database . " failed with error: " . $this->get_error_string());
      return false;
    } else {
      $this->set_OCI_error(ocierror());
      // $this->verbose->log(OCI, "oci_pconnect:: " . $this->username . "@" . $this->database . " success with no error: " . $this->get_error_string());
      return true;
    }
  }

 /**
  * \brief Enable or disable commit
  * @param bool
  */

  function commit_enable($state) {
    $this->commit_enabled=$state;
  }


 /**
  * \brief Set and parse query
  * @param query SQL query (string)
  * @return (bool)
  */

  function set_query($query) {

    $this->clear_OCI_error();
    // reset num_fetched_rows iterator and result set
    $this->num_fetched_rows=0;
    $this->result=array();

    // set query

    if($this->enable_pagination) {

      $this->query = "select *
      from (select /*+ FIRST_ROWS(10)) */
      a.*, ROWNUM rnum
      from (".$query.") a
      where ROWNUM<=".$this->end." )
      where rnum>=".$this->begin;

    } else {
      $this->query=$query;
    }

    $this->statement = ociparse($this->connect, $this->query);
    $this->set_OCI_error(ocierror($this->connect));
    if (!is_resource($this->statement))
      $this->verbose->log(ERROR, "ociparse:: failed on " . $this->query . " with error: " . $this->get_error_string());

    if(!empty($this->bind_list)) {
      foreach($this->bind_list as $k=>$v) {
        if (version_compare(PHP_VERSION,'5','>='))
          $success = oci_bind_by_name($this->statement, $v["name"], $v["value"], $v["maxlength"], $v["type"]);
        else
          $success = ocibindbyname($this->statement, $v["name"], $v["value"]);
        $this->set_OCI_error(ocierror($this->statement));
        if (!$success) {
          $this->verbose->log(ERROR, "ocibindbyname:: failed on " . $this->query . " binding " . $v["name"] . " to " . $v["value"] . "type: ". $v["type"] . " with error: " . $this->get_error_string());
          }
      }
      $this->bind_list = array();
    }


    if($this->commit_enabled) {
      $success = ociexecute($this->statement, OCI_COMMIT_ON_SUCCESS);
      if (version_compare(PHP_VERSION,'5','>='))
        $this->num_rows=oci_num_rows($this->statement);
      else
        $this->num_rows=ocirowcount($this->statement);
    } else {
      $success = ociexecute($this->statement, OCI_DEFAULT);
      if (version_compare(PHP_VERSION,'5','>='))
        $this->num_rows=oci_num_rows($this->statement);
      else
        $this->num_rows=ocirowcount($this->statement);
    }
    $this->set_OCI_error(ocierror($this->statement));

    if (!$success) {
      $this->verbose->log(ERROR, "ociexecute:: failed on " . $this->query . " with error: " . $this->get_error_string());
      return FALSE;
    }
    else {
      $this->verbose->log(OCI, "ociexecute:: " . $this->query . " success with no error: " . $this->get_error_string());
      return TRUE;
    }
  }


 /**
  * \brief Commits outstanding statements
  * @return bool
  */

  function commit() {
    if (version_compare(PHP_VERSION,'5','>='))
      return oci_commit($this->connect);
    else
      return ocicommit($this->connect);
  }


 /**
  * \brief Rollback outstanding statements
  * @return bool
  */

  function rollback() {
    return oci_rollback($this->connect);
  }


 /**
  * \brief Creates an empty OCI lob
  * @return OCI lob
  */
  function create_lob() {
    return oci_new_descriptor($this->connect, OCI_D_LOB);
  }


  function bind($name, $value, $maxlength=-1, $type=SQLT_CHR) {
    $bind_array["name"]=($name[0] == ":"? $name : ":".$name);
    $bind_array["value"]=$value;
    $bind_array["maxlength"]=$maxlength;
    $bind_array["type"]=$type;
    $this->bind_list[]=$bind_array;
  }

 /**
  * \brief Get query
  * @return string
  */

  function get_query()
  {
    return $this->query;
  }

 /**
  * \brief Fetches current data into an associative array (use while loop around this function  to get all)
  * @return array | bool
  */

  function fetch_into_assoc() {

    if (version_compare(PHP_VERSION,'5','>='))
      #$this->result=oci_fetch_assoc($this->statement);
      $this->result=oci_fetch_array($this->statement, OCI_ASSOC+OCI_RETURN_NULLS);
    else
      if(!OCIFetchInto ($this->statement, $this->result, OCI_ASSOC+OCI_RETURN_NULLS))
        return false;
    $this->set_OCI_error(ocierror($this->statement));
    $this->num_fetched_rows++;
    return $this->result;
  }


 /**
  * \brief Fetches all data into an associative array
  * @return array
  */

  function fetch_all_into_assoc() {

    if (version_compare(PHP_VERSION,'5','>='))
      #while($tmp_result=oci_fetch_assoc($this->statement)) {
      while($tmp_result=oci_fetch_array($this->statement, OCI_ASSOC+OCI_RETURN_NULLS)) {
        $this->num_fetched_rows++;
        $this->result[]=$tmp_result;
      }
    else
      while(OCIFetchInto ($this->statement, $tmp_result, OCI_ASSOC+OCI_RETURN_NULLS)) {
        $this->num_fetched_rows++;
        $this->result[]=$tmp_result;
      }
    $this->set_OCI_error(ocierror($this->statement));
    return $this->result;
  }


 /**
  * \brief Returns last number of rows fetched
  * @return int
  */

  function get_num_fetched_rows() {
    return $this->num_fetched_rows;
  }


 /**
  * \brief Closes OCI connection
  */

  function disconnect() {
    ocilogoff($this->connect);
  }

  /////////////////////
  // PRIVATE METHODS //
  /////////////////////

 /**
  * \brief Set's OCI error
  * @param oci_error Expects output from ocierror() function (array)
  */

  function set_OCI_error($OCIerror) {
    if ($OCIerror && empty($this->error))
      $this->error = $OCIerror;
  }

 /**
  * \brief Clear OCI error
  */

  function clear_OCI_error() {
    $this->error = array();
  }

 /**
  * \brief Set's connection to persistent
  */

  function set_persistent() {
    $this->persistent_connect=true;
  }

}
?>
