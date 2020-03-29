<?php
//deleted user can be added to friends.
//need to send confirmation email to the new and updated address
//limit uploadable images to 9, also in the temporary folder
//notify the other party on unmatching ?
//limit message character count for displaying notification body
//if target name contains emoji, does it get displayed correctly?
// revert escaped { in messages
//what happens if two of the same messages are insterted
//when someone unmatches, remove friend, if the target person was added

ini_set("display_errors",1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'vendor/autoload.php';

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\Converter\StandardConverter;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\Serializer\CompactSerializer;

require("Connection.php");
$conn=new Connection();

$tempUploadFolder=isset($_GET["testDB"])?$_ENV["ROOT"]."userimagestesttemp":$_ENV["ROOT"]."userimagestemp";
$uploadFolder=isset($_GET["testDB"])?$_ENV["ROOT"]."userimagestest":$_ENV["ROOT"]."userimages";

$path=substr($_ENV["PATH"],1);  //remove the first slash 

if (preg_match("/^.+\.jpg$/i",$path) || preg_match("/^.+\.jpeg$/i",$path)) {
    $file=$_ENV["ROOT"].$path;
    if (file_exists($file)) {
        header("Content-Type: image/jpeg");                                                
        header("Content-Length: ".filesize($file));
        readfile($file);
        die();   
    }
    else {
        http_response_code(404);
        exit("Not found");
    }
}
else if (preg_match("/^.+\.png$/i",$path)) {
    $file=$_ENV["ROOT"].$path;
    if (file_exists($file)) {
        header("Content-Type: image/png");                                                
        header("Content-Length: ".filesize($file));
        readfile($file); 
        die();  
    }
    else {
        http_response_code(404);
        exit("Not found");
    }
}
else if (preg_match("/^.+\.svg$/i",$path)) {
    $file=$_ENV["ROOT"].$path;
    if (file_exists($file)) {
        header("Content-Type: image/svg+xml");                                                
        header("Content-Length: ".filesize($file));
        readfile($file); 
        die();  
    }
    else {
        http_response_code(404);
        exit("Not found");
    }
}
else if (preg_match("/^.+\.mp3$/i",$path)) {
    $file=$_ENV["ROOT"].$path;
    if (file_exists($file)) {
        header("Content-Type: audio/mpeg");                                                
        header("Content-Length: ".filesize($file));
        readfile($file); 
        die();  
    }
    else {
        http_response_code(404);
        exit("Not found");
    }
}
else {
    switch($path) {
        case "balintfodor.locationconnection.apk":
            $file="balintfodor.locationconnection-Signed.apk";
            header("Content-Type: application/octet-stream");
            header("Content-Disposition: attachment; filename=balintfodor.locationconnection.apk"); 
            header("Content-Length: ".filesize($file));
            $conn->sqlConnect();
            if (!in_array($userip, $conn::EXCLUDED_IPS)) {
                sqlinsert("log_downloads", array(
                    "Time"=>array("s",date("Y-m-d H:i:s",time())),
                    "IP"=>array("s",$userip)
                ),false);
            }        
            readfile($file);
            die();     
        case "": //api request
            break;    
        default:
            http_response_code(404);
            exit("Not found.");
    }
}  

$errorText="";
$smallImageSize=480;
$largeImageSize=1440;
$maxNumPictures=9;
$allowedTimeDelay=10;
$maxResultCount=100;//isset($_GET["testDB"])?3:100; 
$listTypeDefault="public";
$sortByDefault="LastActiveDate";
$orderByDefault="desc";
$geoFilterDefault="False";
$geoSourceOtherDefault="False";
$distanceLimitDefault=50;
$resultsFromDefault=1;
$matchInAppDefault="True";
$messageInAppDefault="True";
$unmatchInAppDefault="True";
$rematchInAppDefault="True";
$matchBackgroundDefault="True";
$messageBackgroundDefault="True";
$unmatchBackgroundDefault="True";
$rematchBackgroundDefault="True";
$locationAccuracyDefault=0;
$inAppLocationRateDefault=60;
$backgroundLocationRateDefault=600;
$inAppLocationRateMin=15;
$inAppLocationRateMax=300;
$backgroundLocationRateMin=300;
$backgroundLocationRateMax=3600;

$result="";
const NEWMESSAGEFROM="New message from";
const YOUMATCHEDWITH="You matched with";
const YOUREMATCHEDWITH="You re-matched with";
const YOUUNMATCHEDFROM="unmatched you.";
const BLOCKEDYOU="blocked you.";
$monthlyGeocodingAllowance=40000;
$maxLogLength=1024;
$homeLogLength=255;
$maxMessageLength=4000;
$secondsInDay=60*60*24;

$conn->sqlConnect();

$requestID=0;
if (!(isset($_GET["action"]) && ($_GET["action"]=="register" || $_GET["action"]=="login" || $_GET["action"]=="loginsession" || $_GET["action"]=="profileedit" || $_GET["action"]=="setpassword" || $_GET["action"]=="changepassword"))) {
    //if (!in_array($userip, $conn::EXCLUDED_IPS)) {
        $requestID=sqlinsert("log_input", array(
            "Time"=>array("s",date("Y-m-d H:i:s",time())),
            "URL"=>array("s",removeSession(truncateString($_SERVER["REQUEST_URI"],$maxLogLength))),
            "Response"=>array("s",""),
            "IP"=>array("s",$userip)
        ),true);   
    //}
}

if (isset($_GET["action"])) {
    if ($_GET["action"] == "reporterror") {
        $ID=$_GET["ID"]==""?0:$_GET["ID"];
        $content=$_POST["Content"];
        if ($ID!=0) {
            $sessionid=$_GET["SessionID"];
            if (authSession($ID,$sessionid)) {            
                $result=reportError($ID,$content);
            }
            else {
                $result="AUTHORIZATION_ERROR";
            }
        }
        else { //non-registered users
            $result=reportError($ID,$content);    
        }    
    }
    else if ($_GET["action"] == "reportprofileview") {
        $ID=$_GET["ID"];
        $sessionid=$_GET["SessionID"];
        if (authSession($ID,$sessionid)) {            
            $result=reportuser($ID, $_GET["TargetID"]);
        }
        else {
            $result="AUTHORIZATION_ERROR";
        }
    }
    else if ($_GET["action"] == "reportchatone") {
        $ID=$_GET["ID"];
        $sessionid=$_GET["SessionID"];
        if (authSession($ID,$sessionid)) {            
            $result=reportmatch($ID, $_GET["TargetID"], $_GET["MatchID"]);
        }
        else {
            $result="AUTHORIZATION_ERROR";
        }
    }
    else if ($_GET["action"] == "blockprofileview") {
        $ID=$_GET["ID"];
        $sessionid=$_GET["SessionID"];
        if (authSession($ID,$sessionid)) {            
            $result=blockuser($ID, $_GET["TargetID"], $_GET["time"]);
        }
        else {
            $result="AUTHORIZATION_ERROR";
        }
    }
    else if ($_GET["action"] == "blockchatone") {
        $ID=$_GET["ID"];
        $sessionid=$_GET["SessionID"];
        if (authSession($ID,$sessionid)) {            
            $result=blockuser($ID, $_GET["TargetID"], $_GET["time"]);
        }
        else {
            $result="AUTHORIZATION_ERROR";
        }
    }
    else if ($_GET["action"] == "helpcenter") {
        $result=getQuestions();
    }
    else if ($_GET["action"] == "helpcentermessage") {
        $ID=$_GET["ID"]==""?0:$_GET["ID"];
        $content=$_GET["Content"];
        if ($ID!=0) {
            $sessionid=$_GET["SessionID"];
            if (authSession($ID,$sessionid)) {            
                $result=helpCenterMessage($ID,$content);
            }
            else {
                $result="AUTHORIZATION_ERROR";
            }
        }
        else { //non-registered users
            $result=helpCenterMessage($ID,$content);    
        }
    }
    else if ($_GET["action"]=="geocoding") {
        $ID=$_GET["ID"]==""?0:$_GET["ID"];
        $address=$_GET["Address"];
        if ($ID!=0) {
            $sessionid=$_GET["SessionID"];
            if (authSession($ID,$sessionid)) {            
                $result=geocoding($ID,$address);
            }
            else {
                $result="AUTHORIZATION_ERROR";
            }
        }
        else { //non-registered users
            $result=geocoding($ID,$address);    
        }
    }
    else if ($_GET["action"] == "usercheck") { 
        $Username=$_GET["Username"];
        $result=checkUsername($Username);    
    }     
    else if ($_GET["action"]=="uploadtotemp") {
        if (isset($_GET["regsessionid"])) {
            $regsessionid=$_GET["regsessionid"];
        }
        else {
            $regsessionid=password_hash($userip.microtime(), PASSWORD_DEFAULT, ["cost" => 8]);
            $regsessionid=filenameSafe($regsessionid);
        }         
        $file=$_FILES["file"];
        $result=uploadImage(false,$regsessionid,$file);       
    }
    else if ($_GET["action"]=="uploadtouser") {
        $ID=$_GET["ID"];
        $sessionid=$_GET["SessionID"];
        $file=$_FILES["file"];
        if (authSession($ID,$sessionid)) {            
            $result=uploadImage(true,$ID,$file);
        }
        else {
            $result="AUTHORIZATION_ERROR";
        }     
    }      
    else if ($_GET["action"]=="deletetemp") {
        $regsessionid=$_GET["regsessionid"];
        $imageName=$_GET["imageName"];
        $result=deleteTempImage($regsessionid,$imageName);       
    }
    else if ($_GET["action"]=="deleteexisting") {
        $ID=$_GET["ID"];
        $sessionid=$_GET["SessionID"];
        $imageName=$_GET["imageName"];
        if (authSession($ID,$sessionid)) {
            $result=deleteExistingImage($ID,$imageName);
        }
        else {
            $result="AUTHORIZATION_ERROR";
        }
    } 
    else if ($_GET["action"]=="updatepictures") {
        $ID=$_GET["ID"];
        $sessionid=$_GET["SessionID"];
        $Pictures=$_GET["Pictures"];
        if (authSession($ID,$sessionid)) {
            $result=updatePictures($ID,$Pictures);
        }
        else {
            $result="AUTHORIZATION_ERROR";
        }
    }     
    else if ($_GET["action"] == "resetform") {
        $regsessionid=$_GET["regsessionid"];
        $result=resetForm($regsessionid); 
        $imageName=$_GET["imageName"];
               
    }    
    else if ($_GET["action"] == "register") {
        $result=registerUser();
    }     
    else if ($_GET["action"] == "login") {
        $User=$_GET["User"];
        $Password=$_GET["Password"];
        $result=loginUser($User,$Password);
    }
    else if ($_GET["action"] == "loginsession") {
        $ID=$_GET["ID"];
        $sessionid=$_GET["SessionID"];
        if (authSession($ID,$sessionid)) {
            $result=getLoginInfo($ID,$sessionid);
        }
        else {
            $result="ERROR_LoginFailed";
        }
    }
    else if ($_GET["action"] == "updatetoken") {
        $ID=$_GET["ID"];
        $sessionid=$_GET["SessionID"];
        if (authSession($ID,$sessionid)) {
            if (isset($_GET["token"])) {
                //can be removed after Android update
                $ios=(isset($_GET["ios"]))?$_GET["ios"]:0;
                sqlupdate("session", array("Token" => array("s",$_GET["token"]), "iOS" => array("i", $ios)), array("ID" => array("i",$ID))); 
                $result="OK";   
            }
            else {
                $result="Error: Token missing.";
            }
        }
        else {
            $result="AUTHORIZATION_ERROR";
        }
    }
    else if ($_GET["action"] == "profileedit") {
        $ID=$_GET["ID"];
        $sessionid=$_GET["SessionID"];
        if (authSession($ID,$sessionid)) {
            $result=updateProfile($ID);
        }
        else {
            $result="AUTHORIZATION_ERROR";
        }
    }
    else if ($_GET["action"] == "updatesettings") {
        $ID=$_GET["ID"];
        $sessionid=$_GET["SessionID"];
        if (authSession($ID,$sessionid)) {
            $result=updateSettings($ID);
        }
        else {
            $result="AUTHORIZATION_ERROR";
        }
    }    
    else if ($_GET["action"] == "list") {
        $ID=$_GET["ID"];
        $sessionid=$_GET["SessionID"];
        if ($ID != "") {
            if (authSession($ID,$sessionid)) {
                $result=loadList($ID);
            }
            else {
                $result="AUTHORIZATION_ERROR";
            }
        }
        else {
            $result=loadList();
        }               
    }
    else if ($_GET["action"] == "listsearch") {
        $ID=$_GET["ID"];
        $sessionid=$_GET["SessionID"];
        if ($ID != "") {
            if (authSession($ID,$sessionid)) {
                $result=loadListSearch($ID);
            }
            else {
                $result="AUTHORIZATION_ERROR";
            }
        }
        else {
            $result=loadListSearch();
        }       
    }
    else if ($_GET["action"] == "getuserdata") {
        $ID=$_GET["ID"];
        $target=$_GET["target"];
        $sessionid=$_GET["SessionID"];
        if (authSession($ID,$sessionid)) {
            $result=getuserdata($ID, $target);
        }
        else {
            $result="AUTHORIZATION_ERROR";
        }       
    }
    else if ($_GET["action"] == "updatelocation") {
        $ID=$_GET["ID"];
        $sessionid=$_GET["SessionID"];
        $latitude=$_GET["Latitude"];
        $longitude=$_GET["Longitude"];
        $time=$_GET["LocationTime"];
        if (authSession($ID,$sessionid)) {
            $result=updateLocation($ID,$latitude,$longitude,$time);
        }
        else {
            $result="AUTHORIZATION_ERROR";
        }              
    }
    else if ($_GET["action"] == "updatelocationmatch") {
        $ID=$_GET["ID"];
        $sessionid=$_GET["SessionID"];
        $latitude=$_GET["Latitude"];
        $longitude=$_GET["Longitude"];
        $time=$_GET["LocationTime"];
        if (authSession($ID,$sessionid)) {
            $result=updateLocationMatch($ID,$latitude,$longitude,$time);
        }
        else {
            $result="AUTHORIZATION_ERROR";
        }              
    }
    else if ($_GET["action"] == "updatelocationend" && isset($_GET["LocationUpdates"])) {
        $ID=$_GET["ID"];
        $sessionid=$_GET["SessionID"];
        if (authSession($ID,$sessionid)) {
            $result=updateLocationEnd($ID);
        }
        else {
            $result="AUTHORIZATION_ERROR";
        }              
    }
    else if ($_GET["action"] == "like") {
        $ID=$_GET["ID"];
        $target=$_GET["target"];
        $time=$_GET["time"];
        $sessionid=$_GET["SessionID"];
        if (authSession($ID,$sessionid)) {
            $result=likeProfile($ID,$target,$time);
        }
        else {
            $result="AUTHORIZATION_ERROR";
        }
    }
    else if ($_GET["action"] == "addfriend") {
        $ID=$_GET["ID"];
        $target=$_GET["target"];
        $time=$_GET["time"];
        $sessionid=$_GET["SessionID"];
        if (authSession($ID,$sessionid)) {
            $result=addFriend($ID,$target,$time);
        }
        else {
            $result="AUTHORIZATION_ERROR";
        }
    }
    else if ($_GET["action"] == "removefriend") {
        $ID=$_GET["ID"];
        $target=$_GET["target"];
        $time=$_GET["time"];
        $sessionid=$_GET["SessionID"];
        if (authSession($ID,$sessionid)) {
            $result=removeFriend($ID,$target,$time);
        }
        else {
            $result="AUTHORIZATION_ERROR";
        }
    }
    else if ($_GET["action"] == "unmatch") {
        $ID=$_GET["ID"];
        $target=$_GET["target"];
        $time=$_GET["time"];
        $sessionid=$_GET["SessionID"];
        if (authSession($ID,$sessionid)) {
            $result=unmatchProfile($ID,$target,$time);
        }
        else {
            $result="AUTHORIZATION_ERROR";
        }    
    }
    else if ($_GET["action"] == "hide") {
        $ID=$_GET["ID"];
        $target=$_GET["target"];
        $time=$_GET["time"];
        $sessionid=$_GET["SessionID"];        
        if (authSession($ID,$sessionid)) {
            $result=hideProfile($ID,$target,$time);
        }
        else {
            $result="AUTHORIZATION_ERROR";
        }
    }
    else if ($_GET["action"] == "unhide") {
        $ID=$_GET["ID"];
        $target=$_GET["target"];
        $time=$_GET["time"];
        $sessionid=$_GET["SessionID"];        
        if (authSession($ID,$sessionid)) {
            $result=unhideProfile($ID,$target);
        }
        else {
            $result="AUTHORIZATION_ERROR";
        }
    } 
    else if ($_GET["action"]=="deactivateaccount") {
        $ID=$_GET["ID"];
        $sessionid=$_GET["SessionID"];
        if (authSession($ID,$sessionid)) {
            $result=deactivateAccount($ID);
        }
        else {
            $result="AUTHORIZATION_ERROR";
        }
    }
    else if ($_GET["action"]=="activateaccount") {
        $ID=$_GET["ID"];
        $sessionid=$_GET["SessionID"];
        if (authSession($ID,$sessionid)) {
            $result=activateAccount($ID);
        }
        else {
            $result="AUTHORIZATION_ERROR";
        }
    }
    else if ($_GET["action"]=="deleteaccount") {
        $ID=$_GET["ID"];
        if (isset($_GET["SessionID"])) {
            $sessionid=$_GET["SessionID"];
            if (authSession($ID,$sessionid)) {
                $result=deleteAccount($ID);
            } 
            else {
                $result="AUTHORIZATION_ERROR";
            }
        }
        else {
            $regsessionid=$_GET["RegSessionID"];
            if (authRegSession($ID,$regsessionid)) {
                $result=deleteRegAccount($ID);
            } 
            else {
                $result="AUTHORIZATION_ERROR";
            }
        }
    } 
    else if ($_GET["action"]=="requestmatchid") {
        $ID=$_GET["ID"];
        $target=$_GET["target"];
        $sessionid=$_GET["SessionID"];
        if (authSession($ID,$sessionid)) {
            $result=requestmatchid($ID,$target);
        } 
        else {
            $result="AUTHORIZATION_ERROR";
        } 
    }  
    else if ($_GET["action"]=="loadmessagelist") {
        $ID=$_GET["ID"];
        $sessionid=$_GET["SessionID"];
        if (authSession($ID,$sessionid)) {
            $result=loadmessagelist($ID);
        } 
        else {
            $result="AUTHORIZATION_ERROR";
        }
    }    
    else if ($_GET["action"]=="loadmessages") {
        $ID=$_GET["ID"];        
        $sessionid=$_GET["SessionID"];
        if (isset($_GET["MatchID"])) {
            $MatchID=$_GET["MatchID"];
            $TargetID=null;
        }
        else {
            $MatchID=null;
            $TargetID=$_GET["TargetID"];
        }
        
        if (authSession($ID,$sessionid)) {
            $result=loadmessages($ID,$MatchID,$TargetID);
        } 
        else {
            $result="AUTHORIZATION_ERROR";
        }
    }    
    else if ($_GET["action"]=="sendmessage") {
        $ID=$_GET["ID"];        
        $sessionid=$_GET["SessionID"];
        $MatchID=$_GET["MatchID"];
        $message=$_GET["message"];        
        if (authSession($ID,$sessionid)) {
            $result=sendmessage($ID, $MatchID, $message);
        } 
        else {
            $result="AUTHORIZATION_ERROR";
        }
    }
    else if ($_GET["action"]=="messagedelivered") {
        $ID=$_GET["ID"];        
        $sessionid=$_GET["SessionID"];
        $MatchID=$_GET["MatchID"];
        $MessageID=$_GET["MessageID"];  
        $Status=$_GET["Status"];      
        if (authSession($ID,$sessionid)) {
            $result=messagedelivered($ID, $MatchID, $MessageID, $Status);
        } 
        else {
            $result="AUTHORIZATION_ERROR";
        }
    }
    else if ($_GET["action"]=="resetpassword") {
        $Email=$_GET["Email"];
        $result=resetPassword($Email);
    }
    else if ($_GET["action"]=="setpassword") {
        $ID=$_GET["ID"];        
        $sessionid=$_GET["SessionID"];
        if (authSession($ID,$sessionid)) {
            $result=setpassword($ID,$sessionid);
        } 
        else {
            $message="Link expired. Have you already changed your password / logged in?";
            $content=file_get_contents("base_message.html");
            $result=str_replace("[message]",$message,$content);            
        }
    }
    else if ($_GET["action"]=="changepassword") {
        $ID=$_GET["ID"];        
        $sessionid=$_GET["SessionID"];
        $Password=$_POST["Password"];
        $ConfirmPassword=$_POST["ConfirmPassword"];
        if (authSession($ID,$sessionid)) {
            $result=changepassword($ID, $sessionid, $Password, $ConfirmPassword);
        } 
        else {
            $result="AUTHORIZATION_ERROR";
        }    
    } 
    else if ($_GET["action"]=="eula") {
        $text=file_get_contents("eula.html");  
        $link="https://locationconnection.me/?page=legal#terms"; 
        $text=str_replace("[link]","on <a href=\"$link\">$link</a>",$text);
        $result="OK;$text";
    }   
    else {
        MainPage("home");
    }
}
else if (isset($_POST["homepagemessages"])) {
    global $userip;
    
    $content=$_POST["homepagemessages"];
    sqlinsert("homepage_messages",array(
        "Time"=>array("s",date("Y-m-d H:i:s",time())),
        "Content"=>array("s",$content),
        "IP"=>array("s",$userip)
    ),false);
    
    require("mail.php");
    $res=sendMail("New home page message", $content);
    if ($res !== true) {
        insertError($res);
    }
    
    switch($_GET["page"]) {
        case "ios":
            $page="ios";
            break;
        case "helpcenter":
            $page="helpcenter";
            break;
        default:
            $page="home";
            break;
    }
    MainPage($page,"Your message was sent.");    
}
else if (isset($_GET["page"])) {
    switch ($_GET["page"]) {
        default:
        case "home":
            MainPage("home");
            break;
        case "screenshots":
            MainPage("screenshots");
            break;
        case "ios":
            MainPage("ios");
            break;
        case "helpcenter":
            MainPage("helpcenter");
            break;
        case "legal":
            MainPage("legal");
            break;
    } 
}
else {
    MainPage("home");
}

if ($result != "") {
    if (!(isset($_GET["action"]) && ($_GET["action"]=="register" || $_GET["action"]=="login" || $_GET["action"]=="loginsession" || $_GET["action"]=="profileedit" || $_GET["action"]=="setpassword" || $_GET["action"]=="changepassword"))) {
        //if (!in_array($userip, $conn::EXCLUDED_IPS)) {
            sqlupdate("log_input", array("Response"=>array("s",removeSession(truncateString($result,$maxLogLength)))), array("ID"=>array("i",$requestID)));   
        //}
    }
    print $result;
}                   

//---------------- WEB VIEW ----------------

function MainPage($page, $result="") {
    global $conn, $userip, $homeLogLength;
    
    if (!in_array($userip, $conn::EXCLUDED_IPS)) {
        sqlinsert("log_homepage", array(
            "Time"=>array("s",date("Y-m-d H:i:s",time())),
            "URL"=>array("s",truncateString($_SERVER["REQUEST_URI"],$homeLogLength)),
            "Referer"=>array("s",isset($_SERVER["HTTP_REFERER"])?str_replace("https://".$_SERVER["HTTP_HOST"],"",truncateString($_SERVER["HTTP_REFERER"],$homeLogLength)):""),
            "IP"=>array("s",$userip)
        ),false);   
    }
    
    $frame=file_get_contents("frame.html");
    
    $frame=str_replace("onclick=\"go('$page')\" class=\"menu\"","onclick=\"go('$page')\" class=\"menuselected\"",$frame);
    
    $content=file_get_contents("$page.html");
    if ($page=="screenshots") {
        $images=array(
            "Screenshot_20191222_180003_balintfodor.locationconnection.jpg",
            "Screenshot_20191231_173422_balintfodor.locationconnection.jpg",
            "Screenshot_20191223_143941_balintfodor.locationconnection.jpg",
            "Screenshot_20191221_195806_balintfodor.locationconnection.jpg",
            "Screenshot_20191221_200020_balintfodor.locationconnection.jpg",
            "Screenshot_20191222_184337_balintfodor.locationconnection.jpg",
            "Screenshot_20191221_200859_balintfodor.locationconnection.jpg",
            "Screenshot_20191219_084149_balintfodor.locationconnection.jpg",         
            "Screenshot_20191231_131847_balintfodor.locationconnection.jpg"            
        );
        $counter=0;
        $str="";
        $imagew=320;
        $imageh=512;
        foreach ($images as $image) {
            $left=$counter%3*($imagew+10)+10;
            $top=($counter-$counter%3)/3*($imageh+10)+10;            
            $str.='<img class="screenshot" id="image'.$counter.'" onclick="zoomImage(this,'.$counter.')" src="screenshots/'.$image.'" style="z-index:'.$counter.'; width: '.$imagew.'px; top:'.$top.'px; left:'.$left.'px" />';    
            $counter++;
        } 
        $contentheight=$top+$imageh+10;                  
        $content=str_replace("[images]",$str,$content);
        $content=str_replace("[contentheight]",$contentheight,$content);
    }
    else if ($page=="helpcenter") {
        $stmt=&sqlselectall("select question, answer from helpcenter_questions");
        $stmt->bind_result($question, $answer);
        $str="";
        while($stmt->fetch()) {
            $str.="<p class='question'>".nl2br($question)."</p>".nl2br($answer)."<br /><br />";
        }
        $stmt->close();
        $content=str_replace("[questions]",$str,$content);
    }
    else if ($page == "legal") {
        $content=str_replace("[eula]",str_replace("[link]", "here", file_get_contents("eula.html")),$content);
    }
    
    $frame=str_replace("[content]",$content,$frame);
    
    if ($result != "") {
        $result="alert('$result');";
    }
    $frame=str_replace("[result]",$result,$frame);
    $frame=str_replace("[page]",$page,$frame); //tell Javascript which page we are on
        
    print $frame;
}

//---------------- APP FUNCTIONS ----------------

function reportError($ID, $content) {       
    insertError($content, $ID);
    
    require("mail.php");
    $res=sendMail("Error", $content);
    if ($res !== true) {
        insertError($res, $ID);
    } 
    return "OK";
}

function insertError($content, $ID=0) {
    global $userip;
    
    sqlinsert("errors",array(
        "Time"=>array("s",date("Y-m-d H:i:s",time())),
        "UserID"=>array("i",$ID),
        "Content"=>array("s",$content),
        "IP"=>array("s",$userip)
    ),false);
}

function reportuser($ID, $target) {
    $stmt=&sqlselectbymany("select ID from matches where (FirstID=? and SecondID=?) or (FirstID=? and SecondID=?)", array(array("i",$ID), array("i",$target), array("i",$target), array("i",$ID)));
    $stmt->store_result();
    $count=$stmt->num_rows;
    
    $match=null;
    if ($count != 0) {
        $stmt->bind_result($match);
        $stmt->fetch();
        $stmt->free_result();
    }

    list($Username, $TargetUsername)=insertReport($ID, $target, $match);
    
    require("mail.php");
    $res=sendMail("Reported user", "$Username ($ID) => $TargetUsername ($target)" . (($match !== null)?", MatchID: $match":""));
    if ($res !== true) {
        insertError($res, $ID);
    } 
    return "OK";
}

function reportmatch($ID, $target, $match) {
    list($Username, $TargetUsername)=insertReport($ID, $target, $match);
    
    require("mail.php");
    $res=sendMail("Reported match", "$Username ($ID) => $TargetUsername ($target), MatchID: $match");
    if ($res !== true) {
        insertError($res, $ID);
    } 
    return "OK";
}

function insertReport($ID, $target, $match) {
    global $userip;

    //usernames can change, but getting it now makes lookup easier
    $stmt=&sqlselect("select Username from profiledata where ID=?", array("i",$ID));
    $stmt->bind_result($Username);
    $stmt->fetch();
    $stmt->free_result();

    $stmt=&sqlselect("select Username from profiledata where ID=?", array("i",$target));
    $stmt->bind_result($TargetUsername);
    $stmt->fetch();
    $stmt->free_result();

    sqlinsert("reports",array(
        "Time"=>array("s",date("Y-m-d H:i:s",time())),
        "UserID"=>array("i",$ID),
        "Username"=>array("s",$Username),
        "TargetID"=>array("i",$target),
        "TargetUsername"=>array("s",$TargetUsername),
        "MatchID"=>array("i",$match),
        "IP"=>array("s",$userip)
    ),false);

    return array($Username, $TargetUsername);
}

function blockuser($ID, $target, $time) {
    global $mysqli;

    //check if it is an active or passive match
    $stmt=&sqlselectbymany("select ID, Active, UnmatchInitiator from matches where (FirstID=? and SecondID=?) or (FirstID=? and SecondID=?)", array(array("i",$ID), array("i",$target), array("i",$target), array("i",$ID)));
    $stmt->store_result();
    $count=$stmt->num_rows;
    if ($count != 0) {
        $stmt->bind_result($MatchID, $Active, $UnmatchInitiator);
        $stmt->fetch();
        $stmt->free_result();

        if ($UnmatchInitiator == $target) {
            sqldelete("matches", array("ID" => array("i",$MatchID)));
        }
        else {
            $unmatchDate=date("Y-m-d H:i:s",$time);
            sqlexecuteparams("update matches set Active=0, UnmatchDate=?, UnmatchInitiator=? where (FirstID=? and SecondID=?) or (FirstID=? and SecondID=?)",
        array(array("s", $unmatchDate), array("i",$ID), array("i",$target), array("i",$ID), array("i",$ID), array("i",$target)));
            
            $stmt=&sqlselectbymany("select Token, iOS, UnmatchInApp, UnmatchBackground from session, profilesettings where session.ID = ? and profilesettings.ID = ?", array(array("i",$target), array("i",$target)));
            $stmt->bind_result($token, $ios, $UnmatchInApp, $UnmatchBackground);
            $stmt->fetch();
            $stmt->free_result();
            
            $stmt=&sqlselect("select Name from profiledata where ID=?", array("i",$ID));
            $stmt->bind_result($TargetName);
            $stmt->fetch();
            $stmt->free_result();
            
            //location updates are stopped within the app
            sendCloud($ID, $target, $token, $ios, $UnmatchBackground, $UnmatchInApp, "$TargetName ".YOUUNMATCHEDFROM, null, "unmatchProfile", "$MatchID|$time");
        }
    }

    $mysqli->query("lock tables likehide write");

    //remove the initiator's like from their likes and the target's likedby. Remove friend, friendby from the source and target ID
    $stmt=&sqlselect("select Likes, Friends, FriendsBy from likehide where ID=?", array("i",$ID));
    $stmt->bind_result($Likes, $Friends, $FriendsBy);
    $stmt->fetch(); 
    $stmt->free_result();
    
    removeLikeHideItem($Likes,$target);
    removeLikeHideItem($Friends,$target);
    removeLikeHideItem($FriendsBy,$target);
    
    sqlupdate("likehide", array("Likes" => array("s",$Likes), "Friends"=>array("s",$Friends), "FriendsBy"=>array("s",$FriendsBy)), array("ID" => array("i",$ID)));
    
    $stmt=&sqlselect("select LikedBy, Friends, FriendsBy from likehide where ID=?", array("i",$target));
    $stmt->bind_result($LikedBy, $Friends, $FriendsBy);
    $stmt->fetch(); 
    $stmt->free_result();
    
    removeLikeHideItem($LikedBy,$ID);
    removeLikeHideItem($Friends,$ID);
    removeLikeHideItem($FriendsBy,$ID);
    
    sqlupdate("likehide", array("LikedBy" => array("s",$LikedBy), "Friends"=>array("s",$Friends), "FriendsBy"=>array("s",$FriendsBy)), array("ID" => array("i",$target)));

    //check if block exists and update it. Block should not exist, unless HTTP query is repeated
    $stmt=&sqlselect("select Blocks from likehide where ID=?", array("i",$ID));
    $stmt->bind_result($Blocks);
    $stmt->fetch(); 
    $stmt->free_result();

    $targetblockexists=existsLikeHideItem($Blocks, $target);

    if (!$targetblockexists) {
        addLikeHideItemUpdate("Blocks",$Blocks,$target,$time,$ID);

        $stmt=&sqlselect("select BlockedBy from likehide where ID=?", array("i",$target));
        $stmt->bind_result($BlockedBy);
        $stmt->fetch(); 
        $stmt->free_result();
        
        addLikeHideItemUpdate("BlockedBy",$BlockedBy,$ID,$time,$target);
        
        $mysqli->query("unlock tables");
        $stmt->close();

        return "OK";
    }
    else {
        $mysqli->query("unlock tables");
        $stmt->close();

        return "Error: Block already exists.";
    }
}

function getQuestions() {
    $stmt=&sqlselectall("select question, answer from helpcenter_questions");
    $stmt->bind_result($question, $answer);
    $str="";
    while($stmt->fetch()) {
        $str.=$question."\t".$answer."\t";
    }
    $stmt->close();
    $str=substr($str,0,strlen($str)-1);
    return "OK;$str";
}

function helpCenterMessage($ID, $content) {
    global $userip;
    sqlinsert("helpcenter_messages",array(
        "Time"=>array("s",date("Y-m-d H:i:s",time())),
        "UserID"=>array("i",$ID),
        "Content"=>array("s",$content),
        "IP"=>array("s",$userip)
    ),false);
    
    require("mail.php");
    $res=sendMail("New helpcenter message", $content);
    if ($res !== true) {
        insertError($res, $ID);
    }
    
    return "OK";
}

function geocoding($ID, $address) {
    global $conn, $userip, $requestID, $monthlyGeocodingAllowance;
    
    $stmt=&sqlselect("select Address, Latitude, Longitude from geocoding where SearchAddress = ?", array("s",$address));
    $stmt->store_result();
    $count=$stmt->num_rows;
    if ($count!=0) {
        $stmt->bind_result($Address, $Latitude, $Longitude);
        $stmt->fetch();
        $stmt->close();
        if ($Address != "ZERO_RESULTS") {
            return "OK;$Address|$Latitude|$Longitude";
        }
        else {
            return "ZERO_RESULTS";
        }         
    } 
    $stmt->free_result();
    
    $time=time();
    $startTime=date("Y-m-01 00:00:00",$time);
    $stmt=&sqlselectall("select count(*) from geocoding where Time >= '$startTime' and Time <= now()");
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();      
    
    $daysInMonth=cal_days_in_month(CAL_GREGORIAN, date("n"), date("Y"));
    $dailyAllowance=floor($monthlyGeocodingAllowance/$daysInMonth);
    $daysElapsed=date("j");
    if ($count >= $daysElapsed * $dailyAllowance) {
        sqlinsert("log_errors", array(
            "RequestID"=>array("i",$requestID),
            "Time"=>array("s",date("Y-m-d H:i:s",time())),
            "Content"=>array("s","Over query limit on startup."),
        ),false);
        return "OVER_QUERY_LIMIT";
    }
    
    $url = "https://maps.googleapis.com/maps/api/geocode/json?address=".urlencode($address)."&key=".$conn::GOOGLEMAPS_KEY;
    $result = file_get_contents($url);
    if ($result === false) {
        sqlinsert("log_errors", array(
            "RequestID"=>array("i",$requestID),
            "Time"=>array("s",date("Y-m-d H:i:s",time())),
            "Content"=>array("s","Error requesting coordinates."),
        ),false);
        return "Error requesting coordinates.";
    }
    
    $statusPos = strpos($result, "\"status\"");
	$quota1Pos = strpos($result, '"', $statusPos + 8);
	$quota2Pos = strpos($result, '"', $quota1Pos + 1);
	$status = substr($result, $quota1Pos + 1, $quota2Pos - $quota1Pos - 1);
	if ($status == "OK")
	{
		$formattedPos = strpos($result, "\"formatted_address\"");
		$quota1Pos = strpos($result, '"', $formattedPos + 19);
		$quota2Pos = strpos($result, '"', $quota1Pos + 1);
		$formattedAddress = substr($result, $quota1Pos + 1, $quota2Pos - $quota1Pos - 1);

		$latPos = strpos($result, "\"lat\"");
		$colonPos = strpos($result, ':', $latPos + 5);
		$commaPos = strpos($result, ',', $colonPos + 1);
		$latitude = trim(substr($result, $colonPos + 1, $commaPos - $colonPos - 1));

		$longPos = strpos($result, "\"lng\"");
		$colonPos = strpos($result, ':', $longPos + 5);
		$bracePos = strpos($result, '}', $colonPos + 1);
		$longitude = trim(substr($result, $colonPos + 1, $bracePos - $colonPos - 1));
        
        sqlinsert("geocoding", array(
            "Time" =>array("s",date("Y-m-d H:i:s",time())),
            "UserID" =>array("i",$ID),
            "SearchAddress" =>array("s",$address),
            "Address" =>array("s",$formattedAddress),
            "Latitude" =>array("d",$latitude),
            "Longitude" =>array("d",$longitude),
            "IP"=>array("s",$userip)
        ), false);
        
        return "OK;$formattedAddress|$latitude|$longitude";
    }
    else if ($status=="ZERO_RESULTS") { //counts in the quota too
        sqlinsert("geocoding", array(
            "Time" =>array("s",date("Y-m-d H:i:s",time())),
            "UserID" =>array("i",$ID),
            "SearchAddress" =>array("s",$address),
            "Address" =>array("s",$status),
            "Latitude" =>array("d",null),
            "Longitude" =>array("d",null),
            "IP"=>array("s",$userip)
        ), false);
        
        return $status;
    }
    else if ($status=="OVER_QUERY_LIMIT") {
        sqlinsert("log_errors", array(
            "RequestID"=>array("i",$requestID),
            "Time"=>array("s",date("Y-m-d H:i:s",time())),
            "Content"=>array("s","Over query limit from Google."),
        ),false);
        return $status;
    }
    else { //other status
        sqlinsert("log_errors", array(
            "RequestID"=>array("i",$requestID),
            "Time"=>array("s",date("Y-m-d H:i:s",time())),
            "Content"=>array("s","Other geocoding response: ".$status),
        ),false);
        return $status;
    }    
}

function checkUsername($Username) {
    $stmt=&sqlselect("select ID from profiledata where Username=?",array("s",$Username));
    $stmt->store_result();
    $count=$stmt->num_rows;
    $stmt->close();
    if ($count != 0) {
        return "ERROR_UsernameExists";
    }
    return "OK";
}

function checkEmail($Email) {
    $stmt=&sqlselect("select ID from profiledata where Email=?",array("s",$Email));
    $stmt->store_result();
    $count=$stmt->num_rows;
    $stmt->close();
    if ($count != 0) {
        return "ERROR_EmailExists";
    }
    return "OK";
}

function uploadImage($touser, $ID, $file) {
    global $conn, $tempUploadFolder, $uploadFolder, $smallImageSize, $largeImageSize;   
    
    $tmp_name=$file["tmp_name"];  
    $fileName = basename($file["name"]);
    //we need to bypass the caching system, so the user can upload a different picture with the same name.
    $pos=strrpos($fileName,".");
    $fileName=filenameSafe(substr($fileName,0,$pos))."_".time().".".substr($fileName,$pos+1); 
                            
    $imageFileType = strtolower(pathinfo($file["name"],PATHINFO_EXTENSION));
    
    if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg") {
        return "ERROR_WrongImageExtension";
    }
    else {
        $check=getimagesize($tmp_name);
        if ($check === false) {
            return "ERROR_NotAnImage";
        }
        else {
            if ($file["size"] > 20*1024*1024) {
                return "ERROR_PictureTooLarge";
            }
            else {
                $exif=@exif_read_data($tmp_name);
                if ($exif != null && array_key_exists("Orientation", $exif)) {
                    $orientation=exif_read_data($tmp_name)["Orientation"];
                }
                else {
                    $orientation=1;
                }
                
                if (!$touser) {
                    $targetDirSmall="$tempUploadFolder/$ID/$smallImageSize/";
                    $targetDirLarge="$tempUploadFolder/$ID/$largeImageSize/";
                    
                    if (!$conn->bucket) { //unnecessary for Cloud, but it works.
                        if (!is_dir("$tempUploadFolder/$ID/")) {
                            mkdir("$tempUploadFolder/$ID/");
                        }
                        if (!is_dir($targetDirSmall)) {
                            mkdir($targetDirSmall);
                        }
                        if (!is_dir($targetDirLarge)) {
                            mkdir($targetDirLarge);
                        }
                    }
                    
                    $targetFileSmall=$targetDirSmall.$fileName;
                    $targetFileLarge=$targetDirLarge.$fileName;
                    if (file_exists($targetFileSmall) || file_exists($targetFileLarge)) {
                        return "Error: This image already exists.";
                    }
                    
                    if (resize_image($tmp_name,$targetFileSmall,$smallImageSize,$smallImageSize,$orientation)) { //move_uploaded_file($tmp_name,$targetFile)
                        if (resize_image($tmp_name,$targetFileLarge,$largeImageSize,$largeImageSize,$orientation)) {
                            return "OK;$fileName;$ID";    
                        }
                        else {
                            return "There was an error uploading your file: ".$errorText;
                        }
                    }
                    else {
                        return "There was an error uploading your file: ".$errorText;
                    }
                }
                else {
                    $stmt=&sqlselect("select Pictures from profiledata where ID=?", array("i",$ID));
                    $stmt->bind_result($Pictures);
                    $stmt->fetch();
                    $stmt->close();
                    $newPictures=$Pictures."|".$fileName;
                    
                    $targetFileSmall="$uploadFolder/$ID/$smallImageSize/".$fileName;
                    $targetFileLarge="$uploadFolder/$ID/$largeImageSize/".$fileName; 
                    
                    if (file_exists($targetFileSmall) || file_exists($targetFileLarge)) {
                        return "Error: This image already exists.";
                    }
                                        
                    if (resize_image($tmp_name,$targetFileSmall,$smallImageSize,$smallImageSize,$orientation)) {
                        if (resize_image($tmp_name,$targetFileLarge,$largeImageSize,$largeImageSize,$orientation)) {
                            
                            sqlupdate("profiledata", array("Pictures"=>array("s",$newPictures)), array("ID"=>array("i",$ID)));
                            return "OK;$fileName";    
                        }
                        else {
                            return "There was an error uploading your file: ".$errorText;
                        }
                    }
                    else {
                        return "There was an error uploading your file: ".$errorText;
                    }
                }                
            }
        }
    }
}

