<?php

define("DEFAULT_HOST", "lakito.dbc.dk:2105");

//global $TARGET;	// aht wget

$TARGET["dfa"] = array (
    "host"		=> DEFAULT_HOST,
    "database"		=> 'dfa',
    "piggyback"		=> false,
    "raw_record"	=> true,
    "authentication"	=> "webgr/dfa/hundepine",
    "fors_name"		=> "bibliotek.dk",
    "cclfields"		=> 'danbib.ccl2rpn',
    "format"		=> 1,
    "formats"		=> array("dc" => "xml/ws_dc",
                         "marcx" => "xml/marcxchange",
                         "abm" => "xml/abm_xml"),
    "start"		=> 1,
    "step"		=> 1,
    "filter"		=> "",
    "sort"		=> "",
    "sort_default"	=> "aar",
    "sort_max"		=> 100000,
    //"vis_max"		=> 1000000,
    "timeout"		=> 30
);




$TARGET["dan"] = array (
    "host"		=> HOST,
    "database"		=> 'danbibv3',
    "piggyback"		=> true,
    "authentication"	=> "webgr/dfa/hundepine",
    "fors_name"		=> "danbib",
    "cclfields"		=> 'danbib.ccl2rpn',
    "format"		=> "f2",
    "formats"		=> array("f2" => "xml/f2", "ws_dc" => "xml/ws_dc"),
    "start"		=> 1,
    "step"		=> 100,
    "filter"		=> "",
    "sort"		=> "",
    "sort_default"	=> "aar",
    "sort_max"		=> 100000,
    //"vis_max"		=> 1000000,
    "timeout"		=> 30
);
?>
