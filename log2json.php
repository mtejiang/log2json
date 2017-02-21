<?php

function preprocess($original_log) {
	$trimmed = trim($original_log);
	$trimmed = str_replace(array("\n\r", "\n", "\r"), '', $trimmed);
	$pattern = '/T:(\d+)\((\d{2}:\d{2}:\d{2})\)\[([^]]+)\]\ /';
	$removed = preg_replace($pattern, '', $trimmed);
	return strstr($removed, '[S');
}

$result = "";

function readChar($str, &$idx, $expected_char) {
	$char = $str[$idx++];
	if ($char != $expected_char)
	{
		throw new Exception("Expected ".$expected_char." not ".$char." at position ".$idx);
	}
	return $char;
}

function readType($str, &$idx) {
	readChar($str, $idx, "[");
	$type = substr($str, $idx, 3);
	$idx += 3;
	readChar($str, $idx, "]");

	return $type;
}

function readNumber($str, &$idx) {
	$number = "";
	while (is_numeric($str[$idx]))
	{
		$number .= $str[$idx++];
	}
	return $number;
}

function readCount($str, &$idx) {
	readChar($str, $idx, "[");
	$count = readNumber($str, $idx);
	readChar($str, $idx, "]");
	
	return intval($count);
}

function readKey($str, &$idx) {
	global $result;
	$key = "";
	while ($str[$idx] != "=") {
		$key .= $str[$idx++];
	}
	$result .= '"'.$key.'":';
}

function readValue($str, &$idx) {
	readChar($str, $idx, "{");
	parseVariant($str, $idx);
	readChar($str, $idx, "}");
}

function readStruct($str, &$idx, $count) {
	global $result;
	readChar($str, $idx, "[");
	$result .= "{";
	for ($i = 0; $i < $count; $i++)
	{
		if ($i != 0)
		{
			$result .= ",";
		}
		readKey($str, $idx);
		readChar($str, $idx, "=");
		readValue($str, $idx);
	}
	readChar($str, $idx, "]");
	$result .= "}";
}

function readArray($str, &$idx, $count) {
	global $result;
	readChar($str, $idx, "[");
	$result .= "[";
	for ($i = 0; $i < $count; $i++)
	{
		if ($i != 0)
		{
			$result .= ",";
		}
		readNumber($str, $idx);
		readChar($str, $idx, "=");
		readValue($str, $idx);
	}
	readChar($str, $idx, "]");
	$result .= "]";
}

function readInteger($str, &$idx) {
	global $result;
	$value = "";
	while ($str[$idx] != ";")
	{
		$value .= $str[$idx++];
	}
	$result .= $value;
}

function readLength($str, &$idx) {
	readChar($str, $idx, "[");
	readChar($str, $idx, "L");
	readChar($str, $idx, ":");
	$length = readNumber($str, $idx);
	readChar($str, $idx, "]");
	return $length;
}

function readString($str, &$idx) {
	global $result;
	$value = "";
	while ($str[$idx] != "[")
	{
		$value .= $str[$idx++];
	}
	$result .= '"'.$value.'"';
	
	readLength($str, $idx);
}

function readNULL() {
	global $result;
	$result .= "{}";
}

function readBinary($str, &$idx) {
	global $result;
	$result .= '"'.readNumber($str, $idx);
	$length = readLength($str, $idx);
	$result .= '('.$length.')"';
}

function readBoolean($str, &$idx) {
	global $result;
	if ($str[$idx] == "F")
	{
		$result .= "false";
	}
	else
	{
		$result .= "true";
	}
	$idx++;
}


function parseVariant($str, &$idx)
{
	global $result;
	$type = readType($str, $idx);
	readChar($str, $idx, ":");
	//echo $type, PHP_EOL;
	if ($type == "STU" || $type == "ARY")
	{
		$count = readCount($str, $idx);
		//echo $count, PHP_EOL;
		if ($type == "STU")
		{
			readStruct($str, $idx, $count);
		}
		else
		{
			readArray($str, $idx, $count);
		}
	}
	else if ($type == "INT" || $type == "LNG")
	{
		readInteger($str, $idx);
	}
	else if ($type == "STR" || $type == "WST")
	{
		readString($str, $idx);
	}
	else if ($type == "NUL")
	{
		readNULL();
	}
	else if ($type == "BIN")
	{
		readBinary($str, $idx);
	}
	else if ($type == "BOL")
	{
		readBoolean($str, $idx);
	}
	readChar($str, $idx, ";");
}

//get the q parameter from URL
$log=$_POST["log"];

$str=preprocess($log);
$idx = 0;
parseVariant($str, $idx);

$json = json_decode($result);
$response = json_encode($json, JSON_PRETTY_PRINT);

//output the response
echo $response;
?>