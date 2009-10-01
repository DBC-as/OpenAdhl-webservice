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

/** required for class-mapping*/
require_once("ADHL_classes.php");

// ready to go
ini_set("soap.wsdl_cache_enabled", "0");
// initialize server
$server=new adhl_server("adhl.ini");

// teseting
if( $_GET['soap'] )
  {
    $GLOBALS['HTTP_RAW_POST_DATA']='<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://oss.dbc.dk/ns/adhl"><SOAP-ENV:Body><ns1:adhlRequest><ns1:id><ns1:localId><ns1:lok>715700</ns1:lok><ns1:lid>27650341</ns1:lid></ns1:localId></ns1:id><ns1:numRecords>5</ns1:numRecords><ns1:sex/><ns1:age><ns1:maxAge>15</ns1:maxAge></ns1:age><ns1:dateInterval/><ns1:outputType>SOAP</ns1:outputType><ns1:callback/></ns1:adhlRequest></SOAP-ENV:Body></SOAP-ENV:Envelope>';
  }

// handle the request
$server->handle_request();

class adhl_server
{
  private $config;
  private $timer;
  private $verbose;

  public function __construct($inifile)
  {   

    $this->config=new inifile($inifile);
    
    if( $this->config->error )
      {
	die("Cannot read " . $inifile." Error: ".$this->config->error );
      }
    
    // initialize verbose-object for logging
    $this->verbose=new verbose($this->config->get_value("logfile", "setup"),
			       $this->config->get_value("verbose", "setup")); 

    // get section for db-access
    $db = $this->config->get_section("database");
    // set constants for database-access            
    define(VIP_US,$db["VIP_US"]);
    define(VIP_PW,$db["VIP_PW"]);
    define(VIP_DB,$db["VIP_DB"]);
    define(TABLE,$db["TABLE"]);

    // start the timer    
    $this->watch = new stopwatch("", " ", "", "%s:%01.3f");
    $this->watch->start('OpenAdhl');
  }

  public function handle_request()
  {
    if( isset($_GET["HowRU"]) )
      {
	$this->HowRU();
	return;
      }
    elseif( isset($GLOBALS['HTTP_RAW_POST_DATA']) )
      { 	
	$this->soap_request(); 
	return;
      }       
    elseif( !empty($_SERVER['QUERY_STRING']) )
      {
        $response = $this->rest_request();
	return;
      }
    else // no valid request was made; go to default client
      {
	Header( "HTTP/1.1 303 See Other" );
	Header( "Location: example.php" ); 
	exit;
	//$this->send_error();
	//return;
      }    
  }

  /**
   * Test function  
   */
  protected function HowRU()
  {
    $req=$this->config->get_section("HowRU");
    $request=new adhlRequest();

    $id->isbn=$req['isbn'];
    if(  $idarray=helpFunc::get_lid_and_lok($req['isbn']) )
      {
	$request->id = new id();
	$request->id->faust=$idarray['lid']; 
	
	$request->numRecords=$req['numRecords'];
	$request->outputType=$req['outputType'];
	
	$met=new methods();
	$response=$met->ADHLRequest($request);
      }
    else
      {
	$response = new adhlResponse();
	$response->error="Could not look up(Zsearch) isbn: ".$req['isbn'];
      }

    if( $response->error )
      die( "not so good ".$response->error );
    else
      die("gr8");    
  }


  protected function soap_request()
  {    
    global $classmap;
   
    $params = array("trace"=>true,                
		    "classmap"=>$classmap);
      try
      {
	$server = new SoapServer($this->config->get_value("wsdl","setup"),$params);

	$server->setClass('methods');

	$server->handle();  

      }
      catch( SoapFault $exception ){$this->verbose->log(FATAL,print_r($exception));};
  }

  protected function rest_request($request=null)
  {   
    if( empty($request) )
      $request = helpFunc::get_request();

    // print_r($request);
    //exit;

    $met = new methods();
    $response = $met->ADHLRequest($request);
       
    $this->handle_response($request,$response);    
  }

  protected function handle_response($request,$response)
  {
    $type = "type";
    if( !empty($request->outputType) )
      $type=strtolower($request->outputType);
    
    switch($type)
      {
      case "json":
	if( !empty($request->callback) )
	  echo $request->callback." && ".$request->callback."(".json_encode($response).")";
	else
	  echo json_encode($response);
	break;
      case "xml":
		header('Content-Type:text/xml;charset=UTF-8');
	echo xml_func::object_to_xml($response);
	//print_r($response);
	break;
      default:
	$message="Please pass a valid outputType (XML or JSON)";
	$this->send_error($message);
	break;
      }     
  }

  protected function send_error($message=null)
  {
    if( empty($message) )
      $message = xml_func::UTF8("Please give me something to look for like ?isbn=87-986036-2-0&outputType=XML");

    $response=new adhlResponse();
    $response->error=$message;
    
    header('Content-Type:text/xml;charset=UTF-8');
    echo xml_func::object_to_xml($response);
    
  }

