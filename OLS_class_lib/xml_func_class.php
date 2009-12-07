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


require_once("xmlwriter_class.php");

/*
  class to hold collection of static functions for handling xml-related actions.

  sample usage
  $xml = xml_func::object_to_xml($object);

*/
class xml_func
{ 
  /* return an object as xml */
  public static function object_to_xml($obj)
  {
    global $key;
    global $xmlwriter;

    if( !isset($xmlwriter) )
      $xmlwriter=new XmlWrite();
    
    if( is_object($obj) )
      {
	$xmlwriter->push(get_class($obj));
	$vars=get_object_vars($obj);
	foreach( $vars as $key=>$var )
	  {
	    if( is_scalar($var) )
	      $xmlwriter->element($key,$var);
	    else
	      self::object_to_xml($var);	      
	  }
	$xmlwriter->pop();
      }
    else if( is_array($obj) )
      {
	foreach( $obj as $whatever=>$val )
	  if( is_scalar($val) )
	    $xmlwriter->element($key,$val);
	  else
	    self::object_to_xml($val);
      }
    
    return $xmlwriter->getXml();
  } 

  /* fix UTF8-encoding */
  public static function UTF8($data)
  {
    $encoding = mb_detect_encoding($data) ;
    if($encoding == "UTF-8" && mb_check_encoding($data,"UTF-8"))
      {
	//hmm . '&' doesn't encode properly TODO find a proper fix
	return str_ireplace('&','&#38;',$data);
      }
    else
      return  str_ireplace('&','&#38;',utf8_encode($data)); 
  }

}
?>
