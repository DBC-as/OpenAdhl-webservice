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



class restconvert {

	private $soap_header;
	private $soap_footer;

	public function __construct() {
		$this->soap_header='<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"><SOAP-ENV:Body>';
		$this->soap_footer='</SOAP-ENV:Body></SOAP-ENV:Envelope>';
	}

	/** \brief Transform REST parameters to SOAP-request
	 *
 	*/
	public function rest2soap(&$config) {
 	 $soap_actions = $config->get_value("soapAction", "setup");
 	 $action_pars = $config->get_value("action", "rest");
   if (!$all_actions = $action_pars["ALL"])
     $all_actions = array();
 	 if (is_array($soap_actions) && is_array($action_pars) 
    && $_GET["action"]
    && $soap_actions[$_GET["action"]] && $action_pars[$_GET["action"]]) {
 	   if ($node_value = $this->build_xml(array_merge($all_actions, &$action_pars[$_GET["action"]]), explode("&", $_SERVER["QUERY_STRING"]))) {
 	     return $this->soap_header .  $this->rest_tag_me($soap_actions[$_GET["action"]], $node_value) .  $this->soap_footer;
			} 
 	 }
	}

	private function build_xml($action, $query) {
 	 foreach ($action as $key => $tag) {
 	   if (is_array($tag)) {
 	     $ret .= $this->rest_tag_me($key, $this->build_xml($tag, $query));
 	   } else {
 	     foreach ($query as $parval) {
 	       list($par, $val) = $this->par_split($parval);
 	       if ($tag == $par) $ret .= $this->rest_tag_me($tag, $val);
 	     }
 	   }
		}
 	 return $ret;
	}

	private function par_split($parval) {
 	 list($par, $val) = explode("=", $parval, 2);
 	 return array(preg_replace("/\[[^]]*\]/", "", urldecode($par)), $val);
	}

	function rest_tag_me($tag, $val) {
    if ($i = strrpos($tag, "."))
      $tag = substr($tag, $i+1);
	  return "<$tag>$val</$tag>";
	}
}

?>
