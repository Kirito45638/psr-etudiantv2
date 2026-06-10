<?php
session_start();

require_once("notifications/mail_helper.php");

$destinataire = "brayanlegendre45@gmail.com";
$sujet = "Test SMTP - PSR EREA";
$messageHtml = "
    <html>
    <body style='font-family: Arial, sans-serif; color: #1f2937;'>
        <h2 style='color: #2563eb;'>Test d'envoi SMTP</h2>
        <p>Bonjour,</p>
        <p>Si vous recevez ce message, cela veut dire que PHPMailer fonctionne correctement avec Gmail SMTP.</p>
        <p><strong>Expéditeur :</strong> noreply-psr45@gmail.com</p>
        <p>Restaurant PSR EREA</p>
    </body>
    </html>
";
$messageTexte = "Bonjour,\n\nSi vous recevez ce message, cela veut dire que PHPMailer fonctionne correctement avec Gmail SMTP.\n\nExpéditeur : noreply-psr45@gmail.com\nRestaurant PSR EREA";

$emailEnvoye = envoyerEmailNotification($destinataire, $sujet, $messageHtml, $messageTexte);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Test mail SMTP</title>
</head>
<body>
    <h1>Test d'envoi d'email</h1>

    <?php if ($emailEnvoye): ?>
        <p style="color: green;">Email envoyé avec succès à <?php echo htmlspecialchars($destinataire); ?>.</p>
    <?php else: ?>
        <p style="color: red;">Échec de l'envoi de l'email.</p>
        <p>Vérifie dans <code>mail_helper.php</code> :</p>
        <ul>
            <li>SMTP_HOST = smtp.gmail.com</li>
            <li>SMTP_PORT = 587</li>
            <li>SMTP_USERNAME = noreply-psr45@gmail.com</li>
            <li>SMTP_PASSWORD = mot de passe d'application Google</li>
            <li>SMTPSecure = STARTTLS</li>
        </ul>
    <?php endif; ?>
</body>
</html>