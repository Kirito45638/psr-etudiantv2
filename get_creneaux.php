<?php
// Démarre la session et charge la connexion à la base de données
session_start();
require_once("config/conf_server.php");

// Indique que la réponse sera envoyée au format JSON
header('Content-Type: application/json; charset=utf-8');

// Vérifie que l'utilisateur est bien connecté
if (!isset($_SESSION["id"])) {
    echo json_encode([
        "success" => false,
        "message" => "Non autorisé."
    ]);
    exit();
}

// Récupère et nettoie la date envoyée en GET
$date_repas = trim($_GET["date"] ?? '');

if ($date_repas === '') {
    echo json_encode([
        "success" => false,
        "message" => "Date manquante."
    ]);
    exit();
}

try {
    // Récupère tous les créneaux disponibles avec leur quota maximum
    $sql = "SELECT heure, quota_max FROM creneaux ORDER BY heure ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $creneaux = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $resultats = [];

    foreach ($creneaux as $creneau) {
        $heure = $creneau["heure"];
        $quota_max = (int) $creneau["quota_max"];

        // Compte le nombre de réservations déjà enregistrées pour ce créneau
        $sqlCount = "SELECT COUNT(*)
                     FROM reservations
                     WHERE date_repas = :date_repas
                     AND creneau = :creneau
                     AND statut IN ('validee', 'en_attente')";
        $stmtCount = $conn->prepare($sqlCount);
        $stmtCount->execute([
            ':date_repas' => $date_repas,
            ':creneau'    => $heure
        ]);

        $reservees = (int) $stmtCount->fetchColumn();
        $restantes = $quota_max - $reservees;

        // Empêche d'avoir une valeur négative si le quota est dépassé
        if ($restantes < 0) {
            $restantes = 0;
        }

        // Prépare les données qui seront envoyées au JavaScript
        $resultats[] = [
            "heure"          => $heure,
            "heure_affichee" => substr($heure, 0, 5),
            "quota_max"      => $quota_max,
            "reservees"      => $reservees,
            "restantes"      => $restantes,
            "complet"        => ($restantes <= 0)
        ];
    }

    // Renvoie la liste des créneaux au format JSON
    echo json_encode([
        "success"  => true,
        "creneaux" => $resultats
    ]);
} catch (Exception $e) {
    // Renvoie une erreur générique en cas de problème
    echo json_encode([
        "success" => false,
        "message" => "Erreur lors du chargement des créneaux."
    ]);
}