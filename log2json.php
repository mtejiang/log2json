<?php
namespace Log2Json;

use Exception;

abstract class LogConverter
{
	abstract protected function readNULL();
	abstract protected function readBoolean();
	abstract protected function readInteger();
	abstract protected function readLong();
	abstract protected function readDouble();
	abstract protected function readString();
	abstract protected function readWideString();
	abstract protected function readCustomString();
	abstract protected function readBinary();
	abstract protected function readTime();
	abstract protected function readArray();
	abstract protected function readStruct();
	abstract protected function readObject();


	public function getResult() {
		return $this->_result;
	}

	protected $_log = "";
	protected $_idx = 0;
	protected $_result = "";

	protected function readChar($expected_char) {
		if ($this->_log == "")
		{
			throw new Exception("Expected ".$expected_char." but log is empty");
		}
		$char = $this->_log[$this->_idx++];
		if ($char != $expected_char)
		{
			throw new Exception("Expected ".$expected_char." not ".$char." at position ".$this->_idx);
		}
		return $char;
	}

	protected function readNumber() {
		$number = "";
		while (is_numeric($this->_log[$this->_idx]))
		{
			$number .= $this->_log[$this->_idx++];
		}
		return $number;
	}

	protected function readUntil($char) {
		$value = "";
		while ($this->_log[$this->_idx] != $char)
		{
			$value .= $this->_log[$this->_idx++];
		}
		return $value;
	}

	protected function readType() {
		$this->readChar("[");
		$type = $this->readUntil("]");
		$this->readChar("]");
		$this->readChar(":");
		return $type;
	}
	
	abstract public function parseLog();
}

class FixedTypeLengthLogConverter extends LogConverter
{
	function __construct($log) {
		$this->_log = $log;
	}

	private function readUntilSemicolon($wrapper_char) {
		$value = $this->readUntil(";");
		$this->_result .= $wrapper_char.$value.$wrapper_char;
	}

	private function readLength($first_char) {
		$this->readChar($first_char);
		$this->readChar("L");
		$this->readChar(":");
		$length = $this->readNumber();
		$this->readChar("]");
		return $length;
	}

	private function readValue() {
		$this->readChar("{");
		$this->parseLog();
		$this->readChar("}");
	}
	private function readKey() {
		$key = $this->readUntil("=");
		$this->_result .= '"'.$key.'":';
	}

	private function readCount() {
		$this->readChar("[");
		$count = $this->readNumber();
		$this->readChar("]");
		
		return intval($count);
	}

	protected function readNULL() {
		$this->_result .= "{}";
	}

	protected function readBoolean() {
		if ($this->_log[$this->_idx] == "F")
		{
			$this->_result .= "false";
		}
		else
		{
			$this->_result .= "true";
		}
		$this->_idx++;
	}

	protected function readInteger() {
		$this->readUntilSemicolon('');
	}

	protected function readLong() {
		$this->readUntilSemicolon('');
	}

	protected function readDouble() {
		$this->readUntilSemicolon('');
	}

	protected function readString() {
		$value = $this->readUntil("[");
		
		$length = $this->readLength("[");
		$this->_result .= '"'.$value.'('.$length.')"';
	}

	protected function readWideString() {
		$this->readString();
	}

	protected function readCustomString() {
		$value = $this->readUntil("[");

		$this->readChar("[");
		$this->readChar("C");
		$this->readChar(":");
		$charset = $this->readUntil(" ");
		$length = $this->readLength(" ");
		$this->_result .= '"'.$value.'('.$charset.','.$length.')"';
	}

	protected function readBinary() {
		$this->readString();
	}

	protected function readTime() {
		$this->readUntilSemicolon('"');
	}

	protected function readArray() {
		$count = $this->readCount();
		$this->readChar("[");
		$this->_result .= "[";
		for ($i = 0; $i < $count; $i++)
		{
			if ($i != 0)
			{
				$this->_result .= ",";
			}
			$this->readNumber();
			$this->readChar("=");
			$this->readValue();
		}
		$this->readChar("]");
		$this->_result .= "]";
	}

	protected function readStruct() {
		$count = $this->readCount();
		$this->readChar("[");
		$this->_result .= "{";
		for ($i = 0; $i < $count; $i++)
		{
			if ($i != 0)
			{
				$this->_result .= ",";
			}
			$this->readKey();
			$this->readChar("=");
			$this->readValue();
		}
		$this->readChar("]");
		$this->_result .= "}";
	}

