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

/** include for handling xml*/
require_once("OLS_class_lib/xml_func_class.php");

/** include for oci database-access */
require_once("OLS_class_lib/oci_class.php");

/** include for postgres database-access */
require_once("OLS_class_lib/pg_database_class.php");

/** includes for zsearch */
require_once("includes/search_func.phpi");
require_once("includes/targets.php");

/** include for webservice server */
require_once("OLS_class_lib/webServiceServer_class.php");

/** include for database-access */
require_once("OLS_class_lib/oci_class.php");

/** include for caching */
//require_once("OLS_class_lib/cache_client_class.php");


class adhl_server extends webServiceServer
{  
  public function __construct($inifile)
  {
    parent::__construct($inifile);
    $this->watch->start("openadhl");
  }
  
  public function ADHLRequest($params)
  {
    $defines=$this->config->get_section("database");
    define(VIP_US,$defines['VIP_US']);
    define(TABLE,$defines['TABLE']);
    define(VIP_PW,$defines['VIP_PW']);
    define(VIP_DB,$defines['VIP_DB']);
  
    $records=$this->records($params);

    // prepare response-object
    $response_xmlobj->adhlResponse->_namespace="http://oss.dbc.dk/ns/adhl";

//    $response_xmlobj->adhlResponse->_value->pure_sql->_value=methods::$pure_sql;
    
    foreach( $records as $record )
      $response_xmlobj->adhlResponse->_value->record[]=$record;

    return $response_xmlobj;
  }

 
  /** \brief Echos config-settings
   *
   */
  public function show_info() 
  {
    echo "<pre>";
    echo "version             " . $this->config->get_value("version", "setup") . "<br/>";
    echo "log                 " . $this->config->get_value("logfile", "setup") . "<br/>";
    echo "</pre>";
    die();
  }
  

  private function records($params)
  {
    $methods=new methods();
    $records=$methods->ADHLRequest($params,$this->watch);

    return $records;
  }

  public function __destruct()
  {
    $this->watch->stop("openadhl");
    verbose::log(TIMER, $this->watch->dump());
  }
  
}

// initialize server
$server=new adhl_server("adhl.ini");

// handle the request
$server->handle_request();

/** \brief class for handling request*/
class methods
{
  /** \brief member holds sql */
  private $sql;
  private $test;

  /** \brief The function handling the request
   * @params $params; the request given from soapclient or derived from url-query
   * @return array with response in abm_xml format
  */
  public function ADHLRequest($params,$watch)
  {
    // get a key to request for tracing
    helpFunc::cache_key($params,$cachekey);
    verbose::log(TRACE,"request-key::".$cachekey);

    //$watch->start("oracle");    
    $ids = $this->get_ids($params,$watch);
    //$watch->stop("oracle");
    if( !$ids ) // empty result-set
      return array();
	
    $watch->start("NEP");
    // get result as array
    if(! $dcarray=$this->set_search($ids,$params,$error) ) 
      {
	verbose::log(FATAL,"zsearch-error: ".$error);
	header("HTTP/1.0 500 Internal server error");
      }
    $watch->stop("NEP");

    $watch->start("PARSE");
    if( is_array($search["records"]) )
      foreach( $search["records"] as $rec )
	  $dcarray[]=$this->get_abm_dc($rec["record"]);
    $watch->stop("PARSE");

    $watch->start("SORTER");
    $ret=$this->sort_array($ids, $dcarray );    
    $watch->stop("SORTER");
    
    return $ret;
  }

  /** \brief
  
  */
  private function set_search($ids,$params,&$error)
  {
    global $TARGET;// from include file : targets.php
    global $search;  
    
    // TODO TARGET should be danbib
    //$search = &$TARGET["dfa"];
    $search = &$TARGET["danbib"];
    $search["format"] = "abm";
    $search["start"]=1;
      
    if( $bibcode=$params->id->_value->lok->_value )
      $search['bibkode']=$bibcode;

    if( $step=$params->numRecords->_value )
      $search["step"]=$step;
    else
      $search["step"]=5;
    
    $ccl=$this->get_ccl_from_ids($ids); 
    
    $search["ccl"]=$ccl;

    // do the actual z3950 search to get results in searcharray
    Zsearch($search); 
    
    if( $search["error"] )
      return false;
    

    $dcarray=array();
     if( is_array($search["records"]) )
      foreach( $search["records"] as $rec )
	  $dcarray[]=$this->get_abm_dc($rec["record"]);

    //print_r($search);
    //exit;

    return $dcarray;
  }
  
