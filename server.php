<?php
/** \brief
 *
 * This file is part of OpenLibrary.
 * Copyright © 2009, Dansk Bibliotekscenter a/s,
 * Tempovej 7-11, DK-2750 Ballerup, Denmark. CVR: 15149043
 *
 * OpenLibrary is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * OpenLibrary is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with OpenLibrary.  If not, see <http://www.gnu.org/licenses/>.
*/

/** includes for setup and logging */
require_once("ws_lib/inifile_class.php");
require_once("ws_lib/verbose_class.php");
require_once("ws_lib/timer_class.php");

/** include for handling xml*/
require_once("ws_lib/xml_func_class.php");

/** include for database-access */
require_once("ws_lib/oci_class.php");

/** includes for zsearch */
require_once("includes/search_func.phpi");
require_once("includes/targets.php");

// get ini-file 
define(INIFILE, "adhl.ini");
$config = new inifile(INIFILE);

// set constants for soap request
define(WSDL, $config->get_value("wsdl", "setup"));
define(CLASSES,$config->get_value("classes","setup"));

/**  include for soap-classes */
require_once(CLASSES);

// set constants for database-access            
define(VIP_US,$config->get_value("VIP_US","setup"));
define(VIP_PW,$config->get_value("VIP_PW","setup"));
define(VIP_DB,$config->get_value("VIP_DB","setup"));
define(TABLE,$config->get_value("TABLE","setup"));

// prepare log-object
$verbose = new verbose($config->get_value("logfile", "setup"), 
                       $config->get_value("verbose", "setup"));

// prepare timer-object
$watch = new stopwatch("", " ", "", "%s:%01.3f");
// ready to go

// check if request comes as postdata -> handle request as soap
if( isset($HTTP_RAW_POST_DATA) )
  {
    // start the timer
    $watch->start('adhl:SOAP');

    // prepare soap-server
    $wsdlpath=WSDL;
    $params = array(
		    "trace"=>true,                
		    "classmap"=>$classmap);

    try
      {
	$server = new SoapServer($wsdlpath,$params);
	$server->setClass('methods');
	$server->handle();  
      }
    catch( SoapFault $exception ){$verbose->log(FATAL,print_r($exception));};
    
    // log the timer
    $watch->stop('adhl:SOAP');
    $verbose->log(TIMER, $watch->dump());
  }

// check if request is REST -> handle request according to chosen outputtype
else
  {  
    // start the timer
    $watch->start('adhl:XML and JSON');
    
    $request = helpFunc::get_request();

    $met = new methods();
    $response= $met->ADHLRequest($request);  
    switch( $request->outputType )
      {
      case "JSON":
	echo json_encode($response);
	
	// log the timer
	$watch->stop('adhl:XML and JSON');
	$verbose->log(TIMER, $watch->dump());

	break;
      case "XML":
	$result=xml_func::object_to_xml($response);
	header('Content-Type:text/xml;charset=UTF-8');
	echo $result;

	// log the timer
	$watch->stop('adhl:XML and JSON');
	$verbose->log(TIMER, $watch->dump());

	break;
      default:
	echo "Please give correct outputType: 'JSON' or 'XML'";

	// log the timer
	$watch->stop('adhl:XML and JSON');
	$verbose->log(TIMER, $watch->dump());

	break;
      }
  }

/** \brief class for handling request*/
class methods
{
  /** \brief member holds sql */
  private static $sql;