	protected function readObject() {
		$this->readUntilSemicolon('"');
	}

	public function parseLog() {
		$type = $this->readType();

		switch ($type) {
			case 'NUL':
				$this->readNULL();
				break;
			case 'BOL':
				$this->readBoolean();
				break;
			case 'INT':
				$this->readInteger();
				break;
			case 'LNG':
				$this->readLong();
				break;
			case 'DBL':
				$this->readDouble();
				break;
			case 'STR':
				$this->readString();
				break;
			case 'WST':
				$this->readWideString();
				break;
			case 'CST':
				$this->readCustomString();
				break;
			case 'BIN':
				$this->readBinary();
				break;
			case 'TIM':
				$this->readTime();
				break;
			case 'ARY':
				$this->readArray();
				break;
			case 'STU':
				$this->readStruct();
				break;
			case 'OBJ':
				$this->readObject();
				break;
			default:
				throw new Exception("Unknown type ".$type);
				break;
		}
		$this->readChar(";");
	}
}

class LogConverterWrapper
{
	private $_converter;

	public function __construct(LogConverter $converter) {
		$this->_converter = $converter;
	}

	public function parseLog() {
		$this->_converter->parseLog();
	}
}

function preprocess($original_log) {
	$trimmed = trim($original_log);
	$trimmed = str_replace(array("\n\r", "\n", "\r"), '', $trimmed);
	$pattern = '/T:(\d+)\((\d{2}:\d{2}:\d{2})\)\[([^]]+)\]\ /';
	$removed = preg_replace($pattern, '', $trimmed);
	return strstr($removed, '[S');
}