function deleteTempImage($regsessionid, $imageName) {
    global $conn, $tempUploadFolder, $smallImageSize, $largeImageSize;
    
    if ($imageName != "") {
        $smallImageFolder="$tempUploadFolder/$regsessionid/$smallImageSize/";
        $largeImageFolder="$tempUploadFolder/$regsessionid/$largeImageSize/";
        unlink($smallImageFolder.$imageName);
        unlink($largeImageFolder.$imageName);
        
        if (!$conn->bucket) { //in Cloud the folder is deleted with the last file.
            $fi = new FilesystemIterator($smallImageFolder, FilesystemIterator::SKIP_DOTS);
            if (iterator_count($fi) == 0) {
                delete_dir("$tempUploadFolder/$regsessionid/");
            }
        }
    }
    else {
        delete_dir("$tempUploadFolder/$regsessionid/");
    }               
    return "OK";
}

function deleteExistingImage($ID, $imageName) {
    global $uploadFolder, $smallImageSize, $largeImageSize;
    
    $stmt=&sqlselect("select Pictures from profiledata where ID=?",array("i",$ID));
    $stmt->bind_result($Pictures);
    $stmt->fetch();
    $stmt->close();
    
    $arr=explode("|",$Pictures);
    if (count($arr) < 2) {
        return "Error: Last picture cannot be deleted.";
    }
    $index=0;
    foreach ($arr as $picture) {
        if ($picture==$imageName) {
            unset($arr[$index]);
            break;
        }
        $index++;
    }
    $Pictures=implode($arr,"|");
    $smallImageFolder="$uploadFolder/$ID/$smallImageSize/";
    $largeImageFolder="$uploadFolder/$ID/$largeImageSize/";
    unlink($smallImageFolder.$imageName);
    unlink($largeImageFolder.$imageName);
    
    sqlupdate("profiledata", array("Pictures"=>array("s",$Pictures)), array("ID"=>array("i",$ID)));
    return "OK";      
}