  public function __destruct()
  {
    $this->watch->stop('OpenAdhl');
    $this->verbose->log(TIMER, $this->watch->dump());
  }
}


/** \brief class for handling request*/
class methods
{
  /** \brief member holds sql */
  private $sql;
  
  private $test;

  /** \brief The function handling the request
   * @params $adhlRequest; the request given from soapclient or derived from url-query
   * @return $adhlResponse; response to given request
  */
  public function ADHLRequest($adhlRequest)
  {
     // prepare zsearch
    global $TARGET;// from include file : targets.php
    global $search;// array holds parameters and result for Zsearch

    // TODO TARGET must be 'danbib'
    $search = &$TARGET["dfa"];


    // print_r($adhlRequest);
    //exit;

    // prepare response-object
    $response = new adhlResponse();
    // set the search array and get ids from database
    $ids=$this->set_search($adhlRequest,$search);

   

    // check for errors
    if( !$ids )
      {
	$response->error = " No results found ";
	//	$response->error=$this->sql;
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
       
    //print_r($dcarray);
    //exit;
  
    // sort the result
    $response->record = $this->sort_array($ids, $dcarray );      
   
    //  $response->error= $this->sql;

    return $response;	       
  }

  /** \brief
   *  Function sets searcharray for Zsearch and returns ids for sorting the results
   *  @param $request; the current request
   *  @param $search; the array to be set for Zsearch, given array is handled as reference
   *  @returns $ids; an array of localid's 
  */
  public function set_search($request, array &$search)
  {  
    $search["format"] = "abm";
    $search["start"]=1;    
    
    $ids = $this->get_ids($request);   

    if( empty($ids) )
      {
	return false;
      }

    if( $request->id->localid->lok )
      $search['bibkode']=$request->id->localid->lok;

    $search['bibkode']=000200;

    $search["step"]=$request->numRecords;
    
    $ccl=$this->get_ccl_from_ids($ids); 
    $search["ccl"]=$ccl;
    // do the actual z3950 search to get results in searcharray
    Zsearch($search); 

    // print_r($search);
    //exit;

    return $ids;
  }
  
  /** \brief Function gets result from database 
   *   @param $request; the current adhlRequest
   *   @returns $ids; an array of localids
   */
  private function get_ids( $request)
  {

    $db = new db();
    // pass db-object to set bind-variables
    if(! $sql=$this->get_sql($request,$db) )
      {
	return false;    
      }	
   
    //  echo $sql;
    //exit;

    $db->query($sql);
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

	foreach( $records as $record )
	  if( $record->recordId == $key )
	    {
	      $ret[]=$record;
	      break;
	    }	     
      }
    return $ret;    
  }
  

