<?php
$_ENV["PATH"]=@parse_url($_SERVER["REQUEST_URI"])["path"]; 
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
    case "/questions":
        require "questions.php";
        break;          
}
?>