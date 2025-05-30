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

/*foreach ($_SERVER as $key=>$elem) {
    print "Server $key: $elem<br />";
}       
foreach ($_ENV as $key=>$elem) {
    print "Env $key: $elem<br />";
}*/

if (!in_array($userip, $conn::EXCLUDED_IPS)) {
    sqlinsert("log_admin", array(
        "Time"=>array("s",date("Y-m-d H:i:s",time())),
        "URL"=>array("s",truncateString($_SERVER["REQUEST_URI"],$logLength)),
        "Referer"=>array("s",isset($_SERVER["HTTP_REFERER"])?str_replace("https://".$_SERVER["HTTP_HOST"],"",truncateString($_SERVER["HTTP_REFERER"],$logLength)):""),
        "IP"=>array("s",$userip)
    ),false);   
}

if (isset($_COOKIE["SessionID"])) {
    $Username=authSession();     
    if (!$Username) { //can happen if I log in from another device while using the admin page
        if (!isset($_GET["action"]) || $_GET["action"] == "logout") {
            setcookie("SessionID", null, -1, '/');
            loginPage(true);
        }
        else {
            print "ERROR:Authorization error.";
        }
    }
    else if (isset($_GET["action"]) && $_GET["action"] == "logout") {
        setcookie("SessionID", null, -1, '/');
        loginPage(true);
    }
    else {
        adminPage($Username);
    }
}
else if (isset($_POST["Username"]) && $_POST["Username"]!="") {
    $Username=$_POST["Username"];
    $Password=$_POST["Password"];

    useRealDatabase();
    $stmt=&sqlselect("select ID, Password from admin where Username=?", array("s",$Username));
    $stmt->store_result();
    $count=$stmt->num_rows;
    
    if ($count==1) {
        $stmt->bind_result($ID, $Hash);
        $stmt->fetch(); 
        
        if (password_verify($Password, $Hash)) {
            $stmt->free_result();
            $sessionid=password_hash($Hash, PASSWORD_DEFAULT, ["cost" => 10]);
            sqlupdate("admin", array("SessionID" => array("s",$sessionid)), array("ID" => array("i",$ID)));
            setcookie("SessionID",$sessionid);
            if (isset($_GET["testDB"])) {
                if (isset($_GET["table"])) {
                    print "<script>window.location.replace(window.location.protocol + '//' +  window.location.hostname + '/admin.php?table={$_GET["table"]}&testDB');</script>";
                }
                else {
                    print "<script>window.location.replace(window.location.protocol + '//' +  window.location.hostname + '/admin.php?testDB');</script>"; //so we can use the back button from the next page without having to resend the form.
                }
            }
            else {
                if (isset($_GET["table"])) {
                    print "<script>window.location.replace(window.location.protocol + '//' +  window.location.hostname + '/admin.php?table={$_GET["table"]}');</script>";
                }
                else {
                    print "<script>window.location.replace(window.location.protocol + '//' +  window.location.hostname + '/admin.php');</script>"; //so we can use the back button from the next page without having to resend the form.
                }
            }            
            die();
        }
        else {
            $stmt->close();
            loginPage(false);
            die();
        }
    } 
    $stmt->close();
    loginPage(false);
    die(); 
}
else {
    loginPage(true);    
}

function loginPage($isNew) {
    $content=file_get_contents("login.html");
    if (!$isNew) {
        $content=str_replace("[message]",'<div id="messagebox">Login failed.</div>',$content);
    }
    else {
        $content=str_replace("[message]","",$content);
    }
    if (isset($_GET["testDB"])) {
        if (isset($_GET["table"])) {
            $content=str_replace("[urladd]","admin.php?table={$_GET["table"]}&testDB",$content);
        }
        else {
            $content=str_replace("[urladd]","admin.php?testDB",$content);
        }
    }
    else {
        if (isset($_GET["table"])) {
            $content=str_replace("[urladd]","admin.php?table={$_GET["table"]}",$content);
        }
        else {
            $content=str_replace("[urladd]","admin.php",$content);
        }
    }
    print $content;
}