  /** \brief The function handling the request
   * @params $adhlRequest; the request given from soapclient or derived from url-query
   * @return $adhlResponse; response to given request
  */
  public function ADHLRequest(adhlRequest $adhlRequest)
  {
     // prepare zsearch
    global $TARGET;// from include file : targets.php
    global $search;// array holds parameters and result for Zsearch

    $search = &$TARGET["dfa"];

    // prepare response-object
    $response = new adhlResponse();
    // set the search array and get ids from database
    $ids=$this->set_search($adhlRequest,$search);

    // check for errors
    if( !$ids )
      {
	$response->error = " No results found ";
	return $response;
      }

     if( $search["error"] )
      {
	// TODO log
	$response->error=$search["error"];
	return $response;
	}

    // get result as array
    $dcarray=array();
    if( is_array($search["records"]) )
      foreach( $search["records"] as $rec )
	  $dcarray[]=$this->get_abm_dc($rec["record"]);
         
    // sort the result
    $response->dc = $this->sort_array($ids, $dcarray );    

    return $response;	       
  }

  /** \brief
   *  Function sets searcharray for Zsearch and returns ids for sorting the results
   *  @param $request; the current request
   *  @param $search; the array to be set for Zsearch, given array is handled as reference
   *  @returns $ids; an array of localid's 
  */
  public function set_search(adhlRequest $request, array &$search)
  {  
    $search["format"] = "abm";
    $search["start"]=1;    
    
    $ids = $this->get_ids($request);   

    if( !$ids )
      return false;

    if( $request->id->local->lok )
      $search['bibkode']=$request->id->local->lok;

    $search["step"]=$request->numRecords;
    
    $ccl=$this->get_ccl_from_ids($ids); 
    $search["ccl"]=$ccl;
    
    Zsearch($search); 

    return $ids;
  }
  
  /** \brief Function gets result from database 
   *   @param $request; the current adhlRequest
   *   @returns $ids; an array of localids
   */
  private function get_ids(adhlRequest $request)
  {
    if(! $this->sql = $this->get_sql($request) )
	return false;    

    $db = new db($this->sql);    
    while( $row = $db->get_row() )
      {	
	$res['lid']=$row["LID"];
	//$res['lok']=$row["LOK"];
	$ids[]=$res;
      }

    return $ids;
  }
  
  /**\brief  Function sorts result from Zsearch
   *   @param $records; an array holding the records retrieved from Zsearch
   *   @param $ids; an array of localids from databasesearch - holding the sortorder
      
   */
  private function sort_array($ids, $records)
  {
    $ret=array();
    foreach( $ids as $id )
      {
	// if lok begins with '7';replace lok for 'folkebiblioteker' with lok for DBC(870970) and THEN do the sorting
	if(  preg_match('/^7/', $id['lok']) || !isset($id['lok']) )
	  $key=$id['lid'].'|'.'870970';
	else
	  $key = $id['lid'].'|'.$id['lok'];

	foreach( $records as $dc )
	  if( $dc->id == $key )
	    {
	      $ret[]=$dc;
	      break;
	    }	     
      }
    return $ret;    
  }
  

  /** \brief
   *  Format a given array of ids to common command language (ccl)
   *  @param $ids; an array of localids
   *  @return $ccl; ccl for zsearch
   */
  private function get_ccl_from_ids($ids)
  {
    foreach( $ids as $id )
      {
	$ccl .= '(lid='.$id["lid"].')';
	$ccl.=" or ";
      }
    // remove the last 'or' (and whitespace)
    $ccl = substr($ccl,0,-3);
    
    return $ccl;
  }
  
 
  /** \brief
   *  Get sql from given request.
   *  @param $adhlRequest; current request
   *  @return $sql; sql formatted from given request
   */
  private function get_sql($request)
  {
    // set where clause
    $where = "where ";
    
   if( $request->id->local->lid && $request->id->local->lok)
      {
     	$where .="l1.lokalid = '".$request->id->local->lid."'";
	$where .="\n";
	$where .='and l1.laant_pa_bibliotek = '.$request->id->local->lok;
      } 

    else if( $request->id->faust )
      {
	// this is the easy part. libraries always use faust-number as localid
	$where .= 'l1.lokalid = '.$request->id->faust;
      }
    else // id was not set or wrong; 
      {
	$this->sql = "ID was not set or wrong";
	return false;
	}
    
    // set and clause
     
    // filter by sex
    if( $request->sex )
      $and .= "and l2.koen='".$request->sex."'\n";
    // filter by minimum age
    if( $request->age->minAge )
      $and .= "and l2.foedt <= sysdate-".$request->age->minAge."*365\n";
    // filter by maximum age
    if( $request->age->maxAge )
      $and .= "and l2.foedt >= sysdate-".$request->age->maxAge."*365\n";
    // filter by minimum date
    if( $request->dateinterval->from )
      $and .= "and l2.dato >to_date('".$request->dateinterval->from."','dd/mon/yy')\n";
    // filter by maximum date
    if( $request->dateinterval->to )
      $and .= "and l2.dato < to_date('".$request->dateinterval->to."','dd/mon/yy')\n";    

    //set query    
    $query =
'select /* first_rows_10 */ lid from
(
select /* first_rows_10 */ distinct l2.lokalid lid, l2.laan_i_klynge
from '.TABLE.' l2 inner join '.TABLE.' l1 on l1.laanerid=l2.laanerid
'.$where.((strlen($and)>7)?"\n".$and:"\n").'order by l2.laan_i_klynge desc
)
where rownum<='.$request->numRecords;

    self::$sql=$query;
    // return query
    return $query;
  }
 
