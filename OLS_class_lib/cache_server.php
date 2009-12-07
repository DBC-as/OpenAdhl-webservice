<?php

class cache_server
{
  private $socket;
  private $stock=array();
  private $cleanup_interval;
  
  public function __construct($domain='127.0.0.1', $port=5000)
  {
    // process's memory-usage must not exceed this
    define(MAXMEM,500000); //500 Kb
    define(ENDCODE,"zzz");
    
    // cleanup every 10 secs.
    $this->cleanup_interval=10;

    if( ! $this->socket=socket_create(AF_INET, SOCK_STREAM, 0) )
      die( "could not create socket: domain $domain ; $port ". socket_strerror(socket_last_error($this->socket)) . "\n" );

    if( ! socket_bind($this->socket, $domain, $port) )
      die( "could not bind socket". socket_strerror(socket_last_error($this->socket))."\n"  );

    if ( !socket_listen($this->socket, 5) )
      die( "socket_listen() failed: reason: " . socket_strerror(socket_last_error($this->socket)) . "\n");

   
    socket_set_block($this->socket);

    $this->listen();
  }

  private function listen()
  {
    $count=0;
    $time = time();
    do
      {
	echo "listening\n";
	
	if(! $client_sock = socket_accept($this->socket) )
	  echo "socket_accept() failed: reason: " . socket_strerror(socket_last_error($this->socket)) . "\n";
	
	// all writes and reads are handled by client_sock from here on

	$buffer = $this->read($client_sock);

	$obj = json_decode($buffer);
	//	print_r($obj);

	//get command
	$command = $obj->cmd;
	switch($command)
	  {
	  case "set":
	    echo "SET\n";
	    $this->stock[$obj->cache->key]=$obj->cache;
	    // print_r($this->stock);
	    $message='ok';
	    break;

	  case "get":
	    if( $this->stock[$obj->key] )
	      {
		// return whole object to cacheclient
		$message = $this->stock[$obj->key];
		// refresh timestamp
		$this->stock[$obj->key]->timestamp = time();
	      }

	    else
	      $message='na';
	    break;

	  case "dump":
	    echo "DUMP\n";
	    $message = $this->stock;
	    break;

	  case "kill.\n":
	    echo "KILL";
	    return;

	  case "delete.\n":
	    echo "DELETE";
	    unset($this->stock[$obj->key]);
	    break;

	  case "clear":
	    echo "CLEAR.\n";
	    unset($this->stock);
	    $message='ok';
	    break;	    
	  }

	$write = json_encode($message).ENDCODE;
	$this->send($client_sock, $write);
	socket_close($client_sock);

	// check if it is time to cleanup
	if( time() > $time+$this->cleanup_interval )
	  {
	    garbage_collector::cleanup($this->stock);
	    // reset time
	    $time = time();
	  }

	// check memory-usage
	if( ($mem = memory_get_usage()) > MAXMEM )
	    garbage_collector::make_space($this->stock);

	
      }
    while(1);

    socket_close($client_sock);
    socket_close($this->socket);
  }

  function send($client_sock, &$message)
  {
    $len = strlen($message);
    $offset = 0;
    while ($offset < $len) {
      $sent = socket_write($client_sock, substr($message, $offset), $len-$offset);
      if ($sent === false) {
        // Error occurred, break the while loop
        break;
      }
      $offset += $sent;
    }
    if ($offset < $len) {
      $errorcode = socket_last_error();
      $errormsg = socket_strerror($errorcode);
      echo "SENDING ERROR: $errormsg";
    } else {
      // Data sent ok
      echo "datalength : ".$len."; sent: ".$sent;
    } 
  }


  function read($client_sock)
  {
    while(  $buf = trim(socket_read($client_sock,1024 , PHP_BINARY_READ)) )
      {
       if( substr($buf,-3)==ENDCODE )
	 {
	   $ret.=substr($buf,0,strlen($buf)-3);
	   break;
	 }

        $ret.=$buf;      
      }

    return $ret;
  }

  public function __destruct()
  {
    if( !empty($this->socket) )
      socket_close($this->socket);
  }
}

class garbage_collector
{
  private function __construct(){}

  public static function cleanup(&$stock)
  {
    foreach( $stock as $key=>$val )
      {
	if( time() > $val->timestamp + $val->time_to_live )
	  unset($stock[$key]);	
      }
  }

  // unset oldest half of stock
  public static function make_space(&$stock)
  {
    //  print_r($stock);
    foreach( $stock as $key=>$val )
      echo $val->timestamp."\n";
    // sort the array
    uasort($stock,array("garbage_collector","compare"));

    $middle = intval(count($stock)/2);
    echo "middle is: ".$middle."\n";
    $index=0;
    foreach( $stock as $key=>$val )
      {
	unset($stock[$key]);
	$index++;
	if( $index >= $middle )
	  break;
      }
    
    foreach( $stock as $key=>$val )
      echo $val->timestamp."\n";   
    
  }

  public static function compare($a,$b)
  {
    $a1 = $a->timestamp;
    $b1 = $b->timestamp;
    if ($a1 == $b1) {
        return 0;
    }
    return ($a1 < $b1) ? -1 : 1;
  }

}


?>