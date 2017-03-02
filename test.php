<?php

namespace Log2Json;

use PHPUnit\Framework\TestCase;

final class ConverterTest extends TestCase
{
	private function parseFixed($log) {
		$processedLog = preprocess($log);
		$converter = new FixedTypeLengthLogConverter($processedLog);
		$converter->parseLog();
		$result = $converter->getResult();
		return json_decode($result);
	}

	private function parseVariable($log) {
		$processedLog = preprocess($log);
		$converter = new VariableTypeLengthLogConverter($processedLog);
		$converter->parseLog();
		$result = $converter->getResult();
		return json_decode($result);
	}

	public function testFixed1() {
		$input = <<<'EOF'
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
		$this->assertNotEquals($this->parseFixed($input), null);
	}

	public function testVariable1() {
		$input = <<<'EOF'
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
		$this->assertNotEquals($this->parseVariable($input), null);
	}
}