function adminPage($Username) {
    global $conn;

    if (isset($_GET["action"])) {
        if ($_GET["action"] != "changepassword" && $_GET["action"] != "logout" && $_GET["action"] != "resetautoincrement" && $_GET["action"] != "clearownlog") {
            $tableName=$_GET["tableName"];
            if ($tableName == "admin" || $tableName=="log_admin") {
                print "ERROR:This table cannot be changed.";
                return;
            }
        }         
        switch($_GET["action"]) {
            case "edit":
                $ID=$_POST["ID"];
                $Field=$_POST["Field"];
                $Value=$_POST["Value"];
                
                if ($Value=="") $Value=null;
                if (is_int($Value)) {
                    $arr = array("i",$Value);
                }
                else if (is_numeric($Value)) {
                    $arr = array("d",$Value);
                }
                else {
                    $arr = array("s",$Value);
                }
                sqlupdate($tableName, array($Field => $arr), array("ID" => array("i",$ID)));
                print "OK|$Value";
                return;
            case "delete":
                $IDs=explode(";",$_POST["IDs"]);
                
                $query="delete from $tableName where ";
                $params=array();
                for($i=0;$i<count($IDs);$i++) {
                    $query.="ID=? or ";
                    $params[]=array("i",$IDs[$i]);    
                }
                $query=substr($query,0,strlen($query)-4);
                sqlexecuteparams($query, $params);
                print "OK";                
                return;
            case "empty":
                $query = "truncate table $tableName";
                sqlexecuteliteral($query);
                print "OK";
                return;
            case "changepassword":
                $ChangeUsername=$_GET["ChangeUsername"];
                $PasswordCurrent=$_GET["PasswordCurrent"];
                $PasswordNew=$_GET["PasswordNew"];
                
                if ($ChangeUsername != $Username) { //can only happen if someone changes the admin table while the other is using the admin page
                    print "ERROR:Incorrect username.";
                    return;
                }
                if (strlen($PasswordNew)<6) {
                    print "ERROR:Password too short.";
                    return;
                }
                if ($PasswordNew == $PasswordCurrent) {
                    print "ERROR:New password is same as the old.";
                    return;
                }                   
                
                $stmt=&sqlselect("select ID, Password from admin where Username=?", array("s",$Username));
                $stmt->store_result();
                $count=$stmt->num_rows;
                if ($count==1) {
                    $stmt->bind_result($ID, $Hash);
                    $stmt->fetch();

                    if (password_verify($PasswordCurrent, $Hash)) {
                        $stmt->free_result();
                        $newHash=password_hash($PasswordNew, PASSWORD_DEFAULT, ["cost" => 10]);
                        $sessionid=password_hash($newHash, PASSWORD_DEFAULT, ["cost" => 10]);
                        sqlupdate("admin", array("Password"=>array("s",$newHash), "SessionID" => array("s",$sessionid)), array("ID" => array("i",$ID)));
                        setcookie("SessionID",$sessionid);
                        print "OK";
                    }
                    else {
                        print "ERROR:Incorrect password.";
                    }
                }
                else { //Cannot happen, username was found earlier.
                    print "ERROR:Wrong username.";
                }
                return;
            case "resetautoincrement":
                $Value=$_GET["Value"];
                if (ctype_digit($Value) && $Value > 100) {
                    $query = "alter table profiledata auto_increment= $Value";
                    sqlexecuteliteral($query);
                    print "OK";
                }
                else {
                    print "Wrong input";
                }
                return; 
            case "clearownlog":
                $query="delete from log_input where IP='".$conn::OWN_IP."' or IP like '192.168.%' or IP like '77.241.%'"; //and sometimes 212.27.%
                sqlexecuteliteral($query);
                print "OK";          
                return;                                
        }         
    }     
    
    $tables=array();
    $tablelist="";
    $stmt=&sqlselectall("show tables");
    $stmt->bind_result($table);
    while($stmt->fetch()) {
        $tables[]=$table;
        $tablelist.="<div class=\"menuitemLeft\" onclick=\"selectTable('$table')\">$table</div>";
    }
    $stmt->free_result();     
    
    $content="";
    if (isset($_GET["table"]) && in_array($_GET["table"],$tables)) {
        $table=$_GET["table"];        
    }
    else {
        $table="log_input";
    }
    $start = isset($_GET["start"])?$_GET["start"]:0;
    $count = isset($_GET["count"])?$_GET["count"]:100;
    $content=getTable($table, $start, $count);    
    
    $colDefs="var colDefs = { ";
    $stmt=&sqlselect("select ScreenWidth, Definitions from admin_layout where TableName=?",array("s",$table));
    $stmt->bind_result($ScreenWidth, $Definitions);
    while($stmt->fetch()) {
        $colDefs.="$ScreenWidth:\"$Definitions\",";
    }
    $colDefs=substr($colDefs,0,strlen($colDefs)-1)."}";
    $stmt->close();
    
    $colDefinitions=array(
        "input"=>array("5%","12%","35%","35%","13%"),
        "homepage"=>array("5%","12%","30%","30%","23%"),
        "downloads"=>array("33%","33%","34%"),
        "admin"=>array("5%","12%","35%","35%","13%"),
        "errors"=>array("5%","10%","12%","73%")        
    );
    
    $tablelist=str_replace("class=\"menuitemLeft\" onclick=\"selectTable('$table')\"","class=\"menuitemLeft_selected\" onclick=\"selectTable('$table')\"",$tablelist);
    $frame=file_get_contents("admin.html"); 
    $frame=str_replace("[username]",$Username,$frame);   
    $frame=str_replace("[colDefs]",$colDefs,$frame);
    $frame=str_replace("[tableName]",$table,$frame);
    $frame=str_replace("[tablelist]",$tablelist,$frame);
    $frame=str_replace("[content]",$content,$frame); 
    $frame=str_replace("[ownIP]",$conn::OWN_IP,$frame); 
    if (isset($_GET["testDB"])) {
        $frame=str_replace("[testDB]","&testDB",$frame);
        $frame=str_replace("[currentTableStyle]","currentTableTest",$frame);
        $frame=str_replace("[switchtest]",'<div class="menuitemRight" id="menuSwitchTest" onclick="switchNormal()">Switch to real</div>',$frame); 
    }
    else {
        $frame=str_replace("[testDB]","",$frame); 
        $frame=str_replace("[currentTableStyle]","currentTable",$frame);
        $frame=str_replace("[switchtest]",'<div class="menuitemRight" id="menuSwitchTest" onclick="switchTest()">Switch to test</div>',$frame);         
    }
    
    
    print $frame;
} 