  /** \brief
   *  parse xml (abm-format) and set dc
   *  @param $xml; the xml to parse
   *  @return $dc; dc element set from given xml
   */
  private function get_abm_dc(&$xml)
  {

    $dom = new DOMDocument('1.0', 'utf-8');
    $dom->LoadXML(utf8_encode($xml));

    $xpath = new DOMXPath($dom);
    
    $dc = new dc();    
       
    // identifier ( lid, lok )
    $query = "/dkabm:record/ac:identifier";
    $nodelist = $xpath->query($query);
    foreach($nodelist as $node)
      $dc->id =  xml_func::UTF8($node->nodeValue);
    // url to bib.dk
    $query = "/dkabm:record/ac:location";
    $nodelist = $xpath->query($query);
    foreach( $nodelist as $node )
      $dc->url =  xml_func::UTF8($node->nodeValue);
    // creator
    $query = "/dkabm:record/dc:creator";
    $nodelist = $xpath->query($query);
    foreach( $nodelist as $node )
      $dc->creator[] =  xml_func::UTF8($node->nodeValue);
    // title
    $query = "/dkabm:record/dc:title";
    $nodelist = $xpath->query($query);
    foreach( $nodelist as $node )
      $dc->title[] =  xml_func::UTF8($node->nodeValue);
    // description
    $query = "/dkabm:record/dc:description";
    $nodelist = $xpath->query($query);
    foreach( $nodelist as $node )
      $dc->description[] =  xml_func::UTF8($node->nodeValue);
    // type
    $query = "/dkabm:record/dc:type";
    $nodelist = $xpath->query($query);
    foreach( $nodelist as $node )
      $dc->type[] =  xml_func::UTF8($node->nodeValue);    
    
    return $dc;     
    
  }
}


/* \brief  wrapper for oci_class
 *  handles database transactions
*/
class db
{
  // member to hold instance of oci_class
  private $oci;
  // constructor
  function db($query)
  {
    $this->oci = new oci(VIP_US,VIP_PW,VIP_DB);
    $this->oci->connect();    
    $this->oci->set_query($query);   
  }

  /** return one row from db */
  function get_row()
  {
    return $this->oci->fetch_into_assoc();
  }
  
  /** destructor; disconnect from database */
  function __destruct()
  {
    $this->oci->disconnect();
  }  

  /** get error from oci-class */ 
  function get_error()
  {
    return $this->oci->get_error();
  }
}

