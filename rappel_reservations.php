<?php
require_once __DIR__ . '/config/conf_server.php';
require_once __DIR__ . '/notifications/mail_helper.php';

date_default_timezone_set('Europe/Paris');

try {
    $maintenant = new DateTime('now');
    $aujourdhui = $maintenant->format('Y-m-d');

    $sql = "
        SELECT 
            r.id,
            r.daterepas,
            r.creneau,
            r.typeRepas,
            r.modeConsommation,
            r.rappel_envoye,
            u.nom,
            u.prenom,
            u.email
        FROM reservations r
        INNER JOIN utilisateurs u ON u.id = r.idUtilisateur
        WHERE r.daterepas = :daterepas
          AND r.statut = 'validee'
          AND r.rappel_envoye = 0
          AND u.email IS NOT NULL
          AND u.email <> ''
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute(['daterepas' => $aujourdhui]);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($reservations as $reservation) {
        $dateHeureRepas = DateTime::createFromFormat(
            'Y-m-d H:i:s',
            $reservation['daterepas'] . ' ' . $reservation['creneau']
        );

        if (!$dateHeureRepas) {
            continue;
        }

        $dateHeureRappel = clone $dateHeureRepas;
        $dateHeureRappel->modify('-1 hour');

        $diffSecondes = abs($maintenant->getTimestamp() - $dateHeureRappel->getTimestamp());

        if ($diffSecondes <= 300) {
            $nomComplet = trim(($reservation['prenom'] ?? '') . ' ' . ($reservation['nom'] ?? ''));
            if ($nomComplet === '') {
                $nomComplet = 'Utilisateur';
            }

            $sujet = 'Rappel : votre repas sera disponible dans 1 heure - PSR EREA';

            $messageHtml = '
                <html>
                <body style="font-family: Arial, sans-serif; color: #1f2937;">
                    <h2 style="color: #2563eb;">Rappel de réservation</h2>
                    <p>Bonjour ' . htmlspecialchars($nomComplet) . ',</p>
                    <p>Votre repas sera disponible dans <strong>1 heure</strong>.</p>
                    <ul>
                        <li><strong>Date :</strong> ' . htmlspecialchars($reservation['daterepas']) . '</li>
                        <li><strong>Créneau :</strong> ' . htmlspecialchars(substr($reservation['creneau'], 0, 5)) . '</li>
                        <li><strong>Type de repas :</strong> ' . htmlspecialchars($reservation['typeRepas']) . '</li>
                        <li><strong>Mode :</strong> ' . htmlspecialchars($reservation['modeConsommation']) . '</li>
                    </ul>
                    <p>Merci de penser à récupérer votre repas à l\'heure prévue.</p>
                    <p>Restaurant PSR EREA</p>
                </body>
                </html>
            ';

            $messageTexte = "Bonjour {$nomComplet},\n\n"
                . "Votre repas sera disponible dans 1 heure.\n"
                . "Date : {$reservation['daterepas']}\n"
                . "Créneau : " . substr($reservation['creneau'], 0, 5) . "\n"
                . "Type de repas : {$reservation['typeRepas']}\n"
                . "Mode : {$reservation['modeConsommation']}\n\n"
                . "Merci de penser à récupérer votre repas à l'heure prévue.\n"
                . "Restaurant PSR EREA";

            $mailEnvoye = envoyerEmailNotification(
                $reservation['email'],
                $sujet,
                $messageHtml,
                $messageTexte
            );

            if ($mailEnvoye) {
                $sqlUpdate = "UPDATE reservations SET rappel_envoye = :rappel_envoye WHERE id = :id";
                $stmtUpdate = $conn->prepare($sqlUpdate);
                $stmtUpdate->execute([
                    'rappel_envoye' => 1,
                    'id' => $reservation['id']
                ]);
            }
        }
    }

} catch (Exception $e) {
    file_put_contents(
        __DIR__ . '/logs_rappel.txt',
        '[' . date('Y-m-d H:i:s') . '] ' . $e->getMessage() . PHP_EOL,
        FILE_APPEND
    );
}