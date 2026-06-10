<?php
session_start();
require_once("../config/conf_server.php");

// Vérifie que l'utilisateur est connecté
if (!isset($_SESSION["id"])) {
    header("Location: ../index.php");
    exit();
}

// Vérifie que l'utilisateur connecté est administrateur
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    die("Accès refusé.");
}

// Variables pour afficher les messages
$message = "";
$erreur = "";
$selected_login = trim($_GET["login"] ?? "");

// Récupération de tous les utilisateurs pour la liste déroulante
$sqlUsers = "SELECT id, nom, prenom, login, role
             FROM utilisateurs
             ORDER BY nom ASC, prenom ASC";
$stmtUsers = $conn->prepare($sqlUsers);
$stmtUsers->execute();
$utilisateurs = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

// Vérifie si le login reçu en GET existe bien
if ($selected_login !== "") {
    $loginExiste = false;

    foreach ($utilisateurs as $u) {
        if ($u["login"] === $selected_login) {
            $loginExiste = true;
            break;
        }
    }

    // Si le login n'existe pas, on annule la sélection
    if (!$loginExiste) {
        $selected_login = "";
        $erreur = "Utilisateur invalide pour la réinitialisation.";
    }
}

// Si le formulaire est envoyé
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Récupération et nettoyage des champs
    $selected_login = trim($_POST["login"] ?? "");
    $nouveau_mdp = trim($_POST["nouveau_mdp"] ?? "");
    $confirmation_mdp = trim($_POST["confirmation_mdp"] ?? "");

    // Vérifie que tous les champs sont remplis
    if (empty($selected_login) || empty($nouveau_mdp) || empty($confirmation_mdp)) {
        $erreur = "Veuillez remplir tous les champs.";
    }
    // Vérifie la longueur minimale du mot de passe
    elseif (strlen($nouveau_mdp) < 8) {
        $erreur = "Le mot de passe doit contenir au moins 8 caractères.";
    }
    // Vérifie que les deux mots de passe sont identiques
    elseif ($nouveau_mdp !== $confirmation_mdp) {
        $erreur = "Les mots de passe ne correspondent pas.";
    } else {
        // Vérifie que l'utilisateur existe bien
        $sqlCheck = "SELECT id, nom, prenom, login
                     FROM utilisateurs
                     WHERE login = :login
                     LIMIT 1";
        $stmtCheck = $conn->prepare($sqlCheck);
        $stmtCheck->bindParam(':login', $selected_login, PDO::PARAM_STR);
        $stmtCheck->execute();

        $utilisateur = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($utilisateur) {
            try {
                // Début de la transaction
                $conn->beginTransaction();

                // Hash du nouveau mot de passe
                $hash = password_hash($nouveau_mdp, PASSWORD_DEFAULT);

                // Mise à jour du mot de passe en base
                $sqlUpdate = "UPDATE utilisateurs
                              SET mot_de_passe = :mot_de_passe
                              WHERE login = :login";
                $stmtUpdate = $conn->prepare($sqlUpdate);
                $stmtUpdate->bindParam(':mot_de_passe', $hash, PDO::PARAM_STR);
                $stmtUpdate->bindParam(':login', $selected_login, PDO::PARAM_STR);
                $stmtUpdate->execute();

                // Enregistrement de l'action dans les logs admin
                $sqlLog = "INSERT INTO logs_admin (admin_id, action, utilisateur_cible)
                           VALUES (:admin_id, :action, :utilisateur_cible)";
                $stmtLog = $conn->prepare($sqlLog);

                $action = "Réinitialisation mot de passe";
                $stmtLog->bindParam(':admin_id', $_SESSION["id"], PDO::PARAM_INT);
                $stmtLog->bindParam(':action', $action, PDO::PARAM_STR);
                $stmtLog->bindParam(':utilisateur_cible', $selected_login, PDO::PARAM_STR);
                $stmtLog->execute();

                // Validation de la transaction
                $conn->commit();

                // Message de succès
                $message = "Mot de passe réinitialisé pour " . htmlspecialchars($utilisateur["prenom"] . " " . $utilisateur["nom"]) . ".";
                $selected_login = "";
            } catch (Exception $e) {
                // Annule la transaction en cas d'erreur
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                $erreur = "Erreur lors de la réinitialisation du mot de passe.";
            }
        } else {
            // Message si l'utilisateur n'existe pas
            $erreur = "Utilisateur introuvable.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialiser un mot de passe</title>
    <link rel="stylesheet" href="reset_password.css">
</head>
<body>

    <!-- Titre général de la page admin -->
    <h3>~ Administration EREA Amilly ~</h3>

    <!-- Carte principale -->
    <div class="page-card">
        <!-- Titre de la page -->
        <h1>Réinitialiser un mot de passe</h1>
        <p class="subtitle">Réservé aux administrateurs</p>

        <!-- Message de succès -->
        <?php if (!empty($message)) : ?>
            <div class="message-success">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Message d'erreur -->
        <?php if (!empty($erreur)) : ?>
            <div class="message-error">
                <?php echo htmlspecialchars($erreur); ?>
            </div>
        <?php endif; ?>

        <!-- Formulaire de réinitialisation -->
        <form method="POST" action="">
            <!-- Liste déroulante des utilisateurs -->
            <label for="login">Choisir un utilisateur</label>
            <select name="login" id="login" required>
                <option value="">-- Sélectionner un utilisateur --</option>
                <?php foreach ($utilisateurs as $u) : ?>
                    <option value="<?php echo htmlspecialchars($u['login']); ?>"
                        <?php echo ($selected_login === $u['login']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($u['nom'] . ' ' . $u['prenom'] . ' (' . $u['login'] . ' - ' . $u['role'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Champ du nouveau mot de passe -->
            <label for="nouveau_mdp">Nouveau mot de passe</label>
            <div class="password-wrapper">
                <input type="password" name="nouveau_mdp" id="nouveau_mdp" required minlength="8">
                <button type="button" class="toggle-password" data-target="nouveau_mdp" aria-label="Afficher ou masquer le mot de passe">👁</button>
            </div>

            <!-- Champ de confirmation du mot de passe -->
            <label for="confirmation_mdp">Confirmer le nouveau mot de passe</label>
            <div class="password-wrapper">
                <input type="password" name="confirmation_mdp" id="confirmation_mdp" required minlength="8">
                <button type="button" class="toggle-password" data-target="confirmation_mdp" aria-label="Afficher ou masquer la confirmation du mot de passe">👁</button>
            </div>

            <!-- Bouton de validation -->
            <button type="submit" class="btn-primary">Réinitialiser le mot de passe</button>
        </form>

        <!-- Liens de retour -->
        <div class="actions">
            <a href="gestion_comptes.php" class="btn-secondary">Retour à la gestion des comptes</a>
            <a href="../accueil.php" class="btn-secondary">Retour à l'accueil</a>
        </div>
    </div>

    <script>
    // Permet d'afficher ou masquer le mot de passe
    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function () {
            const input = document.getElementById(this.dataset.target);

            if (input.type === 'password') {
                input.type = 'text';
                this.textContent = '🙈';
            } else {
                input.type = 'password';
                this.textContent = '👁';
            }
        });
    });
    </script>

</body>
</html>