function updatePictures($ID,$Pictures) {
    sqlupdate("profiledata", array("Pictures"=>array("s",$Pictures)), array("ID"=>array("i",$ID)));
    return "OK";
}

function validateField($key, $value) {
    global $maxNumPictures, $allowedTimeDelay, $inAppLocationRateMin, $inAppLocationRateMax, $backgroundLocationRateMin, $backgroundLocationRateMax;
    switch($key) {
        case "Sex":
            if (!ctype_digit($value) || $value < 0 || $value > 1) return "Error: Wrong sex value";
            else $updatefieldsnum[$key]=$value;
            break;
        case "Email": 
            $res = preg_match("|^\w+([.+-]?\w+)*@[a-zA-Z0-9]+([.-]?[a-zA-Z0-9]+)*\.[a-zA-z0-9]{2,4}$|",$value); //\w = [a-zA-Z0-9_]
            if ($res === 0 || $res === false) return "Error: Wrong email format";
            $res = checkEmail($value);
            if ($res != "OK") return $res;
            break;
            //need to send confirmation email to the new address
        case "Password":
            if (strlen($value) < 6) return "Error: Password must be at least 6 characters long.";                        
            break;
        case "Username":
            if ($value === "") return "Error: Username is empty.";
            $res = checkUsername($value);
            if ($res != "OK") return $res;
            break;
        case "Name":
            if ($value === "") return "Error: Name is empty.";
            break;
        case "Pictures":
            $arr=explode("|",$value);
            foreach ($arr as $picture) {
                if ($picture === "") return "Error: One of the pictures is empty.";	
            }
            if (count($arr) > $maxNumPictures) return "Error: Too many pictures";
            break;
        case "Description":
            if ($value === "")  return "Error: Introduction is empty.";
            break;
        case "Latitude":
            if ($value !== "" && (!is_numeric($value) || $value > 90 || $value < -90)) return "Error: Invalid latitude";
            break;
        case "Longitude":
            if ($value !== "" && (!is_numeric($value) || $value > 180 || $value < -180)) return "Error: Invalid latitude";
            break;
        case "LocationTime":
            if ($value !== "" && (!ctype_digit($value) || $value < time()-$allowedTimeDelay)) return "Error: Invalid timestamp"; //10 seconds are allowed
            break;
        case "SexChoice":
            if ($value === "" || !ctype_digit($value) || $value < 0 || $value > 2) return "Error: Wrong sex choice value";
            break;
        case "UseLocation":
            if ($value != "True" && $value != "False")  return "Error: Wrong use location value";            
            break;         
        case "BackgroundLocation":
            if ($value != "True" && $value != "False")  return "Error: Wrong background location value";            
            break;
        case "LocationShare":
            if (!ctype_digit($value) || $value < 0 || $value > 4) return "Error: Wrong location share value";
            break;
        case "DistanceShare":
            if (!ctype_digit($value) || $value < 0 || $value > 4) return "Error: Wrong distance share value";
            break;
        case "MatchInApp":
        case "MessageInApp":
        case "UnmatchInApp":
        case "RematchInApp":
        case "MatchBackground":
        case "MessageBackground":
        case "UnmatchBackground":
        case "RematchBackground":
            if ($value != "True" && $value != "False")  return "Error: Wrong notification setting value";            
            break;
        case "LocationAccuracy":
            if (!ctype_digit($value) || $value < 0 || $value > 1) return "Error: Wrong location accuracy value";            
            break;
        case "InAppLocationRate":
            if (!ctype_digit($value) || $value < $inAppLocationRateMin || $value > $inAppLocationRateMax) return "Error: Wrong in-app location rate value";            
            break;
        case "BackgroundLocationRate":
            if (!ctype_digit($value) || $value < $backgroundLocationRateMin || $value > $backgroundLocationRateMax) return "Error: Wrong background location rate value";            
            break;
    }
    return false;
}

function registerUser() {
    global $conn, $tempUploadFolder, $uploadFolder, $userip, $listTypeDefault, $sortByDefault, $orderByDefault, $geoFilterDefault, $geoSourceOtherDefault,$distanceLimitDefault, $resultsFromDefault, $matchInAppDefault, $messageInAppDefault, $unmatchInAppDefault, $rematchInAppDefault, $matchBackgroundDefault, $messageBackgroundDefault, $unmatchBackgroundDefault, $rematchBackgroundDefault, $locationAccuracyDefault, $inAppLocationRateDefault, $backgroundLocationRateDefault;
    
    $regsessionid=$_GET["regsessionid"];
    unset($_GET["action"], $_GET["regsessionid"]);
    $profiledata=array();
    $profilesettings=array();
    $returnstr="OK;{";
    
    foreach ($_GET as $key => $value) {
        switch ($key) {                         
            //strings              
            case "Email":                 
            case "Username":
            case "Name":
            case "Pictures":
            case "Description":              
                if ($res=validatefield($key, $value)) return $res;
                $profiledata[$key] = array("s",$value);
                $returnstr.="$key:\"$value\",";
                break;
            case "Password":
                if ($res=validatefield($key, $value)) return $res;
                $Hash=password_hash($value, PASSWORD_DEFAULT, ["cost" => 10]);
                $profiledata[$key] = array("s",$Hash); //takes about 80 ms on my computer, 280 ms on google's server. 
                break; 
                
            //numbers
            case "Latitude":
            case "Longitude":
                if ($res=validatefield($key, $value)) return $res;
                if ($value === "") $value=null;
                $profiledata[$key] = array("d",$value);
                $returnstr.="$key:$value,";
                break;
            case "LocationTime":
                if ($res=validatefield($key, $value)) return $res;
                $profiledata[$key] = ($value === "") ? array("s",null) : array("s",date("Y-m-d H:i:s",$value));
                $returnstr.="$key:$value,";
                break;
            case "Sex":
                if ($res=validatefield($key, $value)) return $res;
                $profiledata[$key] = array("i",$value);
                $sexchoice=1-$value;
                $profilesettings["SexChoice"] = array("i",$sexchoice);
                $returnstr.="$key:$value,SexChoice:$sexchoice,";
                break; 
            case "UseLocation":
                if ($res=validatefield($key, $value)) return $res;
                $profilesettings[$key] = ($value=="True") ? array("i",1) : array("i",0);
                $returnstr.="$key:$value,";
                break; 
            case "LocationShare":
            case "DistanceShare":
                if ($res=validatefield($key, $value)) return $res;
                $profilesettings[$key] = array("i",$value);
                $returnstr.="$key:$value,";
                break;
            default:
                break;
        }
    }
    
    $time=time();
    $profiledata["RegisterDate"]=array("s",date("Y-m-d H:i:s",$time));
    $profiledata["LastActiveDate"]=array("s",date("Y-m-d H:i:s",$time));
    $profiledata["ResponseRate"]=array("d",1);
    $profiledata["IP"]=array("s",$userip);
    
    $profilesettings["BackgroundLocation"]=array("i",1);
    $profilesettings["ActiveAccount"]=array("i",1);
    $profilesettings["ListType"]=array("s",$listTypeDefault);
    $profilesettings["SortBy"]=array("s",$sortByDefault);
    $profilesettings["OrderBy"]=array("s",$orderByDefault);
    $profilesettings["GeoFilter"]=array("i",($geoFilterDefault=="False")?0:1);
    $profilesettings["GeoSourceOther"]=array("i",($geoSourceOtherDefault=="False")?0:1);
    $profilesettings["DistanceLimit"]=array("i",$distanceLimitDefault);
    $profilesettings["ResultsFrom"]=array("i",$resultsFromDefault);
    
    $profilesettings["MatchInApp"]=array("i",($matchInAppDefault=="False")?0:1);
    $profilesettings["MessageInApp"]=array("i",($messageInAppDefault=="False")?0:1);
    $profilesettings["UnmatchInApp"]=array("i",($unmatchInAppDefault=="False")?0:1);
    $profilesettings["RematchInApp"]=array("i",($rematchInAppDefault=="False")?0:1);
    $profilesettings["MatchBackground"]=array("i",($matchBackgroundDefault=="False")?0:1);
    $profilesettings["MessageBackground"]=array("i",($messageBackgroundDefault=="False")?0:1);
    $profilesettings["UnmatchBackground"]=array("i",($unmatchBackgroundDefault=="False")?0:1);
    $profilesettings["RematchBackground"]=array("i",($rematchBackgroundDefault=="False")?0:1);
    
    $profilesettings["LocationAccuracy"]=array("i",$locationAccuracyDefault);
    $profilesettings["InAppLocationRate"]=array("i",$inAppLocationRateDefault);
    $profilesettings["BackgroundLocationRate"]=array("i",$backgroundLocationRateDefault);
    
    //Defaults
    $returnstr.="RegisterDate:$time,LastActiveDate:$time,ResponseRate:1,BackgroundLocation:True,ActiveAccount:True,ListType:$listTypeDefault,SortBy:$sortByDefault,OrderBy:$orderByDefault,GeoFilter:$geoFilterDefault,GeoSourceOther:$geoSourceOtherDefault,DistanceLimit:$distanceLimitDefault,ResultsFrom:$resultsFromDefault,MatchInApp:$matchInAppDefault,MessageInApp:$messageInAppDefault,UnmatchInApp:$unmatchInAppDefault,RematchInApp:$rematchInAppDefault,MatchBackground:$matchBackgroundDefault,MessageBackground:$messageBackgroundDefault,UnmatchBackground:$unmatchBackgroundDefault,RematchBackground:$rematchBackgroundDefault,LocationAccuracy:$locationAccuracyDefault,InAppLocationRate:$inAppLocationRateDefault,BackgroundLocationRate:$backgroundLocationRateDefault,";
    
    $ID=sqlinsert("profiledata",$profiledata,true);
     
    if ($conn->bucket) {
        $src=str_replace($_ENV["ROOT"],"","$tempUploadFolder/$regsessionid");
        $dst=str_replace($_ENV["ROOT"],"","$uploadFolder/$ID");
        move_folder($src, $dst); 
    }
    else {
        recurse_copy("$tempUploadFolder/$regsessionid","$uploadFolder/$ID");
        delete_dir("$tempUploadFolder/$regsessionid");
    }
    
    $profilesettings["ID"]=array("i",$ID);
    sqlinsert("profilesettings",$profilesettings,false);
    
    $likehide=array("ID"=>array("i",$ID), "Likes"=>array("s",""), "Hides"=>array("s",""),
    "LikedBy"=>array("s",""), "HidBy"=>array("s",""), "Friends"=>array("s",""), "FriendsBy"=>array("s",""));
    sqlinsert("likehide",$likehide,false);
    
    $sessionid=password_hash($Hash, PASSWORD_DEFAULT, ["cost" => 10]);
    $token=(isset($_GET["token"]))?$_GET["token"]:null;
    $ios=(isset($_GET["ios"]))?$_GET["ios"]:0;    
    $session=array("ID"=>array("i",$ID), "Session"=>array("s",$sessionid), "Token"=>array("s",$token), "iOS" => array("i",$ios), "RegSession"=>array("s",$regsessionid));
    sqlinsert("session",$session,false); 
    
    $returnstr.="ID:$ID,SessionID:$sessionid}";
    
    require("mail.php"); 
    $res=sendMail("New registration: ".$_GET["Name"], date("Y-m-d H:i:s",$time));
    if ($res !== true) {
        insertError($res, $ID);
    }
    
    $mailContent=file_get_contents("registration_mail.html");
    $mailContent=str_replace("[name]",$_GET["Name"],$mailContent);
    $mailContent=str_replace("[ip]",$userip,$mailContent);
    $mailContent=str_replace("[id]",$ID,$mailContent);
    $mailContent=str_replace("[sid]",$regsessionid,$mailContent);
    
    $res=sendMail("Registration confirmation", $mailContent, $_GET["Email"], $_GET["Name"]);
    if ($res !== true) {
        insertError($res, $ID);
        return "Registration completed. Confirmation email could not be sent.\nError: ".$res;
    }
    else {
        return $returnstr;
    }               
}

function updateProfile($ID) { //Requests resulting in Error: are not coming from the app, but are direct api requests
    unset($_GET["action"], $_GET["ID"], $_GET["SessionID"]);
    $profiledata=array();
    $profilesettings=array();
    $returnstr="OK";
    $returnstradd="";
    foreach ($_GET as $key => $value) {
        switch ($key) {
            //strings
            case "Email":
            case "Username":
            case "Name":
            case "Description":
                if ($res=validatefield($key, $value)) return $res;
                $profiledata[$key] = array("s",$value);
                $returnstradd.="$key:\"$value\",";
                break;
            case "Password":
                $stmt=&sqlselect("select Password from profiledata where ID=?", array("i",$ID));
                $stmt->bind_result($Hash);
                $stmt->fetch();
                $stmt->close();
                if (!password_verify($_GET["OldPassword"], $Hash)) return "ERROR_PasswordMismatch";
                else if ($res=validatefield($key, $value)) return $res;                
                else {
                    $Hash=password_hash($value, PASSWORD_DEFAULT, ["cost" => 10]);
                    $profiledata[$key] = array("s",$Hash);
                    $sessionid=password_hash($Hash, PASSWORD_DEFAULT, ["cost" => 10]); 
                    sqlupdate("session", array("Session" => array("s",$sessionid)), array("ID" => array("i",$ID)));
                    $returnstradd.="SessionID:$sessionid,";
                }
                break;
            
            //numbers                            
            case "SexChoice":
                if ($res=validatefield($key, $value)) return $res;
                $profilesettings[$key] = array("i",$value);
                $returnstradd.="$key:$value,";
                break;
            case "UseLocation":
                if ($res=validatefield($key, $value)) return $res;
                if ($value=="True") {
                    $profilesettings[$key] =  array("i",1);
                }
                else {
                    $profilesettings[$key] = array("i",0);
                    $profiledata["Latitude"] = array("d",null);
                    $profiledata["Longitude"] = array("d",null);
                    $profiledata["LocationTime"] = array("s",null);
                }
                $returnstradd.="$key:$value,";
                break; 
            case "LocationShare":
            case "DistanceShare":
                if ($res=validatefield($key, $value)) return $res;
                $profilesettings[$key] = array("i",$value);
                $returnstradd.="$key:$value,";
                break;
            default:
                break;
        }
    }
    
    if (count($profiledata)>0) {
        sqlupdate("profiledata", $profiledata, array("ID" => array("i",$ID)));
    }
    if (count($profilesettings)>0) {
        sqlupdate("profilesettings", $profilesettings, array("ID" => array("i",$ID)));
    }
    
    if ($returnstradd != "") {
        $returnstr.=";{".substr($returnstradd,0,strlen($returnstradd)-1)."}"; 
    }
    return $returnstr;
}

