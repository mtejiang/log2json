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
	abstract protected function readArray($count);
	abstract protected function readStruct($count);
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
			$excerpt = substr($this->_log, $this->_idx - 10, 10);
			throw new Exception("Expected ".$expected_char." not ".$char." at position ".$this->_idx.", excerpt: ".$excerpt);
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

	protected function readType() {
		$type = parent::readType();
		$this->readChar(":");
		return $type;
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

	protected function readArray($count) {
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

	protected function readStruct($count) {
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
				$count = $this->readCount();
				$this->readArray($count);
				break;
			case 'STU':
				$count = $this->readCount();
				$this->readStruct($count);
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

class VariableTypeLengthLogConverter extends LogConverter
{
	function __construct($log) {
		$this->_log = $log;
	}

	private function readUntilSemicolon($wrapper_char) {
		$value = $this->readUntil(";");
		$this->_result .= $wrapper_char.$value.$wrapper_char;
	}

	private function readEnd() {
		$this->readChar("[");
		$this->readChar("E");
		$this->readChar("N");
		$this->readChar("D");
		$this->readChar("]");
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
		$this->parseLog();
	}
	private function readKey() {
		$key = $this->readUntil(":");
		$this->_result .= '"'.$key.'":';
	}

	private function readCount() {
		$this->readChar("[");
		$count = $this->readNumber();
		$this->readChar("]");
		
		return intval($count);
	}

	protected function readType() {
		$type = parent::readType();
		$count = 0;
		$pos = strpos($type, '(');
		if ($pos === false) {
			$this->readChar(':');
		} else {
			$count = substr($type, $pos+1, strlen($type)-$pos-2);
			$type = substr($type, 0, $pos);
		}

		return [$type, $count];
	}

	protected function readNULL() {
		$this->_result .= "{}";
	}

	protected function readBoolean() {
		$value = '';
		$this->readChar('[');
		while ($this->_log[$this->_idx] != ']') {
			$value .= $this->_log[$this->_idx];
			$this->_idx++;
		}
		if ($value == "False")
		{
			$this->_result .= "false";
		}
		else
		{
			$this->_result .= "true";
		}
		$this->readChar(']');
	}

	protected function readInteger() {
		while (is_numeric($this->_log[$this->_idx])) {
			$this->_result .= $this->_log[$this->_idx];
			$this->_idx++;
		}
	}

	protected function readLong() {
		$this->readInteger();
	}

	protected function readDouble() {
		$this->readInteger('');
	}

	protected function readString() {
		$pos = strpos($this->_log, '[END]', $this->_idx);
		if ($pos === false) {
			throw new Exception("Can't find [END] while read string");
		} else {
			$this->_result .= '"';
			while ($this->_idx < $pos) {
				$char = $this->_log[$this->_idx];
				if ($char === '"') {
					$this->_result .= '\\';
				}
				$this->_result .= $char;
				$this->_idx++;
			}
			for ($i = $this->_idx; $i < $pos; $i++) {
				$this->_result .= $value[$i];
			}
			$this->_result .= '"';

			$this->_idx = $pos;
			$this->readEnd();
		}
	}

	protected function readWideString() {
		// $this->readChar('(');
		// $count = $this->readNumber();
		// echo intval($count);
		// echo PHP_EOL;
		// $this->readUntil(')');
		// $this->readchar(')');
		// $value = '';
		// for ($i = 0; $i < $count*2; $i++) {
		// 	echo 'charactor: '.$this->_log[$this->_idx], PHP_EOL;
		// 	$value .= $this->_log[$this->_idx++];
		// }
		// echo 'value: '.$value, PHP_EOL;

		$value = '';
		$char = $this->_log[$this->_idx];
		while ($char !== ',' && $char !== '}') {
			if ($char === '"') {
				$value .= '\\';
			}
			$value .= $char;
			$this->_idx++;
			$char = $this->_log[$this->_idx];
		}
		$this->_result .= '"'.$value.'"';
	}

	protected function readCustomString() {
		$this->readWideString();
	}

	protected function readBinary() {
		$this->readInteger();
	}

	protected function readTime() {
		// $time = "";
		// while($this->_log[$this->_idx] === '-' ||
		// 	$this->_log[$this->_idx] === ' ' ||
		// 	$this->_log[$this->_idx] === ':' ||
		// 	ctype_digit($this->_log[$this->_idx]))
		// {
		// 	$time .= $this->_log[$this->_idx];
		// 	$this->_idx++;
		// }
		// $this->_result .= '"'.$time.'"';
		$this->readWideString();
	}

	protected function readArray($count) {
		$this->readChar("{");
		$this->_result .= "[";
		for ($i = 0; $i < $count; $i++)
		{
			$this->readValue();
			if ($i < $count-1)
			{
				$this->readChar(',');
				$this->_result .= ',';
			}
		}
		$this->readChar("}");
		$this->_result .= "]";
	}

	protected function readStruct($count) {
		$this->readChar("{");
		$this->_result .= "{";
		for ($i = 0; $i < $count; $i++)
		{
			$this->readKey();
			$this->readChar(":");
			$this->readValue();
			if ($i < $count-1)
			{
				$this->readChar(',');
				$this->_result .= ',';
			}
		}
		$this->readChar("}");
		$this->_result .= "}";
	}

	protected function readObject() {
		$this->readUntilSemicolon('"');
	}

	public function parseLog() {
		list($type, $count) = $this->readType();
		//echo 'type: '.$type, PHP_EOL;
		//echo 'count: '.$count, PHP_EOL;

		switch ($type) {
			case 'NUL':
				$this->readNULL();
				break;
			case 'Bool':
				$this->readBoolean();
				break;
			case 'Int':
				$this->readInteger();
				break;
			case 'Long':
				$this->readLong();
				break;
			case 'Double':
				$this->readDouble();
				break;
			case 'Str':
				$this->readString();
				break;
			case 'WStr':
				$this->readWideString();
				break;
			case 'CStr':
				$this->readCustomString();
				break;
			case 'Bin':
				$this->readBinary();
				break;
			case 'Time':
				$this->readTime();
				break;
			case 'Ary':
				$this->readArray($count);
				break;
			case 'Struct':
				$this->readStruct($count);
				break;
			case 'Obj':
				$this->readObject();
				break;
			default:
				throw new Exception("Unknown type ".$type);
				break;
		}
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

//$log = "T:2877286144(13:42:42)[App.Archive-AQAAfwDn7+_Sx69YcAIAAA--.3W:Debug] From 127.0.0.1[p:105][socket:264] receive cmd:[Struct(6)]{_Q:[Str]:AQAAfwDn7+_Sx69YcAIAAA--.3W[END],cmd:[Int]:1,ctime:[Time]:2017-02-24 13:42:42,ext:[Struct(2)]{sip:[Str]:192.168.33.1[END],tid:[Str]:AQAAfwDn7+_Sx69YcAIAAA--.3W[END]},info:[Struct(2)]{abc@abc.com:[Struct(4)]{ed:[Int]:360,mid:[Str]:1tbiAQALCFcfBjYAAQAEs-[END],rcpt:[Bool]:[True],tdn:[Str]:un=abc,dn=abc.com,dd=1,ou=(org=a;pro=1)[END]},eli@abc.com:[Struct(4)]{ed:[Int]:360,from:[Bool]:[True],mid:[Str]:[END],tdn:[Str]:un=eli,dn=abc.com,dd=1,ou=(org=a;pro=1)[END]}},size:[Int]:1163} ";
// $log = 'T:3920836352(15:18:38)[App.Archive-:Debug] From 127.0.0.1[p:600][socket:265] receive cmd:[Struct(8)]{aqry:[Struct(5)]{bidirection:[Bool]:[False],ctimea:[Time]:2017-02-24 00:00:00,ctimeb:[Time]:2017-02-24 23:59:59,dn:[Str]:+o -r[END],etime:[Bool]:[True]},cmd:[Int]:15,extaid:[Bool]:[True],off:[Int]:0,qry:[WStr]:(5 chars)+"测试",reverse:[Bool]:[True],size:[Int]:500,sort:[Str]:ctime[END]} ';
// $log = 'T:3920836352(16:00:04)[App.Archive-:Debug] From 127.0.0.1[p:600][socket:265] receive cmd:[Struct(8)]{aqry:[Struct(5)]{bidirection:[Bool]:[False],ctimea:[Time]:2017-02-24 00:00:00,ctimeb:[Time]:2017-02-24 23:59:59,dn:[Str]:+"u$abc@abc.com" -r[END],etime:[Bool]:[True]},cmd:[Int]:15,extaid:[Bool]:[True],off:[Int]:0,qry:[WStr]:(8 chars)+"历史记录吧",reverse:[Bool]:[True],size:[Int]:500,sort:[Str]:ctime[END]} ';
// $log = 'T:2876233472(16:18:29)[App.Archive-:Debug] cmd:16 send back res:[Struct(5)]{Result:[Int]:0,ResultMessage:[Str]:OK![END],aid:[Ary(0)]{},all:[Int]:0,utime:[Long]:0}';
/*
$log = <<<'EOF'
T:2876233472(16:38:00)[App.Archive-:Debug] cmd:3 send back res:[Struct(11)]{Result:[Int]:0,ResultMessage:[Str]:parse ok[END],p0:[Int]:10,p
1:[Ary(1)]{[Ary(3)]{[Int]:0,[Int]:0,[Str]:Received: by ajax-webmail-localhost.localdomain (Coremail) ; Thu, 12 Jan
T:2876233472(16:38:00)[App.Archive-:Debug]  2017 17:08:24 +0800 (GMT+08:00)
T:2876233472(16:38:00)[App.Archive-:Debug] X-Originating-IP: [192.168.33.1]
T:2876233472(16:38:00)[App.Archive-:Debug] Date: Thu, 12 Jan 2017 17:08:24 +0800 (GMT+08:00)
T:2876233472(16:38:00)[App.Archive-:Debug] X-CM-HeaderCharset: UTF-8
T:2876233472(16:38:00)[App.Archive-:Debug] From: test@abc.com
T:2876233472(16:38:00)[App.Archive-:Debug] To: abc@abc.com
T:2876233472(16:38:00)[App.Archive-:Debug] Subject: 123
T:2876233472(16:38:00)[App.Archive-:Debug] X-Priority: 3
T:2876233472(16:38:00)[App.Archive-:Debug] X-Mailer: Coremail Webmail Server Version XT5.0.3 build 20160413(83301.8609)
T:2876233472(16:38:00)[App.Archive-:Debug]  Copyright (c) 2002-2017 www.mailtech.cn demo-test
T:2876233472(16:38:00)[App.Archive-:Debug] Content-Type: multipart/alternative; 
T:2876233472(16:38:00)[App.Archive-:Debug]      boundary="----=_Part_2_446208731.1484212104583"
T:2876233472(16:38:00)[App.Archive-:Debug] MIME-Version: 1.0
T:2876233472(16:38:00)[App.Archive-:Debug] Message-ID: <7529c481.0.15991ef6d88.Coremail.test@abc.com>
T:2876233472(16:38:00)[App.Archive-:Debug] X-Coremail-Locale: zh_CN
T:2876233472(16:38:00)[App.Archive-:Debug] X-CM-TRANSID:AQAAfwB3ntGIR3dYGAAAAA--.0W
T:2876233472(16:38:00)[App.Archive-:Debug] X-CM-SenderInfo: hwhv3qxdefhudrp/1tbiAQASDlcfBi4AAAAAs9
T:2876233472(16:38:00)[App.Archive-:Debug] X-Coremail-Antispam: 1Ur529EdanIXcx71UUUUU7IcSsGvfJ3iIAIbVAYjsxI4VWxJw
T:2876233472(16:38:00)[App.Archive-:Debug]      CS07vEb4IE77IF4wCS07vE1I0E4x80FVAKz4kxMIAIbVAFxVCaYxvI4VCIwcAKzIAtYxBI
T:2876233472(16:38:00)[App.Archive-:Debug]      daVFxhVjvjDU=
T:2876233472(16:38:00)[App.Archive-:Debug] [END]}},p2:[Int]:
T:2876233472(16:38:00)[App.Archive-:Debug] 2,p3:[Ary(2)]{[Struct(15)]{n0:[Int]:1,n1:[Int]:0,n10:[Str]:text/plain; charset=UTF-8[END],n11:[
Str]:[END],n12:[Bool]:[True],n13:[Str]:7bit[END],n14:[Str]:[END],n2:[Int]:0,n3:[Bool]:[False],n4:[Int]:988,n5:[Int]:6,n6:[Int]:2,n7:[Int]:
2,n8:[Int]:512,n9:[Str]:[END]},[Struct(15)]{n0:[Int]:2,n1:[Int]:0,n10:[Str]:text/html; charset=UTF-8[END],n11:[Str]:[END],n12:[Bool]:[True
],n13:[Str]:7bit[END],n14:[Str]:[END],n2:[Int]:0,n3:[Bool]:[False],n4:[Int]:1111,n5:[Int]:6,n6:[Int]:2,n7:[Int]:5,n8:[Int]:0,n9:[Str]:[END
]}},p4:[Int]:5,p5:[Struct(3)]{4:[Bool]:[True],p0:[Int]:10,p4:[Int]:5},p6:[Int]:289,p7:[Int]:872,p8:[Int]:1161}
EOF;
*/

//get the q parameter from URL
$log=$_POST["log"];

$log=preprocess($log);
//echo $log, PHP_EOL;

$converter = '';
if (substr($log, 0, 5) === '[STU]') {
	$converter = new FixedTypeLengthLogConverter($log);
} else {
	$converter = new VariableTypeLengthLogConverter($log);
}
$wrapper = new LogConverterWrapper($converter);
$wrapper->parseLog();
$result = $converter->getResult();
//echo $result, PHP_EOL;

$json = json_decode($result);
$response = json_encode($json, JSON_PRETTY_PRINT);

//output the response
echo $response;
?>
