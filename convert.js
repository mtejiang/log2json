function convert()
{
	var data = "log=" + document.getElementById("input").value;
	var req = getXmlHttpObject();
	
	req.open("POST", '/tools/log2json.php', true);
	req.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
	req.onreadystatechange = function() {
	    if(req.readyState == 4 && req.status == 200) {
			document.getElementById("output").innerHTML = req.responseText;
	    }
    }
	req.send(data);
}

function getXmlHttpObject()
{
var xmlHttp=null;

try
 {
 // Firefox, Opera 8.0+, Safari
 xmlHttp=new XMLHttpRequest();
 }
catch (e)
 {
 // Internet Explorer
 try
  {
  xmlHttp=new ActiveXObject("Msxml2.XMLHTTP");
  }
 catch (e)
  {
  xmlHttp=new ActiveXObject("Microsoft.XMLHTTP");
  }
 }
return xmlHttp;
}