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
			$excerpt = substr($this->_log, $this->_idx - 16, 16);
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

	protected function readUntil($needle) {
		$value = "";
		$remain = substr($this->_log, $this->_idx);
		$pos = strpos($remain, $needle);
		if ($pos === false) {
			throw new Exception("Can't find ".$needle." after position ".$this->_idx);
		} else {
			$value = substr($remain, 0, $pos);
			$this->_idx += $pos;
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
		$value = $this->readUntil("[L:");
		$length = $this->readLength("[");
		$this->_result .= json_encode($value.'('.$length.')', JSON_UNESCAPED_UNICODE);
	}

	protected function readWideString() {
		$this->readString();
	}

	protected function readCustomString() {
		$value = $this->readUntil("[C:");

		$this->readChar("[");
		$this->readChar("C");
		$this->readChar(":");
		$charset = $this->readUntil(" ");
		$length = $this->readLength(" ");
		$this->_result .= json_encode('"'.$value.'('.$charset.','.$length.')"', JSON_UNESCAPED_UNICODE);
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
		$value = $this->readUntil("[END]");
		$this->readEnd();
		$this->_result .= json_encode($value, JSON_UNESCAPED_UNICODE);
	}

	protected function readWideString() {
		$value = '';
		preg_match('/[,}]/', substr($this->_log, $this->_idx), $matches, PREG_OFFSET_CAPTURE);
		if (!$matches) {
			throw new Exception("Can't find ',' or '}' after position ".$this->_idx);
		} else {
			$value = substr($this->_log, $this->_idx, $matches[0][1]);
			$this->_idx += $matches[0][1];
		}
		$this->_result .= json_encode($value, JSON_UNESCAPED_UNICODE);
	}

	protected function readCustomString() {
		$this->readWideString();
	}

	protected function readBinary() {
		$this->readInteger();
	}

	protected function readTime() {
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
//$log = 'T:3920836352(15:18:38)[App.Archive-:Debug] From 127.0.0.1[p:600][socket:265] receive cmd:[Struct(8)]{aqry:[Struct(5)]{bidirection:[Bool]:[False],ctimea:[Time]:2017-02-24 00:00:00,ctimeb:[Time]:2017-02-24 23:59:59,dn:[Str]:+o -r[END],etime:[Bool]:[True]},cmd:[Int]:15,extaid:[Bool]:[True],off:[Int]:0,qry:[WStr]:(5 chars)+"测试",reverse:[Bool]:[True],size:[Int]:500,sort:[Str]:ctime[END]} ';
//$log = 'T:3920836352(16:00:04)[App.Archive-:Debug] From 127.0.0.1[p:600][socket:265] receive cmd:[Struct(8)]{aqry:[Struct(5)]{bidirection:[Bool]:[False],ctimea:[Time]:2017-02-24 00:00:00,ctimeb:[Time]:2017-02-24 23:59:59,dn:[Str]:+"u$abc@abc.com" -r[END],etime:[Bool]:[True]},cmd:[Int]:15,extaid:[Bool]:[True],off:[Int]:0,qry:[WStr]:(8 chars)+"历史记录吧",reverse:[Bool]:[True],size:[Int]:500,sort:[Str]:ctime[END]} ';
//$log = 'T:2876233472(16:18:29)[App.Archive-:Debug] cmd:16 send back res:[Struct(5)]{Result:[Int]:0,ResultMessage:[Str]:OK![END],aid:[Ary(0)]{},all:[Int]:0,utime:[Long]:0}';
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
/*
$log = <<<'EOF'
T:3664185088(16:17:11)[app.ud.param-W001AAudBXOgaIRYJHKS:Debug] param: [STU]:[8][_Q={[STR]:W001AAudBXOgaIRYJHKS[L:20];}cmd={[INT]:2005;}fl
ags={[INT]:8;}mboxid={[STR]:1_chen####_01_1000007c[L:22];}miscinfo={[STU]:[6][hmid={[STR]:<000c01d26a52$6a08cd70$3e1a6850$@abachem.com>[L:
45];}letterst={[ARY]:[6][0={[ARY]:[7][0={[INT]:0;}1={[STR]:70c:1a6d6:r:n:n[L:15];}2={[WST]:[L:0];}3={[WST]:[L:0];}4={[INT]:108246;}5={[INT
]:0;}6={[STU]:[2][headerfields={[STR]:Cc^=?UTF-8?B?X2plc3NpZXpodeacseS9qemdkg==?= <jessiezhu@abachem.com>^Content-Language^zh-cn^Content-T
ype^multipart/related;__        boundary="----=_NextPart_000_000D_01D26A95.782C82A0"^Date^Mon, 9 Jan 2017 16^^28^^53 +0800^From^"hujm" <hu
jm@abachem.com>^MIME-Version^1.0^Message-ID^<000c01d26a52$6a08cd70$3e1a6850$@abachem.com>^Received^from hjmPC (unknown [101.81.252.98])__by mail (Coremail) with SMTP id AQAAfwCHODnHSXNYf5EAAA--.5004S2;__       Mon, 09 Jan 2017 16^^28^^56 +0800 (CST)^Subject^=?UTF-8?B?55uW56ug
56Gu6K6kLS3msYfogZTmmJPmnI3liqHlj4rova/ku7bplIDllK4=?=__        =?UTF-8?B?5ZCI5ZCM?=^Thread-Index^AdJqUk6pbT6cDuU/T4+Wb9zf9Q7T+w==^To^=?UT
F-8
T:3664185088(16:17:11)[app.ud.param-W001AAudBXOgaIRYJHKS:Debug] ?Q?=5FCaiTong_=E8=94=A1_=E5=BD=A4?= <caitong@abachem.com>^X-CM-TRANSID^AQA
AfwCHODnHSXNYf5EAAA--.5004S2^X-Coremail-Antispam^1UD129KBjDUn29KB7ZKAUJUUUUU529EdanIXcx71UUUUU7v73__    VFW2AGmfu7bjvjm3AaLaJ3UjIYCTnIWjp_
UUUYP7AC8VAFwI0_Jr0_Gr1l1xkIjI8I6I8E__  6xAIw20EY4v20xvaj40_Wr0E3s1l1IIY67AEw4v_Jr0_Jr4l8cAvFVAK0II2c7xJM28Cjx__        kF64kEwVA0rcxSw2x7
M28EF7xvwVC0I7IYx2IY67AKxVW8JVW5JwA2z4x0Y4vE2Ix0cI8I__  cVCY1x0267AKxVW8JVWxJwA2z4x0Y4vEx4A2jsIE14v26r4UJVWxJr1l84ACjcxK6I8E87__        Iv
6xkF7I0E14v26F4UJVW0owAS0I0E0xvYzxvE52x082IY62kv0487M2AExVA0xI801c8C__  04v7Mc02F40Eb7x2x7xS6r4j6ryUMc02F40E57IF67AEF4xIwI1l5I8CrVAKz4kIr2
xC04__  v26r4j6ryUMc02F40E42I26xC2a48xMcIj6xIIjxv20xvE14v26r126r1DMcIj6I8E87Iv__        67AKxVWxJVW8Jr1lOx8S6xCaFVCjc4AY6r1j6r4UM4x0x7Aq67
IIx4CEVc8vx2IErcIFxw__  Cjr7xvwVCIw2I0I7xG6c02F41l4I8I3I0E4IkC6x0Yz7v_Jr0_Gr1lx2IqxVAqx4xG67AK__        xVWUGVWUWwC20s026x8GjcxK67AKxVWUGV
WUWwC2zVAF1VAY17CE14v26r1Y6r17MIIF0x__  vE2Ix0cI8IcVAFwI0_Jr0_JF4lIxAIcVC0I7IYx2IY6xkF7I0E14v26r1j6r4UMIIF0xvE__        42xK8VAvwI8IcIk0rV
WrZr1j6s0DMIIF0xvEx4A2jsIE14
T:3664185088(16:17:11)[app.ud.param-W001AAudBXOgaIRYJHKS:Debug] v26r1j6r4UMIIF0xvEx4A2js__      IEc7CjxVAFwI0_Jr0_GrUvcSsGvfC2KfnxnUUI43ZE
Xa7VU1S4iUUUUUU==^X-Mailer^Microsoft Outlook 14.0[L:1778];}imapinfo={[STR]:multipart/related;__ boundary="----=_NextPart_000_000D_01D26A95
.782C82A0":n:n:n:n:1461:1802[L:91];}];}];}1={[ARY]:[7][0={[INT]:1896;}1={[STR]:7c7:ea5:a:n:n:1[L:15];}2={[WST]:[L:0];}3={[WST]:[L:0];}4={[
INT]:3749;}5={[INT]:0;}6={[STU]:[1][imapinfo={[STR]:multipart/alternative;__    boundary="----=_NextPart_001_000E_01D26A95.782C82A0":n:n:n
:n:109:93[L:92];}];}];}2={[ARY]:[7][0={[INT]:2038;}1={[STR]:848:48:t:b:8:2[L:14];}2={[WST]:[L:0];}3={[WST]:[L:0];}4={[INT]:51;}5={[INT]:8;
}6={[STU]:[1][imapinfo={[STR]:text/plain;__     charset="UTF-8":n:n:n:n:2:80[L:42];}];}];}3={[ARY]:[7][0={[INT]:2237;}1={[STR]:918:d25:h:q
:8:2[L:15];}2={[WST]:[L:0];}3={[WST]:[L:0];}4={[INT]:2865;}5={[INT]:8;}6={[STU]:[1][imapinfo={[STR]:text/html;__        charset="UTF-8":n:
n:n:n:96:89[L:42];}];}];}4={[ARY]:[7][0={[INT]:5787;}1={[STR]:17a6:858c:application/vnd.openxmlformats-officedocument.wordprocessingml.doc
ume
T:3664185088(16:17:11)[app.ud.param-W001AAudBXOgaIRYJHKS:Debug] nt:b:n:1[L:87];}2={[WST]:[L:0];}3={[WST]:雅本化学保密协议.docx[L:29];}4={[
INT]:24980;}5={[INT]:69;}6={[STU]:[1][imapinfo={[STR]:application/vnd.openxmlformats-officedocument.wordprocessingml.document;__        na
me="=?UTF-8?B?6ZuF5pys5YyW5a2m5L+d5a+G5Y2P6K6uLmRvY3g=?=":<F0FE9A14E1616142BCAEEA10112DE034@CHNPR01.prod.partner.outlook.cn>:n:n:n:440:265
[L:215];}];}];}5={[ARY]:[7][0={[INT]:40287;}1={[STR]:9e95:10f1e:application/vnd.openxmlformats-officedocument.wordprocessingml.document:b:
n:1[L:88];}2={[WST]:[L:0];}3={[WST]:雅本化学汇联易软件及服务合同v2.docx[L:49];}4={[INT]:50718;}5={[INT]:69;}6={[STU]:[1][imapinfo={[STR]:a
pplication/vnd.openxmlformats-officedocument.wordprocessingml.document;__       name="=?UTF-8?B?6ZuF5pys5YyW5a2m5rGH6IGU5piT6L2v5Lu25Y+K5p
yN5Yqh5ZCI5ZCMdjIuZG8=?=__      =?UTF-8?B?Y3g=?=":<FD343F449B3E7C459F3D7AD149EC14FD@CHNPR01.prod.partner.outlook.cn>:n:n:n:891:308[L:258];
}];}];}];}retolist={[STR]:jessiezhu@abachem.com,1/a/chen[L:30];}sdip={[STR]:101.81.252.98[L:13];}sdtid={[STR]:AQAAfwCH
T:3664185088(16:17:11)[app.ud.param-W001AAudBXOgaIRYJHKS:Debug] ODnHSXNYf5EAAA--.5004S2[L:31];}sender={[STR]:hujm@abachem.com[L:16];}];}ms
ginfo={[STU]:[10][fid={[INT]:1;}flag={[LNG]:24;}fmask={[LNG]:152;}from={[WST]:"hujm" <hujm@abachem.com>[L:25];}msgid={[STR]:1tbiAQAGAFh4fU
YAAwACsd[L:22];}msid={[INT]:1;}size={[INT]:110052;}subj={[WST]:盖章确认--汇联易服务及软件销售合同[L:50];}time={[TIM]:2017-01-09 16:28:53;}
to={[WST]:"_CaiTong 蔡 彤" <caitong@abachem.com>[L:40];}];}nosync={[BOL]:F;}tdn={[STR]:un=chen,dn=coremail.cn,dd=1,ou=(org=a;pro=1)[L:44];
}];
EOF;
*/

//get the q parameter from URL
$log = "";
if (isset($_POST["log"])) {
	$log = $_POST["log"];
}

$response = "";
if (strlen($log) !== 0)
{
	try {
		$log=preprocess($log);
		//echo $log, PHP_EOL;

		if (strlen($log) === 0) {
			throw new Exception("Input format error");
		}

		$converter = '';
		if (substr($log, 0, strlen('[STU')) === '[STU') {
			$converter = new FixedTypeLengthLogConverter($log);
		} else if (substr($log, 0, strlen('[Struct')) === '[Struct') {
			$converter = new VariableTypeLengthLogConverter($log);
		} else {
			throw new Exception("Input should start with '[STU' or '[Struct'");
		}
		$wrapper = new LogConverterWrapper($converter);
		$wrapper->parseLog();
		$result = $converter->getResult();
		//echo $result, PHP_EOL;

		$json = json_decode($result);
		$response = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
	} catch (Exception $e) {
		$response = $e->getMessage();
	}
}

//output the response
echo $response;
?>
