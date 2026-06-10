<?php
session_start();
require_once("config/conf_server.php");

// Vérifie que l'utilisateur est connecté
if (!isset($_SESSION["id"])) {
    header("Location: index.php");
    exit();
}

// Variables pour les messages
$message = "";
$erreur = "";

// Récupération des valeurs du formulaire
$date_repas = trim($_POST["date"] ?? '');
$creneau = trim($_POST["creneau"] ?? '');
$mode = trim($_POST["mode"] ?? '');

// Récupère l'id de l'utilisateur connecté
$id_utilisateur = $_SESSION["id"];

// Récupère le menu de Noël actif
$sqlMenuNoel = "SELECT * FROM repas_noel WHERE actif = 1 ORDER BY id DESC LIMIT 1";
$stmtMenuNoel = $conn->prepare($sqlMenuNoel);
$stmtMenuNoel->execute();
$menuNoel = $stmtMenuNoel->fetch(PDO::FETCH_ASSOC);

// Récupère les points de fidélité de l'utilisateur
$sqlPoints = "SELECT points_fidelite FROM utilisateurs WHERE id = :id";
$stmtPoints = $conn->prepare($sqlPoints);
$stmtPoints->execute([':id' => $id_utilisateur]);
$utilisateur = $stmtPoints->fetch(PDO::FETCH_ASSOC);
$pointsFidelite = $utilisateur ? (int)$utilisateur["points_fidelite"] : 0;

// Vérifie que l'utilisateur a au moins 200 points
if ($pointsFidelite < 200) {
    die("Accès refusé : le repas de Noël est réservé aux utilisateurs ayant au moins 200 points de fidélité.");
}

