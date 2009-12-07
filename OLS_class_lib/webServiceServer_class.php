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


/** \brief Webservice server
 *
 *
 */

require_once("OLS_class_lib/curl_class.php");
require_once("OLS_class_lib/verbose_class.php");
require_once("OLS_class_lib/inifile_class.php");
require_once("OLS_class_lib/timer_class.php");
require_once("OLS_class_lib/restconvert_class.php");
require_once("OLS_class_lib/xmlconvert_class.php");
require_once("OLS_class_lib/objconvert_class.php");

abstract class webServiceServer {

  protected $config; /// inifile object
  protected $verbose;  /// verbose object for logging
  protected $watch; /// timer object
	protected $xmldir="./"; /// xml directory
	protected $validate= array(); /// xml validate schemas
  protected $objconvert; /// OLS object to xml convert
  protected $xmlconvert; /// xml to OLS object convert
  protected $xmlns; /// xml to OLS object convert

	/** \brief Webservice constructer
 	*
	* @param inifile <filename>
 	*
 	*/
	public function  __construct($inifile) {
	  // initialize config and verbose objects
    $this->config = new inifile($inifile); 
                                           
    if ($this->config->error) {                                    
        die("Error: ".$this->config->error );
      }                                                                

    $this->verbose=new verbose($this->config->get_value("logfile", "setup"),
                               $this->config->get_value("verbose", "setup"));    
    $this->watch = new stopwatch("", " ", "", "%s:%01.3f");

		if ($this->config->get_value('xmldir')) 
			$this->xmldir=$this->config->get_value('xmldir');
    $this->xmlns = $this->config->get_value('xmlns', 'setup');
	}

  /** \brief Handles request from webservice client
  *
  */
	public function handle_request() {
	  if (isset($_GET["HowRU"]) ) {                          
     	$this->HowRU();          
    } elseif (isset($GLOBALS["HTTP_RAW_POST_DATA"])) {
      $this->soap_request($GLOBALS["HTTP_RAW_POST_DATA"]);                    
    } elseif (isset($_POST['xml'])) {
			$xml=trim(stripslashes($_POST['xml']));
      $this->soap_request($xml);                    
    } elseif (!empty($_SERVER['QUERY_STRING']) ) {
      $this->rest_request();    
    } else {                                                
			$this->create_sample_forms();
    }    
	}

  /** \brief Handles and validates soap request
	*
  * @param xml <string>
  */
	private function soap_request($xml) {
    // Debug $this->verbose->log(TRACE, "Request " . $xml);

    // validate request
    $this->validate = $this->config->get_value('validate');

		if ($this->validate["request"] && !$this->validate_xml($xml,$this->validate["request"]))
			$error=1;

		if (empty($error)) {
      // parse to object
      $this->xmlconvert=new xmlconvert();
      $xmlobj=$this->xmlconvert->soap2obj($xml);

      // initialize objconvert and load namespaces
      $this->objconvert=new objconvert($this->xmlns);

      // handle request
			if ($response_xmlobj=$this->call_xmlobj_function($xmlobj)) {
        // validate response
		    if ($this->validate["response"]) {
			    $response_xml=$this->objconvert->obj2soap($response_xmlobj);
          if (!$this->validate_xml($response_xml,$this->validate["response"]))
				    $error=1;
        }

		    if (empty($error)) {
        // Branch to outputType
          list($service, $req) = each($xmlobj->Envelope->_value->Body->_value);
          switch ($req->_value->outputType->_value) {
            case "json":
              header("Content-Type: application/json");
              $callback = &$req->_value->callback->_value;
              if ($callback && preg_match("/^\w+$/", $callback))
			          echo $callback . ' && ' . $callback . '(' . $this->objconvert->obj2json($response_xmlobj) . ')';
              else
			          echo $this->objconvert->obj2json($response_xmlobj);
              break;
            case "php":
              header("Content-Type: application/php");
			        echo $this->objconvert->obj2phps($response_xmlobj);
              break;
            case "xml":
              header("Content-Type: text/xml");
			        echo $this->objconvert->obj2xmlNS($response_xmlobj);
              break;
            default: 
              if (empty($response_xml))
			          $response_xml =  $this->objconvert->obj2soap($response_xmlobj);
              header("Content-Type: text/xml");
			        echo $response_xml;
          }
			  } else
				  echo "Error in response validation.";
			} else
				echo "Error in request validation.";
		} else
			echo "Error in request validation.";
	}

