<?php
session_start();
require_once '../config/conf_server.php';

// Vérifie que l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../index.php');
    exit();
}

// Vérifie que l'utilisateur est administrateur
if ($_SESSION['role'] !== 'admin') {
    die('Accès refusé.');
}

$erreur = '';

// Filtres de dates
$dateDebut = trim($_GET['date_debut'] ?? '');
$dateFin = trim($_GET['date_fin'] ?? '');

// Construction du filtre SQL principal
$where = " WHERE 1=1 ";
$params = [];

if (!empty($dateDebut)) {
    $where .= " AND r.date_repas >= :date_debut ";
    $params['date_debut'] = $dateDebut;
}

if (!empty($dateFin)) {
    $where .= " AND r.date_repas <= :date_fin ";
    $params['date_fin'] = $dateFin;
}

// Variables statistiques
$totalReservations = 0;
$totalValidees = 0;
$totalEnAttente = 0;
$totalAnnulees = 0;
$totalPointsDistribues = 0;
$tauxValidation = 0;
$estimationGaspillage = 0;
$pourcentageGaspillage = 0;

$reservationsParCreneau = [];
$reservationsParType = [];
$reservationsParMode = [];
$reservationsRecentes = [];
$remplissageCreneaux = [];

// Construit la query string pour les exports
$queryExport = http_build_query([
    'date_debut' => $dateDebut,
    'date_fin' => $dateFin
]);

function badgeStatutClass(string $statut): string
{
    $statut = strtolower(trim($statut));

    return match ($statut) {
        'validee', 'validée' => 'badge badge-success',
        'en_attente', 'en attente' => 'badge badge-warning',
        'annulee', 'annulée' => 'badge badge-danger',
        default => 'badge badge-neutral',
    };
}

function formatStatut(string $statut): string
{
    $statut = strtolower(trim($statut));

    return match ($statut) {
        'validee', 'validée' => 'Validée',
        'en_attente', 'en attente' => 'En attente',
        'annulee', 'annulée' => 'Annulée',
        default => ucfirst($statut),
    };
}