// Traitement du formulaire de réservation
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Vérifie que tous les champs sont remplis
    if (!empty($date_repas) && !empty($creneau) && !empty($mode)) {
        try {
            $conn->beginTransaction();

            // Récupère le quota du créneau choisi
            $sqlQuota = "SELECT quota_max
                         FROM creneaux
                         WHERE heure = :creneau
                         LIMIT 1";
            $stmtQuota = $conn->prepare($sqlQuota);
            $stmtQuota->execute([
                ':creneau' => $creneau
            ]);
            $quotaData = $stmtQuota->fetch(PDO::FETCH_ASSOC);

            if (!$quotaData) {
                throw new Exception("Créneau invalide.");
            }

            $quota_max = (int)$quotaData["quota_max"];

            // Vérifie si l'utilisateur a déjà réservé pour cette date
            $sqlDejaReserve = "SELECT COUNT(*)
                               FROM reservations
                               WHERE id_utilisateur = :id_utilisateur
                               AND date_repas = :date_repas
                               AND type_repas = 'noel'";
            $stmtDejaReserve = $conn->prepare($sqlDejaReserve);
            $stmtDejaReserve->execute([
                ':id_utilisateur' => $id_utilisateur,
                ':date_repas' => $date_repas
            ]);

            if ($stmtDejaReserve->fetchColumn() > 0) {
                throw new Exception("Vous avez déjà réservé le repas de Noël pour cette date.");
            }

            // Compte le nombre de réservations déjà prises sur ce créneau
            $sqlCount = "SELECT COUNT(*)
                         FROM reservations
                         WHERE date_repas = :date_repas
                         AND creneau = :creneau
                         AND type_repas = 'noel'
                         AND statut IN ('validee', 'en_attente')";
            $stmtCount = $conn->prepare($sqlCount);
            $stmtCount->execute([
                ':date_repas' => $date_repas,
                ':creneau' => $creneau
            ]);
            $nombre_reservations = (int)$stmtCount->fetchColumn();

            // Vérifie si le créneau est complet
            if ($nombre_reservations >= $quota_max) {
                throw new Exception("Ce créneau est complet pour le repas de Noël.");
            }

            // Insère la réservation du repas de Noël
            $sqlReservation = "INSERT INTO reservations
                (id_utilisateur, date_repas, creneau, type_repas, mode_consommation, statut, points_attribues, date_creation)
                VALUES (:id_utilisateur, :date_repas, :creneau, 'noel', :mode_consommation, 'validee', 10, NOW())";

            $stmtReservation = $conn->prepare($sqlReservation);
            $stmtReservation->execute([
                ':id_utilisateur' => $id_utilisateur,
                ':date_repas' => $date_repas,
                ':creneau' => $creneau,
                ':mode_consommation' => $mode
            ]);

            // Ajoute 10 points de fidélité à l'utilisateur
            $sqlMajPoints = "UPDATE utilisateurs
                             SET points_fidelite = points_fidelite + 10
                             WHERE id = :id_utilisateur";
            $stmtMajPoints = $conn->prepare($sqlMajPoints);
            $stmtMajPoints->execute([
                ':id_utilisateur' => $id_utilisateur
            ]);

            $conn->commit();
            $message = "Réservation du repas de Noël effectuée avec succès ! 10 points de fidélité ajoutés.";

            // Réinitialise le formulaire
            $date_repas = '';
            $creneau = '';
            $mode = '';

            // Recharge les points de fidélité après mise à jour
            $sqlPoints = "SELECT points_fidelite FROM utilisateurs WHERE id = :id";
            $stmtPoints = $conn->prepare($sqlPoints);
            $stmtPoints->execute([':id' => $id_utilisateur]);
            $utilisateur = $stmtPoints->fetch(PDO::FETCH_ASSOC);
            $pointsFidelite = $utilisateur ? (int)$utilisateur["points_fidelite"] : 0;

        } catch (Exception $e) {
            // Annule la transaction en cas d'erreur
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $erreur = "Erreur lors de la réservation : " . $e->getMessage();
        }
    } else {
        $erreur = "Veuillez remplir tous les champs.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Repas de Noël - Restaurant PSR EREA</title>
    <link rel="stylesheet" href="repasnoel.css">
</head>
<body>
    <!-- Bouton de retour vers l'accueil -->
    <div class="button-container">
        <button class="button-9" onclick="window.location.href='accueil.php'" type="button">Accueil</button>
    </div>

    <!-- Conteneur principal -->
    <div class="container">
        <!-- Titre de la page -->
        <h1>🎄 Repas de Noël 🎄</h1>

        <!-- Bloc d'information sur les points fidélité -->
        <div class="priority-notice">
            <h3>⭐ Priorité aux clients fidèles</h3>
            <p>Vous disposez actuellement de <?php echo $pointsFidelite; ?> point(s) de fidélité.</p>
        </div>

        <!-- Affichage du menu de Noël -->
        <?php if ($menuNoel) : ?>
            <div class="menu-card">
                <h2>Menu de Noël</h2>

                <div class="menu-item">
                    <h3>Entrée</h3>
                    <p><?php echo htmlspecialchars($menuNoel['entree']); ?></p>
                </div>

                <div class="menu-item">
                    <h3>Plat principal</h3>
                    <p><?php echo htmlspecialchars($menuNoel['plat']); ?></p>
                </div>

                <div class="menu-item">
                    <h3>Dessert</h3>
                    <p><?php echo htmlspecialchars($menuNoel['dessert']); ?></p>
                </div>

                <div class="menu-item">
                    <h3>Boisson</h3>
                    <p><?php echo htmlspecialchars($menuNoel['boisson']); ?></p>
                </div>
            </div>

        <?php else : ?>
            <div class="menu-card">
                <p>Aucun menu de Noël n'est disponible pour le moment.</p>
            </div>
        <?php endif; ?>

        <!-- Formulaire de réservation -->
        <form method="POST" action="">
            <div class="form-group">
                <label for="date">Date du repas de Noël :</label>
                <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($date_repas); ?>" required>
            </div>

            <div class="form-group">
                <label for="creneau">Créneau horaire :</label>
                <select id="creneau" name="creneau" required>
                    <option value="">Sélectionnez un créneau</option>
                    <option value="11:00:00" <?php echo ($creneau === '11:00:00') ? 'selected' : ''; ?>>11h00</option>
                    <option value="11:30:00" <?php echo ($creneau === '11:30:00') ? 'selected' : ''; ?>>11h30</option>
                    <option value="12:30:00" <?php echo ($creneau === '12:30:00') ? 'selected' : ''; ?>>12h30</option>
                </select>
            </div>

            <div class="form-group">
                <label>Mode de consommation :</label>
                <div class="radio-group">
                    <div class="radio-option">
                        <input type="radio" id="sur-place" name="mode" value="sur-place" <?php echo ($mode === 'sur-place') ? 'checked' : ''; ?> required>
                        <label for="sur-place">Sur place</label>
                    </div>
                    <div class="radio-option">
                        <input type="radio" id="emporter" name="mode" value="emporter" <?php echo ($mode === 'emporter') ? 'checked' : ''; ?>>
                        <label for="emporter">À emporter</label>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-submit">Réserver le repas de Noël</button>
        </form>

        <!-- Message de succès -->
        <?php if (!empty($message)) : ?>
            <div class="success-message show">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Message d'erreur -->
        <?php if (!empty($erreur)) : ?>
            <div class="success-message show" style="background-color:#f8d7da; color:#721c24;">
                <?php echo htmlspecialchars($erreur); ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Conteneurs des animations décoratives -->
    <div id="snowflakes"></div>
    <div id="stars"></div>

    <script>
        // Crée les flocons de neige animés
        function createSnowflakes() {
            const snowflakesContainer = document.getElementById('snowflakes');
            const snowflakeSymbols = ['❄', '❅', '❆', '✻', '✼', '✽', '✾', '✿'];

            for (let i = 0; i < 50; i++) {
                const snowflake = document.createElement('div');
                snowflake.className = 'snowflake';
                snowflake.textContent = snowflakeSymbols[Math.floor(Math.random() * snowflakeSymbols.length)];
                snowflake.style.left = Math.random() * 100 + '%';
                snowflake.style.animationDuration = (Math.random() * 3 + 2) + 's';
                snowflake.style.opacity = Math.random();
                snowflake.style.fontSize = (Math.random() * 10 + 10) + 'px';
                snowflakesContainer.appendChild(snowflake);
            }
        }

        // Crée les étoiles animées
        function createStars() {
            const starsContainer = document.getElementById('stars');

            for (let i = 0; i < 20; i++) {
                const star = document.createElement('div');
                star.className = 'star';
                star.textContent = '⭐';
                star.style.left = Math.random() * 100 + '%';
                star.style.top = Math.random() * 100 + '%';
                star.style.animationDelay = Math.random() * 2 + 's';
                starsContainer.appendChild(star);
            }
        }

        // Lance les animations au chargement
        createSnowflakes();
        createStars();
    </script>
</body>
</html>