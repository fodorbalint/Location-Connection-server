<?php
ini_set("display_errors",1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require("Connection.php");
$conn=new Connection();
$conn->sqlconnect();



$contentarr=file("helpcenter.txt");
$isdesc=true;
$istutorial=true;

$descindex=0;

$descriptions=array();
$pictures=array();
$questions=array();
$answers=array();

foreach ($contentarr as $line) {
    $line=trim($line);//we have to get rid of the newline endings
    if ($line != "" && $line[0]=="'") { //comments
        continue;
    }
    if ($line == "-----") {
        $istutorial=false;
        $isquestion=false;
        $descindex--;
        $questionindex=-1;
        continue;
    }

    if ($istutorial) {
        if ($isdesc) {
            if ($line != "") { //question line
                $descriptions[$descindex]=$line;
            }
            else { //empty line after a question
                $isdesc=false;
            }
        }
        else {
            if ($line != "") { //answer line
                if (count($pictures)<=$descindex) {
                    $pictures[]=array($line);
                }
                else {
                    $pictures[$descindex][]=$line;
                }
            }
            else { //empty line after an answer 
                $isdesc=true;
                $descindex++;
            } 
        }
    }
    else {
        if ($isquestion) {
            if ($line != "") { //question line
                $questions[$questionindex]=$line;
            }
            else { //empty line after a question
                $isquestion=false;
            }
        }
        else {
            if ($line != "") { //answer line
                if (count($answers)<=$questionindex) { //new answer starts
                    $answers[]=$line;
                }
                else { //adding to existing answer
                    $answers[$questionindex].=PHP_EOL.$line;
                }
            }
            else { //empty line after an answer 
                $isquestion=true;
                $questionindex++;
            } 
        }
    }
}

sqlexecuteliteral("truncate table helpcenter_questions");

$sql="insert into helpcenter_questions (Question, Answer) values ";
for($i=0;$i<=$questionindex;$i++) {
    $sql.="('".mysqli_escape_string($mysqli, $questions[$i])."','".mysqli_escape_string($mysqli, $answers[$i])."'),";
}
$sql=substr($sql,0,strlen($sql)-1);

sqlexecuteliteral($sql);

sqlexecuteliteral("truncate table tutorial");

$sql="insert into tutorial (`Description`, `Android phone`, `Android tablet`, `iOS phone`, `iOS tablet`) values ";
for($i=0;$i<=$descindex;$i++) {
    $sql.="('".mysqli_escape_string($mysqli, $descriptions[$i])."'";
    foreach($pictures[$i] as $picture) {
        $sql.=",'".$picture."'";
    }
    $sql.="),";
}
$sql=substr($sql,0,strlen($sql)-1);

sqlexecuteliteral($sql);

print ($descindex+1)." descriptions and ".($questionindex+1)." questions uploaded.";

print "<pre>";
print_r($descriptions);
print_r($pictures);
print_r($questions);
print_r($answers);
print "</pre>";

?>