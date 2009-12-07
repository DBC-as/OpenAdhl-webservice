<?php

// Simon Willison, 16th April 2003
// Based on Lars Marius Garshol's Python XMLWriter class
// See http://www.xml.com/pub/a/2003/04/09/py-xml.html


// Per Johansen DBC June 09. 
// fixed class for UTF8-encoding
class XmlWrite {
    var $xml;
    var $indent;
    var $stack = array();
    function XmlWrite($indent = '  ') {
        $this->indent = $indent;
        $this->xml = '<?xml version="1.0" encoding="utf-8"?>'."\n";
    }
    function _indent() {
        for ($i = 0, $j = count($this->stack); $i < $j; $i++) {
            $this->xml .= $this->indent;
        }
    }
    function push($element, $attributes = array()) {
        $this->_indent();
        $this->xml .= '<'.$element;
        foreach ($attributes as $key => $value) {
	  $this->xml .= ' '.$key.'="'.$this->fix_encoding($value).'"';

        }
        $this->xml .= ">\n";
        $this->stack[] = $element;
    }
    function element($element, $content, $attributes = array()) {
        $this->_indent();
        $this->xml .= '<'.$element;
        foreach ($attributes as $key => $value) {
	  $this->xml .= ' '.$key.'="'.$this->fix_encoding($value).'"';
	  
        }
        $this->xml .= '>'.$this->fix_encoding($content).'</'.$element.'>'."\n";
    }
    function emptyelement($element, $attributes = array()) {
        $this->_indent();
        $this->xml .= '<'.$element;
        foreach ($attributes as $key => $value) {
	  $this->xml .= ' '.$key.'="'.$this->fix_encoding($value).'"';
	}
	
        $this->xml .= " />\n";
    } 
    function pop() {
        $element = array_pop($this->stack);
        $this->_indent();
        $this->xml .= "</$element>\n";
    }
    
    // return xml
    function getXml() {
        return $this->xml;
    }

     /* fix UTF8-encoding */
  private function fix_encoding($data)
  {
    $encoding = mb_detect_encoding($data) ;
    if($encoding == "UTF-8" && mb_check_encoding($data,"UTF-8"))
      {
	return $data;
      }
    else
      return utf8_encode($data); 
  }
}
?>
