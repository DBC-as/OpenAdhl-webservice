<?php
class adhlRequest
{
	public $id;//id
	public $numRecords;//integer
	public $sex;//sexType
	public $age;//age
	public $dateinterval;//dateinterval
	public $outputType;//outPutType
	public $callback;//string
}
class outPutType
{
	public $outPutType;//string
}
class id
{
	public $localid;//localid
	public $isbn;//string
	public $faust;//integer
}
class localid
{
	public $lok;//integer
	public $lid;//string
}
class sexType
{
	public $sexType;//string
}
class age
{
	public $minAge;//integer
	public $maxAge;//integer
}
class dateinterval
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
"id"=>"id",
"localid"=>"localid",
"sexType"=>"sexType",
"age"=>"age",
"dateinterval"=>"dateinterval",
"adhlResponse"=>"adhlResponse",
"dc"=>"dc");
?>