  /** \brief Function gets result from database 
   *   @param $request; the current adhlRequest
   *   @returns $ids; an array of localids
   */
  private function get_ids($params,$watch=null)
  {  

    /* OCI */
    // $db = new db($watch);

    // POSTGRES
    $db = new pg_db($watch);

    // pass db-object to set bind-variables

    /* OCI */
     //if(! $sql=$this->get_sql($params,$db) )

    /* POSTGRES */
    if( ! $sql = $this->get_pg_sql($params,$db) )
	return false;    
     
    
    $db->query($sql);
 
    if( $error=$db->get_error() )
	verbose::log(FATAL," OCI error: ".$error);

    while( $row = $db->get_row() )
      {	
	//	print_r($row);
	$res['lid']=$row["lid"];
	//$res['lok']=$row["LOK"];
	$ids[]=$res;
      }
    //exit;

     return $ids;
  }
 
  
  private function get_pg_sql($params,$db)
  {
     if( $isbn=$params->id->_value->isbn->_value )
      {	
	if( $idarr = helpFunc::get_lid_and_lok($isbn) )
	  {
	    $params->id->_value->faust->_value=$idarr['lid'];	    
	  }
	else
	  return false;
      }

     $db->bind("foo",$params->id->_value->faust->_value,SQLT_INT);

     if( $numRecords=$params->numRecords->_value )
       ;
     else
       $numRecords=5;
     
     $db->bind("foo",$numRecords,SQLT_INT);
     
     $where = "and l1.lokalid=$1";//.$params->id->_value->faust->_value."'";
     //$where = "and l1.lokalid='".$params->id->_value->faust->_value."'";
     /*     $query =
"select lid from
(
select lid, count(lid) as count from
(select l2.lokalid as lid
from laan as l1 left join laan as l2
on l2.laanerid = l1.laanerid ".$where.") as foo
group by lid order by count desc limit $2
) as bar";*/

 $query =
"select lid,count from 
(select lokalid as lid, count(*) as count from laan 
where laanerid in 
(select laanerid from laan where lokalid=$1) 
group by lid order by count desc limit $2) as foo";

 return $query;
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
	  if( $record->_value->recordId->_value == $key )
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
  private function get_sql( $params, db $db)
  {
    // set clauses
    $where = "\nand ";     
   
    // if isbn is set - set localid and location via z-search
    // if( $request->id->isbn )
    if( $isbn=$params->id->_value->isbn->_value )
      {	
	if( $idarr = helpFunc::get_lid_and_lok($isbn) )
	  {
	    $params->id->_value->faust->_value=$idarr['lid'];	    
	  }
	else
	  return false;
      }

    // if( $request->id->localId->lid && $request->id->localId->lok)
    if( $lid=$params->id->_value->lid->_value && $lok=$params->id->_value->lok->_value )
      {
	// bind variablese lok and lid
	$db->bind("bind_lok",$lok,SQLT_INT);
	$db->bind("bind_lid",$lid);

     	$where .="l1.lokalid = :bind_lid";
	$where .="\n";
	$where .="and l1.laant_pa_bibliotek = :bind_lok";
	$where .="\n";
	// do NOT select same work
	$where .="and l2.lokalid != :bind_lid";
	$where .="\n";
      } 

      //elseif( $request->id->faust )
      elseif( $faust=$params->id->_value->faust->_value )
      {
	// bind variable faust
	$db->bind("faust_bind",$faust);
	// this is the easy part. libraries always use faust-number as localid

	$where .= "l1.lokalid = :faust_bind";  
	$where .= "\n";
	// do NOT select same work
	$where .= "and l2.lokalid != :faust_bind";
	$where .="\n";
      }
    
    else // id was not set or wrong; 
      return false;

    
     // filter by sex
      // if( $request->sex )
      if( $sex=$params->sex->_value )
      {
	// bind sex-variable
	$db->bind("sex_bind",$sex);
	$where .= "and l2.koen= :sex_bind\n";
      }
    // filter by minimum age
      // if( $request->age->minAge )
      if( $minAge=$params->age->_value->minAge->_value )
      {
	// bind minAge-variable
	$db->bind("minAge_bind",$minAge);
	$where .= "and l2.foedt <= sysdate - (:minAge_bind *365)\n";
      }
    // filter by maximum age
      // if( $request->age->maxAge )
      if( $maxAge=$params->age->_value->maxAge->_value)
      {
	// bind maxAge-variable
	$db->bind("maxAge_bind",$maxAge);	
	$where .= "and l2.foedt >= sysdate - (:maxAge_bind *365)\n";
      }
    // filter by minimum date
    //if( $request->dateInterval->from )
      if( $from=$params->dateInterval->_value->from->_value )
      {
	// bind from-variable
	$db->bind("bind_from",$from );
	$where .= "and l2.dato > to_date(:bind_from,'YYYYMMDD')\n";
      }
    // filter by maximum date
      // if( $request->dateInterval->to )
      if( $to=$params->dateInterval->_value->to->_value)
      {
	//bind to-variable
	$db->bind("bind_to",$to);
	$where .= "and l2.dato < to_date(:bind_to,'YYYYMMDD')\n";    
      }

    // finally bind numRecords-variable
      //  $db->bind("bind_numRecords",$request->numRecords,SQLT_INT);
      if( $numRecords=$params->numRecords->_value )
	;
      else
	$numRecords=5;

      $db->bind("bind_numRecords",$numRecords,SQLT_INT);

// also bind l1max and l2max
$l1max=3;
$db->bind("bind_l1max",$l1max,SQLT_INT);
$l2max=2;
$db->bind("bind_l2max",$l2max,SQLT_INT);

  
    //set query    
      /*          $query =
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
where rownum<=:bind_numRecords';*/

/*     $query =
'select lid from
(select distinct l2.lokalid lid, l2.laan_i_klynge count
from '.TABLE.' l1 inner join '.TABLE.' l2
on l2.laanerid = l1.laanerid'
.$where.
'and l1.laan_i_klynge>:bind_l1max and l2.laan_i_klynge>:bind_l2max order by count desc
)
where rownum<=:bind_numRecords';*/

 $query =
'select lid from
(
select lid, count(*) count from
(select l2.lokalid lid
from '.TABLE.' l1 inner join '.TABLE.' l2
on l2.laanerid = l1.laanerid'
.$where.
'and l1.laan_i_klynge>:bind_l1max and l2.laan_i_klynge>:bind_l2max
)
group by lid order by count desc
)
where rownum<=:bind_numRecords';

    return $query;
  }
 
  /** \brief
   *  parse xml (abm-format) and set dc
   *  @param $xml; the xml to parse
   *  @return $dc; dc element set from given xml
   */
  private function get_abm_dc(&$xml)
  {
  
    libxml_use_internal_errors(true);

    $dom = new DOMDocument('1.0', 'utf-8');
    $dom->LoadXML(utf8_encode($xml));
   
    $xpath = new DOMXPath($dom);

    libxml_clear_errors();
       
    $record->_namespace="http://oss.dbc.dk/ns/adhl";
    // identifier ( lid, lok )
    $query = "/dkabm:record/ac:identifier";
    $nodelist = $xpath->query($query);
    if( $nodelist )
      foreach($nodelist as $node)
	{
	  $record->_value->recordId->_value=xml_func::UTF8($node->nodeValue);
	  $record->_value->recordId->_namespace="http://oss.dbc.dk/ns/adhl";
	  //	$record->recordId =  xml_func::UTF8($node->nodeValue);
	}	
    // url to bib.dk
    $query = "/dkabm:record/ac:location";
    $nodelist = $xpath->query($query);
    if( $nodelist )
      foreach( $nodelist as $node )
	{
	  $record->_value->url->_value=xml_func::UTF8($node->nodeValue);
	  $record->_value->url->_namespace="http://oss.dbc.dk/ns/adhl";
	}
    // creator
    $query = "/dkabm:record/dc:creator";
    $nodelist = $xpath->query($query);
    if( $nodelist )
      foreach( $nodelist as $node )
	{
	  //echo $node->nodeValue."<br />\n";
	  $creator->_value=xml_func::UTF8($node->nodeValue);
	  $creator->_namespace="http://oss.dbc.dk/ns/adhl";
	  $record->_value->creator[]=$creator;
	  $creator=null;
	}
    //    print_r($record->_value->creator);
    //exit;

    // title
    $query = "/dkabm:record/dc:title";
    $nodelist = $xpath->query($query);
    if( $nodelist )
      foreach( $nodelist as $node )
	{
	  $title->_value=xml_func::UTF8($node->nodeValue);
	  $title->_namespace="http://oss.dbc.dk/ns/adhl";
	  $record->_value->title[]=$title;
	  $title=null;
	  
	  // $record->title[] =  xml_func::UTF8($node->nodeValue);
	}
    // description
    $query = "/dkabm:record/dc:description";
    $nodelist = $xpath->query($query);
    if( $nodelist )
      foreach( $nodelist as $node )
	{
	  $description->_value=xml_func::UTF8($node->nodeValue);
	  $description->_namespace="http://oss.dbc.dk/ns/adhl";
	  $record->_value->description[]=$description;
	  $description=null;

	  //  $record->description[] =  xml_func::UTF8($node->nodeValue);
	}
    
    // type
    $query = "/dkabm:record/dc:type";
    $nodelist = $xpath->query($query);
    if( $nodelist )
      foreach( $nodelist as $node )
	{
	  $type->_value=xml_func::UTF8($node->nodeValue);
	  $type->_namespace="http://oss.dbc.dk/ns/adhl";
	  $record->_value->type[]=$type;
	  $type=null;
	  // $record->type[] =  xml_func::UTF8($node->nodeValue);    
	}
    
    $errors = libxml_get_errors();
    if( $errors )
      {
	foreach( $errors as $error )
	  {
	    $log_txt=" :get_abm_dc : ".trim($error->message);
	    // $log_txt.="\n xml: \n".$xml;
	    // TODO alternative way of logging
	    // adhl_server::verbose::log(WARNING,$log_txt);
	    echo $log_txt;
	    exit;
	    libxml_clear_errors();
	  }
	return false;
      }
    return $record;         
  }
}


class pg_db
{
 // member to hold instance of oci_class
  const connectionstring = "host=sotara port=5432 dbname=yaaodb user=pjo password=pjo";
  private $pg;
  public $watch;
  // constructor
  function pg_db($watch)
  {
    $this->pg=new pg_database(self::connectionstring);
    $this->pg->open();

    if( $watch )
      {
	$this->watch = $watch;
	$watch->start("POSTGRES");
      }
  }

