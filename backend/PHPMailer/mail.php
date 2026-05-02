<?php
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\SMTP;
    use PHPMailer\PHPMailer\Exception;
    require 'c:\xampp\htdocs\Exception.php';
    require 'c:\xampp\htdocs\PHPMailer.php';
    require 'c:\xampp\htdocs\SMTP.php';
    $mail = new PHPMailer(true);
    $mail->SMTPDebug = 0;
    $mail->isSMTP();
    $mail->Host = 'ssl0.ovh.net';
    $mail->SMTPAuth = true;
    $mail->Username = "software@groupe-hammami.com.tn";
    $mail->Password = "S4fpY2DmA*";
    $mail->SMTPSecure ="ssl0.ovh.net";
    $mail->Port = 465;
    $mail->From = "software@groupe-hammami.com.tn";
    $mail->FromName = "Hammami-groupe";
    $mail->addAddress("frikhamariem95@gmail.com", "Mariem");
    $mail->isHTML(true);
    $mail->Subject = "Mail sent from php send mail script.";
    $mail->Body = "<i>Text content from send mail.</i>";
    $mail->AltBody = "This is the plain text version of the email content";
    try {
        $mail->send();
        echo "Message has been sent successfully";
    } catch (Exception $e) {
        echo "Mailer Error: " . $mail->ErrorInfo;
    }
?>