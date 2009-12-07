<?php
ini_set('soap.wsdl_cache_enabled',0);
define(WSDL,"adhl.wsdl");

define(XSD,"http://www.w3.org/2001/XMLSchema"); // simpletypes are defined here

require_once("xmlwriter_class.php");

$test = new wsdl2xml();

// test
$dom = new DOMDocument();

print_r($test->operations());



print_r($test->namespaces());



class wsdl2xml
{

  private $functions=array();
  private $namespaces=array();
  private $schemas=array();
  private $soap_client;
  private $xpath;
  private $types=array();

  private $current_scheme;

  public function __construct()
  {
    try{$this->soap_client = new SoapClient(WSDL);}
    catch(SoapFault $exception){print_r( $exception );  exit;}    

    $dom = new DOMDocument();
    $dom->load(WSDL);
    $this->xpath = new DOMXPath($dom);
    $this->namespaces = $this->get_namespaces(); 

    $this->set_schemas();

    $this->types = $this->set_types();   
   
    $this->functions = $this->get_functions();
 
    print_r($this->namespaces);
    print_r($this->schemas);
    echo $this->schemas['types']->document->saveXML();
 //   exit;
  }

  private function set_schemas()
  {
    // print_r($this->types);
    //  print_r($this->namespaces);
    
    foreach( $this->namespaces as $prefix=>$namespace )
      {
	//	echo $prefix."\n";
	if($location = $this->get_schema($prefix)) // schema is imported
	  {
	    //  echo $location."\n";
	    // do NOT import schemas from out of this world
	    if( ! $this->is_url($location) )
	      {
		$dom=new DOMDocument();
		$dom->load($location);
		$xpath=new DOMXPath($dom);
		$this->schemas[$prefix]=$xpath;

		// set namespaces for imported schema
		// now get the schemas imported in xsd
		$this->get_schema_imports($xpath);		
	      }
	  }
	elseif( $xpath=$this->get_wsdl_schema($namespace) )
	  {
	    $this->schemas[$prefix]=$xpath;	   
	  }
      }  

  }

  private function get_schema_imports($xpath)
  {
    // get the namespaces
    $query = "/*[local-name()='schema']/namespace::*";
    $nodelist = $xpath->query($query);
    
    $namespaces=array();
    // remove 'xlmns:' 
    foreach( $nodelist as $node){
      if( $index=strpos($node->nodeName,':') )
	$key = substr($node->nodeName,$index+1);
      else
	$key = $node->nodeName;
      
      $namespaces[$key]=$node->nodeValue;

      // add namespaces to object if not already ther
      foreach( $namespaces as $key=>$value )
	if( ! $this->namespaces[$key] )
	  $this->namespaces[$key]=$value;
    }


    /*foreach( $namespaces as $prefix=>$namespace)
      {
	$query="//*[local-name()='schema']/*[local-name()='import'][@namespace='".$namespace."']";
	$nodes = $xpath->query($query);

	if( !$nodes->length )
	  continue;

	$loc = $nodes->item(0)->getAttribute('schemaLocation');

	if( ! $this->is_url($loc) )
	  {
	    $dom=new DOMDocument();
	    $dom->load($loc);
	    $xpath1=new DOMXPath($dom);
	    $this->schemas[$prefix]=$xpath1;
	  }
	  }*/

  }

  private function get_wsdl_schema($namespace)
  {
    // echo $namespace."\n";
    $query="//*[local-name()='types']//*[local-name()='schema'][@targetNamespace='".$namespace."']";
    //echo $query."\n";
    $nodes=$this->xpath->query($query);

    if( $nodes->length )
      {
	$xml = $this->xpath->document->saveXML($nodes->item(0));	
	$dom=new DOMDocument();
	$dom->loadXML($xml);
	$xpath=new DOMXPath($dom);
	return $xpath;
      }
    return false;    
  }