function updateSettings($ID) {
    unset($_GET["action"], $_GET["ID"], $_GET["SessionID"]);
    $profilesettings=array();
    $returnstr="OK";
    $returnstradd="";
    foreach ($_GET as $key => $value) {
        switch($key) {
            case "MatchInApp":
            case "MessageInApp":
            case "UnmatchInApp":
            case "RematchInApp":
            case "MatchBackground":
            case "MessageBackground":
            case "UnmatchBackground":
            case "RematchBackground":
            case "BackgroundLocation": 
                if ($res=validatefield($key, $value)) return $res;           
                $profilesettings[$key]=($value=="True")?array("i",1):array("i",0);
                $returnstradd.="$key:$value,";
                break;
            case "LocationAccuracy":
            case "InAppLocationRate":
            case "BackgroundLocationRate":
                if ($res=validatefield($key, $value)) return $res;
                $profilesettings[$key]=array("i",$value);
                $returnstradd.="$key:$value,";
                break;
        }    
    }       
    if (count($profilesettings)>0) {
        sqlupdate("profilesettings", $profilesettings, array("ID" => array("i",$ID)));
    }      
    if ($returnstradd != "") {
        $returnstr.=";{".substr($returnstradd,0,strlen($returnstradd)-1)."}"; 
    }
    return $returnstr;   
}

function resetForm($regsessionid,$image) {
    global $tempUploadFolder;
    
    if (strlen($regsessionid) != 0) {
        $targetDir = "$tempUploadFolder/$regsessionid/";
        if (is_dir($targetDir)) {//an upload have happened
            if ($image === "0") {//delete all files and the directory
                delete_dir($targetDir);
            }
            else {//delete 
                unlink($targetDir.$file);
            }                
            return "OK"; 
        }
        else { //folder has been already deleted, or URL has been tampered with.
            return "INVALID_TOKEN";
        }
    }
    else { //no upload have happened yet
        return "OK";
    }
}

function loginUser($User, $Password) {
    $stmt=&sqlselectbymany("select ID, Password from profiledata where (Email=? or Username=?)", array(array("s",$User), array("s",$User)));
    $stmt->store_result();
    $count=$stmt->num_rows;
    if ($count==1) { 
        $stmt->bind_result($ID, $Hash);
        $stmt->fetch();         
        if (password_verify($Password, $Hash)) {
            $stmt->free_result();
            $sessionid=password_hash($Hash, PASSWORD_DEFAULT, ["cost" => 10]); 
            sqlupdate("session", array("Session" => array("s",$sessionid)), array("ID" => array("i",$ID)));
            sqlupdate("profiledata",array("LastActiveDate" => array("s",date("Y-m-d H:i:s",time()))), array("ID" => array("i", $ID)));           
            return getLoginInfo($ID, $sessionid); 
        } 
        else {
            $stmt->close();
            return "ERROR_LoginFailed";
        }                   
    } 
    $stmt->close();
    return "ERROR_LoginFailed";  
}

function authSession($ID, $sessionid) {
    $stmt=&sqlselectbymany("select ID from session where ID=? and Session=?", array(array("i",$ID), array("s",$sessionid)));
    $stmt->store_result(); //we cannot determine the number of rows without this
    $count=$stmt->num_rows;
    $stmt->close();
    if ($count!=1) return false; 
    if (!isset($_GET["Background"]) || $_GET["Background"]=="False") {
        sqlupdate("profiledata",array("LastActiveDate" => array("s",date("Y-m-d H:i:s",time()))), array("ID" => array("i", $ID)));
    }
    return true;
}

function authRegSession($ID, $regsessionid) {
    $stmt=&sqlselectbymany("select ID from session where ID=? and RegSession=?", array(array("i",$ID), array("s",$regsessionid)));
    $stmt->store_result(); //we cannot determine the number of rows without this
    $count=$stmt->num_rows;
    $stmt->close();
    if ($count!=1) return false;
    return true;
}

function getLoginInfo($ID, $sessionid) {
    if (isset($_GET["token"])) {
        $ios=(isset($_GET["ios"]))?$_GET["ios"]:0;
        sqlupdate("session", array("Token" => array("s",$_GET["token"]), "iOS" => array("i",$ios)), array("ID" => array("i",$ID)));    
    }
    $stmt=&sqlselectbymany("select profiledata.ID, Email, Sex, Username, Name, Pictures, Description, unix_timestamp(RegisterDate) as RegisterDate, unix_timestamp(LastActiveDate) as LastActiveDate, ResponseRate, Latitude, Longitude, unix_timestamp(LocationTime) as LocationTime, SexChoice, UseLocation, BackgroundLocation, LocationShare, DistanceShare, ActiveAccount, SearchTerm, SearchIn, ListType, SortBy, OrderBy, GeoFilter, GeoSourceOther, OtherLatitude, OtherLongitude, OtherAddress, DistanceLimit, ResultsFrom, MatchInApp, MessageInApp, UnmatchInApp, RematchInApp, MatchBackground, MessageBackground, UnmatchBackground, RematchBackground, LocationAccuracy, InAppLocationRate, BackgroundLocationRate from profiledata, profilesettings where profiledata.ID=? and profilesettings.ID=?", array(array("i",$ID), array("i",$ID)));
    $result=$stmt->get_result(); //do not use store_result before, this will return false
    $row = $result->fetch_assoc();
    $stmt->close();
    
    $str="{SessionID:$sessionid,";
    $row["UseLocation"]=($row["UseLocation"]==1)?"True":"False";
    $row["BackgroundLocation"]=($row["BackgroundLocation"]==1)?"True":"False";
    $row["ActiveAccount"]=($row["ActiveAccount"]==1)?"True":"False";
    $row["GeoFilter"]=($row["GeoFilter"]==1)?"True":"False";
    $row["GeoSourceOther"]=($row["GeoSourceOther"]==1)?"True":"False";
    
    $row["MatchInApp"]=($row["MatchInApp"]==1)?"True":"False";
    $row["MessageInApp"]=($row["MessageInApp"]==1)?"True":"False";
    $row["UnmatchInApp"]=($row["UnmatchInApp"]==1)?"True":"False";
    $row["RematchInApp"]=($row["RematchInApp"]==1)?"True":"False";
    $row["MatchBackground"]=($row["MatchBackground"]==1)?"True":"False";
    $row["MessageBackground"]=($row["MessageBackground"]==1)?"True":"False";
    $row["UnmatchBackground"]=($row["UnmatchBackground"]==1)?"True":"False";
    $row["RematchBackground"]=($row["RematchBackground"]==1)?"True":"False";
    
    foreach ($row as $key => $value) {
        $str.="$key:";
        if ($key=="Email" || $key=="Username" || $key=="Name" || $key=="Pictures" || $key=="Description" || $key=="OtherAddress") {
            $str.="\"$value\",";
        }
        else {
            $str.="$value,";
        }
    }
    $str=substr($str,0,strlen($str)-1);
    $str.="}";
    
    return "OK;$str";
}

function loadList($ID=0) {
    global $maxResultCount;
    
    switch ($_GET["SortBy"]) {
        case "LastActiveDate":
            $sortBy="LastActiveDate";
            break;
        case "ResponseRate":
            $sortBy="ResponseRate";
            break;
        case "RegisterDate":
            $sortBy="RegisterDate";
            break;
        default: //will work for missing GET parameter too, with a notice for the undefined index first
            return "Error: Wrong sort by value";
    }
    switch ($_GET["OrderBy"]) {
        case "asc":
            $orderBy="asc";
            break;
        case "desc":
            $orderBy="desc";
            break;
        default:
            return "Error: Wrong order by value";
    }
    if (!ctype_digit($_GET["ResultsFrom"]) || $_GET["ResultsFrom"] < 1) {
        return "Error: Wrong start parameter";
    }
    
    if ($_GET["GeoFilter"]=="True") {
        $geoFilter=true;
        if ($_GET["GeoSourceOther"] == "True") {
            $latitude=$_GET["OtherLatitude"];
            $longitude=$_GET["OtherLongitude"];
        }
        else {
            $latitude=$_GET["Latitude"];
            $longitude=$_GET["Longitude"];
        }
    }
    else {
        $geoFilter=false;
        $latitude=$_GET["Latitude"];
        $longitude=$_GET["Longitude"];
    }
    
    $resultsFrom=$_GET["ResultsFrom"]-1;
    
    //sql caching needs to be on
    $sqlfields="select profiledata.ID, Sex, Username, Name, Pictures, Description, unix_timestamp(RegisterDate) as RegisterDate, unix_timestamp(LastActiveDate) as LastActiveDate, ResponseRate, Latitude, Longitude, unix_timestamp(LocationTime) as LocationTime, UseLocation, LocationShare, DistanceShare";
    $sqlcount="select count(*)";        
    if (!$geoFilter) {
        $sqlbase=" from profiledata, profilesettings where profiledata.ID = profilesettings.ID and profilesettings.ActiveAccount = 1 order by $sortBy $orderBy";
        $sqlbasecount=$sqlbase;       
    }
    else {
        //execution times must me measured: does selecting count take less time, or the same? In the latter case we should not create column Distance, but calculate it again in PHP for the results.
        if (!is_numeric($latitude) || $latitude > 90 || $latitude < -90) return "Error: Invalid latitude";
        if (!is_numeric($longitude) || $longitude > 180 || $longitude < -180) return "Error: Invalid longitude"; 
        $distanceLimit=$_GET["DistanceLimit"];
        
        $calc="6371 * acos(
            cos(radians($latitude)) * cos(radians(Latitude)) * cos(radians(Longitude) - radians($longitude))
            + sin(radians($latitude)) * sin(radians(Latitude))
            )";
        $sqlfields.=", ($calc) as Distance"; 
                
        $sqlbase=" from profiledata, profilesettings where profiledata.ID = profilesettings.ID and profilesettings.ActiveAccount = 1 having Distance <= $distanceLimit order by $sortBy $orderBy";
        $sqlbasecount= " from profiledata, profilesettings where profiledata.ID = profilesettings.ID and profilesettings.ActiveAccount = 1 and $calc <= $distanceLimit order by $sortBy $orderBy";
    }
    $sqllimit=" limit ?, $maxResultCount";
    
    if ($ID != 0) { //logged in user
        $str="";
        //sex filter
        $stmt=&sqlselect("select SexChoice from profilesettings where ID=?", array("i",$ID));
        $stmt->bind_result($SexChoice);
        $stmt->fetch(); 
        $stmt->free_result();
        
        if ($SexChoice != 2) {
            $sqlbase=str_replace("where","where Sex=$SexChoice and", $sqlbase);
            $sqlbasecount=str_replace("where","where Sex=$SexChoice and", $sqlbasecount);
        } 
        
        $targetMatches=array();
        $likeids=array();
        $likedbyids=array();
        $hideids=array();
        $hidbyids=array();
        $friendids=array(); 
        $friendbyids=array();
        $blockids=array(); 
        
        getRelations($ID, $targetMatches, $likeids, $likedbyids, $hideids, $hidbyids, $friendids, $friendbyids, $blockids);
        
        switch ($_GET["ListType"]) {
            case "public":
                $conditions="where profiledata.ID != ? and";
                foreach ($hideids as $hideid) {
                    $conditions.=" profiledata.ID != $hideid and";
                } 
                foreach ($blockids as $blockid) {
                    $conditions.=" profiledata.ID != $blockid and";
                }                        
                $sqlbase=str_replace("where",$conditions,$sqlbase); 
                $sqlbasecount=str_replace("where",$conditions,$sqlbasecount);
                
                //var_dump($sqlfields.$sqlbase.$sqllimit);
                $stmt=&sqlselectbymany($sqlfields.$sqlbase.$sqllimit, array(array("i",$ID), array("i",$resultsFrom)));
                $res=$stmt->get_result();
                while($row=$res->fetch_assoc()) { //fetch_array would return with double as many elements
                    reverseRelation($row, $likedbyids, $targetMatches, $friendbyids, $latitude, $longitude);
                    
                    $resultID=$row["ID"];
                    $userrelation=0;
                    
                    foreach ($likeids as $target) {
                        if ($target == $resultID) {
                            $userrelation=2;
                        }
                    }
                    foreach ($targetMatches as $target) {
                        if ($target == $resultID) {
                            $userrelation=3;
                        }
                    }
                    foreach ($friendids as $target) {
                        if ($target == $resultID) { 
                            $userrelation=4;
                        }
                    }
                    addrowRelation($row, $str, $userrelation);
                }
                $stmt->free_result();
                $stmt=&sqlselect($sqlcount.$sqlbasecount, array("i",$ID));
                $stmt->bind_result($resultCount);
                $stmt->fetch();
                break;
            case "undecided":
                $conditions="where profiledata.ID != ? and";
                foreach ($likeids as $likeid) {
                    $conditions.=" profiledata.ID != $likeid and";
                }
                foreach ($hideids as $hideid) {
                    $conditions.=" profiledata.ID != $hideid and";
                }
                foreach ($blockids as $blockid) {
                    $conditions.=" profiledata.ID != $blockid and";
                }                         
                $sqlbase=str_replace("where",$conditions,$sqlbase); 
                $sqlbasecount=str_replace("where",$conditions,$sqlbasecount);
                
                $stmt=&sqlselectbymany($sqlfields.$sqlbase.$sqllimit, array(array("i",$ID), array("i",$resultsFrom)));
                $res=$stmt->get_result();
                while($row=$res->fetch_assoc()) { //fetch_array would return with double as many elements
                    reverseRelation($row, $likedbyids, $targetMatches, $friendbyids, $latitude, $longitude);
                    addrowRelation($row, $str, 0);
                }
                $stmt->free_result();
                $stmt=&sqlselect($sqlcount.$sqlbasecount, array("i",$ID));
                $stmt->bind_result($resultCount);
                $stmt->fetch();
                break;
            case "friends":
                if (count($friendids) != 0) {
                    $conditions="where (";
                    foreach ($friendids as $friendid) {
                        $conditions.="profiledata.ID = $friendid or ";
                    }
                    $conditions=substr($conditions, 0, strlen($conditions)-4).") and";
                    $sqlbase=str_replace("where",$conditions,$sqlbase);
                    $sqlbasecount=str_replace("where",$conditions,$sqlbasecount);
                    
                    $stmt=&sqlselect($sqlfields.$sqlbase.$sqllimit, array("i",$resultsFrom));
                    $res=$stmt->get_result();
                    while($row=$res->fetch_assoc()) { //fetch_array would return with double as many elements
                        reverseRelation($row, $likedbyids, $targetMatches, $friendbyids, $latitude, $longitude);
                        addrowRelation($row, $str, 4);
                    }
                    $stmt->free_result();
                    $stmt=&sqlselectall($sqlcount.$sqlbasecount);
                    $stmt->bind_result($resultCount);
                    $stmt->fetch();
                }
                else {
                    $resultCount=0;
                }
                break;
            case "matches":
                if (count($targetMatches) != 0) {
                    $conditions="where (";                    
                    foreach ($targetMatches as $targetMatch) {
                        $conditions.="profiledata.ID = $targetMatch or ";
                    }
                    $conditions=substr($conditions, 0, strlen($conditions)-4).") and";
                    $sqlbase=str_replace("where",$conditions,$sqlbase);
                    $sqlbasecount=str_replace("where",$conditions,$sqlbasecount);
                    
                    $stmt=&sqlselect($sqlfields.$sqlbase.$sqllimit, array("i",$resultsFrom));
                    $res=$stmt->get_result();
                    while($row=$res->fetch_assoc()) { //fetch_array would return with double as many elements
                        reverseRelation($row, $likedbyids, $targetMatches, $friendbyids, $latitude, $longitude);
                        $resultID=$row["ID"];
                        $userrelation=3;
                        foreach ($friendids as $target) {
                            if ($target == $resultID) {
                                $userrelation=4;
                            }
                        }
                        addrowRelation($row, $str, $userrelation);
                    }
                    $stmt->free_result();
                    $stmt=&sqlselectall($sqlcount.$sqlbasecount);
                    $stmt->bind_result($resultCount);
                    $stmt->fetch();
                } 
                else {
                    $resultCount=0;
                }  
                break;
            case "liked":
                if (count($likeids) != 0) {
                    $conditions="where (";
                    foreach ($likeids as $likeid) {
                        $conditions.="profiledata.ID = $likeid or ";
                    }
                    $conditions=substr($conditions, 0, strlen($conditions)-4).") and";                       
                    $sqlbase=str_replace("where",$conditions,$sqlbase);
                    $sqlbasecount=str_replace("where",$conditions,$sqlbasecount);
                    
                    $stmt=&sqlselect($sqlfields.$sqlbase.$sqllimit, array("i",$resultsFrom));
                    $res=$stmt->get_result();
                    while($row=$res->fetch_assoc()) { //fetch_array would return with double as many elements
                        reverseRelation($row, $likedbyids, $targetMatches, $friendbyids, $latitude, $longitude);
                        addrowRelation($row, $str, 2);
                    }
                    $stmt->free_result();
                    $stmt=&sqlselectall($sqlcount.$sqlbasecount);
                    $stmt->bind_result($resultCount);
                    $stmt->fetch();
                }
                else {
                    $resultCount=0;
                }                    
                break;
            case "likedby":
                if (count($likedbyids) != 0) {
                    $conditions="where (";
                    foreach ($likedbyids as $likedbyid) {
                        $conditions.="profiledata.ID = $likedbyid or ";
                    }
                    $conditions=substr($conditions, 0, strlen($conditions)-4).") and"; 
                    
                    if (count($hideids) != 0 || count($blockids) != 0) {
                        $conditions.=" (";
                        foreach ($hideids as $hideid) {
                            $conditions.="profiledata.ID != $hideid and ";
                        }
                        foreach ($blockids as $blockid) {
                            $conditions.="profiledata.ID != $blockid and ";
                        } 
                        $conditions=substr($conditions, 0, strlen($conditions)-4).") and";
                    }
                                          
                    $sqlbase=str_replace("where",$conditions,$sqlbase);
                    $sqlbasecount=str_replace("where",$conditions,$sqlbasecount);
                    
                    //var_dump($sqlfields.$sqlbase.$sqllimit);
                    $stmt=&sqlselect($sqlfields.$sqlbase.$sqllimit, array("i",$resultsFrom));
                    $res=$stmt->get_result();
                    while($row=$res->fetch_assoc()) { //fetch_array would return with double as many elements
                        reverseRelation($row, $likedbyids, $targetMatches, $friendbyids, $latitude, $longitude);
                        
                        $resultID=$row["ID"];
                        $userrelation=0;
                        
                        foreach ($likeids as $target) {
                            if ($target == $resultID) {
                                $userrelation=2;
                            }
                        }
                        foreach ($targetMatches as $target) {
                            if ($target == $resultID) {
                                $userrelation=3;
                            }
                        }
                        foreach ($friendids as $target) {
                            if ($target == $resultID) {
                                $userrelation=4;
                            }
                        }
                        addrowRelation($row, $str, $userrelation);
                    }
                    $stmt->free_result();
                    $stmt=&sqlselectall($sqlcount.$sqlbasecount);
                    $stmt->bind_result($resultCount);
                    $stmt->fetch();
                }  
                else {
                    $resultCount=0;
                }
                break;
            case "hid":
                if (count($hideids) != 0) {
                    $conditions="where (";
                    foreach ($hideids as $hideid) {
                        $conditions.="profiledata.ID = $hideid or ";
                    } 
                    $conditions=substr($conditions, 0, strlen($conditions)-4).") and";
                                          
                    $sqlbase=str_replace("where",$conditions,$sqlbase);
                    $sqlbasecount=str_replace("where",$conditions,$sqlbasecount);
                    
                    $stmt=&sqlselect($sqlfields.$sqlbase.$sqllimit, array("i",$resultsFrom));
                    $res=$stmt->get_result();
                    while($row=$res->fetch_assoc()) { //fetch_array would return with double as many elements
                        reverseRelation($row, $likedbyids, $targetMatches, $friendbyids, $latitude, $longitude);
                        addrowRelation($row, $str, 1);
                    }
                    $stmt->free_result();
                    $stmt=&sqlselectall($sqlcount.$sqlbasecount);
                    $stmt->bind_result($resultCount);
                    $stmt->fetch();
                } 
                else {
                    $resultCount=0;
                }
                break;
            case "hidby":
                if (count($hidbyids) != 0) {
                    $conditions="where (";
                    foreach ($hidbyids as $hidbyid) {
                        $conditions.="profiledata.ID = $hidbyid or ";
                    }
                    $conditions=substr($conditions, 0, strlen($conditions)-4).") and";
                    
                    if (count($hideids) != 0 || count($blockids) != 0) {
                        $conditions.=" (";
                        foreach ($hideids as $hideid) {
                            $conditions.="profiledata.ID != $hideid and ";
                        }
                        foreach ($blockids as $blockid) {
                            $conditions.="profiledata.ID != $blockid and ";
                        } 
                        $conditions=substr($conditions, 0, strlen($conditions)-4).") and";
                    }
                    
                    $sqlbase=str_replace("where",$conditions,$sqlbase);
                    $sqlbasecount=str_replace("where",$conditions,$sqlbasecount);
                    
                    //var_dump($sqlfields.$sqlbase.$sqllimit);
                    $stmt=&sqlselect($sqlfields.$sqlbase.$sqllimit, array("i",$resultsFrom));
                    $res=$stmt->get_result();
                    while($row=$res->fetch_assoc()) { //fetch_array would return with double as many elements
                        reverseRelation($row, $likedbyids, $targetMatches, $friendbyids, $latitude, $longitude);
                        
                        $resultID=$row["ID"];
                        $userrelation=0;
                        
                        foreach ($likeids as $target) {
                            if ($target == $resultID) {
                                $userrelation=2;
                            }
                        }
                        addrowRelation($row, $str, $userrelation);
                    }
                    $stmt->free_result();
                    $stmt=&sqlselectall($sqlcount.$sqlbasecount);
                    $stmt->bind_result($resultCount);
                    $stmt->fetch();
                }
                else {
                    $resultCount=0;
                }
                break;
            default:
                return "Error: Wrong list type";
        }
        $stmt->close();
        updateSearchSettingsFilter($ID);
    }
    else {//ListType public
        switch ($_GET["ListType"]) {
            case "public":
                $SexChoice=2;
                break;
            case "women":
                $SexChoice=0;
                break;
            case "men":
                $SexChoice=1;
                break;
            default:
                return "Error: Wrong list type";
        }
        
        if ($SexChoice != 2) {
            $sqlbase=str_replace("where","where Sex=$SexChoice and", $sqlbase);
            $sqlbasecount=str_replace("where","where Sex=$SexChoice and", $sqlbasecount);
        }
            
        $stmt=&sqlselect($sqlfields.$sqlbase.$sqllimit, array("i",$resultsFrom));
        $str="";
        $res=$stmt->get_result();
        while($row=$res->fetch_assoc()) { //fetch_array would return with double as many elements
            if ($row["UseLocation"]==0) { //we already null-ed the location data on profile update
                $row["Distance"]=null; //added field
            }
            else {
                if ($row["DistanceShare"] < 4) { //not sharing distance with public
                    $row["Distance"]=null; 
                }
                else {
                    if (!isset($row["Distance"])) {
                        if ($latitude != null && $longitude != null && $row["Latitude"] != null && $row["Longitude"] != null) {
                            $row["Distance"]=calculateDistance($latitude,$longitude,$row["Latitude"],$row["Longitude"]);
                        }
                        else {
                            $row["Distance"]=null;
                        }
                    }
                    else {
                        $row["Distance"]=round($row["Distance"],1);
                    }
                }
                
                if ($row["LocationShare"] < 4) { //not sharing location with public
                    $row["Latitude"]=null;
                    $row["Longitude"]=null;
                }
            }
            addrow($row,$str);
        }
        $stmt->free_result();
        $stmt=&sqlselectall($sqlcount.$sqlbasecount);
        $stmt->bind_result($resultCount);
        $stmt->fetch();
        $stmt->close();
    }
    return "OK;$resultCount|$str";
}

