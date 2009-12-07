<?php

/**
   
   wrapper for memcache class.

*/

define( HOST,"localhost" );
define( PORT,"11211" );

class cache
{
  
  private static $_memcache=null;
  // private constructor to avoid abuse( new() ) of class
  private function __construct(){}
  
  public static function client()
  {
    if( self::$_memcache==null )
      {
	self::$_memcache=new Memcache();
	self::$_memcache->connect(HOST,PORT);
      }
    return self::$_memcache;
  }

  public function get($key)
  {
    return self::$_memcache->get("mykey");
  }

  public function set($key,$data)
  {
    self::$_memcache->set("mykey", "testhest", false, 600);
  }
}


?>