//$log = "[STU]:[1][cmd={[INT]:-2;}];";
//$log = "[STU]:[4][cmd={[INT]:2010;}mboxa={[INT]:0;}mboxid={[STR]:1_eli#####_01_100000+z[L:22];}msgid={[STR]:1tbiAQAFBlcfBi8AAgAAmh[L:22];}];";
//$log = "[STU]:[1][msginfo={[BIN]:1631746269415141[L:967];}];";
//$log = "[STU]:[6][cmd={[INT]:2008;}ip={[WST]:192.168.33.1[L:12];}mboxid={[STR]:1_eli#####_01_100000+z[L:22];}modlist={[STU]:[2][flag={[LNG]:2;}fmask={[LNG]:2;}];}msglist={[ARY]:[1][0={[STR]:1tbiAQAFBlcfBi8AAgAAmh[L:22];}];}tdn={[STR]:un=eli,dn=abc.com,dd=1,ou=(org=a;pro=1)[L:39];}];";
//$log = "[STU]:[1][supportkeys={[STU]:[2][cmdlist={[ARY]:[9][0={[INT]:2030;}1={[INT]:2034;}2={[INT]:2035;}3={[INT]:2036;}4={[INT]:5100;}5={[INT]:5310;}6={[INT]:5011;}7={[INT]:2060;}8={[INT]:2061;}];}mboxfileattr={[STU]:[3][b={[NUL]:;}s={[NUL]:;}u={[NUL]:;}];}];}];";
//$log = "[STU]:[4][cmd={[INT]:1003;}detectpwdattack={[BOL]:F;}tdn={[STR]:un=eli,dn=abc.com,dd=1,ou=(org=a;pro=1)[L:39];}userattr={[STU]:[3][app_login_notify={[NUL]:;}sms_login_notify={[NUL]:;}smsaddr={[NUL]:;}];}];";
//$log = "T:1684821760(10:09:20)[app.ud.param:Debug] result: [STU]:[2][list={[ARY]:[11][0={[STU]:[8][c={[STU]:[1][ban={[INT]:0;}];}cnt={[INT]:1;}flag={[LNG]:0;}ip={[STR]:192.168.33.1[L:12];}lastlogintime={[TIM]:2017-02-23 10:08:29;}logintime={[TIM]:2017-02-23 10:08:29;}logintype={[INT]:1;}result={[INT]:1;}];}1={[STU]:[8][c={[STU]:[1][ban={[INT]:0;}];}cnt={[INT]:2;}flag={[LNG]:0;}ip={[STR]:192.168.33.1[L:12];}lastlogintime={[TIM]:2017-02-22 11:21:07;}logintime={[TIM]:2017-02-22 11:05:44;}logintype={[INT]:1;}result={[INT]:1;}];}2={[STU]:[8][c={[STU]:[1][ban={[INT]:0;}];}cnt={[INT]:3;}flag={[LNG]:0;}ip={[STR]:192.168.33.1[L:12];}lastlogintime={[TIM]:2017-02-22 11:09:56;}logintime={[TIM]:2017-02-22 11:09:56;}logintype={[INT]:3;}result={[INT]:1;}];}3={[STU]:[8][c={[STU]:[1][ban={[INT]:0;}];}cnt={[INT]:1;}flag={[LNG]:0;}ip={[STR]:192.168.33.1[L:12];}lastlogintime={[TIM]:2017-02-21 14:45:11;}logintime={[TIM]:2017-02-21 14:45:11;}logintype={[INT]:1;}result={[INT]:1;}];}4={[STU]:[8][c={[STU]:[1][ban={[INT]:0;}];}cnt={[INT]:1;}flag={[LNG]:0;}ip={[STR]:192.168.33.1[L:12];T:1684821760(10:09:20)[app.ud.param:Debug] }lastlogintime={[TIM]:2017-02-20 13:59:09;}logintime={[TIM]:2017-02-20 13:59:09;}logintype={[INT]:1;}result={[INT]:1;}];}5={[STU]:[8][c={[STU]:[1][ban={[INT]:0;}];}cnt={[INT]:1;}flag={[LNG]:0;}ip={[STR]:192.168.33.1[L:12];}lastlogintime={[TIM]:2017-02-15 10:24:02;}logintime={[TIM]:2017-02-15 10:24:02;}logintype={[INT]:1;}result={[INT]:1;}];}6={[STU]:[8][c={[STU]:[1][ban={[INT]:0;}];}cnt={[INT]:1;}flag={[LNG]:0;}ip={[STR]:192.168.33.1[L:12];}lastlogintime={[TIM]:2017-02-14 13:17:48;}logintime={[TIM]:2017-02-14 13:17:48;}logintype={[INT]:1;}result={[INT]:1;}];}7={[STU]:[8][c={[STU]:[1][ban={[INT]:0;}];}cnt={[INT]:1;}flag={[LNG]:0;}ip={[STR]:192.168.33.1[L:12];}lastlogintime={[TIM]:2017-02-14 10:56:40;}logintime={[TIM]:2017-02-14 10:56:40;}logintype={[INT]:1;}result={[INT]:1;}];}8={[STU]:[8][c={[STU]:[1][ban={[INT]:0;}];}cnt={[INT]:2;}flag={[LNG]:0;}ip={[STR]:192.168.33.1[L:12];}lastlogintime={[TIM]:2017-02-10 16:47:09;}logintime={[TIM]:2017-02-10 16:45:45;}logintype={[INT]:1;}result={[INT]:1;}];}9={[STU]:[8][c=T:1684821760(10:09:20)[app.ud.param:Debug] {[STU]:[1][ban={[INT]:0;}];}cnt={[INT]:2;}flag={[LNG]:0;}ip={[STR]:192.168.33.1[L:12];}lastlogintime={[TIM]:2017-02-10 16:45:39;}logintime={[TIM]:2017-02-10 16:45:31;}logintype={[INT]:1;}result={[INT]:2;}];}10={[STU]:[8][c={[STU]:[1][ban={[INT]:0;}];}cnt={[INT]:3;}flag={[LNG]:0;}ip={[STR]:192.168.33.1[L:12];}lastlogintime={[TIM]:2017-02-10 15:15:38;}logintime={[TIM]:2017-02-10 15:15:28;}logintype={[INT]:3;}result={[INT]:1;}];}];}total={[INT]:11;}];";

//get the q parameter from URL
$log=$_POST["log"];

$log=preprocess($log);

$converter = new FixedTypeLengthLogConverter($log);
$wrapper = new LogConverterWrapper($converter);
$wrapper->parseLog();

$result = $converter->getResult();

//echo $result, PHP_EOL;

$json = json_decode($result);
$response = json_encode($json, JSON_PRETTY_PRINT);

//output the response
echo $response;
?>