/** \brief
 *  Class holds static functions used for handling the request.
 *  only function get_request is called from outside the class; 
 *  other functions are private
*/
class helpFunc
{
/** make an adhlRequest-object from url-parameters*/
 public static function get_request()
  {

    $request = new adhlRequest();
    // lok and lid
    if( $_GET['lok'] && $_GET['lid'] )
      {
	$localid = new localid();
	$localid->lok =$_GET['lok'];
	$localid->lid =$_GET['lid'];
	$request->id = new idtype();
	$request->id->local=$localid;      
      }
    
    else if( $_GET['faust'] )
      {
	$request->id = new idtype();
	$request->id->faust=$_GET['faust'];  	
      }
    else if( $_GET['isbn'] )
      {
	// TODO handle isbn.. lookup equivalent faust-number from somewhere
	if( ! $idarray=self::get_lid_and_lok($_GET['isbn']) )
	    return false;
	
	//	print_r($idarray);
	$request->id = new idtype();
	$request->id->faust=$idarray['lid']; 
      }
    
    // outputType
    if( $_GET['outputType'] )
      $request->outputType = $_GET['outputType'];
    else
      $request->outputType = "JSON";
    // age-interval
    if( $_GET['minAge'] || $_GET['maxAge'] )
      {
	$age =new ageType();
	if( $_GET['minAge'] )
	  $age->minAge=$_GET['minAge'];
	if( $_GET['maxAge'] )
	  $age->maxAge=$_GET['maxAge'];
	
	$request->age=$age;
      }
    // date-interval
    if( $_GET['from'] || $_GET['to'] )
      {
	$date=new interval();
	if( $_GET['from'] )
	  $date->from=$_GET['from'];
	if( $_GET['to'] )
	  $date->to=$_GET['to'];
	
	$request->dateinterval=$date;
      }
    // sex
    if( $_GET['sex'] )
      {
	$request->sex=$_GET['sex'];      
      }  
     
    //number of records
    if( $_GET['numRecords'] )
      $request->numRecords = $_GET['numRecords'];
    else
      $request->numRecords=5;
       
    return $request;

  }
  
 /** \brief  lookup localid and location from given isbn via Zsearch 
  *   @param $isbn; the isbn-number to look for
  *   @returns $idarray; an array with ONE element (lid,lok)
  */
  private static function get_lid_and_lok($isbn)
  {
    global $TARGET;// from include file : targets.php
    global $search;  
    
    // TODO there is no need to get a full 'abm' post.
    // check if there is a way to get the identifier only
    $search = &$TARGET["dfa"];
    $search["format"] = "abm";
    $search["start"]=1;
 
    $ccl='is ='.$isbn;
    
    $search["ccl"]=$ccl;
    
    Zsearch($search); 
    
    // remember to unset rpn as $search is a global array and is used elsewhere
    unset($search['rpn']);

    if( is_array($search["records"]) ) //  grab the first record if more are found   
      {
	if( !$idarray=self::get_identifier($search["records"][1]["record"]) )
	  return false;
	
      }
    else // no record found; return false; 
      return false; 
    
    return $idarray;
  }
  
  /** \brief  get ad:identifier from given xml(abm-format)
   *   @param $xml; the abm-record from Zsearch
   *   @returns $idarray; an array with ONE element (lok,lid)     
  */
  private static function get_identifier(&$xml)
  {
    // echo $xml;
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->LoadXML(utf8_encode($xml));
    $xpath = new DOMXPath($dom);
    $query = "/dkabm:record/ac:identifier";
    
    $nodelist = $xpath->query($query);
    
    if( $nodelist->length < 1 )
      return false;
    
    $idarray = array();
    $parts=explode('|',$nodelist->item(0)->nodeValue);
    
    // both lok and lid must be set
    if( ! isset($parts[0]) || ! isset($parts[1]) )
      return false;
    
    $idarray["lok"]=$parts[1];
    $idarray["lid"]=$parts[0];
    
    return $idarray;  
  }
  
}


// this function replaces load_lang_tab function in search_func.
// it is only called when an error occurs - that is if zsearch is
// not properly setup or fails for some reason;

// log errors
function load_lang_tab()
{
  // TODO do error-logging
}




?>