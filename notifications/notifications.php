<?php
session_start();
require_once("../config/conf_server.php");
require_once("mail_helper.php");

// Vérifie que l'utilisateur est connecté
if (!isset($_SESSION["id"])) {
    header("Location: index.php");
    exit();
}

// Vérifie que l'utilisateur est administrateur
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    die("Accès refusé.");
}

$message = "";
$erreur = "";
$notificationsEnvoyees = 0;
$notificationsEchec = 0;

// Envoi des rappels pour les réservations du lendemain
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? '') === 'envoyer_rappels') {
    try {
        $dateDemain = (new DateTime('tomorrow'))->format('Y-m-d');

        $sqlReservations = "SELECT 
                                r.id,
                                r.id_utilisateur,
                                r.date_repas,
                                r.creneau,
                                r.type_repas,
                                r.mode_consommation,
                                r.statut,
                                u.nom,
                                u.prenom,
                                u.email
                            FROM reservations r
                            INNER JOIN utilisateurs u ON u.id = r.id_utilisateur
                            WHERE r.date_repas = :date_repas
                              AND r.statut IN ('validee', 'en_attente')
                              AND u.email IS NOT NULL
                              AND u.email <> ''
                            ORDER BY r.creneau ASC, u.nom ASC, u.prenom ASC";

        $stmtReservations = $conn->prepare($sqlReservations);
        $stmtReservations->execute([
            ':date_repas' => $dateDemain
        ]);

        $reservations = $stmtReservations->fetchAll(PDO::FETCH_ASSOC);

        if (!$reservations) {
            $message = "Aucune réservation à notifier pour le lendemain.";
        } else {
            foreach ($reservations as $reservation) {
                $utilisateur = [
                    'id' => $reservation['id_utilisateur'],
                    'nom' => $reservation['nom'],
                    'prenom' => $reservation['prenom'],
                    'email' => $reservation['email']
                ];

                $reservationData = [
                    'daterepas' => $reservation['date_repas'],
                    'creneau' => $reservation['creneau'],
                    'typerepas' => $reservation['type_repas'],
                    'modeconsommation' => $reservation['mode_consommation']
                ];

                $mailData = construireMessageRappelReservation($reservationData, $utilisateur);

                $emailEnvoye = envoyerEmailNotification(
                    $utilisateur['email'],
                    $mailData['sujet'],
                    $mailData['html'],
                    $mailData['texte']
                );

                enregistrerNotification(
                    $conn,
                    (int)$utilisateur['id'],
                    'rappel',
                    'email',
                    $mailData['sujet'],
                    $mailData['texte'],
                    $emailEnvoye ? 'envoye' : 'echec'
                );

                if ($emailEnvoye) {
                    $notificationsEnvoyees++;
                } else {
                    $notificationsEchec++;
                }
            }

            $message = "Envoi terminé : {$notificationsEnvoyees} rappel(s) envoyé(s), {$notificationsEchec} échec(s).";
        }
    } catch (Exception $e) {
        $erreur = "Erreur lors de l'envoi des rappels : " . $e->getMessage();
    }
}

// Récupère les 20 dernières notifications
$sqlNotifications = "SELECT 
                        n.id,
                        n.type_notification,
                        n.canal,
                        n.sujet,
                        n.message,
                        n.statut,
                        n.date_creation,
                        n.date_envoi,
                        u.nom,
                        u.prenom,
                        u.email
                    FROM notifications n
                    INNER JOIN utilisateurs u ON u.id = n.id_utilisateur
                    ORDER BY n.id DESC
                    LIMIT 20";

$stmtNotifications = $conn->query($sqlNotifications);
$listeNotifications = $stmtNotifications->fetchAll(PDO::FETCH_ASSOC);

// Récupère les réservations du lendemain pour aperçu
$dateDemainAffichage = (new DateTime('tomorrow'))->format('Y-m-d');

$sqlApercu = "SELECT 
                r.date_repas,
                r.creneau,
                r.type_repas,
                r.mode_consommation,
                r.statut,
                u.nom,
                u.prenom,
                u.email
              FROM reservations r
              INNER JOIN utilisateurs u ON u.id = r.id_utilisateur
              WHERE r.date_repas = :date_repas
                AND r.statut IN ('validee', 'en_attente')
              ORDER BY r.creneau ASC, u.nom ASC, u.prenom ASC";

$stmtApercu = $conn->prepare($sqlApercu);
$stmtApercu->execute([
    ':date_repas' => $dateDemainAffichage
]);
$apercuReservations = $stmtApercu->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Administration PSR EREA</title>
    <link rel="stylesheet" href="notifications.css">
</head>
<body>
    <div class="page-wrapper">
        <a class="back-link" href="../accueil.php">← Retour à l'accueil</a>

        <h1>Gestion des notifications</h1>

        <?php if (!empty($message)) : ?>
            <div class="message-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if (!empty($erreur)) : ?>
            <div class="message-error"><?php echo htmlspecialchars($erreur); ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>Envoyer les rappels</h2>
            <p class="small-text">
                Cette action envoie un email de rappel à tous les utilisateurs ayant une réservation valide ou en attente pour le lendemain.
            </p>

            <form method="POST" action="">
                <input type="hidden" name="action" value="envoyer_rappels">
                <button type="submit" class="btn">Envoyer les rappels du lendemain</button>
            </form>
        </div>

        <div class="card">
            <h2>Aperçu des réservations du lendemain (<?php echo htmlspecialchars($dateDemainAffichage); ?>)</h2>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Utilisateur</th>
                            <th>Email</th>
                            <th>Date</th>
                            <th>Créneau</th>
                            <th>Type</th>
                            <th>Mode</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($apercuReservations)) : ?>
                            <?php foreach ($apercuReservations as $reservation) : ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($reservation['prenom'] . ' ' . $reservation['nom']); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['email']); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['date_repas']); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['creneau']); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['type_repas']); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['mode_consommation']); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['statut']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="7">Aucune réservation prévue pour le lendemain.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <h2>Historique récent des notifications</h2>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Utilisateur</th>
                            <th>Email</th>
                            <th>Type</th>
                            <th>Canal</th>
                            <th>Sujet</th>
                            <th>Statut</th>
                            <th>Date création</th>
                            <th>Date envoi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($listeNotifications)) : ?>
                            <?php foreach ($listeNotifications as $notification) : ?>
                                <tr>
                                    <td><?php echo (int)$notification['id']; ?></td>
                                    <td><?php echo htmlspecialchars($notification['prenom'] . ' ' . $notification['nom']); ?></td>
                                    <td><?php echo htmlspecialchars($notification['email']); ?></td>
                                    <td>
                                        <?php if ($notification['type_notification'] === 'rappel') : ?>
                                            <span class="badge badge-rappel">rappel</span>
                                        <?php else : ?>
                                            <span class="badge badge-confirmation"><?php echo htmlspecialchars($notification['type_notification']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($notification['canal']); ?></td>
                                    <td><?php echo htmlspecialchars($notification['sujet']); ?></td>
                                    <td>
                                        <?php if ($notification['statut'] === 'envoye') : ?>
                                            <span class="badge badge-envoye">envoyé</span>
                                        <?php else : ?>
                                            <span class="badge badge-echec"><?php echo htmlspecialchars($notification['statut']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars((string)$notification['date_creation']); ?></td>
                                    <td><?php echo htmlspecialchars((string)$notification['date_envoi']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="9">Aucune notification enregistrée.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>