  /** \brief
   *  Format a given array of ids to common command language (ccl)
   *  @param $ids; an array of localids and locations
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
  private function get_sql( $request, db $db)
  {
    // set clauses
    $where = "\nand ";     
   
    // if isbn is set - set localid and location via z-search
     if( $request->id->isbn )
      {	
	if( $idarr = helpFunc::get_lid_and_lok($request->id->isbn) )
	  {
	    $request->id->faust=$idarr['lid'];	    
	  }
	else
	  return false;
      }

    if( $request->id->localId->lid && $request->id->localId->lok)
      {
	// bind variablese lok and lid
	$db->bind("bind_lok",$request->id->localId->lok,SQLT_INT);
	$db->bind("bind_lid",$request->id->localId->lid);

     	$where .="l1.lokalid = :bind_lid";
	$where .="\n";
	$where .="and l1.laant_pa_bibliotek = :bind_lok";
	$where .="\n";
	// do NOT select same work
	$where .="and l2.lokalid != :bind_lid";
	$where .="\n";
      } 

    elseif( $request->id->faust )
      {
	// bind variable faust
	$db->bind("faust_bind",$request->id->faust);
	// this is the easy part. libraries always use faust-number as localid

	$where .= "l1.lokalid = :faust_bind";  
	$where .= "\n";
	// do NOT select same work
	$where .= "and l2.lokalid != ':faust_bind'";
	$where .="\n";
      }
    
    else // id was not set or wrong; 
      {
	$this->sql = "ID was not set or wrong";
	return false;
      }   
    
     // filter by sex
    if( $request->sex )
      {
	// bind sex-variable
	$db->bind("sex_bind",$request->sex);
	$where .= "and l2.koen= :sex_bind\n";
      }
    // filter by minimum age
    if( $request->age->minAge )
      {
	// bind minAge-variable
	$db->bind("minAge_bind",$request->age->minAge);
	$where .= "and l2.foedt <= sysdate- :minAge_bind *365\n";
      }
    // filter by maximum age
    if( $request->age->maxAge )
      {
	// bind maxAge-variable
	$db->bind("maxAge_bind",$request->age->maxAge);	
	$where .= "and l2.foedt >= sysdate- :maxAge_bind *365\n";
      }
    // filter by minimum date
    if( $request->dateInterval->from )
      {
	// bind from-variable
	$db->bind("bind_from",$request->dateInterval->from );
	$where .= "and l2.dato > to_date(:bind_from,'YYYYMMDD')\n";
      }
    // filter by maximum date
    if( $request->dateInterval->to )
      {
	//bind to-variable
	$db->bind("bind_to",$request->dateInterval->to);
	$where .= "and l2.dato < to_date(:bind_to,'YYYYMMDD')\n";    
      }

    // finally bind numRecords-variable
    $db->bind("bind_numRecords",$request->numRecords,SQLT_INT);
    
    //set query    
    $query =
'select lid from
(select distinct l2.lokalid lid, t.count
from '.TABLE.' l1 inner join '.TABLE.' l2
on l2.laanerid = l1.laanerid'
.$where.
'inner join
(select klynge, count(*) count from '.TABLE.' group by klynge ) t
on l2.klynge = t.klynge
and t.count > 3
order by t.count desc
)
where rownum<=:bind_numRecords';


    $this->sql=$query;

    return $query;
  }
 
  /** \brief
   *  parse xml (abm-format) and set dc
   *  @param $xml; the xml to parse
   *  @return $dc; dc element set from given xml
   */
  private function get_abm_dc(&$xml)
  {

    // echo $xml;
    //exit;

    $dom = new DOMDocument('1.0', 'utf-8');
    $dom->LoadXML(utf8_encode($xml));

    $xpath = new DOMXPath($dom);
    
    $record = new record();    
       
    // identifier ( lid, lok )
    $query = "/dkabm:record/ac:identifier";
    $nodelist = $xpath->query($query);
    foreach($nodelist as $node)
      $record->recordId =  xml_func::UTF8($node->nodeValue);
    // url to bib.dk
    $query = "/dkabm:record/ac:location";
    $nodelist = $xpath->query($query);
    foreach( $nodelist as $node )
      $record->url =  xml_func::UTF8($node->nodeValue);
    // creator
    $query = "/dkabm:record/dc:creator";
    $nodelist = $xpath->query($query);
    foreach( $nodelist as $node )
      $record->creator[] =  xml_func::UTF8($node->nodeValue);
    // title
    $query = "/dkabm:record/dc:title";
    $nodelist = $xpath->query($query);
    foreach( $nodelist as $node )
      $record->title[] =  xml_func::UTF8($node->nodeValue);
    // description
    $query = "/dkabm:record/dc:description";
    $nodelist = $xpath->query($query);
    foreach( $nodelist as $node )
      $record->description[] =  xml_func::UTF8($node->nodeValue);
    // type
    $query = "/dkabm:record/dc:type";
    $nodelist = $xpath->query($query);
    foreach( $nodelist as $node )
      $record->type[] =  xml_func::UTF8($node->nodeValue);    
    
    return $record;     
    
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
  function db()
  {
    $this->oci = new oci(VIP_US,VIP_PW,VIP_DB);   
    $this->oci->connect();     
  }

  function bind($name,$value,$type=SQLT_CHR)
  {
    $this->oci->bind($name, $value, -1, $type);
  }

  function query($query)
  {
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
    //  if( $this->oci )
      $this->oci->destructor();
  }  

  /** get error from oci-class */ 
  function get_error()
  {
    return $this->oci->get_error();
  }
}

/** \brief
 *  Class holds static functions used for handling the request.
 *  only functions get_request and get_lid_and_lok is called from outside the class; 
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
	$localid = new localId();
	$localid->lok =$_GET['lok'];
	$localid->lid =$_GET['lid'];
	$request->id = new id();
	$request->id->localId=$localid;      
      }
    
    else if( $_GET['faust'] )
      {
	$request->id = new id();
	$request->id->faust=$_GET['faust'];  	
      }
    else if( $_GET['isbn'] )
      {
	if( ! $idarray=self::get_lid_and_lok($_GET['isbn']) )
	    return false;
	
	//	print_r($idarray);
	$request->id = new id();
	$request->id->faust=$idarray['lid']; 
      }
    
    // outputType
    if( $_GET['outputType'] )
      $request->outputType = $_GET['outputType'];
    else
      $request->outputType = "JSON";

    //callback
    if( $_GET['callback'] )
      $request->callback=$_GET['callback'];

    // age-interval
    if( $_GET['minAge'] || $_GET['maxAge'] )
      {
	$age =new age();
	if( $_GET['minAge'] )
	  $age->minAge=$_GET['minAge'];
	if( $_GET['maxAge'] )
	  $age->maxAge=$_GET['maxAge'];
	
	$request->age=$age;
      }
    // date-interval
    if( $_GET['from'] || $_GET['to'] )
      {
	$date=new dateInterval();
	if( $_GET['from'] )
	  $date->from=$_GET['from'];
	if( $_GET['to'] )
	  $date->to=$_GET['to'];
	
	$request->dateInterval=$date;
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
  public static function get_lid_and_lok($isbn)
  {
    global $TARGET;// from include file : targets.php
    global $search;  
    
    // TODO TARGET should be danbib
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
    
    // print_r($idarray);

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