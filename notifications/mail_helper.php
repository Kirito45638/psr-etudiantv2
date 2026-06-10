<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

const SMTP_HOST = 'smtp.gmail.com';
const SMTP_PORT = 587;
const SMTP_USERNAME = 'noreply.psr45@gmail.com';
const SMTP_PASSWORD = 'orjwjiaqozsoceme';
const SMTP_FROM_EMAIL = 'noreply.psr45@gmail.com';
const SMTP_FROM_NAME = 'PSR EREA';
const SMTPSecure = 'STARTTLS';

function nettoyerTexteMail(?string $valeur): string
{
    return trim($valeur ?? '');
}

function envoyerEmailNotification(string $destinataire, string $sujet, string $messageHtml, string $messageTexte = ''): bool
{
    $destinataire = nettoyerTexteMail($destinataire);
    $sujet = nettoyerTexteMail($sujet);

    if ($destinataire === '' || $sujet === '' || $messageHtml === '') {
        return false;
    }

    if (!filter_var($destinataire, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    if ($messageTexte === '') {
        $messageTexte = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $messageHtml));
    }

    $mail = new PHPMailer(true);

    try {
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        $mail->SMTPDebug = SMTP::DEBUG_SERVER;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($destinataire);

        $mail->isHTML(true);
        $mail->Subject = $sujet;
        $mail->Body = $messageHtml;
        $mail->AltBody = $messageTexte;

        return $mail->send();
    } catch (Exception $e) {
        error_log('Erreur PHPMailer : ' . $mail->ErrorInfo);
        return false;
    }
}

function construireMessageConfirmationReservation(array $reservation, array $utilisateur): array
{
    $prenom = htmlspecialchars($utilisateur['prenom'] ?? '', ENT_QUOTES, 'UTF-8');
    $nom = htmlspecialchars($utilisateur['nom'] ?? '', ENT_QUOTES, 'UTF-8');
    $dateRepas = htmlspecialchars($reservation['daterepas'] ?? '', ENT_QUOTES, 'UTF-8');
    $creneau = htmlspecialchars($reservation['creneau'] ?? '', ENT_QUOTES, 'UTF-8');
    $typeRepas = htmlspecialchars($reservation['typerepas'] ?? '', ENT_QUOTES, 'UTF-8');
    $mode = htmlspecialchars($reservation['modeconsommation'] ?? '', ENT_QUOTES, 'UTF-8');

    $sujet = "Confirmation de votre réservation - PSR EREA";

    $messageHtml = "
        <html>
        <body style='font-family: Arial, sans-serif; color: #1f2937;'>
            <h2 style='color: #1d4ed8;'>Confirmation de réservation</h2>
            <p>Bonjour {$prenom} {$nom},</p>
            <p>Votre réservation a bien été enregistrée.</p>
            <ul>
                <li><strong>Date :</strong> {$dateRepas}</li>
                <li><strong>Créneau :</strong> {$creneau}</li>
                <li><strong>Type de repas :</strong> {$typeRepas}</li>
                <li><strong>Mode :</strong> {$mode}</li>
            </ul>
            <p>Merci et à bientôt.</p>
            <p>Restaurant PSR EREA</p>
        </body>
        </html>
    ";

    $messageTexte = "Bonjour {$prenom} {$nom},\n\n"
        . "Votre réservation a bien été enregistrée.\n"
        . "- Date : {$dateRepas}\n"
        . "- Créneau : {$creneau}\n"
        . "- Type de repas : {$typeRepas}\n"
        . "- Mode : {$mode}\n\n"
        . "Merci.\nRestaurant PSR EREA";

    return [
        'sujet' => $sujet,
        'html' => $messageHtml,
        'texte' => $messageTexte
    ];
}

function construireMessageRappelReservation(array $reservation, array $utilisateur): array
{
    $prenom = htmlspecialchars($utilisateur['prenom'] ?? '', ENT_QUOTES, 'UTF-8');
    $nom = htmlspecialchars($utilisateur['nom'] ?? '', ENT_QUOTES, 'UTF-8');
    $dateRepas = htmlspecialchars($reservation['daterepas'] ?? '', ENT_QUOTES, 'UTF-8');
    $creneau = htmlspecialchars($reservation['creneau'] ?? '', ENT_QUOTES, 'UTF-8');
    $typeRepas = htmlspecialchars($reservation['typerepas'] ?? '', ENT_QUOTES, 'UTF-8');
    $mode = htmlspecialchars($reservation['modeconsommation'] ?? '', ENT_QUOTES, 'UTF-8');

    $sujet = "Rappel de votre réservation - PSR EREA";

    $messageHtml = "
        <html>
        <body style='font-family: Arial, sans-serif; color: #1f2937;'>
            <h2 style='color: #2563eb;'>Rappel de réservation</h2>
            <p>Bonjour {$prenom} {$nom},</p>
            <p>Ceci est un rappel pour votre repas réservé.</p>
            <ul>
                <li><strong>Date :</strong> {$dateRepas}</li>
                <li><strong>Créneau :</strong> {$creneau}</li>
                <li><strong>Type de repas :</strong> {$typeRepas}</li>
                <li><strong>Mode :</strong> {$mode}</li>
            </ul>
            <p>Merci de votre ponctualité.</p>
            <p>Restaurant PSR EREA</p>
        </body>
        </html>
    ";

    $messageTexte = "Bonjour {$prenom} {$nom},\n\n"
        . "Ceci est un rappel pour votre réservation.\n"
        . "- Date : {$dateRepas}\n"
        . "- Créneau : {$creneau}\n"
        . "- Type de repas : {$typeRepas}\n"
        . "- Mode : {$mode}\n\n"
        . "Restaurant PSR EREA";

    return [
        'sujet' => $sujet,
        'html' => $messageHtml,
        'texte' => $messageTexte
    ];
}

function enregistrerNotification(PDO $conn, int $idUtilisateur, string $typeNotification, string $canal, string $sujet, string $message, string $statut): void
{
    $dateEnvoi = null;

    if ($statut === 'envoye') {
        $dateEnvoi = date('Y-m-d H:i:s');
    }

    $sql = "INSERT INTO notifications (
                id_utilisateur,
                type_notification,
                canal,
                sujet,
                message,
                statut,
                date_creation,
                date_envoi
            ) VALUES (
                :id_utilisateur,
                :type_notification,
                :canal,
                :sujet,
                :message,
                :statut,
                NOW(),
                :date_envoi
            )";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        'id_utilisateur' => $idUtilisateur,
        'type_notification' => $typeNotification,
        'canal' => $canal,
        'sujet' => $sujet,
        'message' => $message,
        'statut' => $statut,
        'date_envoi' => $dateEnvoi
    ]);
}