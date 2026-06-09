<?php
// Démarre la session et charge la connexion à la base de données
session_start();
require_once("config/conf_server.php");

// Redirige l'utilisateur s'il n'est pas connecté
if (!isset($_SESSION["id"])) {
    header("Location: index.html");
    exit();
}

// Initialise les variables utiles pour l'affichage
$id_utilisateur = $_SESSION["id"];
$nom_affichage = "Utilisateur";
$points = 0;
$erreur = "";
$message_fidelite = "";
$recompenses_obtenues = [];

try {
    // Récupère les informations de l'utilisateur connecté
    $sql = "SELECT nom, prenom, points_fidelite 
            FROM utilisateurs 
            WHERE id = :id 
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':id' => $id_utilisateur
    ]);

    $utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($utilisateur) {
        $nom_affichage = $utilisateur["prenom"] . " " . $utilisateur["nom"];
        $points = (int)$utilisateur["points_fidelite"];

        // Détermine les récompenses déjà débloquées selon le nombre de points
        if ($points >= 50) {
            $recompenses_obtenues[] = "Boisson offerte débloquée";
        }

        if ($points >= 100) {
            $recompenses_obtenues[] = "Dessert offert débloqué";
        }

        if ($points >= 200) {
            $recompenses_obtenues[] = "Repas offert débloqué";
            $recompenses_obtenues[] = "Statut client fidèle activé";
        }

        // Génère un message pour indiquer la prochaine récompense à atteindre
        if ($points < 50) {
            $points_restants = 50 - $points;
            $message_fidelite = "Il vous manque $points_restants points pour obtenir une boisson offerte.";
        } elseif ($points < 100) {
            $points_restants = 100 - $points;
            $message_fidelite = "Boisson offerte débloquée. Il vous manque $points_restants points pour obtenir un dessert offert.";
        } elseif ($points < 200) {
            $points_restants = 200 - $points;
            $message_fidelite = "Boisson et dessert débloqués. Il vous manque $points_restants points pour obtenir un repas offert.";
        } else {
            $message_fidelite = "Vous avez débloqué toutes les récompenses. Vous êtes client fidèle et prioritaire pour les repas de Noël et les événements spéciaux.";
        }

    } else {
        $erreur = "Utilisateur introuvable.";
    }
} catch (Exception $e) {
    $erreur = "Erreur lors du chargement de la carte fidélité.";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carte Fidélité - Restaurant PSR EREA</title>
    <link rel="stylesheet" href="cartefidelite.css">
</head>
<body>

    <div class="button-container">
        <button class="button-9" onclick="window.location.href='accueil.php'" role="button">Accueil</button>
    </div>

    <div class="container">
        <h1>Ma Carte Fidélité</h1>

        <!-- Affiche un message si une erreur est survenue -->
        <?php if (!empty($erreur)) : ?>
            <div class="error-message" style="background-color:#f8d7da; color:#721c24; padding:15px; border-radius:5px; margin-bottom:20px;">
                <?php echo htmlspecialchars($erreur); ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <div class="card-title">Restaurant PSR EREA</div>
                <div id="username-display"><?php echo htmlspecialchars($nom_affichage); ?></div>
            </div>
            
            <div class="points-display">
                <div class="points-label">Vos points de fidélité</div>
                <div class="points-number" id="points-display"><?php echo $points; ?></div>
                <div class="points-label">points</div>
            </div>

            <!-- Affiche le message lié à la progression fidélité -->
            <div class="fidelite-message">
                <?php echo htmlspecialchars($message_fidelite); ?>
            </div>

            <!-- Affiche la liste des récompenses déjà obtenues -->
            <?php if (!empty($recompenses_obtenues)) : ?>
                <div class="recompenses-obtenues">
                    <h3>Récompenses débloquées</h3>
                    <ul>
                        <?php foreach ($recompenses_obtenues as $recompense) : ?>
                            <li><?php echo htmlspecialchars($recompense); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="info-section">
                <div class="info-item">
                    <h3>Comment gagner des points ?</h3>
                    <p>Chaque repas réservé et validé vous rapporte 10 points de fidélité.</p>
                </div>
                <div class="info-item">
                    <h3>Avantages de la carte</h3>
                    <p>Les clients fidèles bénéficient de priorités pour les repas spéciaux et peuvent obtenir des récompenses.</p>
                </div>
            </div>
        </div>

        <div class="advantages">
            <h2>Avantages et Récompenses</h2>
            
            <div class="advantage-item">
                <h3>🎁 50 points</h3>
                <p>Boisson offerte</p>
            </div>
            
            <div class="advantage-item">
                <h3>🎁 100 points</h3>
                <p>Dessert offert</p>
            </div>
            
            <div class="advantage-item">
                <h3>🎁 200 points</h3>
                <p>Repas offert</p>
            </div>
            
            <div class="advantage-item">
                <h3>⭐ Client fidèle (&gt; 200 pts)</h3>
                <p>Priorité pour les repas de Noël et événements spéciaux</p>
            </div>
        </div>
    </div>

</body>
</html>