  private function set_wsdl_schema($prefix)
  {
    $namespace=$this->namespaces[$prefix];
    //	$query="//*[local-name()='types']//*[local-name()='schema' and namespace-uri()='".$namespace."']";
    
    $query="//*[local-name()='types']//*[local-name()='schema']";
    $nodes=$this->xpath->query($query);
    $xml = $this->xpath->document->saveXML($nodes->item(0));
    $dom=new DOMDocument();
    $dom->loadXML($xml);
    $xpath=new DOMXPath($dom);
    $this->schemas[$prefix]=$xpath;
    $this->current_scheme=$xpath;   
    //echo $this->schemas[$prefix]->document->saveXML();
    //exit;
  }

  /** Return true if given path starts with 'http' */
  private function is_url($path)
  {
    $elements = parse_url($path);
    //print_r($elements);
    if( strtolower($elements['scheme']) == 'http' )
      return true;
    return false;	 
  }
  
  private function get_schema($prefix)
  { 
    $namespace = $this->namespaces[$prefix];

    $query="//*[local-name()='schema']/*[@namespace='".$namespace."']";
    $nodes=$this->xpath->query($query);
    $node=$nodes->item(0);
    

    //echo $node->nodeName;
    
    if(! $split=explode(':',$node->nodeName) )
      return false;

    if( $split[1]=='import' )
      return $node->getAttribute('schemaLocation');

    return false;
  }

  public function operations()
  {
    return $this->set_operations();    
  }

  /** Use php SOAP client to grab the types defined in the wsdl */
  private function set_types()
  {

    // get the types
    $types = $this->soap_client->__getTypes();
    // print_r($types);
    
    $classes = array();
    foreach( $types as $type )
      {
	$lines = explode("\n",$type);
	$class = explode(" ",$lines[0]);
	$class_name = $class[1];
	
	$members = array();
	if( $class[0] != 'struct' && $this->get_type($class_name) ) // this is not a class; choice or enumeration
	  {
	    for( $i=0 ; $i < count($lines); $i++ )
	      {
		$elements = explode(" ",trim($lines[$i]));
		$classes[$elements[1]]=$elements[0];
	      }
	  }
	else
	  {	    
	    if( $type=$this->get_type($class_name) )
	      {	
		for( $i=1 ; $i < count($lines)-1; $i++ )
		  {
		    $elements = explode(" ",trim($lines[$i]));
		    $members[trim($elements[1],';')] =  $elements[0];
		    $classes[$class_name] = $members;		
		  }
	      }	
	    else
	      if( $this->get_type($class_name) )
		  $classes[$class_name]=$class_name;
	  }
      }
  
    return $classes;
  }

  // lookup type eg. scanRequest in schema(s) and return fully qualified type eg. scan:scanRequest
  private function get_type($type)
  { 
    $type=trim($type);

    // print_r($this->schemas);
    //exit;

    $element_query = "//*[local-name()='element'][@name='".$type."']";
    $simpletype_query = "//*[local-name()='simpleType'][@name='".$type."']";
    $complex_query = "//*[local-name()='complexType'][@name='".$type."']";
    $ref_query = "//*[local-name()='element'][@ref='".$type."']";

   
   
    $query=$element_query."|".$simpletype_query."|".$complex_query."|".$ref_query;

    foreach( $this->schemas as $key=>$val )
      { 
	$nodes=$val->query($query);	
	if( $nodes->length )
	  {
	    break;
	  }
      }
  
    if(! $nodes->length )
      {
	//	print_r($this->schemas);
	//	return false;

	
	//echo $out_query."\n";
	//echo $type."\n";
	//exit;
	
	//	return $type;
       return false; 
      }

    if( $this->namespaces[$key] )
      {
	return $key;
      }
   
    else
      {
	return 'types';
	//	echo $key."\n"; 
	//exit;
	//return $key.":".$value;
      } 
    
  }
  

  private function set_operations()
  {
    $operations=array();
    foreach( $this->functions as $function )
      {
	//print_r($function);
	$operation['name']=$function['name'];
	if( $function['request'] )
	 $operation['request'] = $this->get_request($function['name']);
	if( $function['response'] )
	  $operation['response'] = $this->get_response($function['name']);	

	$operations[]=$operation;
      }
    return $operations;
  }

