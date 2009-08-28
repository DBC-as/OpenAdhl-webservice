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

/** include for class-mapping  */
require_once("ADHL_classes.php");
/** include for remote calls*/
require_once("ws_lib/curl_class.php");
/** include for setup */
require_once("ws_lib/inifile_class.php");

//header("Content-Type: text/html; charset=utf-8");

// disable caching while developing                      
ini_set('soap.wsdl_cache_enabled',0);                 

// get ini-file
define(INIFILE, "adhl.ini");
$config = new inifile(INIFILE);

// base-url for using curl to retrieve answer

define(WSDL, $config->get_value("wsdl", "setup"));
define(BASEURL,$config->get_value("baseurl","setup"));

if( $_POST['outputType'] )
  $type=$_POST['outputType'];
else
  $type="SOAP";

switch($type)
  {
  case "JSON":
    XML_and_JSON();
    exit;
  case "XML":
    header("Content-Type:text/xml;charset=UTF-8");
    XML_and_JSON();
    exit;
  case "SOAP":
    SOAPRequest();
    break;
  default:
    echo "Something went wrong";
    break;
  }

function XML_and_JSON()
{
 if( !$request = get_request() )
      {
	echo get_head();  
	echo get_form();
	exit;
      }
 
    $url = get_request_url($request);
    
    $curl=new curl();    
    $curl->set_url($url);
  
    echo $curl->get();
}


function SOAPRequest()
{
  $wsdlpath=WSDL;
  $param = array(    
		 //"classmap"=>$classmap,
     "trace" =>true            
	 );         
  echo get_head();  

  if( !isset($_POST["submit"]) )
    {
      echo get_form();
      exit;
    }

  if( !$request = get_request() )
    exit;  

  // var_dump($request);

  try{
    // TODO once php-version is updated; give $param as input to soap-client for proper class-mapping
    $client = new SoapClient($wsdlpath,$param);
    
    $response=$client->ADHLRequest($request);   
  }
  catch(SoapFault $exception)
    {
      // show some kind of error
      var_dump($exception);
      exit;
    }

  //echo $client->__getLastResponse();
  // echo $client->__getLastRequest();
 
  echo "<div class='container'>";
 
  echo get_form($request);
  echo get_results($response);
  
  echo "</div>\n";

 
  // var_dump($response);
  //print_r($request);
  
 
  
}
?>


<?php

function get_head()
{
return 
'<style>

div.container
{
width:650px;
border: 1px solid #CCCCCC;
padding:5px;
}
div.post
{
  float:left;
}
p
{
  margin:0px;
}

div.break
{
 clear:both;
height:10px;
}

div.error
{
display:none;
}

div.record
{
  
}
div.record h2
{
  font-size:14px;
}
</style>';
}

/** /brief
 * set and return an url with parameters set from given adhlRequest
*/
function get_request_url(adhlRequest $request)
{
  $params = "?";
  // lok and lid
  if( $request->id->localid->lok && $request->id->localid->lid )
    {
      $params.="lok=".$request->id->localid->lok;
      $params.="&lid=".$request->id->localid->lid;
    }
  else
    return "";

  // age-interval
  if( $request->age->from )
    $params.="&minage=".$request->age->minAge;
  if( $request->age->to )
    $params.="&maxage=".$request->age->maxAge;

  // dateinterval
  if( $request->dateinterval->from )
    $params.="&from=".$request->dateinterval->from;
  if( $request->dateinterval->to )
    $params.="&to=".$request->dateinterval->to;

  // number of records
  if( $request->numRecords )
    $params.="&numRecords=".$request->numRecords;
  else
    $params.="&numRecords=5";

  // sex
  if( $request->sex )
    $params.="&sex=".$request->sex;
  // outputType
  if( $request->outputType )
    $params.="&outputType=".$request->outputType;

  return BASEURL.$params;
  
}

/** /brief
 * return records holding html-formatted posts (one post for each record in response)
*/
function get_results(&$response)
{
$count=count($response->dc);
$rec;
if( $count >= 1 ) 
  foreach( $response->dc as $dc )
    {
      $rec.= '<div class="record">';
      // set link to bib.dk
      $ahref=$dc->url;
      $title;
      
      if( $ahref )
	$rec.='<a href="'.$ahref.'">';

      if( is_array($dc->title) )
	//pick first title if more than one is given
	$title = reset($dc->title);
      else
      	$title = $dc->title;

      $rec.= '<h2>'.$title.'</h2>';
      
      if( $ahref )
	$rec.='</a>';

      $rec.='<p> Forfatter: ';
      if( is_array($dc->creator) )
	{
	  foreach( $dc->creator as $creator )
	    $rec.=$creator.'; ';

	  // remove last ';'
	  $rec = substr($rec,-strlen($rec),-2);
	  $rec.='</p>';
	}
      else
	if( !empty($dc->creator) )
	  $rec.=$dc->creator.'</p>';
      if( is_array($dc->description) )
	foreach( $dc->description as $description )
	  {
	    if( strlen($description) > 200 )
	      $rec.='<p>'.substr($description,0,200).'...</p>';
	    else
	      $rec.='<p>'.$description.'</p>';	
	  }
      else
	{
	   if( strlen($dc->description) > 200 )
	      $rec.='<p>'.substr($dc->description,0,200).'...</p>';
	    else
	      $rec.='<p>'.$dc->description.'</p>';	
	}	
     
    }  
    
else
  $rec.= "Ingen resultater";

return $rec;
}  