function loadListSearch($ID=0) {
    //listtype, sortby, orderby, resultsfrom
    global $maxResultCount;
        
    switch ($_GET["SearchIn"]) {
        case "all":
            $searchFields=array("Username", "Name", "Description");
            break;
        case "username":
            $searchFields=array("Username");
            break;
        case "name":
            $searchFields=array("Name");
            break;
        case "bio":
            $searchFields=array("Description");
            break;
        default: //will work for missing GET parameter too, with a notice for the undefined index first
            return "Error: Wrong search field value";
    }
    
    switch ($_GET["SortBy"]) {
        case "LastActiveDate":
            $sortBy="LastActiveDate";
            break;
        case "ResponseRate":
            $sortBy="ResponseRate";
            break;
        case "RegisterDate":
            $sortBy="RegisterDate";
            break;
        default: //will work for missing GET parameter too, with a notice for the undefined index first
            return "Error: Wrong sort by value";
    }
    
    switch ($_GET["OrderBy"]) {
        case "asc":
            $orderBy="asc";
            break;
        case "desc":
            $orderBy="desc";
            break;
        default:
            return "Error: Wrong order by value";
    }
    
    if (!ctype_digit($_GET["ResultsFrom"]) || $_GET["ResultsFrom"] < 1) {
        return "Error: Wrong start parameter value";
    }
    
    $latitude=$_GET["Latitude"];
    $longitude=$_GET["Longitude"]; 
    $resultsFrom=$_GET["ResultsFrom"]-1;    
    
    $sqlfields="select profiledata.ID, Sex, Username, Name, Pictures, Description, unix_timestamp(RegisterDate) as RegisterDate, unix_timestamp(LastActiveDate) as LastActiveDate, ResponseRate, Latitude, Longitude, unix_timestamp(LocationTime) as LocationTime, UseLocation, LocationShare, DistanceShare";
    $sqlcount="select count(*)";
    
    $searchTerm=$_GET["SearchTerm"];
    $condition="(";
    $params=array();
    
    if (preg_match("/\\s?(\d+)-(\d+)\\s?/",$searchTerm,$matches)) {
        $pattern= "/".preg_quote($matches[0],"/")."/";  
        if ($matches[1]<$matches[2]) {
            $start=$matches[1];
            $end=$matches[2];
        }
        else {
            $start=$matches[2];
            $end=$matches[1];
        }        
        if (($start>=0) && ($end<100)) {
            $searchTerm=preg_replace($pattern,"",$searchTerm,1);
            foreach ($searchFields as $field) {             
                $condition.="(lower($field) like lower(?) and (";        
                for($i=$start;$i<=$end;$i++) {
                    $condition.="$field like '%$i%' or ";
                }
                $condition=substr($condition,0,strlen($condition)-4).")) or ";
                $params[]=array("s","%$searchTerm%");
            }
        }
        else {
            foreach ($searchFields as $field) {
                $condition.="lower($field) like lower(?) or ";
                $params[]=array("s","%$searchTerm%");
            }
        }        
    }
    else {
        foreach ($searchFields as $field) {
            $condition.="lower($field) like lower(?) or ";
            $params[]=array("s","%$searchTerm%");
        }
    }    
    $condition=substr($condition,0,strlen($condition)-4).") and";

    $sqlbase=" from profiledata, profilesettings where $condition profiledata.ID = profilesettings.ID and profilesettings.ActiveAccount = 1 order by $sortBy $orderBy";
    $sqllimit=" limit ?, $maxResultCount";
    
    //var_dump($sqlfields.$sqlbase.$sqllimit);
    if ($ID!=0) {
        $str="";
        
        $stmt=&sqlselect("select SexChoice from profilesettings where ID=?", array("i",$ID));
        $stmt->bind_result($SexChoice);
        $stmt->fetch(); 
        $stmt->free_result(); 
        
        if ($SexChoice != 2) {
            $sqlbase=str_replace("where","where Sex=$SexChoice and", $sqlbase);
        } 
        
        $targetMatches=array();
        $likeids=array();
        $likedbyids=array();
        $hideids=array();
        $hidbyids=array();
        $friendids=array(); 
        $friendbyids=array();
        $blockids=array(); 
        
        getRelations($ID, $targetMatches, $likeids, $likedbyids, $hideids, $hidbyids, $friendids, $friendbyids, $blockids);
        
        $conditions="where profiledata.ID != ? and";
        foreach ($hideids as $hideid) {
            $conditions.=" profiledata.ID != $hideid and";
        }
        foreach ($blockids as $blockid) {
            $conditions.=" profiledata.ID != $blockid and";
        }                          
        $sqlbase=str_replace("where",$conditions,$sqlbase);
         
        array_unshift($params, array("i",$ID));
        $paramsCount=$params;
        array_push($params, array("i",$resultsFrom));
        //$time=microtime(true);
        $stmt=&sqlselectbymany($sqlfields.$sqlbase.$sqllimit, $params);
        //$time2=microtime(true);
        //print $time2-$time."<br />";
        $res=$stmt->get_result();
        while($row=$res->fetch_assoc()) {
            reverseRelation($row, $likedbyids, $targetMatches, $friendbyids, $latitude, $longitude);
            
            $resultID=$row["ID"];
            $userrelation=0;
            
            foreach ($likeids as $target) {
                if ($target == $resultID) {
                    $userrelation=2;
                }
            }
            foreach ($targetMatches as $target) {
                if ($target == $resultID) {
                    $userrelation=3;
                }
            }
            foreach ($friendids as $target) {
                if ($target == $resultID) { 
                    $userrelation=4;
                }
            }
            addrowRelation($row, $str, $userrelation);
        }
        $stmt->free_result(); 
        //$time=microtime(true);
        $stmt=&sqlselectbymany($sqlcount.$sqlbase, $paramsCount);
        //$time2=microtime(true);
        //print $time2-$time."<br />";
        $stmt->bind_result($resultCount);
        $stmt->fetch();
        $stmt->close();
        updateSearchSettingsText($ID);       
    }
    else {
        switch ($_GET["ListType"]) {
            case "public":
                $SexChoice=2;
                break;
            case "women":
                $SexChoice=0;
                break;
            case "men":
                $SexChoice=1;
                break;
            default:
                return "Error: Wrong list type";
        }
        
        if ($SexChoice != 2) {
            $sqlbase=str_replace("where","where Sex=$SexChoice and", $sqlbase);
        }
        
        $paramsCount=$params;
        array_push($params, array("i",$resultsFrom));

        $stmt=&sqlselectbymany($sqlfields.$sqlbase.$sqllimit, $params);
        $str="";
        $res=$stmt->get_result();
        while($row=$res->fetch_assoc()) {
            if ($row["UseLocation"]==0) { //we already null-ed the location data on profile update
                $row["Distance"]=null; //added field
            }
            else {
                if ($row["DistanceShare"] < 4) { //not sharing distance with public
                    $row["Distance"]=null; 
                }
                else {
                    if ($latitude != null && $longitude != null && $row["Latitude"] != null && $row["Longitude"] != null) {
                        $row["Distance"]=calculateDistance($latitude,$longitude,$row["Latitude"],$row["Longitude"]);
                    }
                    else {
                        $row["Distance"]=null;
                    }
                }
                
                if ($row["LocationShare"] < 4) { //not sharing location with public
                    $row["Latitude"]=null;
                    $row["Longitude"]=null;
                }
            }
            addrow($row,$str);        
        }
        $stmt->free_result();
        $stmt=&sqlselectbymany($sqlcount.$sqlbase, $paramsCount);
        $stmt->bind_result($resultCount);
        $stmt->fetch();
        $stmt->close();
    }
    return "OK;$resultCount|$str";
}

function getRelations($ID, &$targetMatches, &$likeids, &$likedbyids, &$hideids, &$hidbyids, &$friendids, &$friendbyids, &$blockids) {
    $stmt=&sqlselect("select Likes, LikedBy, Hides, HidBy, Friends, FriendsBy, Blocks from likehide where ID=?", array("i",$ID));
    $stmt->bind_result($Likes, $LikedBy, $Hides, $HidBy, $Friends, $FriendsBy, $Blocks);
    $stmt->fetch(); 
    $stmt->free_result();
     
    $stmt=&sqlselectbymany("select FirstID, SecondID from matches where Active=1 and (FirstID=? or SecondID=?)",
        array(array("i",$ID), array("i",$ID)));
    $stmt->bind_result($FirstID, $SecondID);
    $targetMatches=array();
    while ($stmt->fetch()) {
        if ($ID==$FirstID) {
            $targetMatches[]=$SecondID;
        }
        else {
            $targetMatches[]=$FirstID;
        }
    } 
    $stmt->free_result();
    
    if ($Likes != "") {
        $likearr=explode("|",$Likes);         
        foreach ($likearr as $like) {
            $arr=explode(":",$like);
            $likeids[]=$arr[0];
        }
    }
    
    if ($LikedBy != "") {
        $likedbyarr=explode("|",$LikedBy);         
        foreach ($likedbyarr as $likedby) {
            $arr=explode(":",$likedby);
            $likedbyids[]=$arr[0];
        }
    }
    
    if ($Hides != "") {
        $hidearr=explode("|",$Hides);         
        foreach ($hidearr as $hide) {
            $arr=explode(":",$hide);
            $hideids[]=$arr[0];
        }
    }
     
    if ($HidBy != "") {
        $hidbyarr=explode("|",$HidBy);         
        foreach ($hidbyarr as $hidby) {
            $arr=explode(":",$hidby);
            $hidbyids[]=$arr[0];
        }
    }
    
    if ($Friends != "") {
        $friendarr=explode("|",$Friends);         
        foreach ($friendarr as $friend) {
            $arr=explode(":",$friend);
            $friendids[]=$arr[0];
        }
    }
    
    if ($FriendsBy != "") {
        $friendbyarr=explode("|",$FriendsBy);         
        foreach ($friendbyarr as $friendby) {
            $arr=explode(":",$friendby);
            $friendbyids[]=$arr[0];
        }
    }

    if ($Blocks != "") {
        $blockarr=explode("|",$Blocks);         
        foreach ($blockarr as $block) {
            $arr=explode(":",$block);
            $blockids[]=$arr[0];
        }
    }
}

function updateSearchSettingsFilter($ID) {
    sqlupdate("profilesettings", array(
        "ListType"=>array("s",$_GET["ListType"]),
        "SortBy"=>array("s",$_GET["SortBy"]),
        "OrderBy"=>array("s",$_GET["OrderBy"]),
        "GeoFilter"=>array("i",(int)($_GET["GeoFilter"] == "True")),
        "GeoSourceOther"=>array("i",(int)($_GET["GeoSourceOther"] == "True")),
        "OtherLatitude"=>array("d",$_GET["OtherLatitude"]),
        "OtherLongitude"=>array("d",$_GET["OtherLongitude"]),
        "OtherAddress"=>array("s",$_GET["OtherAddress"]),
        "DistanceLimit"=>array("i",$_GET["DistanceLimit"]),
        "ResultsFrom"=>array("i",$_GET["ResultsFrom"]),
    ), array("ID"=>array("i",$ID)));
}

function updateSearchSettingsText($ID) {
    sqlupdate("profilesettings", array(
        "SearchTerm"=>array("s",$_GET["SearchTerm"]),
        "SearchIn"=>array("s",$_GET["SearchIn"]),
        "ResultsFrom"=>array("i",$_GET["ResultsFrom"]),
    ), array("ID"=>array("i",$ID)));
}

function reverseRelation (&$row, $likedbyids, $targetMatches, $friendbyids, $latitude, $longitude) {
    $resultID=$row["ID"];
    if ($row["UseLocation"]!=0) { //we already null-ed the location data on profile update
        switch($row["DistanceShare"]) { //locationtime is used for distance even if the user is not sharing location
            case 4:
                if (!isset($row["Distance"])) {
                    if ($latitude != null && $longitude != null && $row["Latitude"] != null && $row["Longitude"] != null) {
                        $row["Distance"]=calculateDistance($latitude,$longitude,$row["Latitude"],$row["Longitude"]);
                    }
                }
                else {
                    $row["Distance"]=round($row["Distance"],1);
                }
                break;
            case 3:
                if (in_array($resultID,$likedbyids)) {
                    if (!isset($row["Distance"])) {
                        if ($latitude != null && $longitude != null && $row["Latitude"] != null && $row["Longitude"] != null) {
                            $row["Distance"]=calculateDistance($latitude,$longitude,$row["Latitude"],$row["Longitude"]);
                        }
                    }
                    else {
                        $row["Distance"]=round($row["Distance"],1);
                    }
                }
                else {
                    $row["Distance"]=null;
                }
                break;
            case 2:
                if (in_array($resultID,$targetMatches)) {
                    if (!isset($row["Distance"])) {
                        if ($latitude != null && $longitude != null && $row["Latitude"] != null && $row["Longitude"] != null) {
                            $row["Distance"]=calculateDistance($latitude,$longitude,$row["Latitude"],$row["Longitude"]);
                        }
                    }
                    else {
                        $row["Distance"]=round($row["Distance"],1);
                    }
                }
                else {
                    $row["Distance"]=null;
                }
                break;
            case 1:
                if (in_array($resultID,$friendbyids)) {
                    if (!isset($row["Distance"])) {
                        if ($latitude != null && $longitude != null && $row["Latitude"] != null && $row["Longitude"] != null) {
                            $row["Distance"]=calculateDistance($latitude,$longitude,$row["Latitude"],$row["Longitude"]);
                        }
                    }
                    else {
                        $row["Distance"]=round($row["Distance"],1);
                    }
                }
                else {
                    $row["Distance"]=null;
                }
                break;
            case 0:
                $row["Distance"]=null;
                break;
        } 
        
        switch($row["LocationShare"]) {
            case 4:
                break;
            case 3:
                if (!in_array($resultID,$likedbyids)) {
                    $row["Latitude"]=null;
                    $row["Longitude"]=null;
                }
                break;
            case 2:
                if (!in_array($resultID,$targetMatches)) {
                    $row["Latitude"]=null;
                    $row["Longitude"]=null;
                }
                break;
            case 1:
                if (!in_array($resultID,$friendbyids)) {
                    $row["Latitude"]=null;
                    $row["Longitude"]=null;
                }
                break;
            case 0:
                $row["Latitude"]=null;
                $row["Longitude"]=null;
                break;
        }
    }
}