  private function get_request($operation_name)
  {
    //echo "OPERATION:".$operation_name;

    global $xmlwriter;
    $xmlwriter=new xmlwrite();
    // Get the input message
    $query = "//*[local-name()='operation'][@name='".$operation_name."']/*[local-name()='input']/@message";
    $nodes = $this->xpath->query($query);
    
    // echo $nodes->item(0)->nodeValue;

    $type=$this->get_message_type($nodes->item(0)->nodeValue);
    // echo "TYPE:".$type;
    // $elements = $this->xpath->query($query);
    
    if( ! $split=explode(':',$type) )
      return false;
   
    // check if type is in schemas
    /* if( !$this->schemas[$split[0]] )
      {
	//	print_r($split);
	//exit;
	$this->set_wsdl_schema($split[0]);

	}*/

    $type_name=$split[1];
        
    $xml=$this->set_fields($type_name);
    $soap=$this->soap_header().$xml.$this->soap_footer();

    return $soap;
  }

  
  private function soap_header()
  {
    $ret.='<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope"'."\n";
    foreach($this->namespaces as $prefix=>$namespace)
      {
	$ret.=' xmlns:'.$prefix.'="'.$namespace."\"\n";
      }
    $ret.='><SOAP-ENV:Body>'."\n";
    
    return $ret;
  }

  private function soap_footer()
  {
    $ret.='</SOAP-ENV:Body>'."\n";
    $ret.='</SOAP-ENV:Envelope>'."\n";
 

    return $ret;    
  }

  private function is_complex_type($class)
  {
    $complex_query="//*[local-name()='complexType'][@name='".$class."']";
    
    $query=$complex_query;

    foreach( $this->schemas as $key=>$val )
      { 
	$nodes=$val->query($query);	
	if( $nodes->length )
	  return true;
      }

    // echo $this->current_scheme->document->saveXML();

    // $nodes=$this->current_scheme->query($query);
    //    if( $nodes->length )
    //	return true;

    return false;
  }

  private function get_out_ref($key,$parent)
  {
    $out_query = "//*[local-name()='element'][@name='".$parent."']//*[local-name()='element'][contains(@ref,'".$key."')]";

           echo "KEY:".$key."PARENT:".$parent.";". $out_query;
    //exit;
    foreach( $this->schemas as $key=>$val )
      { 
	$nodes=$val->query($out_query);	
	if( $nodes->length )
	  {
	    if( $nodes->length > 1 )
	     {
	       echo "TUTTUTLEU";
		foreach( $nodes as $node )
		  echo $parent.";;".$node->getAttribute("ref")."\n";
		
			echo $nodes->length;
		exit;
	     }
	    // there might be more than one node eg. dc:title, dkabm:title, but if we get here
	    // there has been a bunch of imported schemas and the type has been given up, so... grab the first one
	    return $nodes->item(0)->getAttribute("ref");
	    break;
	  }
      }
   
    return "OUT_OF_REF:";
  }
  // TODO can this function be recursive ?
  private function set_fields($class)
  {

    echo $class."\n";

    global $key;
    global $xmlwriter;
    global $parent;
    global $parent_class;

    if( !isset($xmlwriter) )
      $xmlwriter=new XmlWrite();

    if( is_array($this->types[$class]) )
      {
	$parent_class=$class;
	if( $this->is_complex_type($class) )
	  $xmlwriter->push($this->get_type($parent).":".$parent);	  
	else
	  $xmlwriter->push($this->get_type($class).":".$class);
	
	foreach( $this->types[$class] as $key=>$val )
	  {	    
	    $parent=$key;
	    $this->set_fields($val);	  
	  }
	$xmlwriter->pop();
      }      
    elseif( is_scalar($this->types[$class]) )
      {
	$xmlwriter->element($this->get_type($parent).":".$parent,"?");
      }
    else
      {
	if( $this->get_type($key) )
	  $xmlwriter->element($this->get_type($key).":".$key,"?");		  	
	else
	  $xmlwriter->element($this->get_out_ref($key,$parent_class),"?");
      }
      
    return $xmlwriter->getXml();
  } 


