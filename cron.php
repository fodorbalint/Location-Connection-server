<?php
ini_set("display_errors",1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$logLength=255;

require("Connection.php");
$conn=new Connection();
$conn->sqlConnect();

/*if (!isset($_SERVER["HTTP_X_APPENGINE_CRON"]) || !$_SERVER["HTTP_X_APPENGINE_CRON"]) {
    if (!in_array($userip, $conn::EXCLUDED_IPS)) {
        sqlinsert("log_admin", array(
            "Time"=>array("s",date("Y-m-d H:i:s",time())),
            "URL"=>array("s",truncateString($_SERVER["REQUEST_URI"],$logLength)),
            "Referer"=>array("s",isset($_SERVER["HTTP_REFERER"])?str_replace("https://".$_SERVER["HTTP_HOST"],"",truncateString($_SERVER["HTTP_REFERER"],$logLength)):""),
            "IP"=>array("s",$userip)
        ),false);   
    }
    
    die("You are not authorized to access this page.");
}*/   

$received=array();
$answered=array();
$now=time();
$secondsInDay=60*60*24;
$stmt=&sqlselectall("select FirstID, SecondID, unix_timestamp(FirstLatestMessage) as FirstLatestMessage, unix_timestamp(SecondLatestMessage) as SecondLatestMessage from matches");
$stmt->bind_result($FirstID, $SecondID, $FirstLatestMessage, $SecondLatestMessage);
while ($stmt->fetch()) {    
    if ($FirstLatestMessage != null && $SecondLatestMessage != null) {
        //both will get a point as we do not know who was the first one who sent a message.
        if (!array_key_exists($FirstID,$received)) {
            $received[$FirstID]=0; 
            $answered[$FirstID]=0;
        }
        if (!array_key_exists($SecondID,$received)) {
            $received[$SecondID]=0; 
            $answered[$SecondID]=0;
        }
        $received[$FirstID]++;
        $received[$SecondID]++;
        $answered[$FirstID]++;
        $answered[$SecondID]++;
    }
    else if ($FirstLatestMessage != null && $SecondLatestMessage == null) {
        if (!array_key_exists($SecondID,$received)) {
            $received[$SecondID]=0; 
            $answered[$SecondID]=0;
        }
        $received[$SecondID]++;         
        $diff=$now-$FirstLatestMessage;
        if ($diff <= $secondsInDay) {
            $answered[$SecondID]++;    
        }
    }
    else if ($FirstLatestMessage == null && $SecondLatestMessage != null) {
        if (!array_key_exists($FirstID,$received)) {
            $received[$FirstID]=0; 
            $answered[$FirstID]=0;
        }
        $received[$FirstID]++;         
        $diff=$now-$SecondLatestMessage;
        if ($diff <= $secondsInDay) {
            $answered[$FirstID]++;    
        }
    }	
}
$stmt->free_result(); 

//print "<pre>";
//print_r($received);
//print_r($answered);
//print "</pre>";

$result=date("Y-m-d H:i:s",$now)."\n";
foreach ($received as $key => $elem) {
    $ratio = $answered[$key]/$elem;
    $query="update profiledata set ResponseRate=$ratio where ID=$key";
    sqlexecuteliteral($query);
    $result.=$key.":".$answered[$key]."/".$elem."=".$ratio."\n";    
}

//print nl2br($result);
appendLog($_ENV["ROOT"]."logs/cron.txt",$result);

function appendLog($file, $text) { //google cloud does not support file_put_contents(,,FILE_APPEND), or fopen(). file_get_contents would fail on a non-existent file.
    if (file_exists($file)) {
        file_put_contents($file, file_get_contents($file).$text.PHP_EOL);
    }
    else {
        file_put_contents($file, $text.PHP_EOL);
    }
}

function truncateString($str, $length) {
    return (strlen($str)>$length)?substr($str,0,$length):$str;
}
?>