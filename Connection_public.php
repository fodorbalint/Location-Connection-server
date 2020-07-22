<?php
use Google\Cloud\Storage\StorageClient;

if (isset($_SERVER["HTTP_X_APPENGINE_USER_IP"])) {
    $userip=$_SERVER["HTTP_X_APPENGINE_USER_IP"];
}
else {
    $userip=$_SERVER["REMOTE_ADDR"];
}

class Connection {

    private $servername;
    private $username;
    private $password;
    private $dbname;
    private $port=null;
    private $socket=null;
    public $bucket=null;
    const FIREBASE_KEY = "";
    const GOOGLEMAPS_KEY = "";
    const OWN_IP = "";
    const EXCLUDED_IPS=array("","","",self::OWN_IP);
    const KEY_FILE = "";
    const KID = "";
    const ISS = "";
    const BUNDLE_ID = "";
    
    function __construct() {
        switch ($_SERVER["HTTP_HOST"]) {
            case "":
            case "":
            case "":
                // PHP Version 7.3.10
                $this->servername = "";
                $this->username = "root";
                $this->password = "";
                $this->dbname = isset($_GET["testDB"])?"":"";
                $this->port = 3306;
                $_ENV["ROOT"] = ""; //local server needs to make a directory before writing nested file
                break;
            case "":
            case "":
            case "":
                $this->servername = "";
                $this->username = "";
                $this->password = "";
                $this->dbname = isset($_GET["testDB"])?"":"";
                $this->socket = "";        
                require_once __DIR__ . '/vendor/autoload.php';
                $projectID = $_ENV["PROJECT_NAME"];          
                $client = new StorageClient(['projectId' => $projectID]);
                $client->registerStreamWrapper();
                $_ENV["ROOT"] = "gs://$projectID.appspot.com/"; //creates directory when writing nested file
                $this->bucket = $client->bucket("$projectID.appspot.com");      
                break;
            case "":
                // PHP Version 7.2.17
                $this->servername = "";
                $this->username = "";
                $this->password = "";
                $this->dbname= "";
                $_ENV["ROOT"] = "";
                break;
        }        
    }
    
    function sqlConnect() {
        global $mysqli;
        
        $mysqli = new mysqli($this->servername, $this->username, $this->password, $this->dbname, $this->port, $this->socket); 
        if($mysqli->connect_error)
        {
            die("$mysqli->connect_errno: $mysqli->connect_error");
        }
        $mysqli->set_charset('utf8mb4');
    }     
}

function useTestDatabase() { //used for admin login, where credentials are always stored in the real database
    global $mysqli;
    $mysqli->query("use ");
}

function useRealDatabase() {
    global $mysqli;
    $mysqli->query("use ");
}

function sqlexecuteliteral($querystr) {
    global $mysqli;
    
    $stmt = $mysqli->stmt_init();
    if (!$stmt->prepare($querystr)) {
        die("Failed to prepare statement: ".$stmt->error);
    }
    else {
        if (!$stmt->execute()) {
            die("Database error: ".$stmt->error);    
        }
        $stmt->close();
    }
}

function sqlexecuteparams($querystr, $values) {
    global $mysqli;
    
    $typestr="";
    $params=array();
    foreach($values as $value) {
        $typestr.=$value[0];
        $params[]=$value[1];
    }
    
    $stmt = $mysqli->stmt_init();
    if (!$stmt->prepare($querystr)) {
        die("Failed to prepare statement: ".$stmt->error);
    }
    else {
        $stmt->bind_param($typestr, ...$params);
        if (!$stmt->execute()) {
            die("Database error: ".$stmt->error);    
        }
        $stmt->close();
    }
}

function &sqlselectall($querystr) {
    global $mysqli;
    
    $stmt = $mysqli->stmt_init();
    if (!$stmt->prepare($querystr)) {
        die("Failed to prepare statement: ".$stmt->error);
    }
    else {
        if (!$stmt->execute()) {
            die("Database error: ".$stmt->error);    
        }
        return $stmt;
    }
}

function &sqlselect($querystr, $value) {
    global $mysqli;
    
    $typestr=$value[0];
    $param=$value[1];
    
    $stmt = $mysqli->stmt_init();
    if (!$stmt->prepare($querystr)) {
        die("Failed to prepare statement: ".$stmt->error);
    }
    else {
        $stmt->bind_param($typestr, $param);
        if (!$stmt->execute()) {
            die("Database error: ".$stmt->error);    
        }
        return $stmt;
    }
}

function &sqlselectbymany($querystr, $values) {
    global $mysqli;
    
    $typestr="";
    $params=array();
    foreach($values as $value) {
        $typestr.=$value[0];
        $params[]=$value[1];
    }
    
    $stmt = $mysqli->stmt_init();
    if (!$stmt->prepare($querystr)) {
        die("Failed to prepare statement: ".$stmt->error);
    }
    else {
        $stmt->bind_param($typestr, ...$params);
        if (!$stmt->execute()) {
            die("Database error: ".$stmt->error);    
        }
        return $stmt;
    }
}

function sqlinsert($table, $values, $requestid) {
    global $mysqli;
    
    $querystr="insert into $table (";
    $querystrend="";
    $typestr="";
    $params=array();
    foreach($values as $key => $value) {
          $querystr.="$key,";
          $querystrend.="?,";
          $typestr.= $value[0];
          $params[]=$value[1];       
    }
    $querystr=substr($querystr,0,strlen($querystr)-1);
    $querystrend=substr($querystrend,0,strlen($querystrend)-1);
    $querystr.=") values (".$querystrend.")";
    
    $stmt = $mysqli->stmt_init();
    if (!$stmt->prepare($querystr)) {
        die("Failed to prepare statement: ".$stmt->error);
    }
    else {
        $stmt->bind_param($typestr, ...$params);
        if (!$stmt->execute()) {
            exit("Database error: ".$stmt->error);    
        }
        $id=$stmt->insert_id;         
        $stmt->close();
        if ($requestid) {
            return $id;
        }
    }
}

function sqlupdate($table, $updatefields, $condition) {
    global $mysqli;
     
    $querystr="update $table set ";
    $typestr="";
    $params=array();
    foreach($updatefields as $key=>$value) {
        $querystr.="$key=?,";
        $typestr.= $value[0];
        $params[]=$value[1];
    }
    $querystr=substr($querystr,0,strlen($querystr)-1);
    foreach($condition as $key=>$value) {
        $querystr.=" where $key=?";
        $typestr.= $value[0];
        $params[]=$value[1];
    }
    
    $stmt = $mysqli->stmt_init();
    if (!$stmt->prepare($querystr)) {
        die("Failed to prepare statement: ".$stmt->error);
    }
    else {
        $stmt->bind_param($typestr, ...$params);
        if (!$stmt->execute()) {
            if ($table != "log_input") { //response can be cut half at a special character, resulting in database error
                exit("Database error: ".$stmt->error);
            }               
        }
        $stmt->close();
    }
    
}

function sqldelete($table, $condition) {
     global $mysqli;
     
    $querystr="delete from $table";
    foreach($condition as $key=>$value) {
        $querystr.=" where $key=?";
        $typestr=$value[0];
        $param=$value[1];
    }
    
    $stmt = $mysqli->stmt_init();
    if (!$stmt->prepare($querystr)) {
        die("Failed to prepare statement: ".$stmt->error);
    }
    else {
        $stmt->bind_param($typestr, $param);
        if (!$stmt->execute()) {
            exit("Database error: ".$stmt->error);    
        }
        $stmt->close();
    }
}
?>