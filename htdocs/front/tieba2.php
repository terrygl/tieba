<?php
//header("content-type:text/html; charset=gbk"); 
//header("content-type:text/html ; charset=gbk");
libxml_use_internal_errors(true);
$dsn = "mysql: host=localhost;dbname=tieba";//顺序不能颠倒
$user = 'root';
$password ='';
$pdo = new pdo($dsn,$user,$password);
$statement="insert into topiclist(title,authorId,content,dataTime) values('sfsdf','13','阿桑发送发撒旦飞洒发斯蒂芬','2013-11-13 16:26')";
$pdo->exec($statement);
// if($pdo->commit()){
// 	echo "succeed";
// }
echo $pdo->lastinsertid();
$statement2="select * from topiclist";
$rs=$pdo->query($statement2);
while($row=$rs->fetch()){
	print_r($row);
}

mysql_connect("localhost","root","");
mysql_select_db("tieba");
mysql_query($statement2);
mysql_close();
exit();
// exit();
//$html=file_get_contents("http://www.baidu.com");//没有问题
//$urlparam="http://tieba.baidu.com/p/2680133252?pn=2";
$urlparam="http://tieba.baidu.com/p/2699685853?pn=1";
$html=file_get_contents($urlparam);//有问题原因：网页的meta标签不规范，当将meta标签改为如下形式问题解决<meta http-equiv=Content-Type content="text/html;charset=gbk"/>
//$html=file_get_contents("1.html");
$html=str_replace("charset=\"gbk\"","http-equiv=\"Content-Type\" content=\"text/html; charset=gbk\" /",$html);
//echo $html;
//exit();

//$html=file_get_contents("http://www.soso.com");//没有问题
//$html=mb_convert_encoding($html,'utf-8','gbk');//根本没有用

 /*** a new dom object ***/ 
    $dom = new domDocument; 

    /*** load the html into the object ***/ 
    $dom->loadHTML($html); 

    /*** discard white space ***/ 
    $dom->preserveWhiteSpace = false; 
 

    $title=$dom->getElementsByTagName('title')->item(0)->nodeValue;//查看网页title
	//echo $title;
	//echo mb_convert_encoding($title,"gbk","UTF-8");
    $pattern="/(?<=共有)\d+/";//匹配模式


    /*** the table by its tag name ***/ 
    $tables = $dom->getElementsByTagName('li'); 
/*
 * 查找总页数
 * http：//协议
 * tieba.baidu.com//域名
 * //p/2680133252//相对路径
 * 前两者相结合是绝对路径
 * ？p=xxx//参数
 */
	for ($i = 0; $i < $tables->length; $i++) {
    	$subject=$tables->item($i)->nodeValue;
    	if(preg_match($pattern,$subject,$arr)){
    		print_r($arr);
    		echo "<br>";
    		break;
    	}
	}
	$pageTotal=$arr[0];
//查询由此开始
/*
 * 创建网页数组
 */
//	获取绝对路径
$url=strtok($urlparam,'?');
echo $url;
//先简单点
$urlArr=array();
for($i=1;$i<=$pageTotal;$i++){
	array_push($urlArr,$url."?pn=".$i);
}
print_r($urlArr);
/*
 * 数组查重
 */
function uniquenessArray($item,&$arr){
	if(!in_array($item,$arr)){
		array_push($arr,$item);
	}
}
/*
 * 抽取所有用户的用户名 PHP 文档对象模型方式
 */
$divClassd_author=$dom->getElementsByTagName('div');
$divLen=$divClassd_author->length;
$tempArr=array();
echo $divLen;
echo gettype($divClassd_author->item(0));
for($i=0;$i<$divLen;$i++){
	$divClassName=$divClassd_author->item($i)->getAttribute("class");
	//echo $divClassName;
	if($divClassName&&!strcmp($divClassName,"d_author")){
		$domliList=$divClassd_author->item($i)->getElementsByTagName("ul")->item(0)->getElementsByTagName("li");
		$liLen=$domliList->length;
		for($j=0;$j<$liLen;$j++){
			$liClassName=$domliList->item($j)->getAttribute("class");			
			if($liClassName&&!strcmp($liClassName,"d_name")){
				$username=$domliList->item($j)->nodeValue;
				uniquenessArray($username,$tempArr);
			}
		}
		//array_push($tempArr,$attr);
	}
}
print_r($tempArr);
//if($divClassd_author->item(0) instanceof DOMElement ){
//	echo ":dfddfafasdf";
//}if($divClassd_author->item(0) instanceof DOMNode  ){
//	echo ":dfddfafasdf";
//}
//exit();

/*
 * 抽取所有用户的用户名 正则表达式方式
 */
$sourceArr=array();
$arr=array();
$pattern="/(?<=username=\")(([".chr(0xb0)."-".chr(0xf7)."][".chr(0xa1)."-".chr(0xfe)."])|\w)+(?=\")/";//匹配所有中文+英文字母的用户名 此种方式是用GB2312方式匹配的
foreach($urlArr as $valurl){
	echo $valurl."<br>";
	$str=file_get_contents($valurl);
	
	preg_match_all($pattern,$str,$arr);	
	foreach($arr[0] as $val){
		if(!in_array($val,$sourceArr)){
			array_push($sourceArr,$val);
		}
	}
}
//$sourceArr=mb_convert_encoding($sourceArr,'UTF-8','gb2312');
//foreach($urlArr as $val){
//	$newHtml=file_get_co
//}
	print_r($sourceArr);
	
/*
 * 数组转码
 */
foreach($sourceArr as $key=>$val){
	$sourceArr[$key]=mb_convert_encoding($val,"utf-8","gbk");
}
print_r($sourceArr);
    /*** get all rows from the table ***/ 
//    $rows = $tables->item(0)->getElementsByTagName('a'); 
//    echo "<br>";
//print_r($tables->item(0));
//echo "<br>";
//print_r($rows->item(1));
//echo "<br>";
//print_r($tables->item(2));
    /*** loop over the table rows ***/ 
//    foreach ($rows as $row) 
//    { 
//        /*** get each column by tag name ***/ 
////        $cols = $row->getElementsByTagName('td'); 
////        /*** echo the values ***/ 
////        echo $cols->item(0)->nodeValue.'<br />'; 
////        echo $cols->item(1)->nodeValue.'<br />'; 
//// //       echo $cols->item(2)->nodeValue; 
//        echo '<hr />'; 
//    } 
//print_r($rows);
    
?>