  // return part-name from wsdl
  private function get_message_type($message)
  {
    echo $message;
    $split=explode(':',$message);
    
    print_r($split);
    if( $type=$split[1] )
      ;
    else
      $type=$split[0];
    
    echo "TYPE: ".$type;

    // make a query for each of the options : type,element,ref
    $type_query="//*[local-name()='message'][@name='".$type."']/*[local-name()='part']/@type";
    $element_query="//*[local-name()='message'][@name='".$type."']/*[local-name()='part']/@element";
    $ref_query="//*[local-name()='message'][@name='".$type."']/*[local-name()='part']/@ref";
   
    $query=$type_query.'|'.$element_query.'|'.$ref_query;
  
    echo $query;

    $nodes=$this->xpath->query($query);

    echo "RETURN: ".$nodes->item(0)->nodeValue;

    return $nodes->item(0)->nodeValue;
  }
  


  public function functions()
  {
    return $this->functions;
  }

  public function namespaces()
  {
    return $this->namespaces;
  }

  private function get_functions()
  {
    $operations = $this->soap_client->__getFunctions();
    
    $functions=array();
    foreach( $operations as $operation )
      {
	$matches = array();
	if(preg_match('/^(\w[\w\d_]*) (\w[\w\d_]*)\(([\w\$\d,_ ]*)\)$/', $operation, $matches)) 
	  {
	    $response = $matches[1];
	    $name = $matches[2];
	    $params = $matches[3];
	  } 
	$split=explode(' ',$params);
	
	$function = array('name'=>$name,
			  'response'=>$response,
			  // TODO will requests have more than óne parameter?
			  // grab the first parameter
			  'request'=>$split[0]
			  );

	//	print_r($function);
	
	$functions[]=$function;	
      }
    
    return $functions;
  } 
  
  // get the outuput message
  function get_response($operation_name)
  {
    global $xmlwriter;
    $xmlwriter=new xmlwrite();

    $query = "//*[local-name()='operation'][@name='".$operation_name."']/*[local-name()='output']/@message";
    $nodes = $this->xpath->query($query);
    
    $type=$this->get_message_type($nodes->item(0)->nodeValue);
    // echo $type;
    // $elements = $this->xpath->query($query);
    
    if( ! $split=explode(':',$type) )
      return false;

    $type_name=$split[1];

    $xml = $this->set_fields($type_name); 

    return $this->soap_header().$xml.$this->soap_footer();
  }

  private function get_namespaces()
  {
    // get the namespaces used for the schema(s)
    $query = "/*[local-name()='definitions']/namespace::*";
    $nodelist = $this->xpath->query($query);
    
    $namespaces=array();
    // remove 'xlmns:' 
    foreach( $nodelist as $node){
      if( $index=strpos($node->nodeName,':') )
	$key = substr($node->nodeName,$index+1);
      else
	$key = $node->nodeName;
      
      $namespaces[$key]=$node->nodeValue;
    }

    return $namespaces;
  } 
  
  function get_classes()
  {
  $types = $this->soap_client->__getTypes();
    
  $classes = array();
  foreach( $types as $type )
    {
      $lines = explode("\n",$type);
      $class = explode(" ",$lines[0]);
      $class_name = $class[1];
      $members = array();
      
      if( $class[0] != 'struct' ) // this is an enumeration
	{
	  for( $i=0 ; $i < count($lines); $i++ )
	    {
	      $elements = explode(" ",trim($lines[$i]));
	      $members[] = array('name' => $elements[1],'type' => $elements[0]);		 
	    }		  
	}
      else
	{		  
	  for( $i=1 ; $i < count($lines)-1; $i++ )
	    {
	      $elements = explode(" ",trim($lines[$i]));
	      $members[] = array('name' => $elements[1],'type' => $elements[0]);		 
	    }
	}
      
      $class = array($class_name=>$members);
      // add class to document
      $classes[] = $class;
    }
  return $classes;
}
  
}
?>
