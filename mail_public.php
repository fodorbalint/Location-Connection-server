<?php 
require("sendgrid/sendgrid-php.php");
const SENDGRID_API_KEY = '---------------------------------------------------------------------';
const OWN_EMAIL="fodorbalint@gmail.com";
const OWN_EMAIL_TO="---------------------";
const OWN_NAME="Balint Fodor";

function sendmail($subject, $htmlcontent, $to = OWN_EMAIL_TO, $toName = OWN_NAME) {
    try {
        $email = new \SendGrid\Mail\Mail(); 
        $email->setFrom(OWN_EMAIL, "Location Connection");
        $email->setSubject($subject);
        $email->addTo($to, $toName);
        $email->addContent("text/html", $htmlcontent);
        $sendgrid = new \SendGrid(SENDGRID_API_KEY);
        $response = $sendgrid->send($email);
        if ($response->statusCode() != 202) {
            return $response->headers()[0];
        }
        return true;
    }
    catch (Exception $e) {
        return $e->getMessage();
    }
}
?>