function reverseRelationStandalone(&$row, $friendbyids, $latitude, $longitude) {
    $resultID=$row["ID"];
    if ($row["UseLocation"]!=0) { //we already null-ed the location data on profile update
        if ($row["DistanceShare"] > 1) { //shares location with matches
            if ($latitude != null && $longitude != null && $row["Latitude"] != null && $row["Longitude"] != null) {
                $row["Distance"]=calculateDistance($latitude,$longitude,$row["Latitude"],$row["Longitude"]);
            }
        }
        else if ($row["DistanceShare"] == 1) { //only with friends
            if (in_array($resultID,$friendbyids)) {
                if ($latitude != null && $longitude != null && $row["Latitude"] != null && $row["Longitude"] != null) {
                    $row["Distance"]=calculateDistance($latitude,$longitude,$row["Latitude"],$row["Longitude"]);
                }
            }
        }
        else {
            $row["Distance"]=null;
        }
        
        if ($row["LocationShare"] == 1) {
            if (!in_array($resultID,$friendbyids)) {
                $row["Latitude"]=null;
                $row["Longitude"]=null;
            }
        }
        else if ($row["LocationShare"] == 0) {
            $row["Latitude"]=null;
            $row["Longitude"]=null;
        }
    }
}

function calculateDistance($lat1, $long1, $lat2, $long2) {
    return round(6371 * acos(
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($long2) - deg2rad($long1))
            + sin(deg2rad($lat1)) * sin(deg2rad($lat2))
            ),1);
	/*$r = 6371000;
	$lat1rad = (pi() / 180) * $lat1;
	$lat2rad = (pi() / 180) * $lat2;
	$dLat = (pi() / 180) * ($lat2 - $lat1);
	$dLong = (pi() / 180) * ($long2 - $long1);
	$a = sin($dLat / 2) * sin($dLat / 2) + cos($lat1rad) * cos($lat2rad) * sin($dLong / 2) * sin($dLong / 2);
	$c = 2 * atan2(sqrt($a), sqrt(1 - $a));
	return round($r * $c / 1000, 1); */
}

function addrowRelation($row, &$str, $userrelation) {
    unset($row["UseLocation"], $row["LocationShare"], $row["DistanceShare"]);
    $str.="{";
    foreach ($row as $key => $value) {
        $str.="$key:";
        if ($key=="Username" || $key=="Name" || $key=="Pictures" || $key=="Description") {
            $str.="\"$value\",";
        }
        else {
            $str.="$value,";
        }
    }
    $str.="UserRelation:$userrelation}";     
}

function addrow($row, &$str) {
    unset($row["UseLocation"], $row["LocationShare"], $row["DistanceShare"]);
    $str.="{";
    foreach ($row as $key => $value) {
        $str.="$key:";
        if ($key=="Username" || $key=="Name" || $key=="Pictures" || $key=="Description") {
            $str.="\"$value\",";
        }
        else {
            $str.="$value,";
        }
    }
    $str=substr($str,0,strlen($str)-1);
    $str.="}";
}

function getuserdata($ID, $target) {
    $stmt=&sqlselect("select profiledata.ID, Sex, Username, Name, Pictures, Description, unix_timestamp(RegisterDate) as RegisterDate, unix_timestamp(LastActiveDate) as LastActiveDate, ResponseRate, Latitude, Longitude, unix_timestamp(LocationTime) as LocationTime, UseLocation, LocationShare, DistanceShare from profiledata, profilesettings where profiledata.ID = ? and profiledata.ID = profilesettings.ID and profilesettings.ActiveAccount = 1", array("i",$target)); //conditioning Active is for cases when the user profile is opened from chat, but has been deactivated meanwhile.
    $res=$stmt->get_result();
    $row=$res->fetch_assoc();
    $stmt->free_result();
    
    if ($row != null) {
        $userrelation=2;
         
        //we need to check if it is an active match    
        $stmt=&sqlselectbymany("select FirstID, SecondID from matches where Active=1 and (FirstID=? or SecondID=?)",
            array(array("i",$ID), array("i",$ID)));
        $stmt->bind_result($FirstID, $SecondID);
        $targetMatches=array();
        while ($stmt->fetch()) {
            if ($ID==$FirstID) {
                $targetMatches[]=$SecondID;
            }
            else {
                $targetMatches[]=$FirstID;
            }
        }                        
        $stmt->free_result();
        
        foreach ($targetMatches as $targetMatch) {
            if ($targetMatch == $target) {
                $userrelation=3;
            }
        }
        
        $stmt=&sqlselect("select FriendsBy from likehide where ID=?", array("i",$ID));
        $stmt->bind_result($FriendsBy);
        $stmt->fetch(); 
        $stmt->close();
        
        $friendbyids=array();
        if ($FriendsBy != "") {
            $friendbyarr=explode("|",$FriendsBy);         
            foreach ($friendbyarr as $friendby) {
                $arr=explode(":",$friendby);
                $friendbyids[]=$arr[0];
            }
        }
        
        reverseRelationStandalone($row, $friendbyids, $_GET["Latitude"], $_GET["Longitude"]);
        $str="";
        addrowRelation($row, $str, $userrelation);
        return "OK;$str";
    }
    $stmt->close();
    return "ERROR_UserPassive";
}

function updateLocation($ID, $Latitude, $Longitude, $time) {
    $params=array(
                "Latitude" => array("d", $Latitude),
                "Longitude" => array("d", $Longitude),
                "LocationTime" => array("s",date("Y-m-d H:i:s",$time))
            );
    sqlupdate("profiledata", $params, array("ID" => array("i", $ID)));
    
    if (isset($_GET["LocationUpdates"])) {
    
        $stmt=&sqlselect("select Name from profiledata where ID=?", array("i",$ID));
        $stmt->bind_result($TargetName);
        $stmt->fetch();
        $stmt->free_result();
    
        $arr=explode("|",$_GET["LocationUpdates"]);     
        foreach ($arr as $targetID) {
            //verify this is a match
            $stmt=&sqlselectbymany("select ID from matches where (FirstID = $ID and SecondID = ?) or (FirstID = ? and SecondID = $ID)",
            array( array("i",$targetID), array("i",$targetID) ));
            $stmt->store_result();
            if ($stmt->num_rows == 1) {
                $stmt->free_result();
                $stmt=&sqlselect("select Token, iOS from session where ID = ?", array("i",$targetID));
                $stmt->bind_result($token, $ios);
                $stmt->fetch();
                $stmt->free_result();
                
                sendCloud($ID, $targetID, $token, $ios, false, true, null, null, "locationUpdate", "$TargetName|".$_GET["Frequency"]."|$time|$Latitude|$Longitude");
            }
        }
    }
    return "OK";
}

function updateLocationMatch($ID, $Latitude, $Longitude, $time) { //no location update, sending it to match(es) only    
    $stmt=&sqlselect("select Name from profiledata where ID=?", array("i",$ID));
    $stmt->bind_result($TargetName);
    $stmt->fetch();
    $stmt->free_result();
    
    $arr=explode("|",$_GET["LocationUpdates"]);     
    foreach ($arr as $targetID) {
        //verify this is a match
        $stmt=&sqlselectbymany("select ID from matches where (FirstID = $ID and SecondID = ?) or (FirstID = ? and SecondID = $ID)",
        array( array("i",$targetID), array("i",$targetID) ));
        $stmt->store_result();
        if ($stmt->num_rows == 1) {
            $stmt->free_result();
            $stmt=&sqlselect("select Token, iOS from session where ID = ?", array("i",$targetID));
            $stmt->bind_result($token, $ios);
            $stmt->fetch();
            $stmt->free_result();
            
            sendCloud($ID, $targetID, $token, $ios, false, true, null, null, "locationUpdate", "$TargetName|".$_GET["Frequency"]."|$time|$Latitude|$Longitude");
        }
    }
    return "OK";
}

function updateLocationEnd($ID) {
    $stmt=&sqlselect("select Name from profiledata where ID=?", array("i",$ID));
    $stmt->bind_result($TargetName);
    $stmt->fetch();
    $stmt->free_result();

    $arr=explode("|",$_GET["LocationUpdates"]);     
    foreach ($arr as $targetID) {
        //verify this is a match
        $stmt=&sqlselectbymany("select ID from matches where (FirstID = $ID and SecondID = ?) or (FirstID = ? and SecondID = $ID)",
        array( array("i",$targetID), array("i",$targetID) ));
        $stmt->store_result();
        if ($stmt->num_rows == 1) {
            $stmt->free_result();
            $stmt=&sqlselect("select Token, iOS from session where ID = ?", array("i",$targetID));
            $stmt->bind_result($token, $ios);
            $stmt->fetch();
            $stmt->free_result();
            
            sendCloud($ID, $targetID, $token, $ios, false, true, null, null, "locationUpdateEnd", "$TargetName");
        }
    }
    return "OK";
}

function likeProfile($ID, $target, $time) {
    global $mysqli;
    $mysqli->query("lock tables likehide, matches write profilesettings read");
    
    $stmt=&sqlselect("select Likes, LikedBy from likehide where ID=?", array("i",$ID));
    $stmt->bind_result($Likes, $LikedBy);
    $stmt->fetch(); 
    $stmt->close();
    
    //Check if a like of that user already exists, though the app does not make it possible to send repeated like requests.
    if (!existsLikeHideItem($Likes,$target)) {
        $MatchID="";
        //Check for match
        if (existsLikeHideItem($LikedBy,$target)) {
            //Passive accounts can match too.
            $stmt=sqlselect("select ActiveAccount from profilesettings where ID=?", array("i",$ID));
            $stmt->bind_result($ActiveAccount);
            $stmt->fetch();
            $stmt->free_result();
            
            //check for existing match (this can be a re-match)
            $stmt=sqlselectbymany("select ID from matches where (FirstID=? and SecondID=?) or (FirstID=? and SecondID=?)",
            array(array("i",$target), array("i",$ID), array("i",$ID), array("i",$target)));
            $stmt->store_result();
            $count=$stmt->num_rows;
            if ($count == 0) {//new match
                $stmt->free_result();
                $MatchDate=date("Y-m-d H:i:s",$time);
                $MatchID=sqlinsert("matches", array(
                    "Active" => array("i",$ActiveAccount),
                    "MatchDate" => array("s",$MatchDate),
                    "FirstID" => array("i",$target),
                    "SecondID" => array("i",$ID),
                    "Chat" => array("s",null)
                ), true);
                              
                $stmt=&sqlselectbymany("select Token, iOS, MatchInApp, MatchBackground from session, profilesettings where session.ID = ? and profilesettings.ID = ?", array(array("i",$target), array("i",$target)));
                $stmt->bind_result($token, $ios, $MatchInApp, $MatchBackground);
                $stmt->fetch();
                $stmt->free_result();
                
                $stmt=&sqlselect("select Name, Username, Pictures from profiledata where ID=?", array("i",$ID));
                $stmt->bind_result($TargetName, $TargetUsername, $TargetPictures);
                $stmt->fetch();
                $stmt->free_result();
                
                $TargetPicture=explode("|",$TargetPictures)[0];
                $Active=($ActiveAccount==1)?"True":"False";
                $ActiveAccountStr=($ActiveAccount==1)?"True":"False";
                
                sendCloud($ID, $target, $token, $ios, $MatchBackground, $MatchInApp, YOUMATCHEDWITH." $TargetName.", null, "matchProfile", "{MatchID:$MatchID,Active:$Active,MatchDate:$time,UnmatchDate:,Chat:,TargetID:$ID,TargetUsername:\\\"$TargetUsername\\\",TargetName:\\\"$TargetName\\\",TargetPicture:\\\"$TargetPicture\\\",ActiveAccount:$ActiveAccountStr}");
            }
            else {
                $stmt->bind_result($MatchID);
                $stmt->fetch();
                $stmt->free_result();
                sqlupdate("matches", array(
                    "Active" => array("i",$ActiveAccount),
                    "UnmatchDate" => array("s",null),
                    "UnmatchInitiator" => array("i",null)
                ), array("ID" => array("i",$MatchID)));
                
                $stmt=&sqlselectbymany("select Token, iOS, RematchInApp, RematchBackground from session, profilesettings where session.ID = ? and profilesettings.ID = ?", array(array("i",$target), array("i",$target)));
                $stmt->bind_result($token, $ios, $RematchInApp, $RematchBackground);
                $stmt->fetch();
                $stmt->free_result();
                
                $stmt=&sqlselect("select Name from profiledata where ID=?", array("i",$ID));
                $stmt->bind_result($TargetName);
                $stmt->fetch();
                $stmt->free_result();
                
                $Active=($ActiveAccount==1)?"True":"False";
                
                sendCloud($ID, $target, $token, $ios, $RematchBackground, $RematchInApp, YOUREMATCHEDWITH." $TargetName.", null, "rematchProfile", "$MatchID|$Active");
            }
        }
        
        //Add to Likes and LikedBy
        addLikeHideItemUpdate("Likes",$Likes,$target,$time,$ID);
        
        $stmt=&sqlselect("select LikedBy from likehide where ID=?", array("i",$target));
        $stmt->bind_result($LikedBy);
        $stmt->fetch(); 
        $stmt->close();
        
        addLikeHideItemUpdate("LikedBy",$LikedBy,$ID,$time,$target);
        
        $mysqli->query("unlock tables");
        return "OK;$MatchID";
    }
    $mysqli->query("unlock tables");
    return "Error: Like already exists."; 
}

function addFriend($ID, $target, $time) {
    global $mysqli;
    $mysqli->query("lock tables likehide write");
    
    $stmt=&sqlselect("select Friends from likehide where ID=?", array("i",$ID));
    $stmt->bind_result($Friends);
    $stmt->fetch();
    $stmt->close();
    
    //Check if a friend already exists, though the app does not make it possible to send repeated friend requests with a normal request timeframe.
    if (!existsLikeHideItem($Friends,$target)) {
        addLikeHideItemUpdate("Friends",$Friends,$target,$time,$ID);
        
        $stmt=&sqlselect("select FriendsBy from likehide where ID=?", array("i",$target));
        $stmt->bind_result($FriendsBy);
        $stmt->fetch(); 
        $stmt->close();
        
        addLikeHideItemUpdate("FriendsBy",$FriendsBy,$ID,$time,$target);
        $mysqli->query("unlock tables");
        return "OK";
    }
    $mysqli->query("unlock tables");
    return "Error: Friend already exists";
}

function removeFriend($ID, $target, $time) { //$time is not currently used
    global $mysqli;
    $mysqli->query("lock tables likehide write");
    
    $stmt=&sqlselect("select Friends from likehide where ID=?", array("i",$ID));
    $stmt->bind_result($Friends);
    $stmt->fetch();
    $stmt->close();
    
    if (removeLikeHideItem($Friends,$target)) {
        sqlupdate("likehide", array("Friends" => array("s",$Friends)), array("ID" => array("i",$ID)));
        
        $stmt=&sqlselect("select FriendsBy from likehide where ID=?", array("i",$target));
        $stmt->bind_result($FriendsBy);
        $stmt->fetch(); 
        $stmt->close();
        
        removeLikeHideItem($FriendsBy,$ID);
        sqlupdate("likehide", array("FriendsBy" => array("s",$FriendsBy)), array("ID" => array("i",$target)));        
    }
    $mysqli->query("unlock tables");
    return "OK";
}

function unmatchProfile($ID, $target, $time) {
    global $mysqli;
    
    $stmt=&sqlselectbymany("select ID, Active, UnmatchInitiator from matches where (FirstID=? and SecondID=?) or (FirstID=? and SecondID=?)", array(array("i",$target), array("i",$ID), array("i",$ID), array("i",$target)));
    $stmt->bind_result($MatchID, $Active, $UnmatchInitiator);
    $stmt->fetch(); 
    $stmt->close();
    
    if ($UnmatchInitiator == $target) { //match can be passive without anyone initiating an unmatch if one person sets their profile inactive
        sqldelete("matches", array("ID" => array("i",$MatchID)));
    }
    else {
        $unmatchDate=date("Y-m-d H:i:s",$time);
        sqlexecuteparams("update matches set Active=0, UnmatchDate=?, UnmatchInitiator=? where (FirstID=? and SecondID=?) or (FirstID=? and SecondID=?)",
    array(array("s", $unmatchDate), array("i",$ID), array("i",$target), array("i",$ID), array("i",$ID), array("i",$target)));
        
        $stmt=&sqlselectbymany("select Token, iOS, UnmatchInApp, UnmatchBackground from session, profilesettings where session.ID = ? and profilesettings.ID = ?", array(array("i",$target), array("i",$target)));
        $stmt->bind_result($token, $ios, $UnmatchInApp, $UnmatchBackground);
        $stmt->fetch();
        $stmt->free_result();
        
        $stmt=&sqlselect("select Name from profiledata where ID=?", array("i",$ID));
        $stmt->bind_result($TargetName);
        $stmt->fetch();
        $stmt->free_result();
        
        sendCloud($ID, $target, $token, $ios, $UnmatchBackground, $UnmatchInApp, "$TargetName ".YOUUNMATCHEDFROM, null, "unmatchProfile", "$MatchID|$time");
    }
    
    $mysqli->query("lock tables likehide write");
    
    //remove the initiator's like from their likes and the target's likedby. Remove friend, friendby from the source and target ID
    $stmt=&sqlselect("select Likes, Friends, FriendsBy from likehide where ID=?", array("i",$ID));
    $stmt->bind_result($Likes, $Friends, $FriendsBy);
    $stmt->fetch(); 
    $stmt->free_result();
    
    removeLikeHideItem($Likes,$target);
    removeLikeHideItem($Friends,$target);
    removeLikeHideItem($FriendsBy,$target);
    
    sqlupdate("likehide", array("Likes" => array("s",$Likes), "Friends"=>array("s",$Friends), "FriendsBy"=>array("s",$FriendsBy)), array("ID" => array("i",$ID)));
    
    $stmt=&sqlselect("select LikedBy, Friends, FriendsBy from likehide where ID=?", array("i",$target));
    $stmt->bind_result($LikedBy, $Friends, $FriendsBy);
    $stmt->fetch(); 
    $stmt->free_result();
    
    removeLikeHideItem($LikedBy,$ID);
    removeLikeHideItem($Friends,$ID);
    removeLikeHideItem($FriendsBy,$ID);
    
    sqlupdate("likehide", array("LikedBy" => array("s",$LikedBy), "Friends"=>array("s",$Friends), "FriendsBy"=>array("s",$FriendsBy)), array("ID" => array("i",$target)));
        
    $mysqli->query("unlock tables");
    return "OK";
}

function hideProfile($ID, $target, $time) {
    global $mysqli;
    
    $stmt=&sqlselectbymany("select ID from matches where ((FirstID=? and SecondID=?) or (FirstID=? and SecondID=?)) and UnmatchInitiator!=?", array(array("i",$ID), array("i",$target), array("i",$target), array("i",$ID), array("i",$ID)));
    $stmt->store_result();
    $count=$stmt->num_rows;
    if ($count != 0) {
        $stmt->close();
        return "ERROR_IsAMatch";
    }
    $stmt->free_result();
    
    $mysqli->query("lock tables likehide write");
    
    $stmt=&sqlselect("select Likes, Hides from likehide where ID=?", array("i",$ID));
    $stmt->bind_result($Likes, $Hides);
    $stmt->fetch(); 
    $stmt->close();
    
    $targetlikeexists = removeLikeHideItem($Likes,$target); 
     
    //Remove like from Likes and LikedBy 
    if ($targetlikeexists) {
        sqlupdate("likehide", array("Likes" => array("s",$Likes)), array("ID" => array("i",$ID)));
        
        $stmt=&sqlselect("select LikedBy from likehide where ID=?", array("i",$target));
        $stmt->bind_result($LikedBy);
        $stmt->fetch(); 
        $stmt->close();
        
        $targetlikeexists = removeLikeHideItem($LikedBy,$ID);
        sqlupdate("likehide", array("LikedBy" => array("s",$LikedBy)), array("ID" => array("i",$target)));
    }    
        
    //Check if a Hide exists
    if (!$targetlikeexists) {//if there was a like to start with, a hide cannot exist yet. Otherwise we check.
        $targethideexists=existsLikeHideItem($Hides, $target);
    }
    else {
        $targethideexists=false;
    }  
         
    //Add entry to Hides and HidBy   
    if (!$targethideexists) {
        addLikeHideItemUpdate("Hides",$Hides,$target,$time,$ID);
        
        $stmt=&sqlselect("select HidBy from likehide where ID=?", array("i",$target));
        $stmt->bind_result($HidBy);
        $stmt->fetch(); 
        $stmt->close();
        
        addLikeHideItemUpdate("HidBy",$HidBy,$ID,$time,$target);
        
        $mysqli->query("unlock tables");
        return "OK";
    } 
    $mysqli->query("unlock tables");
    return "Error: Hide already exists.";
}