//,$definition
function getTable($page, $start, $count) {
    
    $stmt=&sqlselectall("select * from $page order by ID desc limit $start, $count");
    $result=$stmt->get_result();
    $row = $result->fetch_assoc(); 
    if ($row) { 
        $header="<tr class=\"rowPair\">";
        $body="<tr class=\"rowImpair\">"; 
        $counter=0; 
        $header.="<th width=\"20\"><input type=\"checkbox\" onclick=\"selectRow(event)\" /></th>";
        $body.="<td><input type=\"checkbox\" onclick=\"selectRow(event)\" /></td>";         
        foreach ($row as $key => $elem) {
            $header.="<th>$key</th>";
            if ($elem != "" && strpos($elem,"\n")) {
                $elem=nl2br($elem);
            }
            $body.="<td><div>$elem</div></td>"; 
            $counter++; 
        }
        $header.="</tr>";
        $body.="</tr>";
        $counter=2;     
        while ($row = $result->fetch_assoc()) {
            if ($counter%2==0) {
                $body.="<tr class=\"rowPair\">";
            }
            else {
                $body.="<tr class=\"rowImpair\">";
            }
            $body.="<td><input type=\"checkbox\" onclick=\"selectRow(event)\" /></td>";
            foreach ($row as $elem) {                   
                if ($elem != "" && strpos($elem,"\n")) {
                    $elem=nl2br($elem);
                }
                $body.="<td><div>$elem</div></td>";                
            }
            
            $body.="</tr>";
            $counter++;
        }
        $table="</script><table id=\"data\" cellspacing=\"1\" cellpadding=\"3\">$header$body</table>";
        $stmt->free_result();

        $stmt=&sqlselectall("select count(*) from $page");
        $stmt->bind_result($total);
        $stmt->fetch();
        
        $end = (($start + $count) < $total)? $start + $count : $total;
        $bottomlinks = ($start + 1). "-".$end." / ".$total;
        if ($start > 0) {
            $newstart=($start-$count > 0)?$start-$count:0;
            $testDB=isset($_GET["testDB"])?"&testDB":"";
            $bottomlinks="<a href='/admin.php?table=$page$testDB&start=$newstart&count=$count'>Prev</a>&nbsp;&nbsp;".$bottomlinks;
        }
        if ($end < $total) {
            $newstart=$start+$count;
            $testDB=isset($_GET["testDB"])?"&testDB":"";
            $bottomlinks.="&nbsp;&nbsp;<a href='/admin.php?table=$page$testDB&start=$newstart&count=$count'>Next</a>";
        }
        $table.="<div style='padding:10px'>$bottomlinks</div>";
        $stmt->close();
        return $table;
    } 
    else {
        $stmt->close(); 
        return "<div style=\"width:100%; text-align:center; padding:15px\">No records.</div>";
    }  
}

function authSession() {
    $sessionid = $_COOKIE["SessionID"];
    //useRealDatabase();
    $stmt=&sqlselect("select Username from admin where SessionID=?", array("s",$sessionid));
    $stmt->store_result();
    $count=$stmt->num_rows;    
    if ($count!=1) {
        $stmt->close();
        return false;
    }
    else {
        $stmt->bind_result($Username);
        $stmt->fetch();
        $stmt->close();
        if (isset($_GET["testDB"])) {
            useTestDatabase();
        }        
        return $Username;
    }
}

function truncateString($str, $length) {
    return (strlen($str)>$length)?substr($str,0,$length):$str;
}  
?>