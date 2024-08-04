<?php
function getPresentation($storeVariant) {
    $content=file("presentation.txt");
    if ($storeVariant==0) {
        $out="";
        foreach($content as $line) {
            if ($line[0]=="-") {
                $out.="<b>".trim(substr($line, 2))."</b>\n";
            }
            else {
                $out.=$line;
            }
        }
        print nl2br($out);
        file_put_contents("store listing.txt",$out);
    }
    else {
        $out="<div class='contentMain'>\n";
        $counter=0;
        $contentstart=false;
        $emptycount=0;
        foreach($content as $line) {
            if (trim($line) == "") {
                $emptycount++;
                if ($emptycount!=1) {
                    $contentstart=true;
                    //$out.="</div>";
                    $out=substr($out, 0, strlen($out)-8)."\n</div>\n"; //removing previous line's newline character
                }
                else {
                    $out.="\t".nl2br($line);
                }
                continue;
            }
            if ($line[0]=="-") {
                $counter++;
                $out.="<div class='counter'>\n";
                for($i=0;$i<$counter;$i++) {
                    $out.="\t<img class='circle' src='images/circle.svg' width='9' />\n";
                }
                $out.="</div>\n";
                $out.= "<div class='feature'>\n\t".trim(substr($line, 1))."\n</div>\n"; 
            }
            else if ($contentstart) {
                $contentstart=false;
                $out.="<div class='contentMain'>\n\t".nl2br($line);
            }
            else {
                $out.="\t".nl2br($line);
            }
        }
        $out.="</div>";
        return $out;
    }
}
?>