<?php

$usefile=1;
$noofpoints=5;
$rmainsmall=120;
$rmainlarge=120;
$rsmall=80;
$rlarge=$rsmall*$rmainlarge/$rmainsmall;
$minDist=$rmainlarge+$rlarge;
$maxDist=500-$rlarge;
$wideLineWidth=50;
$narrowLineWidth=30; 
$bigMainCircleColor1="#".dechex(255).dechex(255).dechex(255);
$bigMainCircleColor2="#".dechex(255).dechex(255).dechex(255);
$smallMainCircleColor1="#".dechex(255).dechex(255).dechex(255);
$smallMainCircleColor2="#".dechex(255).dechex(255).dechex(255); 
$wideLineStartColor="#".dechex(255).dechex(255).dechex(255);
$wideLineEndColor="#".dechex(255).dechex(255).dechex(255);
$narrowLineColor="#".dechex(255).dechex(255).dechex(255);
$bigCircleStartColor=array(255,255,255);
$bigCircleEndColor=array(255,255,255); 
$smallCircleStartColor=array(255,255,255);
$smallCircleEndColor=array(255,255,255);
$shadeoffset=0;
//$shaderatio=0.3; 

$valuesX=array();
$valuesY=array();
$centerX=500;
$centerY=500;

$valuesXY=array();

if ($usefile) {
    $arr=explode(";",file_get_contents("svgdata.txt"));
    $valuesX=explode("|",$arr[0]);
    $valuesY=explode("|",$arr[1]);
}
else {
    //points within a cirle
    for ($i=0; $i<$noofpoints; $i++) {
        //all point have different distance
        $dist=$minDist+($maxDist-$minDist)*$i/($noofpoints-1);
        $angle=rand(0,359);
        $x=$centerX + cos(deg2rad($angle))*$dist;
        $y=$centerY - sin(deg2rad($angle))*$dist;
        $valuesXY[$i]=array($x,$y);
        //random point within the allowed range
        /*$startVal=$rlarge;
        $endVal=1000-$rlarge;
        do {
            $x=rand($startVal,$endVal);       
            $y=rand($startVal,$endVal);
            $distX=abs(500-$x);
            $distY=abs(500-$y);
            $dist=sqrt($distX*$distX+$distY*$distY);              
        } while ($dist<$minDist || $dist>$maxDist);                
        $valuesX[$i]=$x;  
        $valuesY[$i]=$y;*/         
    }
    shuffle($valuesXY);
}

foreach($valuesXY as $arr) {
    $valuesX[]=$arr[0];
    $valuesY[]=$arr[1];
}

$lines="";
$circles="";
$circles="\n\t<path d='M$centerX,$centerY m-$rmainlarge,0 a $rmainlarge,$rmainlarge 0,1 1,".($rmainlarge*2).",0 a $rmainlarge,$rmainlarge,0,1,1,-".($rmainlarge*2).",0z' fill='url(#gradm1)' />\n\t<path d='M$centerX,$centerY m-$rmainsmall,0 a $rmainsmall,$rmainsmall 0,1 1,".($rmainsmall*2).",0 a $rmainsmall,$rmainsmall,0,1,1,-".($rmainsmall*2).",0z' fill='url(#gradm2)' />\n";
$circles.='    <defs>
        <linearGradient id="gradm1" x1="'.(50-50*cos(deg2rad(30))).'%" y1="'.(50+50*sin(deg2rad(30))).'%" x2="'.(50+50*cos(deg2rad(30))).'%" y2="'.(50-50*sin(deg2rad(30))).'%">
            <stop offset="0%" style="stop-color:'.$bigMainCircleColor1.'" />
            <stop offset="100%" style="stop-color:'.$bigMainCircleColor2.'" />
        </linearGradient>
    </defs>
    <defs>
        <linearGradient id="gradm2" x1="'.(50-50*cos(deg2rad(30))).'%" y1="'.(50+50*sin(deg2rad(30))).'%" x2="'.(50+50*cos(deg2rad(30))).'%" y2="'.(50-50*sin(deg2rad(30))).'%">
            <stop offset="0%" style="stop-color:'.$smallMainCircleColor1.'" />
            <stop offset="100%" style="stop-color:'.$smallMainCircleColor2.'" />
        </linearGradient>
    </defs>
    ';