function is_url($url)
{
  $contents = parse_url($url);
  if( $contents['scheme']=='http' || $contents['scheme']=='https' )
    return true;
  return false;
  }

/** /brief
 *   set and return request from input-fields on form
*/
function get_request()
{
  $request = new adhlRequest();

  // number of records
  if( !empty($_POST["numRecords"]) )
    $request->numRecords = $_POST["numRecords"];
  else // set default to .. 5
    $request->numRecords = 5;

  // lok ( library code ) and lid ( local id )
  if( !empty($_POST["lok"]) && !empty($_POST["lid"]) )
    {
      $localid = new localid();
      $localid->lok = $_POST["lok"];//714700;
      $localid->lid = $_POST["lid"];//"00122181";
      $request->id = new id();
      $request->id->localid=$localid;
    }
  // isbn
  else if( !empty($_POST["isbn"]) )
    {
      //$request->id=new id();
      $request->id->isbn=$_POST["isbn"];        
    }
  else // both lid and lok must be set for the request to make sense; exit with an error
    {
      return false;
      exit;
    }

  // date interval; either from- or to-date can be set
  if( !empty($_POST["from"]) || !empty($_POST["to"]) )
    {
      $date=new interval();
      if( !empty($_POST["from"]) )
	$date->from=$_POST["from"];//'04-SEP-07';
      if( !empty($_POST["to"]) )
	$date->to=$_POST["to"];//$date->to='03-OCT-08';

      $request->dateinterval=$date;
    }

  // sex ??
  if( isset($_POST["sex"]) && $_POST["sex"] != "m/k" )
    {
      $request->sex=$_POST["sex"];
    }

  // age interval; either min- or max-age can be set
  if( !empty($_POST["minage"]) || !empty($_POST["maxage"])  )
    {
      $age=new age();
      if( !empty($_POST["minage"]) )
	$age->minAge=$_POST["minage"];//10;
      if( !empty($_POST["maxage"]) )
	$age->maxAge=$_POST["maxage"];//80;
      
      $request->age=$age;
    }    

  // outputType - can be set or not
  if( !empty($_POST["outputType"]) )
      $request->outputType = $_POST["outputType"];
  
  return $request;
}


/** /brief
 *  set and return the form with given request-parameters
 */
function get_form($request=null)
{
  $ret=
'<form  method="post" name="phpform">
<div class="post">
<p>  Antal poster: </p>
<input type="text" name="numRecords" value="'.(( $request->numRecords )?$request->numRecords:'').'"/> 
</div>

<div class="post">
<p>Type</p>
<select name="outputType">
<option value="SOAP"';
$type=$_POST["outputType"];
if( $type== "SOAP" ){$ret.='SELECTED';}
$ret.='>SOAP</option>';
$ret.='<option value="JSON"';
if( $type== "JSON" ){$ret.='SELECTED';}
$ret.='>JSON</option>';

$ret.='<option value="XML"';
if( $type== "XML" ){$ret.='SELECTED';}
$ret.='>XML</option>';

$ret.='</select>';

$ret.='
</div>

<div class="break"></div>

<div class="post">
<p>  Bibliotekskode: </p>
<input type="text" name="lok" value="'.(( $request->id->local->lok )?$request->id->local->lok:'715700').'"/>
</div>
<div class="post">
<p> Lokalid :</p>
<input type="text" name="lid" value="'.(( $request->id->local->lid )?$request->id->local->lid:'22716360').'"/>
</div>

<div class="break"></div>

<div class="post">
  <p>Fra:(DD-MMM-YY)</p>
<input type="text" name="from" value="'.(( $request->dateinterval->from )?$request->dateinterval->from:'').'"/>
</div>
<div class="post">
  <p>Til:(DD-MMM-YY)</p>
<input type="text" name="to" value="'.(( $request->dateinterval->to )?$request->dateinterval->to:'').'"/></div>

<div class="break"></div>

<div class="post">
<p>Køn</p>

<select name="sex" style="width:75px;height:26px">
<option value="m/k"';
$sex = $_POST["sex"];
if($sex=="m/k"){$ret.='SELECTED';}
$ret.=
'>m/k</option>
<option value="m"';
if($sex=="m"){$ret.='SELECTED';}
$ret.='>m</option>
<option value="k"';
if($sex=="k"){$ret.='SELECTED';}
$ret.='>k</option>
</select>
</div>
<div class="post">
  <p>Min. alder:</p>
<input type="text" name="minage" value="'.(( $request->age->minAge)?$request->age->minAge:'').'"/>
</div>
<div class="post">
  <p>Max. alder : </p>
<input type="text" name="maxage" value="'.(( $request->age->maxAge)?$request->age->maxAge:'').'"/>
</div>

<div class="break"></div>

<div class="post">
<input type="text" name="isbn"/>
</div>

<input type="submit" name="submit" value="Go"/>

</form>
';

  return $ret;
}
?>