function unhideProfile($ID, $target) {//A hide must exist to start with
    global $mysqli;
    $mysqli->query("lock tables likehide write");
    
    $stmt=sqlselect("select Hides from likehide where ID=?", array("i",$ID));
    $stmt->bind_result($Hides);
    $stmt->fetch(); 
    $stmt->close();
    
    removeLikeHideItem($Hides,$target);
    sqlupdate("likehide", array("Hides" => array("s",$Hides)), array("ID" => array("i",$ID)));
    
    $stmt=&sqlselect("select HidBy from likehide where ID=?", array("i",$target));
    $stmt->bind_result($HidBy);
    $stmt->fetch(); 
    $stmt->close();
    
    removeLikeHideItem($HidBy,$ID); 
    sqlupdate("likehide", array("HidBy" => array("s",$HidBy)), array("ID" => array("i",$target)));
    
    $mysqli->query("unlock tables");
    return "OK";
}

function existsLikeHideItem($field, $ID) {
    $found=false;
    if ($field != "") {
        $found=false;
        $fieldarr=explode("|",$field);
        foreach ($fieldarr as $key => $entry) {
            $arr=explode(":",$entry);
            if ($arr[0]==$ID) { 
                $found=true;           
                break;
            }
        }
    }
    return $found;
}

function removeLikeHideItem(&$field, $ID) {
    $found=false;
    if ($field != "") {
        $found=false;
        $fieldarr=explode("|",$field);
        foreach ($fieldarr as $key => $entry) {
            $arr=explode(":",$entry);
            if ($arr[0]==$ID) { 
                $found=true;           
                break;
            }
        }
        if ($found) {
            unset($fieldarr[$key]);
            $field=implode($fieldarr,"|"); 
        }
    }
    return $found;
}

function addLikeHideItemUpdate($fieldName, $field, $entryID, $time, $rowID) {
    if ($field != "") $field.="|";
    $field.="$entryID:$time";
    sqlupdate("likehide", array($fieldName => array("s",$field)), array("ID" => array ("i",$rowID)));
}

function deactivateAccount($ID) {//does not mean unmatching from existing matches
    sqlupdate("profilesettings", array("ActiveAccount" => array("i",0)), array("ID" => array("i",$ID)));
    sqlexecuteparams("update matches set Active=0 where (FirstID=? or SecondID=?)", array(array("i",$ID), array("i",$ID)));
    
    if (isset($_GET["LocationUpdates"])) {
        updateLocationEnd($ID);
    }
    return "OK";
}

function activateAccount($ID) {
    sqlupdate("profilesettings", array("ActiveAccount" => array("i",1)), array("ID" => array("i",$ID)));
    sqlexecuteparams("update matches set Active=1 where (FirstID=? or SecondID=?) and UnmatchInitiator is null", array(array("i",$ID), array("i",$ID)));
    return "OK";
}

function deleteAccount($ID) {
    global $uploadFolder, $mysqli;
    
    if (isset($_GET["LocationUpdates"])) {
        updateLocationEnd($ID);
    }
    
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
    $fields=array("Likes","Hides","LikedBy","HidBy","Friends","FriendsBy");
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
    
    return "OK";
}

function deleteRegAccount($ID) {
    deleteAccount($ID);
    $content=file_get_contents("base_message.html");
    return str_replace("[message]","The account has been deleted.",$content); 
}

function requestmatchid($ID, $target) {
    $stmt=&sqlselectbymany("select ID from matches where (FirstID=? and SecondID=?) or (FirstID=? and SecondID=?)",
    array(array("i",$target), array("i",$ID), array("i",$ID), array("i",$target)));
    $stmt->bind_result($MatchID);
    $stmt->fetch();
    $stmt->close();
    
    return "OK;$MatchID";
}

function getmessageparts($entry) {
    $sep1Pos = strpos($entry,'|',0);
	$sep2Pos = strpos($entry,'|',$sep1Pos + 1);
	$sep3Pos = strpos($entry,'|',$sep2Pos + 1);
    $sep4Pos = strpos($entry,'|',$sep3Pos + 1);
    $sep5Pos = strpos($entry,'|',$sep4Pos + 1);
    $messageID=substr($entry, 0, $sep1Pos);
    $senderID=substr($entry, $sep1Pos+1, $sep2Pos-$sep1Pos-1);
    $sentTime=substr($entry, $sep2Pos+1, $sep3Pos-$sep2Pos-1);
    $seenTime=substr($entry, $sep3Pos+1, $sep4Pos-$sep3Pos-1);
    $readTime=substr($entry, $sep4Pos+1, $sep5Pos-$sep4Pos-1);
    $message=substr($entry, $sep5Pos+1);
    return array($messageID, $senderID, $sentTime, $seenTime, $readTime, $message);
}

function updatemessage(&$entry, $ID, $read, &$updated) {
    $time=time(); 
    list($messageID, $senderID, $sentTime, $seenTime, $readTime, $message)=getmessageparts($entry);

    if ($senderID != $ID) {
        if (!$read && $seenTime=="0") { //update seen time
            $seenTime=$time;              
            $updated=true;
            $entry="$messageID|$senderID|$sentTime|$seenTime|$readTime|$message";
        }
        if ($read && $readTime=="0") { //update seen time (if not yet updated) and read time
            if ($seenTime=="0") {
                $seenTime=$time;
            }
            $readTime=$time;
            $updated=true;
            $entry="$messageID|$senderID|$sentTime|$seenTime|$readTime|$message";    
        }
    }
}

function loadmessagelist($ID) {
    $stmt=&sqlselectbymany("select matches.ID, Active, unix_timestamp(MatchDate), unix_timestamp(UnmatchDate), UnmatchInitiator, Chat, profiledata.ID, Username, Name, Pictures, ActiveAccount from matches, profiledata, profilesettings where profilesettings.ID = profiledata.ID and profiledata.ID = case when matches.FirstID = ? then matches.SecondID when matches.SecondID = ? then matches.FirstID end order by greatest (MatchDate, ifnull(FirstLatestMessage,0), ifnull(SecondLatestMessage,0)) desc", array(array("i",$ID), array("i",$ID)));
    $stmt->bind_result($MatchID, $Active, $MatchDate, $UnmatchDate, $UnmatchInitiator, $Chat, $TargetID, $TargetUsername, $TargetName, $TargetPictures, $ActiveAccount);
    $result="";
    
    $updatedChats=array();
    $updatedChatItems=array();
    while ($stmt->fetch()) { 
        if ($UnmatchInitiator == $ID) continue;
        if ($Chat != null) {
            $mainUpdated=false; //update read time of the last 3 messages
            $Chat=substr($Chat,1,strlen($Chat)-2);
            $messageItems=explode("}{",$Chat);
            $count=count($messageItems);
            $latestEntries=array();
            if ($count > 3) { //select 3 latest messages, we will display each in one line.
                for($i=$count-3; $i<$count; $i++) {
                    $updated=false;
                    updatemessage($messageItems[$i], $ID, false, $updated);
                    $latestEntries[]=$messageItems[$i];
                    if ($updated) {
                        $mainUpdated=true;
                        list($messageID, $senderID, $sentTime, $seenTime, $readTime, $message)=getmessageparts($messageItems[$i]);
                        $newItem="{"."$messageID|$sentTime|$seenTime|$readTime}";
                        if (array_key_exists($senderID, $updatedChatItems)) {
                            $updatedChatItems[$senderID].=$newItem; //max 3 chat items, length cannot exceed 4kB
                        }
                        else {
                            $updatedChatItems[$senderID]=$newItem;
                        }
                    }
                }
            }
            else {
                for ($i=0; $i<count($messageItems);$i++) {
                    $updated=false; 
                    updatemessage($messageItems[$i], $ID, false, $updated);
                    if ($updated) {
                        $mainUpdated=true;
                        list($messageID, $senderID, $sentTime, $seenTime, $readTime, $message)=getmessageparts($messageItems[$i]);
                        $newItem="{"."$messageID|$sentTime|$seenTime|$readTime}";
                        if (array_key_exists($senderID, $updatedChatItems)) {
                            $updatedChatItems[$senderID].=$newItem; //max 3 chat items, length cannot exceed 4kB
                        }
                        else {
                            $updatedChatItems[$senderID]=$newItem;
                        }                        
                    }
                }
                $latestEntries=$messageItems;
            }
            $UpdateChat="{".implode($messageItems,"}{")."}";
            if ($mainUpdated) {
                $updatedChats[]=array($MatchID, $UpdateChat);
            }
            $Chat="{".implode($latestEntries,"}{")."}";
        }
        $TargetPicture=explode("|",$TargetPictures)[0];
        
        $Active=($Active==1)?"True":"False";
        $ActiveAccount=($ActiveAccount==1)?"True":"False";
        
        $result.="{MatchID:$MatchID,Active:$Active,MatchDate:$MatchDate,UnmatchDate:$UnmatchDate,Chat:\"$Chat\",TargetID:$TargetID,TargetUsername:\"$TargetUsername\",TargetName:\"$TargetName\",TargetPicture:\"$TargetPicture\",ActiveAccount:$ActiveAccount}";             
    }
    $stmt->close();
    
    foreach($updatedChats as $arr) {//cannot update them inside the fetch loop while the connection is not closed yet.
        $MatchID= $arr[0];
        $UpdateChat= $arr[1];
        sqlupdate("matches", array("Chat"=>array("s",$UpdateChat)), array("ID"=> array("i",$MatchID)));
    }
    
    foreach($updatedChatItems as $senderID => $clouddata) {
        $stmt=&sqlselect("select Token, iOS from session where ID=?", array("i",$senderID));
        $stmt->bind_result($token, $ios);
        $stmt->fetch();
        $stmt->close();
        
        sendCloud($ID, $senderID, $token, $ios, false, true, null, null, "loadMessageList", $clouddata);
    }
    
    return "OK;$result";
}

function loadmessages($ID, $MatchID, $TargetID) {
    if ($MatchID != null) {
        $stmt=&sqlselectbymany("select profilesettings.ID, profiledata.Sex, Active, unix_timestamp(MatchDate), unix_timestamp(UnmatchDate), Chat, ActiveAccount, Friends from matches, profiledata, profilesettings, likehide where matches.ID = ? and likehide.ID = ? and profiledata.ID = profilesettings.ID and profilesettings.ID = case when matches.FirstID = ? then matches.SecondID when matches.SecondID = ? then matches.FirstID end ", array(array("i",$MatchID), array("i",$ID), array("i",$ID), array("i",$ID)));
        $stmt->bind_result($target, $Sex, $Active, $MatchDate, $UnmatchDate, $Chat, $ActiveAccount, $Friends); //If the chat is inactive because the target profile's account is inactive, the user shouldn't be able to view their profiles.
        $stmt->fetch();
        $stmt->close();
        $match=$MatchID;
    }
    else {
        $stmt=&sqlselectbymany("select matches.ID, Active, unix_timestamp(MatchDate), unix_timestamp(UnmatchDate), Chat, Sex, Username, Name, Pictures, ActiveAccount, Friends from matches, profiledata, profilesettings, likehide where (matches.FirstID = ? and matches.SecondID = ? or matches.FirstID = ? and matches.SecondID = ?) and likehide.ID = ? and profilesettings.ID = profiledata.ID and profiledata.ID = ?", array(array("i",$ID), array("i",$TargetID), array("i",$TargetID), array("i",$ID), array("i",$ID), array("i",$TargetID)));
        $stmt->bind_result($match, $Active, $MatchDate, $UnmatchDate, $Chat, $Sex, $TargetUsername, $TargetName, $TargetPictures, $ActiveAccount, $Friends);
        $stmt->fetch();
        $stmt->close();
        //UnmatchInitiator is not needed, it is not possible to unmatch from someone before clicking on the message notification (it will disappear when the app opens), unless they unmatch from another device, but then they are logged out of here.

        if ($match == null) { //user deleted itself while the other was on its standalone page, and now loading chat. Chat remains, but userid does not exist anymore. 
            return "ERROR_MatchNotFound";
        }        
    }
    
    if ($Chat != null) { //Update read time
        $mainUpdated=false;
        $clouddata=""; //when someone loads their messages, we should update the other party in case they are looking at the conversation too. Read times have to be set. Updating seen times for other parties when someone loads their chat list is not implemented.
        $Chat=substr($Chat,1,strlen($Chat)-2);
        $messageItems=explode("}{",$Chat);
        for ($i=0; $i<count($messageItems);$i++) { 
            $updated=false;
            updatemessage($messageItems[$i], $ID, true, $updated);
            if ($updated) {
                $mainUpdated=true;
                list($messageID, $senderID, $sentTime, $seenTime, $readTime)=getmessageparts($messageItems[$i]);                 
                $newdata=$clouddata."{"."$messageID|$sentTime|$seenTime|$readTime}"; 
                if (strlen($newdata < 4096-12)) { //at least 90 messages can be updated at a time.
                    $clouddata=$newdata;
                }   
            }
        }
        $Chat="{".implode($messageItems,"}{")."}";
        if ($mainUpdated) {
            sqlupdate("matches", array("Chat"=>array("s",$Chat)), array("ID"=> array("i",$match)));
        }
        if ($clouddata != "") {
            $stmt=&sqlselect("select Token, iOS from session where ID=?", array("i",$senderID));
            $stmt->bind_result($token, $ios);
            $stmt->fetch();
            $stmt->close();
            
            sendCloud($ID, $senderID, $token, $ios, false, true, null, null, "loadMessages", $clouddata);
        }
    }
    $targetexists=false;
    if ($Friends != "") {
        $friendarr=explode("|",$Friends);
        foreach ($friendarr as $friend) {
            $arr=explode(":",$friend);
            if ($arr[0]==$target) {
                $targetexists=true;
                break;
            }
        }
    }   
    $Friend=($targetexists)?"True":"False";
    $Active=($Active==1)?"True":"False";
    $ActiveAccount=($ActiveAccount==1)?"True":"False";

    if ($MatchID != null) {
        return "OK;{Sex:$Sex,Active:$Active,MatchDate:$MatchDate,UnmatchDate:$UnmatchDate,Chat:\"$Chat\",ActiveAccount:$ActiveAccount,Friend:$Friend}";
    }
    else {
        $arr=explode("|",$TargetPictures);
        $TargetPicture=$arr[0];
        return "OK;{MatchID:$match,Active:$Active,MatchDate:$MatchDate,UnmatchDate:$UnmatchDate,Chat:\"$Chat\",Sex:$Sex,TargetID:$TargetID,TargetUsername:\"$TargetUsername\",TargetName:\"$TargetName\",TargetPicture:\"$TargetPicture\",ActiveAccount:$ActiveAccount,Friend:$Friend}";
    }
}

function sendmessage($ID, $MatchID, $message) {
    global $mysqli, $maxMessageLength, $secondsInDay;
    
    $message=escapeAll($message);
    if (strlen($message) > $maxMessageLength) {
        return "Error: Message exceeds the $maxMessageLength characters limit.";
    }
    //authorize user to add a message to the match
    //anyone can authorize themselves as a user, and could tamper with the matchID, so they spam another conversation. We only allow access to the user's matches. 
    $mysqli->query("lock tables matches write, profilesettings read");
    $stmt=&sqlselectbymany("select Chat, FirstID, SecondID, unix_timestamp(FirstLatestMessage), unix_timestamp(SecondLatestMessage) from matches where ID=? and (FirstID=? or SecondID=?)", array(array("i",$MatchID),array("i",$ID), array("i",$ID)));
    $stmt->store_result();
    $count=$stmt->num_rows;
    if ($count==0) {
        $mysqli->query("unlock tables");
        return "AUTHORIZATION_ERROR";
    } 
    
    $stmt->bind_result($Chat, $FirstID, $SecondID, $FirstLatestMessage, $SecondLatestMessage);
    $stmt->fetch();
    $stmt->free_result();
    
    $targetID=($ID==$FirstID)?$SecondID:$FirstID;
    
    $stmt=&sqlselect("select ActiveAccount from profilesettings where ID=?", array("i",$targetID));
    $stmt->bind_result($ActiveAccount);
    $stmt->fetch();
    $stmt->free_result();
    
    if ($ActiveAccount == 0) {
        $mysqli->query("unlock tables");
        return "ERROR_UserPassive";
    }
    
    if ($Chat == null) {
        $count=0;
    }
    else {
        $messageItems=explode("}{",substr($Chat,1,strlen($Chat)-2));
        $count=count($messageItems);
    }
    
    $nextID=$count+1;
    $time=time();
    $timeStr=date("Y-m-d H:i:s",$time);    
    $updateTimeField=($ID==$FirstID)?"FirstLatestMessage":"SecondLatestMessage";
    
    $messageMeta="$nextID|$ID|$time|0|0";    
    $insertText="{".$messageMeta."|".$message."}";  
     
    sqlexecuteparams("update matches set Chat=?, $updateTimeField='$timeStr' where ID=$MatchID",array(array("s",$Chat.$insertText)));
    $mysqli->query("unlock tables");
    
    //update response rate
    $newRate="";
    $now=time();
    if ($ID==$FirstID && $FirstLatestMessage == null &&  $SecondLatestMessage != null) {
        if ($now-$SecondLatestMessage > $secondsInDay) {
            $newRate=calculateResponseRate($ID);        
        }  	
    }
    else if ($ID!=$FirstID && $FirstLatestMessage != null && $SecondLatestMessage == null) {
        if ($now-$FirstLatestMessage > $secondsInDay) {
            $newRate=calculateResponseRate($ID);    
        }
    }
    
    $stmt=&sqlselectbymany("select Token, iOS, MessageInApp, MessageBackground from session, profilesettings where session.ID = ? and profilesettings.ID = ?", array(array("i",$targetID), array("i",$targetID)));
    $stmt->bind_result($token, $ios, $MessageInApp, $MessageBackground);
    $stmt->fetch();
    $stmt->free_result();
    
    $stmt=&sqlselect("select Name from profiledata where ID=?", array("i",$ID));
    $stmt->bind_result($targetName);
    $stmt->fetch();
    $stmt->close();
   
    sendCloud($ID, $targetID, $token, $ios, $MessageBackground, $MessageInApp, NEWMESSAGEFROM." $targetName", $message, "sendMessage", $messageMeta);
    
    return "OK;$nextID|$time|$newRate";
}