try {
    // Total des réservations
    $sqlTotal = "SELECT COUNT(*) 
                 FROM reservations r
                 $where";
    $stmtTotal = $conn->prepare($sqlTotal);
    $stmtTotal->execute($params);
    $totalReservations = (int)$stmtTotal->fetchColumn();

    // Réservations validées
    $sqlValidees = "SELECT COUNT(*) 
                    FROM reservations r
                    $where
                    AND r.statut = 'validee'";
    $stmtValidees = $conn->prepare($sqlValidees);
    $stmtValidees->execute($params);
    $totalValidees = (int)$stmtValidees->fetchColumn();

    // Réservations en attente
    $sqlEnAttente = "SELECT COUNT(*) 
                     FROM reservations r
                     $where
                     AND r.statut = 'en_attente'";
    $stmtEnAttente = $conn->prepare($sqlEnAttente);
    $stmtEnAttente->execute($params);
    $totalEnAttente = (int)$stmtEnAttente->fetchColumn();

    // Réservations annulées
    $sqlAnnulees = "SELECT COUNT(*) 
                    FROM reservations r
                    $where
                    AND r.statut = 'annulee'";
    $stmtAnnulees = $conn->prepare($sqlAnnulees);
    $stmtAnnulees->execute($params);
    $totalAnnulees = (int)$stmtAnnulees->fetchColumn();

    // Total des points attribués
    $sqlPoints = "SELECT COALESCE(SUM(r.points_attribues), 0) 
                  FROM reservations r
                  $where";
    $stmtPoints = $conn->prepare($sqlPoints);
    $stmtPoints->execute($params);
    $totalPointsDistribues = (int)$stmtPoints->fetchColumn();

    // Taux de validation
    if ($totalReservations > 0) {
        $tauxValidation = round(($totalValidees / $totalReservations) * 100, 1);
    }

    // Estimation simple du gaspillage : nombre de réservations annulées
    $estimationGaspillage = $totalAnnulees;

    if ($totalReservations > 0) {
        $pourcentageGaspillage = round(($estimationGaspillage / $totalReservations) * 100, 1);
    }

    // Répartition par créneau
    $sqlCreneaux = "SELECT r.creneau, COUNT(*) AS total
                    FROM reservations r
                    $where
                    GROUP BY r.creneau
                    ORDER BY r.creneau ASC";
    $stmtCreneaux = $conn->prepare($sqlCreneaux);
    $stmtCreneaux->execute($params);
    $reservationsParCreneau = $stmtCreneaux->fetchAll(PDO::FETCH_ASSOC);

    // Répartition par type de repas
    $sqlTypes = "SELECT r.type_repas, COUNT(*) AS total
                 FROM reservations r
                 $where
                 GROUP BY r.type_repas
                 ORDER BY total DESC";
    $stmtTypes = $conn->prepare($sqlTypes);
    $stmtTypes->execute($params);
    $reservationsParType = $stmtTypes->fetchAll(PDO::FETCH_ASSOC);

    // Répartition par mode de consommation
    $sqlModes = "SELECT r.mode_consommation, COUNT(*) AS total
                 FROM reservations r
                 $where
                 GROUP BY r.mode_consommation
                 ORDER BY total DESC";
    $stmtModes = $conn->prepare($sqlModes);
    $stmtModes->execute($params);
    $reservationsParMode = $stmtModes->fetchAll(PDO::FETCH_ASSOC);

    // 10 dernières réservations
    $sqlRecentes = "SELECT 
                        r.date_repas,
                        r.creneau,
                        r.type_repas,
                        r.mode_consommation,
                        r.statut,
                        u.nom,
                        u.prenom
                    FROM reservations r
                    INNER JOIN utilisateurs u ON r.id_utilisateur = u.id
                    $where
                    ORDER BY r.date_creation DESC
                    LIMIT 10";
    $stmtRecentes = $conn->prepare($sqlRecentes);
    $stmtRecentes->execute($params);
    $reservationsRecentes = $stmtRecentes->fetchAll(PDO::FETCH_ASSOC);

    // Taux de remplissage des créneaux
    $sqlRemplissage = "
        SELECT 
            c.heure AS creneau,
            c.quota_max,
            COUNT(r.id) AS nb_reservations
        FROM creneaux c
        LEFT JOIN reservations r 
            ON r.creneau = c.heure
            AND r.statut IN ('validee', 'en_attente')
    ";

    $conditionsRemplissage = [];
    $paramsRemplissage = [];

    if (!empty($dateDebut)) {
        $conditionsRemplissage[] = "r.date_repas >= :date_debut";
        $paramsRemplissage['date_debut'] = $dateDebut;
    }

    if (!empty($dateFin)) {
        $conditionsRemplissage[] = "r.date_repas <= :date_fin";
        $paramsRemplissage['date_fin'] = $dateFin;
    }

    if (!empty($conditionsRemplissage)) {
        $sqlRemplissage .= " WHERE " . implode(' AND ', $conditionsRemplissage);
    }

    $sqlRemplissage .= "
        GROUP BY c.heure, c.quota_max
        ORDER BY c.heure ASC
    ";

    $stmtRemplissage = $conn->prepare($sqlRemplissage);
    $stmtRemplissage->execute($paramsRemplissage);
    $remplissageCreneaux = $stmtRemplissage->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $erreur = "Erreur lors du chargement du tableau de bord : " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard administrateur</title>
    <link rel="stylesheet" href="../accueil.css">
    <link rel="stylesheet" href="dashboard.css">
</head>
<body>

<div class="page-wrapper">
    <h1>Tableau de bord administrateur</h1>
    <p class="subtitle">Suivi des réservations, fréquentation, gaspillage estimé et exports.</p>

    <?php if (!empty($erreur)) : ?>
        <p class="message-error"><?php echo htmlspecialchars($erreur); ?></p>
    <?php endif; ?>

    <div class="filter-box">
        <form method="GET" action="">
            <label for="date_debut">Date début :</label>
            <input type="date" id="date_debut" name="date_debut" value="<?php echo htmlspecialchars($dateDebut); ?>">

            <label for="date_fin">Date fin :</label>
            <input type="date" id="date_fin" name="date_fin" value="<?php echo htmlspecialchars($dateFin); ?>">

            <button type="submit" class="filter-btn">Filtrer</button>
            <a class="reset-btn" href="dashboard.php">Réinitialiser</a>
        </form>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <h2>Total réservations</h2>
            <p><?php echo $totalReservations; ?></p>
            <span class="stat-note">Toutes réservations sur la période</span>
        </div>

        <div class="stat-card">
            <h2>Réservations validées</h2>
            <p><?php echo $totalValidees; ?></p>
            <span class="stat-note">Repas confirmés</span>
        </div>

        <div class="stat-card">
            <h2>En attente</h2>
            <p><?php echo $totalEnAttente; ?></p>
            <span class="stat-note">À traiter</span>
        </div>

        <div class="stat-card">
            <h2>Réservations annulées</h2>
            <p><?php echo $totalAnnulees; ?></p>
            <span class="stat-note">Impact potentiel sur la production</span>
        </div>

        <div class="stat-card">
            <h2>Taux de validation</h2>
            <p><?php echo $tauxValidation; ?>%</p>
            <span class="stat-note">Part des réservations validées</span>
        </div>

        <div class="stat-card">
            <h2>Points distribués</h2>
            <p><?php echo $totalPointsDistribues; ?></p>
            <span class="stat-note">Fidélité attribuée</span>
        </div>

        <div class="stat-card stat-card-warning">
            <h2>Gaspillage estimé</h2>
            <p><?php echo $estimationGaspillage; ?></p>
            <span class="stat-note"><?php echo $pourcentageGaspillage; ?>% des réservations</span>
        </div>
    </div>

    <div class="table-wrapper">
        <h2>Répartition par créneau</h2>
        <table>
            <thead>
                <tr>
                    <th>Créneau</th>
                    <th>Nombre de réservations</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($reservationsParCreneau)) : ?>
                    <?php foreach ($reservationsParCreneau as $ligne) : ?>
                        <tr>
                            <td><?php echo htmlspecialchars($ligne['creneau']); ?></td>
                            <td><?php echo (int)$ligne['total']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="2">Aucune donnée disponible.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="table-wrapper">
        <h2>Taux de remplissage des créneaux</h2>
        <table>
            <thead>
                <tr>
                    <th>Créneau</th>
                    <th>Quota max</th>
                    <th>Réservations</th>
                    <th>Taux de remplissage</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($remplissageCreneaux)) : ?>
                    <?php foreach ($remplissageCreneaux as $ligne) : ?>
                        <?php
                        $quotaMax = (int)$ligne['quota_max'];
                        $nbReservations = (int)$ligne['nb_reservations'];
                        $tauxRemplissage = $quotaMax > 0 ? round(($nbReservations / $quotaMax) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($ligne['creneau']); ?></td>
                            <td><?php echo $quotaMax; ?></td>
                            <td><?php echo $nbReservations; ?></td>
                            <td><?php echo $tauxRemplissage; ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="4">Aucune donnée disponible.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="table-wrapper">
        <h2>Répartition par type de repas</h2>
        <table>
            <thead>
                <tr>
                    <th>Type de repas</th>
                    <th>Nombre</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($reservationsParType)) : ?>
                    <?php foreach ($reservationsParType as $ligne) : ?>
                        <tr>
                            <td><?php echo htmlspecialchars($ligne['type_repas']); ?></td>
                            <td><?php echo (int)$ligne['total']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="2">Aucune donnée disponible.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="table-wrapper">
        <h2>Répartition par mode de consommation</h2>
        <table>
            <thead>
                <tr>
                    <th>Mode</th>
                    <th>Nombre</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($reservationsParMode)) : ?>
                    <?php foreach ($reservationsParMode as $ligne) : ?>
                        <tr>
                            <td><?php echo htmlspecialchars($ligne['mode_consommation']); ?></td>
                            <td><?php echo (int)$ligne['total']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="2">Aucune donnée disponible.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="table-wrapper">
        <h2>10 dernières réservations</h2>
        <table>
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Prénom</th>
                    <th>Date</th>
                    <th>Créneau</th>
                    <th>Type</th>
                    <th>Mode</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($reservationsRecentes)) : ?>
                    <?php foreach ($reservationsRecentes as $reservation) : ?>
                        <tr>
                            <td><?php echo htmlspecialchars($reservation['nom']); ?></td>
                            <td><?php echo htmlspecialchars($reservation['prenom']); ?></td>
                            <td><?php echo htmlspecialchars($reservation['date_repas']); ?></td>
                            <td><?php echo htmlspecialchars($reservation['creneau']); ?></td>
                            <td><?php echo htmlspecialchars($reservation['type_repas']); ?></td>
                            <td><?php echo htmlspecialchars($reservation['mode_consommation']); ?></td>
                            <td>
                                <span class="<?php echo badgeStatutClass($reservation['statut']); ?>">
                                    <?php echo htmlspecialchars(formatStatut($reservation['statut'])); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="7">Aucune réservation trouvée.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="info-panel">
        <h2>Lecture des indicateurs</h2>
        <p><strong>Gaspillage estimé :</strong> correspond ici au nombre de réservations annulées sur la période sélectionnée. C’est une estimation simple pour le suivi administratif.</p>
        <p><strong>Taux de remplissage :</strong> compare le nombre de réservations actives à la capacité maximale des créneaux définie dans la table <code>creneaux</code>.</p>
    </div>

    <div class="bottom-actions">
        <a class="back-link" href="../accueil.php">Retour à l'accueil</a>
        <a class="export-btn export-excel" href="export_excel.php?<?php echo $queryExport; ?>">Exporter en Excel</a>
        <a class="export-btn export-pdf" href="export_pdf.php?<?php echo $queryExport; ?>" target="_blank">Exporter en PDF</a>
    </div>
</div>

</body>
</html>