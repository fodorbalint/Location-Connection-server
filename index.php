<?php
if (isset($_GET["image"])) {
    $file = $_GET["image"];
    if (file_exists($file)) {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $ext = str_replace("svg", "svg+xml", $ext);
        header("Content-Type: image/".$ext);                                                
        header("Content-Length: ".filesize($file));
        readfile($file);
        die(); 
    }
    else if (file_exists($file.".jpg")) {
        $file = $file.".jpg";
        header("Content-Type: image/jpeg");                                                
        header("Content-Length: ".filesize($file));
        readfile($file);
        die();   
    }
    else if (file_exists($file.".jpeg")) {
        $file = $file.".jpeg";
        header("Content-Type: image/jpeg");                                                
        header("Content-Length: ".filesize($file));
        readfile($file);
        die();   
    }
    else if (file_exists($file.".png")) {
        $file = $file.".png";
        header("Content-Type: image/png");                                                
        header("Content-Length: ".filesize($file));
        readfile($file);
        die();
    }
    else if (file_exists($file.".svg")) {
        $file = $file.".svg";
        header("Content-Type: image/svg+xml");                                                
        header("Content-Length: ".filesize($file));
        readfile($file);
        die();
    }
    else {
        http_response_code(404);
        var_dump($_GET["image"]);
        exit("Not found");
    }
}
else {
    $_ENV["PATH"] = @parse_url($_SERVER["REQUEST_URI"])["path"]; 
    $_ENV["PATH"] = str_replace("/locationconnection.dk", "", $_ENV["PATH"]);
    switch($_ENV["PATH"]) {
        default:
        case "/":
            require "homepage.php";
            break;
        case "/admin":
            require "admin.php";
            break;
        case "/cron":
            require "cron.php";
            break;
        case "/helpcenter":
            require "helpcenter.php";
            break;    
    }
}
?>