	/** \brief Handles rest request, converts it to xml and calls soap_request()
  *
  * @param xml <string>
	*
  */
	private function rest_request() {
			// convert to soap
			$rest = new restconvert();
			$xml=$rest->rest2soap(&$this->config);
			$this->soap_request($xml);
	}

  /** \brief HowRU tests the webservice and answers "Gr8" if none of the tests fail. The test cases resides in the inifile.
  * 
  */
	private function HowRU() {
		$curl = new curl();
		$curl->set_option(CURLOPT_POST, 1);
    $tests = $this->config->get_value('test', "howru");
    if ($tests) {
      $reg_match = $this->config->get_value('preg_match', "howru");
      $reg_error = $this->config->get_value('error', "howru");
      $url = $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"];
		  foreach ($tests as $k=>$v) {
			  $reply=$curl->get($url."?action=".$v);
			  $preg_match=$reg_match[$k];
			  if (!preg_match("/$preg_match/",$reply)) {
				  echo $reg_error[$k];
          die();
			  }
		  }
		  $curl->close();
		}
    echo "Gr8";
    die();
	}

  /** \brief Validates xml
  * 
  * @param xml <string>
  * @param schema_filename <string>
  * @param resolve_externals <bool>
	*
  */

  protected function validate_xml($xml, $schema_filename, $resolve_externals='FALSE') {
		$validateXml = new DomDocument;
    $validateXml->resolveExternals = $resolve_externals;
    $validateXml->loadXml($xml);
    if (@ $validateXml->schemaValidate($schema_filename)) {
      return TRUE;
    } else {
      return FALSE;
    }
  }

  /** \brief Find operation in object created from xml and and calls this function defined by developer in extended class.
  *
  * @param xmlobj <object>
  *
  */
  private function call_xmlobj_function($xmlobj) {
    if ($xmlobj) {
      $soapAction = $this->config->get_value("soapAction", "setup");
      $request=key($xmlobj->Envelope->_value->Body->_value);
      if ($function = array_search($request, $soapAction)) {
        $params=$xmlobj->Envelope->_value->Body->_value->$request->_value;
        if (method_exists($this, $function))
          return $this->$function($params);
      }
    }

    return FALSE;
  }

  /** \brief Create sample form for testing webservice. This is called of no request is send via browser.
  *
  *
  */

	private function create_sample_forms() {
    if (isset($HTTP_RAW_POST_DATA)) return;

    echo "<html><body>";

    // Open a known directory, and proceed to read its contents
    if (is_dir($this->xmldir."/request")) {
      if ($dh = opendir($this->xmldir."/request")) {
        chdir($this->xmldir."/request");
        while (($file = readdir($dh)) !== false) {
          if (!is_dir($file)) {
            $fp=fopen($file,'r');
            if (preg_match('/html$/',$file,$matches)) {
              $info .= fread($fp, filesize($file));
              $found_files=1;
            }
            if (preg_match('/xml$/',$file,$matches)) {
              $found_files=1;
              $contents = fread($fp, filesize($file));
              $reqs[]=str_replace("\n","\\n",addcslashes($contents,'"'));
              $names[]=$file;
            }
            echo '</form>';

            fclose($fp);
          }
        }
        closedir($dh);

        if ($found_files) {

          echo '<script language="javascript">' . "\n" . 'var reqs = Array("' . implode('","', $reqs) . '");</script>';
          echo '<form name="f" method="POST" enctype="text/html; charset=utf-8"><textarea name="xml" rows=20 cols=90>';
          echo stripslashes($_REQUEST["xml"]);
          echo '</textarea><br><br>';
          echo '<select name="no" onChange="if (this.selectedIndex) document.f.xml.value = reqs[this.options[this.selectedIndex].value];">';
          echo '<option>Pick a test-request</option>';
          foreach ($reqs as $key => $req)
            echo '<option value="' . $key . '">'.$names[$key].'</option>';
          echo '</select> &nbsp; <input type="submit" name="subm" value="Try me">';
          echo '</form>';
          echo $info;
        } else {
          echo "No example xml files found...";
        }
      }
    }
    echo "</body></html>";
  }

}

?>
