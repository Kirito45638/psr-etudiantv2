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

    $nomFichier = 'export_reservations_' . date('Y-m-d_H-i-s') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $nomFichier);

    $output = fopen('php://output', 'w');

    // BOM UTF-8 pour Excel
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // En-têtes CSV
    fputcsv($output, [
        'ID',
        'Nom',
        'Prénom',
        'Date repas',
        'Créneau',
        'Type repas',
        'Mode consommation',
        'Statut',
        'Points attribués',
        'Date création'
    ], ';');

    foreach ($reservations as $reservation) {
        fputcsv($output, [
            $reservation['id'],
            $reservation['nom'],
            $reservation['prenom'],
            $reservation['date_repas'],
            $reservation['creneau'],
            $reservation['type_repas'],
            $reservation['mode_consommation'],
            $reservation['statut'],
            $reservation['points_attribues'],
            $reservation['date_creation']
        ], ';');
    }

    fclose($output);
    exit();

} catch (PDOException $e) {
    die('Erreur lors de l’export Excel : ' . $e->getMessage());
}