function calculateResponseRate($ID) {
    global $secondsInDay;
    
    $received=0;
    $answered=0;
    $now=time();
    $secondsInDay=60*60*24;
    $stmt=&sqlselectbymany("select FirstID, SecondID, unix_timestamp(FirstLatestMessage) as FirstLatestMessage, unix_timestamp(SecondLatestMessage) as SecondLatestMessage from matches where FirstID = ? or SecondID = ?", array(array("i",$ID), array("i",$ID)));
    $stmt->bind_result($FirstID, $SecondID, $FirstLatestMessage, $SecondLatestMessage);
    while ($stmt->fetch()) {
        if ($FirstLatestMessage != null && $SecondLatestMessage != null) {
            $received++;
            $answered++;
        }
        else if ($FirstLatestMessage != null && $SecondLatestMessage == null) {
            if ($ID==$SecondID) {
                $received++;
                $diff=$now-$FirstLatestMessage;
                if ($diff <= $secondsInDay) {
                    $answered++;    
                }
            }
        }
        else if ($FirstLatestMessage == null && $SecondLatestMessage != null) {
            if ($ID==$FirstID) {
                $received++;
                $diff=$now-$SecondLatestMessage;
                if ($diff <= $secondsInDay) {
                    $answered++;    
                }
            }
        }
    }
    $stmt->free_result();
    
    $ratio=$answered/$received;
    $query="update profiledata set ResponseRate=$ratio where ID=$ID";
    sqlexecuteliteral($query); 
    return $ratio;
}

function messagedelivered($ID, $MatchID, $messageID, $Status) {
    global $mysqli;
    
    $mysqli->query("lock tables matches write");
    $stmt=&sqlselect("select Chat from matches where ID=?",array("i",$MatchID));
    $stmt->bind_result($Chat);
    $stmt->fetch();
    $stmt->close();
    
    $messageItems=explode("}{",substr($Chat,1,strlen($Chat)-2));
    for ($i=0; $i<count($messageItems);$i++) { 
        $sep1Pos = strpos($messageItems[$i],'|',0);
        $dbmessageID=substr($messageItems[$i], 0, $sep1Pos);
        if ($messageID == $dbmessageID) {
            $updated=false; //not used now, but we need to pass the parameter
            updatemessage($messageItems[$i], $ID, ($Status=="Read")?true:false, $updated); 
            list($messageID, $senderID, $sentTime, $seenTime, $readTime)=getmessageparts($messageItems[$i]);                         
            $clouddata="{"."$messageID|$sentTime|$seenTime|$readTime}";  //max 43 characters, + 12 = 55
            break;
        }         
    }
    $Chat="{".implode($messageItems,"}{")."}";  
    sqlupdate("matches", array("Chat" => array("s",$Chat)), array("ID"=>array("i",$MatchID)));
    $mysqli->query("unlock tables");     
    
    $stmt=&sqlselect("select Token, iOS from session where ID=?", array("i",$senderID));
    $stmt->bind_result($token, $ios);
    $stmt->fetch();
    $stmt->close();
    
    sendCloud($ID, $senderID, $token, $ios, false, true, null, null, "messageDelivered", $clouddata);
    return "OK";
}

function resetpassword($Email) {
    $stmt=&sqlselect("select profiledata.ID, Name, Session from profiledata, session where profiledata.ID = session.ID and Email = ?", array("s", $Email));
    $stmt->store_result();
    $count=$stmt->num_rows;
    if ($count != 0) {
        $stmt->bind_result($ID, $Name, $SessionID);
        $stmt->fetch();
        $stmt->close();
        
        $mailContent=file_get_contents("resetpassword_mail.html");
        $mailContent=str_replace("[name]",$Name,$mailContent);
        $mailContent=str_replace("[id]",$ID,$mailContent);
        $mailContent=str_replace("[sid]",$SessionID,$mailContent);
        
        require("mail.php"); 
        $res=sendMail("Password reset", $mailContent, $Email, $Name);
        if ($res !== true) {
            insertError($res);
            return "Email could not be sent.\nError: ".$res;
        }
        else {
            return "OK";
        }
    }
    return "OK";
}

function setpassword($ID,$SessionID) {
    return getform("",$ID,$SessionID);   
}

function changepassword($ID, $SessionID, $Password, $ConfirmPassword) {
    $message="";
    if ($Password !== $ConfirmPassword) {
        return getform("Error: Passwords do not match.",$ID,$SessionID);
        
    } 
    if ($res=validatefield("Password", $Password)) {
        return getform($res,$ID,$SessionID);
    }
    
    $Hash=password_hash($Password, PASSWORD_DEFAULT, ["cost" => 10]);
    sqlupdate("profiledata", array("Password" => array("s",$Hash)), array("ID" => array("i",$ID)));
    $sessionid=password_hash($Hash, PASSWORD_DEFAULT, ["cost" => 10]); 
    sqlupdate("session", array("Session" => array("s",$sessionid)), array("ID" => array("i",$ID)));
    
    $content=file_get_contents("base_message.html");
    return str_replace("[message]","Password changed. You can log in now.",$content); 
}

function getform($message,$ID,$SessionID) {
    $content=file_get_contents("changepassword_form.html");
    if ($message == "") {
        $content=str_replace("[message]","",$content);
    }
    else {
        $content=str_replace("[message]",'<div id="messagebox">'.$message.'</div>',$content);
    }
    $content=str_replace("[id]",$ID,$content);
    $content=str_replace("[sid]",$SessionID,$content);
    return $content;  
}

//---------------- GENERAL FUNCTIONS ----------------
 
function recurse_copy($src, $dst) {
    if (!is_dir($src)) {
        throw new InvalidArgumentException("$src must be a directory");
    }
    $dir = opendir($src); 
    mkdir($dst); 
    while(($file=readdir($dir)) !== false) { 
        if (($file!='.') && ($file!='..')) { 
            if (is_dir($src. '/' .$file)) { 
                recurse_copy($src.'/'. $file, $dst.'/'. $file); 
            } 
            else { 
                copy($src.'/'.$file, $dst.'/'.$file); 
            } 
        } 
    } 
    closedir($dir);
}

function move_folder($src, $dst) {
    global $conn;
    
    $options=['prefix' => $src];
    foreach($conn->bucket->objects($options) as $object) {
        $dstobject=str_replace($src,$dst,$object->name());
        $object->copy($conn->bucket, ['name' => $dstobject]); 
        $object->delete();         
    }
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
        print "delete_dir ".$dirPath."\n";
        if (!is_dir($dirPath)) {
            throw new InvalidArgumentException("$dirPath must be a directory");
        }
        if (substr($dirPath, strlen($dirPath) - 1, 1) != '/' && substr($dirPath, strlen($dirPath) - 1, 1) != '\\') {
            $dirPath .= '/';
        }
        $files = glob($dirPath . '*', GLOB_MARK);
        foreach ($files as $file) {
            if (is_dir($file)) {                  
                delete_dir($file);
            } else {
                print "unlink ".$file."\n";
                unlink($file);
            }
        }
        print "rmdir ".$dirPath."\n";
        
        $fp=opendir($dirPath);
        while ($file=readdir($fp)) {
            print "file inside1: ".$file."\n";
            if ($file != ".." && $file != ".") {
                $result=rmdir($dirPath.$file."/"); 
                var_dump($result);
                print $result." deleted in after cycle: $file \n";   
            }
        }
        $files = glob($dirPath . '*', GLOB_MARK);
        print "count: ".count($files)."\n";
        foreach ($files as $file) {
            print " file inside2: ".$file."\n";
        }     
        //"directory not empty" error after deleting 480 folder. But deleting the folder again above results in "permission denied error". Upon checking the file system, the 480 folder is deleted.     
        $result=rmdir($dirPath);        
        var_dump($result)."\n";
    }
    
} 

function resize_image($sourceFile, $destFile, $targetW, $targetH, $orientation) {
    global $errortext;
    try {
        list($width, $height) = getimagesize($sourceFile);
        $ratio = $width / $height;
        if ($width > $height) { //landscape         
            $startX=floor($width-$height)/2; //cropped to the left of center if the difference is impair
            $startY=0;
            $srcWidth=$height;
            $srcHeight=$height;
        }
        else { //portrait
            $startX=0;
            $startY=floor($height-$width)/2;
            $srcWidth=$width;
            $srcHeight=$width;
        }
        
        if ($srcWidth < $targetW && $srcHeight < $targetH) { //lower resolution images do not need to be upscaled.
            $targetW=$srcWidth;
            $targetH=$srcHeight;
        }
        
        $dst = imagecreatetruecolor($targetW, $targetH);
        $imageFileType = strtolower(pathinfo($destFile,PATHINFO_EXTENSION));        
        if ($imageFileType == "jpg" || $imageFileType == "jpeg") {
            $src = imagecreatefromjpeg($sourceFile);
            $im=imagecopyresampled($dst, $src, 0, 0, $startX, $startY, $targetW, $targetH, $srcWidth, $srcHeight);
            switch($orientation) {
                case 3:
                    $dst=imagerotate($dst, 180, 0);
                    break;
                case 6:
                    $dst=imagerotate($dst, -90, 0);
                    break;
                case 8:
                    $dst=imagerotate($dst, 90, 0);
                    break;                
            }
            imagejpeg($dst,$destFile,80);             
        }
        else { //png
            $src = imagecreatefrompng($sourceFile);
            imagecopyresampled($dst, $src, 0, 0, $startX, $startY, $targetW, $targetH, $width, $height);
            switch($orientation) {
                case 3:
                    $dst=imagerotate($dst, 180, 0);
                    break;
                case 6:
                    $dst=imagerotate($dst, -90, 0);
                    break;
                case 8:
                    $dst=imagerotate($dst, 90, 0);
                    break;                
            }
            imagepng($dst,$destFile,6);
        }
        return true;
    }
    catch (Exception $ex) {
        $errorText=$ex->getMessage();
        return false;
    }   
}

function formatTime($intervalSeconds) {
    $days= floor($intervalSeconds / 86400);
    $hours = floor($intervalSeconds / 3600) % 24;
    $minutes = floor(($intervalSeconds / 60) % 60);
    $seconds = $intervalSeconds % 60;
    $str="";
    $showminutes=true;
    $showseconds=true;
    if ($days > 1)
	{
		$str.= $days." days ";
        $showminutes=false;
        $showseconds=false;
	}
	else if ($days > 0)
	{
		$str.= $days." day ";
        $showminutes=false;
        $showseconds=false;
	}

	if ($hours > 1)
	{
		$str.= $hours." hours ";
        $showseconds=false;
	}
	else if ($hours > 0)
	{
		$str.= $hours." hour ";
        $showseconds=false;
	}    

    if ($showminutes) {
        if ($minutes > 1)
    	{
    		$str.= $minutes." minutes ";
    	}
    	else if ($minutes > 0)
    	{
    		$str.= $minutes." minute ";
    	}
    }
	
    if ($showseconds) {
        if ($seconds > 1)
    	{
    		$str.= $seconds." seconds ";
    	}
    	else if ($seconds > 0)
    	{
    		$str.= $seconds." second ";
    	}
    }
	 
    return $str; 
}

function sendCloud($from, $to, $token, $ios, $isBackground, $isInApp, $title, $body, $type, $meta) {
    global $conn, $maxLogLength, $requestID;

    
    $url="https://fcm.googleapis.com/fcm/send";  
    
    //using from instead of fromuser results in Bad Request error
    if (!$ios) {
        //for compatibiity
        $content = $meta;     
        if ($type == "sendMessage") {
            $content.="|";
        }
        if ($type == "matchProfile" || $type == "rematchProfile" || $type == "unmatchProfile" || $type == "locationUpdate" || $type == "locationUpdateEnd") {
            $content=$from."|".$meta;
        }

        if ($isBackground) {
            $data='{"to":"'.$token.'","data":{"fromuser":'.$from.',"touser":'.$to.',"type":"'.$type.'","content":"'.$content.'","meta":"'.$meta.'","inapp":'.$isInApp.'},"notification":{"title":"'.$title.'","body":"'.$body.'"}}';
        }
        else if ($title != null) {
            $data='{"to":"'.$token.'","data":{"fromuser":'.$from.',"touser":'.$to.',"type":"'.$type.'","content":"'.$content.'","meta":"'.$meta.'","inapp":'.$isInApp.',"title":"'.$title.'","body":"'.$body.'"}}';
        }
        else {
            $data='{"to":"'.$token.'","data":{"fromuser":'.$from.',"touser":'.$to.',"type":"'.$type.'","content":"'.$content.'","meta":"'.$meta.'","inapp":'.$isInApp.'}}';        
        }
    }
    else {
        if ($isBackground) {
            $data='{"to":"'.$token.'","data":{"fromuser":'.$from.',"touser":'.$to.',"type":"'.$type.'","meta":"'.$meta.'","inapp":'.$isInApp.'},"notification":{"title":"'.$title.'","body":"'.$body.'"}}';
        }
        else if ($title != null) {
            $data='{"to":"'.$token.'","data":{"fromuser":'.$from.',"touser":'.$to.',"type":"'.$type.'","meta":"'.$meta.'","inapp":'.$isInApp.',"title":"'.$title.'","body":"'.$body.'"}}';
        }
        else {
            $data='{"to":"'.$token.'","data":{"fromuser":'.$from.',"touser":'.$to.',"type":"'.$type.'","meta":"'.$meta.'","inapp":'.$isInApp.'}}';        
        }
    }
    
    $options = array(
        "ssl" => array(
            "verify_peer" => false,
            "verify_peer_name" => false,
        ),
        'http' => array(
            'header'  => "Content-Type:application/json\r\n".
            "Authorization:key=".$conn::FIREBASE_KEY."\r\n",
            'method'  => 'POST',
            'content' => $data
        )
    );
    $context  = stream_context_create($options);   
    $result = file_get_contents($url, false, $context);

    /*sqlinsert("log_errors", array(
        "RequestID"=>array("i",$requestID),
        "Time"=>array("s",date("Y-m-d H:i:s",time())),
        "Content"=>array("s",truncateString($data." --- ".$result, $maxLogLength)),
    ),false);*/

    //can't insert emoji string to Content.
    
    if ($result === false) {
        sqlinsert("log_errors", array(
            "RequestID"=>array("i",$requestID),
            "Time"=>array("s",date("Y-m-d H:i:s",time())),
            "Content"=>array("s","Error sending message notification."),
        ),false);
    }
    else if (!strstr($result,'"success":1')) {
        sqlinsert("log_errors", array(
            "RequestID"=>array("i",$requestID),
            "Time"=>array("s",date("Y-m-d H:i:s",time())),
            "Content"=>array("s",truncateString($result." --- ".$to, $maxLogLength)),
        ),false);
    }
    /*}
    else {        
        if ($isBackground) {
            $data='{"aps":{"alert":"'.$title.'","type":"'.$type.'","content":"'.$content.'","inapp":'.$isInApp.'}}';
        }
        else if ($title != null) {
            $data='{"to":"'.$to.'","data":{"type":"'.$type.'","title":"'.$title.'","body":"'.$body.'","content":"'.$content.'","inapp":'.$isInApp.'}}';
        }
        else {
            $data='{"to":"'.$to.'","data":{"type":"'.$type.'","content":"'.$content.'","inapp":'.$isInApp.'}}';        
        }

        $data='{"aps":{"alert":"'.$title.'"}}';

        insertError($data);

        $keyfile = "AuthKey_29FQTQSV9N.p8";
        $kid="29FQTQSV9N";
        $iss="U33242H3P9";

        //https://stackoverflow.com/questions/47646162/unexpected-http-1-x-request-post-3-device-xxxx
        $private_key=JWKFactory::createFromKeyFile($keyfile, null, [
            'kid' => $kid,
            'alg' => 'ES256',
            'use' => 'sig'
        ]);
        
        $header=[
            'alg' => 'ES256',
            'kid' => $private_key->get('kid')
        ];       
        $payload = [
            'iss' => $iss,
            'iat' => time()
        ];

        //Error: Class JWSFactory not found
        $jws=JWSFactory::createJWSToCompactJSON($payload, $private_key, $header);

        var_dump("NEW key ID: " + $private_key->get('kid'));
        var_dump("Private key: ".$private_key);
        var_dump("Jws: ".$jws);

        //https://medium.com/chefling/push-notification-with-json-web-token-33afb5af071e
        $token = getToken($keyfile, $kid, $iss);
        $url="https://api.development.push.apple.com:443/3/device/".urlencode($to);
        $curl = curl_init();
        $headers = array("Content-Type:application/json", "authorization: bearer ".$token, "apns-expiration: 0", "apns-priority: 10", "apns-topic: balintfodor.locationconnection-test");
        curl_setopt($curl, CURLOPT_URL, $url); //No url set! error if defined in options
        $options = array($curl, array(            
            CURLOPT_PORT => 443,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POST => TRUE,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => FALSE,
            CURLOPT_HEADER => 1,
            //CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
        ));

        print " --- ";

        $result = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE); 
        $err = curl_error($curl);  
        
        print " ----- ";

        var_dump($httpcode, $result, $err);
        
        Gives error: Unexpected HTTP/1.x request: POST /3/device/...

        $url="https://api.development.push.apple.com:443/3/device/".urlencode($to);
        $options = array(
            "ssl" => array(
                "verify_peer" => false,
                "verify_peer_name" => false,
            ),
            "http" => array(
                'header'  => "Content-Type:application/json\r\n".
                "authorization:bearer ".$conn::APN_KEY."\r\n".
                "apns-expiration:0\r\n".
                "apns-priority:10\r\n".
                "apns-topic:balintfodor.locationconnection-test\r\n",
                'method'  => 'POST',
                'content' => $data
            )
        );
        $context  = stream_context_create($options);      
        $result = file_get_contents($url, false, $context);

        var_dump($http_response_header, $result, $url);               
    }*/       
}

/*function appendLog($file, $text) { //google cloud does not support file_put_contents(,,FILE_APPEND), or fopen(). file_get_contents would fail on a non-existent file.
    if (file_exists($file)) {
        file_put_contents($file, file_get_contents($file).date("Y-m-d H:i:s")." $text".PHP_EOL);
    }
    else {
        file_put_contents($file, date("Y-m-d H:i:s")." $text".PHP_EOL);
    }
}*/

function truncateString($str, $length) {
    return (strlen($str)>$length)?substr($str,0,$length):$str;
}

function removeSession($str) {
    return preg_replace("/SessionID=[^&]+/","SessionID=[]",$str);
}

function escapeAll($message) {
    $message=str_replace('"','\"',$message);
    $message=str_replace("}","\}",$message);
    return str_replace("{","\{",$message);
}

function filenameSafe($str) {
    $str=str_replace("/","",$str);
    $str=str_replace("\\","",$str);
    $str=str_replace("?","",$str);
    $str=str_replace("%","",$str);
    $str=str_replace("*","",$str);
    $str=str_replace(":","",$str);
    $str=str_replace("|","",$str);
    $str=str_replace("\"","",$str);
    $str=str_replace("<","",$str);
    $str=str_replace(">","",$str);
    $str=str_replace(".","",$str);
    $str=str_replace(" ","_",$str);
    return $str;
}

function getToken($cerPath, $secret, $teamId) {
    // 1.
    //Modified from original after getting the error: Call to undefined method AlgorithmManager::create()
	$algorithmManager = new AlgorithmManager([ 
		new ES256() 
	]);

	// 2.
	$jwk = JWKFactory::createFromKeyFile($cerPath);

	// The JSON Converter.
	$jsonConverter = new StandardConverter();

	// We instantiate our JWS Builder.
	$jwsBuilder = new JWSBuilder(
	    $jsonConverter,
	    $algorithmManager
	);

	// 3.
	$payload = $jsonConverter->encode([
	    'iat' => time(),
	    'iss' => $teamId,
	]);

	// 4.
	$jws = $jwsBuilder
	    ->create()                                                  // We want to create a new JWS
	    ->withPayload($payload)                                     // We set the payload
	    ->addSignature($jwk, ['alg' => 'ES256', 'kid' => $secret])  // We add a signature with a simple protected header
	    ->build();                                                  // We build it

	$serializer = new CompactSerializer($jsonConverter); // The serializer

	// 5.
	$token = $serializer->serialize($jws); // We serialize the signature at index 0 (we only have one signature).

	return $token;
}

?>