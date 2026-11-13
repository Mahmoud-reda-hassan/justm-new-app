<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

public function sendEmail($email,$code){

	require $_SERVER["DOCUMENT_ROOT"].'/plugins/PHPMailer/src/Exception.php';
	require $_SERVER["DOCUMENT_ROOT"].'/plugins/PHPMailer/src/PHPMailer.php';
	require $_SERVER["DOCUMENT_ROOT"].'/plugins/PHPMailer/src/SMTP.php';
	
	$mail = new PHPMailer();
	
	try {
	    //Server settings
	    //$mail->SMTPDebug = SMTP::DEBUG_SERVER;                      //Enable verbose debug output
	    $mail->isSMTP();                                            //Send using SMTP
	    $mail->Host       = 'smtp.gmail.com';                     //Set the SMTP server to send through
	    $mail->SMTPAuth   = true;                                   //Enable SMTP authentication
	    $mail->Username   = 'o1225133341@gmail.com';                     //SMTP username
	    $mail->Password   = getenv('SMTP_PASSWORD');                               //SMTP password
	    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            //Enable implicit TLS encryption
	    $mail->Port       = 465;                                    //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`
	
	    //Recipients
	    $mail->setFrom('m.reda.01225133341@gmail.com', 'JustM - ادارة الشحن');
	    $mail->addAddress('elmesawy.co@gmail.com', 'Joe User');     //Add a recipient
	    $mail->addAddress('elmesawy.co@gmail.com');               //Name is optional
	   	//$mail->addReplyTo('elmesawy.co@gmail.com', 'Information');
	    //$mail->addCC('cc@example.com');
	    //$mail->addBCC('bcc@example.com');
	
	    //Attachments
	    //$mail->addAttachment('/var/tmp/file.tar.gz');         //Add attachments
	    //$mail->addAttachment('/tmp/image.jpg', 'new.jpg');    //Optional name
	
	    //Content
	    $mail->isHTML(true);                                  //Set email format to HTML
	    $mail->Subject = 'Here is the subject';
	    $mail->Body    = 'This is the تاللاتتتتتتتتتت  message body <b>in bold!</b>';
	    $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';
	
	    if($mail->send()){
	    	echo 'Message has been sent';
	    }else{
	    	echo 'error';
	    }
	} catch (Exception $e) {
	     echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
	}
}

?>