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


class objconvert {

	private $namespaces=array();
	private $used_namespaces=array();

	public function __construct($xmlns="") {
    if ($xmlns)
      foreach ($xmlns as $prefix => $ns) {
        if ($prefix == "NONE")
          $prefix = "";
        $this->add_namespace($ns, $prefix);
      }
	}

 /** \brief Convert ols-object to json
 	*/
	public function obj2json($obj) {  
    foreach ($this->namespaces as $ns => $prefix)
      if ($prefix)
        $o_ns->$prefix = $ns;
      else
        $o_ns->{'$'} = $ns;
    $json_obj = $this->obj2badgerfish_obj($obj);
    $json_obj->{'@namespaces'} = $o_ns;
    return json_encode($json_obj);
  }

 /** \brief compress ols object to badgerfish-inspired object
 	*/
  private function obj2badgerfish_obj($obj) {
    if ($obj)
      foreach ($obj as $key => $o)
        if (is_array($o))
          foreach ($o as $o_i)
            $ret->{$key}[] = $this->build_json_obj($o_i);
        else 
          $ret->$key = $this->build_json_obj($o);
    return $ret;
  }

 /** \brief convert one object
 	*/
  private function build_json_obj($obj) {
    if (is_scalar($obj->_value))
      $ret->{'$'} = html_entity_decode($obj->_value);
    else
      $ret = $this->obj2badgerfish_obj($obj->_value);
    if ($obj->_attributes)
      foreach ($obj->_attributes as $aname => $aval)
        $ret->{'@'.$aname} = $this->build_json_obj($aval);
    if ($obj->_namespace)
      $ret->{'@'} = $this->get_namespace_prefix($obj->_namespace);
    return $ret;
  }

 /** \brief experimental php serialized
 	*/
	public function obj2phps($obj) {
    return serialize($obj);
  }

 /** \brief Convert ols-object to xml with namespaces
 	*/
	public function obj2xmlNs($obj) {
    $this->used_namespaces = array();
    $xml = $this->obj2xml($obj);
    foreach ($this->namespaces as $ns => $prefix)
      if ($this->used_namespaces[$ns] || empty($prefix))
        $used_ns .= ' xmlns' . ($prefix ? ':'.$prefix : '') . '="' . $ns . '"';
    if ($used_ns && $i = strpos($xml, ">"))
      $xml = substr($xml, 0, $i) . $used_ns . substr($xml, $i);
    return $this->xml_header() . $xml;
  }

 /** \brief Convert ols-object to soap
 	*/
	public function obj2soap($obj) {
    $this->used_namespaces = array();
    $xml = $this->obj2xml($obj);
    foreach ($this->namespaces as $ns => $prefix)
      if ($this->used_namespaces[$ns] || empty($prefix))
        $used_ns .= ' xmlns' . ($prefix ? ':'.$prefix : '') . '="' . $ns . '"';
    return $this->xml_header() . '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"' . $used_ns . '><SOAP-ENV:Body>' . $xml . '</SOAP-ENV:Body></SOAP-ENV:Envelope>';
  }

 /** \brief UTF-8 header 
 	*/
  private function xml_header() {
    return '<?xml version="1.0" encoding="UTF-8"?>';
  }

 /** \brief Convert ols-object to xml
	*
 	* used namespaces are returned in this->namespaces
 	* namespaces can be preset with add_namespace()
 	*
 	*/
	public function obj2xml($obj) {
    $ret = "";
    if ($obj)
      foreach ($obj as $tag => $o) {
        if (is_array($o))
          foreach ($o as $o_i)
            $ret .= $this->build_xml($tag, $o_i);
        else
 	        $ret .= $this->build_xml($tag, $o);
      }
    return $ret;
	}

 /** \brief handles one node
 	*/
	private function build_xml($tag, $obj) {
 	 $ret = "";
 	 if ($obj->_attributes)
 	   foreach ($obj->_attributes as $a_name => $a_val) {
 	     if ($a_val->_namespace)
 	       $a_prefix = $this->set_prefix_separator($this->get_namespace_prefix($a_val->_namespace));
       else 
         $a_prefix = "";
 	     $attr .= ' ' . $a_prefix . $a_name . '="' . $a_val->_value . '"';
 	   }
 	 if ($obj->_namespace)
 	   $prefix = $this->set_prefix_separator($this->get_namespace_prefix($obj->_namespace));
 	 if (is_scalar($obj->_value))  
 	 	return $this->tag_me($prefix.$tag, $attr, $obj->_value);
 	 else
 	   return $this->tag_me($prefix.$tag, $attr, $this->obj2xml($obj->_value));
	}

 /** \brief returns prefixes and store namespaces 
 	*/
	private function get_namespace_prefix($ns) {
 	 if (empty($this->namespaces[$ns])) {
 	   $i = 1;
 	   while (in_array("ns".$i, $this->namespaces)) $i++;
 	   $this->namespaces[$ns] = "ns".$i;
 	 }
   $this->used_namespaces[$ns] = TRUE;
 	 return $this->namespaces[$ns];
	}

 /** \brief Separator between prefix and tag-name in xml
 	*/
  private function set_prefix_separator($prefix) {
    if ($prefix) return $prefix . ':'; else return $prefix;
  }

 /** \brief Adds known namespaces
 	*/
	public function add_namespace($namespace,$prefix) {
		 $this->namespaces[$namespace]=$prefix;
	}

 /** \brief Returns used namespaces
 	*/
	public function get_namespaces() {
		 return $this->namespaces;
	}

 /** \brief produce balanced xml
 	*/
	private function tag_me($tag, $attr, $val) {
   if ($attr && $attr[0] <> " ") $space = " ";
 	 return '<' . $tag . $space . $attr . '>' . $val . '</' . $tag . '>';
	}

}



?>
