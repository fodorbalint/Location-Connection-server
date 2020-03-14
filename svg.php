<?php

$file="svgdata_notification.txt";
$usefile=1;
$useangles=0;

$noofpoints=4;
$rmainsmall=81;
$rmainlarge=94.5;
$rsmall=54;
$rlarge=63; //$rsmall*$rmainlarge/$rmainsmall;
$minDist=$rmainlarge+$rlarge;

/*
Current rules

Circles:

8 4 3 7
1 10 5
6 9 2

Angles:

65.454545
(difference: 98.181818)
163.636363
261.818181

(at 2/3 angles)
229.090909
130.090909
327,272727

(at 2/3 angles)
108.545454
305.454545
338.181818

Distances:

Max: 374.5 (217)
Min: 169.5 (12)
Second: max dist - rsmall
Third: second - 2*rlarge
Fourth: min + rlarge
*/
$maxDist=500 * 7/8 - $rlarge;
$wideLineWidth=30;
$narrowLineWidth=18; 
$bigMainCircleColor1="#".dechex(128).dechex(128).dechex(128);
$bigMainCircleColor2="#".dechex(128).dechex(128).dechex(128);
$smallMainCircleColor1="#".dechex(0).dechex(0).dechex(0);
$smallMainCircleColor2="#".dechex(255).dechex(255).dechex(255); 
$wideLineStartColor="#".dechex(0).dechex(0).dechex(0);
$wideLineEndColor="#".dechex(204).dechex(204).dechex(204);
$narrowLineColor="#".dechex(204).dechex(204).dechex(204);
$bigCircleStartColor=array(0,128,25);
$bigCircleEndColor=array(128,25,0); 
$smallCircleStartColor=array(255,51,0);
$smallCircleEndColor=array(0,255,51);
$shadeoffset=50;
//$shaderatio=0.3; 

$valuesX=array();
$valuesY=array();
$centerX=500;
$centerY=500;

$valuesXY=array();

