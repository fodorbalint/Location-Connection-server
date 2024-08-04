<?php

/*print phpinfo();
die();*/

ini_set("display_errors",1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$logLength=255;

require("Connection.php");
$conn=new Connection();
$conn->sqlconnect();

$uploadFolder=isset($_GET["testDB"])?$_ENV["ROOT"]."userimagestest":$_ENV["ROOT"]."userimages";

if (isset($_POST["Email"])) {
    $Email=$_POST["Email"];

    global $uploadFolder, $mysqli;    

    $stmt=&sqlselect("select ID from profiledata where Email=?",array("s",$Email));
    $stmt->bind_result($ID);
    $stmt->fetch();
    $stmt->free_result();

    if ($ID != "") {
        /*if (isset($_GET["LocationUpdates"])) {
            updateLocationEnd($ID);
        }*/
        
        //delete records where other party already unmatched
        sqlexecuteparams("delete from matches where (FirstID=? or SecondID=?) and UnmatchInitiator is not null and UnmatchInitiator != ?", array(array("i",$ID), array("i",$ID), array("i",$ID)));
        
        //unmatch from all matches
        sqlupdate("matches", array("Active"=>array("i","0"), "UnmatchDate"=>array("s",date("Y-m-d H:i:s")), "UnmatchInitiator"=>array("i","0"), "FirstID"=>array("i","0")), array("FirstID"=>array("i",$ID)));
        sqlupdate("matches", array("Active"=>array("i","0"), "UnmatchDate"=>array("s",date("Y-m-d H:i:s")), "UnmatchInitiator"=>array("i","0"), "SecondID"=>array("i","0")), array("SecondID"=>array("i",$ID)));
                
        sqldelete("profiledata", array("ID" => array("i",$ID)));
        sqldelete("profilesettings", array("ID" => array("i",$ID)));
        sqldelete("likehide", array("ID" => array("i",$ID)));
        sqldelete("session", array("ID" => array("i",$ID)));
        
        $mysqli->query("lock tables likehide write");
        $fields=array("Likes","Hides","LikedBy","HidBy","Friends","FriendsBy","Blocks","BlockedBy");
        foreach($fields as $column) {
            $stmt=&sqlselect("select ID, $column from likehide where $column regexp ?", array("s","(^$ID:[[:digit:]]+|\|$ID:[[:digit:]]+)"));//in phpmyaddmin \ must be doubled
            $stmt->bind_result($rowID,$field);
            $updaterows=array();
            while($stmt->fetch()) {
                $field=preg_replace("/^$ID:\d+$/","",$field); //single entry
                $field=preg_replace("/^$ID:\d+\|/","",$field); //first entry of many
                $field=preg_replace("/\|$ID:\d+/","",$field); //not first entry
                $updaterows[]=array($rowID, $field);
            }
            $stmt->free_result();
            foreach($updaterows as $arr) {
                sqlupdate("likehide",array($column => array("s",$arr[1])), array("ID" => array("i",$arr[0])));
            }
        }
        $mysqli->query("unlock tables");
        
        $imageFolder="$uploadFolder/$ID/";
        try {
            delete_dir($imageFolder);
        }
        catch (Exception $ex) {}        
    }

    startPage(false); 
}
else {
    startPage(true);    
}

function startPage($isNew) {
    $content=file_get_contents("deleteaccount.html");
    if (!$isNew) {
        $content='<div id="messagebox" style="font-family: Verdana; font-size: 16px;">If an account was associated with this email, it has been deleted.</div>';
    }
    print $content;
}

function delete_dir($dirPath) {
    global $conn;
    
    if ($conn->bucket) {
        $dirPath=str_replace($_ENV["ROOT"],"",$dirPath);
        $options=['prefix' => $dirPath];
        foreach($conn->bucket->objects($options) as $object) {
            $object->delete();         
        }
    }
    else {
        //problems on local file system, see below.
        //print "delete_dir ".$dirPath."\n";
        if (!is_dir($dirPath)) {
            return;
            //throw new InvalidArgumentException("$dirPath must be a directory");
        }
        if (substr($dirPath, strlen($dirPath) - 1, 1) != '/' && substr($dirPath, strlen($dirPath) - 1, 1) != '\\') {
            $dirPath .= '/';
        }
        $files = glob($dirPath . '*', GLOB_MARK);
        foreach ($files as $file) {
            if (is_dir($file)) {                  
                delete_dir($file);
            } else {
                //print "unlink ".$file."\n";
                unlink($file);
            }
        }
        //print "rmdir ".$dirPath."\n";
        
        $fp=opendir($dirPath);
        while ($file=readdir($fp)) {
            //print "file inside1: ".$file."\n";
            if ($file != ".." && $file != ".") {
                $result=rmdir($dirPath.$file."/"); 
                var_dump($result);
                //print $result." deleted in after cycle: $file \n";   
            }
        }
        $files = glob($dirPath . '*', GLOB_MARK);
        //print "count: ".count($files)."\n";
        foreach ($files as $file) {
           // print " file inside2: ".$file."\n";
        }     
        //"directory not empty" error after deleting 480 folder. But deleting the folder again above results in "permission denied error". Upon checking the file system, the 480 folder is deleted.     
        $result=rmdir($dirPath);        
        //var_dump($result)."\n";
    }    
} 
?>