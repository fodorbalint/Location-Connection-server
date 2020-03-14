<?php
ini_set("display_errors",1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require("Connection.php");
$conn=new Connection();
$conn->sqlconnect();

sqlexecuteliteral("truncate table helpcenter_questions");

$contentarr=file("questions.txt");
$isquestion=true;
$index=0;
$questions=array();
$answers=array();

foreach ($contentarr as $line) {
    $line=trim($line);//we have to get rid of the newline endings
    if ($line != "" && $line[0]=="'") { //comments
        continue;
    }
    if ($isquestion) {
        if ($line != "") { //question line
            $questions[$index]=$line;
        }
        else { //empty line after a question
            $isquestion=false;
        }
    }
    else {
        if ($line != "") { //answer line
            if (count($answers)<=$index) {
                $answers[]=$line;
            }
            else {
                $answers[$index].=PHP_EOL.$line;
            }
        }
        else { //empty line after an answer 
            $isquestion=true;
            $index++;
        } 
    }
}

$sql="insert into helpcenter_questions (Question, Answer) values ";
for($i=0;$i<=$index;$i++) {
    $sql.="('".mysqli_escape_string($mysqli, $questions[$i])."','".mysqli_escape_string($mysqli, $answers[$i])."'),";
}
$sql=substr($sql,0,strlen($sql)-1);
sqlexecuteliteral($sql);                             
print ($index+1)." questions uploaded.";
?>