if ($usefile) {
    $arr=explode("\n",file_get_contents($file));

    if (!$useangles) {
        $valuesX=array_map('trim', explode("|",$arr[0]));
        $valuesY=array_map('trim', explode("|",$arr[1]));                
    } 
    else {
        $angles=array_map('trim', explode("|",$arr[2]));
        $distances=array_map('trim', explode("|",$arr[3]));         

        for ($i=0; $i<count($angles); $i++) {            
            $angle=$angles[$i];
            $dist=$distances[$i];            
            $x=round($centerX - sin(deg2rad($angle))*$dist, 1);
            $y=round($centerY - cos(deg2rad($angle))*$dist, 1);            
            $valuesXY[$i]=array($x,$y);
        }        
    } 
}
else { //new point set generation
    //points within a cirle
    for ($i=0; $i<$noofpoints; $i++) {
        //all point have different distance
        $dist=$minDist+($maxDist-$minDist)*$i/($noofpoints-1);
        $angle=rand(0,359); //0 degrees vertical up
        $x=$centerX - sin(deg2rad($angle))*$dist;
        $y=$centerY - cos(deg2rad($angle))*$dist;
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
$circles.="\t<defs>
\t\t<linearGradient id=\"gradm1\" x1=\"".(50-50*cos(deg2rad(30)))."%\" y1=\"".(50+50*sin(deg2rad(30)))."%\" x2=\"".(50+50*cos(deg2rad(30)))."%\" y2=\"".(50-50*sin(deg2rad(30)))."%\">
\t\t\t<stop offset=\"0%\" style=\"stop-color:".$bigMainCircleColor1."\" />
\t\t\t<stop offset=\"100%\" style=\"stop-color:".$bigMainCircleColor2."\" />
\t\t</linearGradient>
\t</defs>
\t<defs>
\t\t<linearGradient id=\"gradm2\" x1=\"".(50-50*cos(deg2rad(30)))."%\" y1=\"".(50+50*sin(deg2rad(30)))."%\" x2=\"".(50+50*cos(deg2rad(30)))."%\" y2=\"".(50-50*sin(deg2rad(30)))."%\">
\t\t\t<stop offset=\"0%\" style=\"stop-color:".$smallMainCircleColor1."\" />
\t\t\t<stop offset=\"100%\" style=\"stop-color:".$smallMainCircleColor2."\" />
\t\t</linearGradient>
\t</defs>\n";

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
    $lines.="\t<defs>
\t\t<linearGradient gradientUnits=\"userSpaceOnUse\" id=\"gradl".$i."1\" x1=\"".$x1."\" y1=\"".$y1."\" x2=\"".$x2."\" y2=\"".$y2."\">
\t\t\t<stop offset=\"0%\" style=\"stop-color:".$wideLineStartColor."\" />
\t\t\t<stop offset=\"100%\" style=\"stop-color:".$wideLineEndColor."\" />
\t\t</linearGradient>
\t</defs>\n";
    
    $circles.="\t<path d='M{$valuesX[$i]},{$valuesY[$i]} m-$rlarge,0 a $rlarge,$rlarge 0,1 1,".($rlarge*2).",0 a $rlarge,$rlarge,0,1,1,-".($rlarge*2).",0z' fill='url(#grads".$i."1)' />\n\t<path d='M{$valuesX[$i]},{$valuesY[$i]} m-$rsmall,0 a $rsmall,$rsmall 0,1 1,".($rsmall*2).",0 a $rsmall,$rsmall,0,1,1,-".($rsmall*2).",0z' fill='url(#grads".$i."2)' />\n";
    $circles.="\t<defs>
\t\t<linearGradient id=\"grads".$i."1\" x1=\"".(50-50*cos(deg2rad(30)))."%\" y1=\"".(50+50*sin(deg2rad(30)))."%\" x2=\"".(50+50*cos(deg2rad(30)))."%\" y2=\"".(50-50*sin(deg2rad(30)))."%\">
\t\t\t<stop offset=\"0%\" style=\"stop-color:".$bcolordark."\" />
\t\t\t<stop offset=\"100%\" style=\"stop-color:".$bcolorlight."\" />
\t\t</linearGradient>
\t\t<linearGradient id=\"grads".$i."2\" x1=\"".(50-50*cos(deg2rad(30)))."%\" y1=\"".(50+50*sin(deg2rad(30)))."%\" x2=\"".(50+50*cos(deg2rad(30)))."%\" y2=\"".(50-50*sin(deg2rad(30)))."%\">
\t\t\t<stop offset=\"0%\" style=\"stop-color:".$scolordark."\" />
\t\t\t<stop offset=\"100%\" style=\"stop-color:".$scolorlight."\" />
\t\t</linearGradient>
\t</defs>\n";
}
//header("Content-type: image/svg+xml");

if (!$useangles) {
    $angles = array();
    $distances = array();    

    for($i=0; $i<count($valuesX); $i++) {         
        $angle=rad2deg(atan(($centerX - $valuesX[$i])/($centerY - $valuesY[$i])));

        if ($valuesX[$i] <= $centerX && $valuesY[$i] <= $centerY) {
            $angles[]=round(rad2deg(atan(($centerX - $valuesX[$i])/($centerY - $valuesY[$i]))), 1);
        }
        else if ($valuesX[$i] <= $centerX && $valuesY[$i] > $centerY) {
            $angles[]=round(180 + rad2deg(atan(($centerX - $valuesX[$i])/($centerY - $valuesY[$i]))), 1);
        }
        else if ($valuesX[$i] > $centerX && $valuesY[$i] > $centerY) {
            $angles[]=round(180 + rad2deg(atan(($centerX - $valuesX[$i])/($centerY - $valuesY[$i]))), 1);
        }
        else {
            $angles[]=round(360 + rad2deg(atan(($centerX - $valuesX[$i])/($centerY - $valuesY[$i]))), 1);
        }  
        
        $distances[]=round(sqrt(pow($centerX - $valuesX[$i], 2) + pow($centerY - $valuesY[$i], 2)), 1);
    }
}

$valuesX = array_map(function ($item) {
    return str_pad($item, 6); 
}, $valuesX);

$valuesY = array_map(function ($item) {
    return str_pad($item, 6); 
}, $valuesY);

$angles = array_map(function ($item) {
    return str_pad($item, 6); 
}, $angles);

$distances = array_map(function ($item) {
    return str_pad($item, 6); 
}, $distances);

file_put_contents($file,implode("|",$valuesX)."\n".implode("|",$valuesY)."\n".implode("|",$angles)."\n".implode("|",$distances));

$content="<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 1000 1000\"><rect width=\"100%\" height=\"100%\" fill=\"#808080\" />\n".$lines.$circles.'</svg>';
file_put_contents("logo.svg",$content);

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