for ($i=0; $i<$noofpoints; $i++) {
    
    $r=$bigCircleStartColor[0]+($bigCircleEndColor[0]-$bigCircleStartColor[0])*($i/($noofpoints-1));
    $g=$bigCircleStartColor[1]+($bigCircleEndColor[1]-$bigCircleStartColor[1])*($i/($noofpoints-1));
    $b=$bigCircleStartColor[2]+($bigCircleEndColor[2]-$bigCircleStartColor[2])*($i/($noofpoints-1));    
    $bcolorlight=hexcolor(lighten2(array($r,$g,$b),$shadeoffset));
    $bcolordark=hexcolor(darken2(array($r,$g,$b),$shadeoffset));
    
    $r=$smallCircleStartColor[0]+($smallCircleEndColor[0]-$smallCircleStartColor[0])*($i/($noofpoints-1));
    $g=$smallCircleStartColor[1]+($smallCircleEndColor[1]-$smallCircleStartColor[1])*($i/($noofpoints-1));
    $b=$smallCircleStartColor[2]+($smallCircleEndColor[2]-$smallCircleStartColor[2])*($i/($noofpoints-1));    
    $scolorlight=hexcolor(lighten2(array($r,$g,$b),$shadeoffset));
    $scolordark=hexcolor(darken2(array($r,$g,$b),$shadeoffset));    
    
    $x1=$centerX; 
    $y1=$centerY;
    $x2=$valuesX[$i];
    $y2=$valuesY[$i];     
    
    $lines.="\t<path d='M$centerX,$centerY L{$valuesX[$i]},{$valuesY[$i]}' stroke='url(#gradl".$i."1)' stroke-width='$wideLineWidth' />\n\t<path d='M$centerX,$centerY L{$valuesX[$i]},{$valuesY[$i]}' stroke='$narrowLineColor' stroke-width='$narrowLineWidth' />\n";
    $lines.='   <defs>
        <linearGradient gradientUnits="userSpaceOnUse" id="gradl'.$i.'1" x1="'.$x1.'" y1="'.$y1.'" x2="'.$x2.'" y2="'.$y2.'">
            <stop offset="0%" style="stop-color:'.$wideLineStartColor.'" />
            <stop offset="100%" style="stop-color:'.$wideLineEndColor.'" />
        </linearGradient>
    </defs>
    ';
    
    $circles.="\t<path d='M{$valuesX[$i]},{$valuesY[$i]} m-$rlarge,0 a $rlarge,$rlarge 0,1 1,".($rlarge*2).",0 a $rlarge,$rlarge,0,1,1,-".($rlarge*2).",0z' fill='url(#grads".$i."1)' />\n\t<path d='M{$valuesX[$i]},{$valuesY[$i]} m-$rsmall,0 a $rsmall,$rsmall 0,1 1,".($rsmall*2).",0 a $rsmall,$rsmall,0,1,1,-".($rsmall*2).",0z' fill='url(#grads".$i."2)' />\n";
    $circles.='    <defs>
        <linearGradient id="grads'.$i.'1" x1="'.(50-50*cos(deg2rad(30))).'%" y1="'.(50+50*sin(deg2rad(30))).'%" x2="'.(50+50*cos(deg2rad(30))).'%" y2="'.(50-50*sin(deg2rad(30))).'%">
            <stop offset="0%" style="stop-color:'.$bcolordark.'" />
            <stop offset="100%" style="stop-color:'.$bcolorlight.'" />
        </linearGradient>
        <linearGradient id="grads'.$i.'2" x1="'.(50-50*cos(deg2rad(30))).'%" y1="'.(50+50*sin(deg2rad(30))).'%" x2="'.(50+50*cos(deg2rad(30))).'%" y2="'.(50-50*sin(deg2rad(30))).'%">
            <stop offset="0%" style="stop-color:'.$scolordark.'" />
            <stop offset="100%" style="stop-color:'.$scolorlight.'" />
        </linearGradient>
    </defs>
    ';
}
//header("Content-type: image/svg+xml");

file_put_contents("svgdata.txt",implode("|",$valuesX).";".implode("|",$valuesY));

$content='<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000">\n\t'.$lines.$circles.'</svg>';
file_put_contents("logo_notification.svg",$content);

print $content;

function hexColor($color) {
    return sprintf("#%02X%02X%02X",$color[0],$color[1],$color[2]);
}

function darken1($arr,$ratio) {
    $r=$arr[0];
    $g=$arr[1];
    $b=$arr[2];
    return array(255-(255-$r)*$ratio,255-(255-$g)*$ratio,255-(255-$b)*$ratio);    
}

function lighten1($arr,$ratio) {
    $r=$arr[0];
    $g=$arr[1];
    $b=$arr[2];
    return array($r*$ratio,$g*$ratio,$b*$ratio);    
}

function darken2($arr,$offset) {
    $r=$arr[0];
    $g=$arr[1];
    $b=$arr[2];
    $r=($r-$offset<0)?0:$r-$offset;
    $g=($g-$offset<0)?0:$g-$offset;
    $b=($b-$offset<0)?0:$b-$offset;
    return array($r,$g,$b);    
}
function lighten2($arr,$offset) {
    $r=$arr[0];
    $g=$arr[1];
    $b=$arr[2];
    $r=($r+$offset>255)?255:$r+$offset;
    $g=($g+$offset>255)?255:$g+$offset;
    $b=($b+$offset>255)?255:$b+$offset;
    return array($r,$g,$b);      
}
?>
