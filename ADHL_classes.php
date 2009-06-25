<?php
class adhlRequest
{
	public $id;//idtype
	public $numRecords;//integer
	public $sex;//sextype
	public $age;//ageType
	public $dateinterval;//interval
	public $outputType;//outPutType
}
class outPutType
{
	public $outPutType;//string
}
class idtype
{
	public $local;//localid
	public $isbn;//string
	public $faust;//number
}
class localid
{
	public $lok;//integer
	public $lid;//string
}
class sextype
{
	public $sextype;//string
}
class ageType
{
	public $minAge;//integer
	public $maxAge;//integer
}
class interval
{
	public $from;//date
	public $to;//date
}
class adhlResponse
{
	public $dc;//dc
	public $error;//string
}
class dc
{
	public $url;//string
	public $id;//string
	public $identifier;//string
	public $creator;//string
	public $alternativename;//string
	public $title;//string
	public $alternative;//string
	public $description;//string
	public $type;//string
	public $rankvalue;//string
}
$classmap=array("adhlRequest"=>"adhlRequest",
"outPutType"=>"outPutType",
"idtype"=>"idtype",
"localid"=>"localid",
"sextype"=>"sextype",
"ageType"=>"ageType",
"interval"=>"interval",
"adhlResponse"=>"adhlResponse",
"dc"=>"dc");
?>
