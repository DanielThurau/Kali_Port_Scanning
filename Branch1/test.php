<?php

require '/Users/danielthurau/vendor/phpmailer/phpmailer/PHPMailerAutoload.php';

$mail = new PHPMailer;

$mail->From = 'daniel.thurau@macbook.com';
$mail->FromName = 'Daniel';
$mail->Subject = 'This is a test';
$mail->Body = 'This is the body';
$mail->AddAddress('dthurau@ucsc.edu');
$mail->AddAttachment('/Users/danielthurau/Desktop/output.csv');

return $mail->Send();


?>