  function bind($name,$value,$type=SQLT_CHR)
  {
    $this->pg->bind($name, $value, -1, $type);
  }

  function query($query)
  {
    $this->pg->set_query($query);
    $this->pg->execute();
  }


  /*function pure_sql()
  {
    return $this->oci->pure_sql();
  }*/

  /** return one row from db */
  function get_row()
  {
    return $this->pg->get_row();
  } 
  
  /** destructor; disconnect from database */
  function __destruct()
  {
    if( $this->watch )
      $this->watch->stop("POSTGRES");
  }  

  /** get error from oci-class */ 
    function get_error()
  {
    //return $this->oci->get_error_string();
    return false;
    }
  
}

/* \brief  wrapper for oci_class
 *  handles database transactions
*/
class db
{
  // member to hold instance of oci_class
  private $oci;
  private $watch;
  // constructor
  function db($watch=null)
  {
    if( $watch )
      {
	$this->watch = $watch;
	$this->watch->start("ORACLE");
      }

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

  /*function pure_sql()
  {
    return $this->oci->pure_sql();
  }*/

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
      if( $this->watch )
	$this->watch->stop("ORACLE");
  }  

  /** get error from oci-class */ 
  function get_error()
  {
    return $this->oci->get_error_string();
  }
}

/** \brief
 *  Class holds static functions used for handling the request.
 *  only functions get_request and get_lid_and_lok is called from outside the class; 
 *  other functions are private
*/
class helpFunc
{  
  public static function cache_key($params,&$cachekey)
  {
    foreach( $params as $obj=>$var )
      {
	if( is_object($var->_value) )
	  self::cache_key($var->_value,$cachekey);
	elseif( is_scalar($var->_value) )
	  {
	    //echo $obj.":".$var->_value."\n";
	    $cachekey.= $obj.":".$var->_value."_";
	  }
      }
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
    // $search = &$TARGET["dfa"];
    $search = &$TARGET["danbib"];
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
    
    //   echo $xml;
    //exit;

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
