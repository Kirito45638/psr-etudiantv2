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

$dateDebut = trim($_GET['date_debut'] ?? '');
$dateFin = trim($_GET['date_fin'] ?? '');

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

$reservations = [];
$totalReservations = 0;
$totalValidees = 0;
$totalAnnulees = 0;
$totalPoints = 0;

try {
    $sql = "SELECT 
                r.id,
                u.nom,
                u.prenom,
                r.date_repas,
                r.creneau,
                r.type_repas,
                r.mode_consommation,
                r.statut,
                r.points_attribues,
                r.date_creation
            FROM reservations r
            INNER JOIN utilisateurs u ON r.id_utilisateur = u.id
            $where
            ORDER BY r.date_repas DESC, r.creneau ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalReservations = count($reservations);

    foreach ($reservations as $reservation) {
        if ($reservation['statut'] === 'validee') {
            $totalValidees++;
        }

        if ($reservation['statut'] === 'annulee') {
            $totalAnnulees++;
        }

        $totalPoints += (int)$reservation['points_attribues'];
    }

} catch (PDOException $e) {
    die('Erreur lors de l’export PDF : ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Export PDF - Réservations</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #1f2937;
            margin: 30px;
            background-color: #ffffff;
        }

        h1 {
            text-align: center;
            color: #1d4ed8;
            margin-bottom: 10px;
        }

        .subtitle {
            text-align: center;
            color: #6b7280;
            margin-bottom: 25px;
        }

        .meta {
            margin-bottom: 20px;
            padding: 12px;
            background-color: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
        }

        .stats {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 25px;
        }

        .stat-box {
            flex: 1 1 200px;
            border: 1px solid #dbeafe;
            background-color: #f8fbff;
            border-radius: 8px;
            padding: 12px;
        }

        .stat-box strong {
            display: block;
            color: #1e3a8a;
            margin-bottom: 6px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        th, td {
            border: 1px solid #cbd5e1;
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #dbeafe;
            color: #1e3a8a;
        }

        tr:nth-child(even) {
            background-color: #f9fafb;
        }

        .actions {
            margin-top: 20px;
            text-align: center;
        }

        .print-btn {
            display: inline-block;
            padding: 10px 16px;
            background-color: #dc2626;
            color: #ffffff;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
        }

        @media print {
            .actions {
                display: none;
            }

            body {
                margin: 10mm;
            }
        }
    </style>
</head>
<body>

<h1>Export des réservations</h1>
<p class="subtitle">Document administratif - Restaurant PSR EREA</p>

<div class="meta">
    <p><strong>Date d’export :</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
    <p><strong>Filtre date début :</strong> <?php echo !empty($dateDebut) ? htmlspecialchars($dateDebut) : 'Aucun'; ?></p>
    <p><strong>Filtre date fin :</strong> <?php echo !empty($dateFin) ? htmlspecialchars($dateFin) : 'Aucun'; ?></p>
</div>

<div class="stats">
    <div class="stat-box">
        <strong>Total réservations</strong>
        <?php echo $totalReservations; ?>
    </div>

    <div class="stat-box">
        <strong>Réservations validées</strong>
        <?php echo $totalValidees; ?>
    </div>

    <div class="stat-box">
        <strong>Réservations annulées</strong>
        <?php echo $totalAnnulees; ?>
    </div>

    <div class="stat-box">
        <strong>Points attribués</strong>
        <?php echo $totalPoints; ?>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Nom</th>
            <th>Prénom</th>
            <th>Date repas</th>
            <th>Créneau</th>
            <th>Type repas</th>
            <th>Mode</th>
            <th>Statut</th>
            <th>Points</th>
            <th>Date création</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($reservations)) : ?>
            <?php foreach ($reservations as $reservation) : ?>
                <tr>
                    <td><?php echo htmlspecialchars($reservation['id']); ?></td>
                    <td><?php echo htmlspecialchars($reservation['nom']); ?></td>
                    <td><?php echo htmlspecialchars($reservation['prenom']); ?></td>
                    <td><?php echo htmlspecialchars($reservation['date_repas']); ?></td>
                    <td><?php echo htmlspecialchars($reservation['creneau']); ?></td>
                    <td><?php echo htmlspecialchars($reservation['type_repas']); ?></td>
                    <td><?php echo htmlspecialchars($reservation['mode_consommation']); ?></td>
                    <td><?php echo htmlspecialchars($reservation['statut']); ?></td>
                    <td><?php echo htmlspecialchars($reservation['points_attribues']); ?></td>
                    <td><?php echo htmlspecialchars($reservation['date_creation']); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else : ?>
            <tr>
                <td colspan="10">Aucune réservation trouvée pour cette période.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<div class="actions">
    <a href="#" class="print-btn" onclick="window.print(); return false;">Imprimer / Enregistrer en PDF</a>
</div>

</body>
</html>