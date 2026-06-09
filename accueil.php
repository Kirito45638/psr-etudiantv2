<?php
// Démarre la session utilisateur
session_start();

require_once("config/conf_server.php");

// Redirige vers la page de connexion si l'utilisateur n'est pas connecté
if (!isset($_SESSION["id"])) {
    header("Location: index.html");
    exit();
}

$id_utilisateur = $_SESSION["id"];

// Récupère les points de fidélité de l'utilisateur connecté
$sql = "SELECT points_fidelite FROM utilisateurs WHERE id = :id";
$stmt = $conn->prepare($sql);
$stmt->execute([':id' => $id_utilisateur]);
$utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);

$points_fidelite = $utilisateur ? (int)$utilisateur["points_fidelite"] : 0;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accueil - EREA Amilly</title>
    <link rel="stylesheet" href="accueil.css">
</head>
<body>
    <!-- Signature en bas de page -->
    <h3>~ Web Site Designed by Brayan ~</h3>
    <br>
    <!-- Affiche le nom de l'utilisateur connecté -->
    <div class="user-card">
    <h1>Bienvenue <?php echo htmlspecialchars($_SESSION["prenom"] . " " . $_SESSION["nom"]); ?> !</h1>
    <p>Vous êtes connecté en tant que : <?php echo htmlspecialchars($_SESSION["role"]); ?></p>
    <div class="points">Vos points de fidélité : <?php echo $points_fidelite; ?></div>
    </div>

    <br>
    <h2>Menu principal</h2>
    <br>

    <div class="button-container">
        <!-- Liens vers les principales fonctionnalités du site -->
        <button class="button-9" onclick="window.location.href='hebdo.php'" role="button">Menu de la Semaine</button>
        <button class="button-9" onclick="window.location.href='reservation.php'" role="button">Réservation</button>
        <button class="button-9" onclick="window.location.href='cartefidelite.php'" role="button">Carte fidélité</button>

        <?php if ($points_fidelite >= 200): ?>
            <button class="button-9" onclick="window.location.href='repasnoel.php'" role="button">Repas de Noël</button>
        <?php endif; ?>

        <?php if ($points_fidelite < 200): ?>
            <div class="info-box">
                <p>Le repas de Noël est accessible à partir de 200 points de fidélité.</p>
            </div>
        <?php endif; ?>

        <button class="button-9" onclick="window.location.href='logout.php'" role="button">Déconnexion</button>

        <!-- Affiche les options d'administration uniquement pour les admins -->
        <?php if ($_SESSION["role"] === "admin") : ?>
            <button class="button-9" onclick="window.location.href='admin/gestion_comptes.php'" role="button">
                Gestion des comptes
            </button>

            <button class="button-9" onclick="window.location.href='admin/planning_presence.php'" role="button">
                Planning de présence
            </button>

            <button class="button-9" onclick="window.location.href='admin/dashboard.php'" role="button">
                Dashboard
            </button>

            <button class="button-9" onclick="window.location.href='notifications/notifications.php'" role="button">
                Notifications
            </button>
            <!--<button class="button-9 button-danger" onclick="window.location.href='admin/reset_password.php'" role="button">
                Réinitialiser mot de passe
            </button>-->
        <?php endif; ?>
    </div>

</body>
</html> 