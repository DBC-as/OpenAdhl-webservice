<?php
class adhlRequest
{
	public $id;//id
	public $numRecords;//integer
	public $sex;//sexType
	public $age;//age
	public $dateInterval;//dateInterval
	public $outputType;//outputType
	public $callback;//string
}
class adhlResponse
{
	public $record;//record
	public $error;//string
}
class age
{
	public $minAge;//integer
	public $maxAge;//integer
}
class dateInterval
{
	public $from;//date
	public $to;//date
}
class id
{
	public $localId;//localId
	public $isbn;//string
	public $faust;//string
}
class record
{
	public $url;//string
	public $recordId;//string
	public $identifier;//string
	public $creator;//string
	public $alternativeName;//string
	public $title;//string
	public $alternative;//string
	public $description;//string
	public $type;//string
	public $rankValue;//string
}
class localId
{
	public $lok;//integer
	public $lid;//string
}
class outputType
{
	public $outputType;//string
}
class sexType
{
	public $sexType;//string
}
$classmap=array("adhlRequest"=>"adhlRequest",
"adhlResponse"=>"adhlResponse",
"age"=>"age",
"dateInterval"=>"dateInterval",
"id"=>"id",
"record"=>"record",
"localId"=>"localId",
"outputType"=>"outputType",
"sexType"=>"sexType");
?>
