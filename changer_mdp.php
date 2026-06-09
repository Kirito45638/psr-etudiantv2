<?php
session_start();
require_once("config/conf_server.php");

// Vérifie que l'utilisateur est connecté
if (!isset($_SESSION["id"])) {
    header("Location: index.php");
    exit();
}

// Récupère l'id de l'utilisateur connecté
$id_utilisateur = $_SESSION["id"];

// Variable pour afficher les erreurs
$erreur = "";

// Récupère les informations utiles de l'utilisateur
$sqlUser = "SELECT id, premiere_connexion, actif, prenom, nom
            FROM utilisateurs
            WHERE id = :id
            LIMIT 1";
$stmtUser = $conn->prepare($sqlUser);
$stmtUser->execute([':id' => $id_utilisateur]);
$utilisateur = $stmtUser->fetch(PDO::FETCH_ASSOC);

// Si l'utilisateur n'existe pas ou que son compte est inactif, on détruit la session
if (!$utilisateur || (int)$utilisateur["actif"] !== 1) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// Si ce n'est plus une première connexion, on renvoie vers l'accueil
if ((int)$utilisateur["premiere_connexion"] !== 1) {
    header("Location: accueil.php");
    exit();
}

// Si le formulaire est envoyé
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Récupération et nettoyage des champs
    $nouveauMotDePasse = trim($_POST["nouveau_mot_de_passe"] ?? '');
    $confirmerMotDePasse = trim($_POST["confirmer_mot_de_passe"] ?? '');

    // Vérifie que les deux champs sont remplis
    if ($nouveauMotDePasse === "" || $confirmerMotDePasse === "") {
        $erreur = "Veuillez remplir tous les champs.";
    }
    // Vérifie la longueur minimale du mot de passe
    elseif (strlen($nouveauMotDePasse) < 8) {
        $erreur = "Le mot de passe doit contenir au moins 8 caractères.";
    }
    // Vérifie que les deux mots de passe sont identiques
    elseif ($nouveauMotDePasse !== $confirmerMotDePasse) {
        $erreur = "Les mots de passe ne correspondent pas.";
    } else {
        // Hash du nouveau mot de passe
        $motDePasseHash = password_hash($nouveauMotDePasse, PASSWORD_DEFAULT);

        // Mise à jour du mot de passe et fin de la première connexion
        $sqlUpdate = "UPDATE utilisateurs
                      SET mot_de_passe = :mot_de_passe,
                          premiere_connexion = 0
                      WHERE id = :id";
        $stmtUpdate = $conn->prepare($sqlUpdate);
        $stmtUpdate->execute([
            ':mot_de_passe' => $motDePasseHash,
            ':id' => $id_utilisateur
        ]);

        // Mise à jour de la session
        $_SESSION["premiere_connexion"] = 0;

        // Redirection vers l'accueil
        header("Location: accueil.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Première connexion</title>
    <link rel="stylesheet" href="changer_mdp.css">
</head>
<body>
    <!-- Conteneur principal -->
    <div class="container">
        <!-- Titre de la page -->
        <h1>Première connexion</h1>

        <!-- Message d'information pour l'utilisateur -->
        <p>Bonjour <?php echo htmlspecialchars($utilisateur["prenom"] . " " . $utilisateur["nom"]); ?>, vous devez définir un mot de passe personnel avant d’accéder au site.</p>

        <!-- Message d'erreur si le formulaire contient une erreur -->
        <?php if (!empty($erreur)) : ?>
            <div class="message-erreur"><?php echo htmlspecialchars($erreur); ?></div>
        <?php endif; ?>

        <!-- Formulaire de changement de mot de passe -->
        <form method="POST" action="" novalidate>
            <!-- Champ du nouveau mot de passe -->
            <label for="nouveau_mot_de_passe">Nouveau mot de passe</label>
            <div class="password-wrapper">
                <input
                    type="password"
                    id="nouveau_mot_de_passe"
                    name="nouveau_mot_de_passe"
                    required
                    minlength="8"
                    autocomplete="new-password"
                >
                <button
                    type="button"
                    class="toggle-password"
                    data-target="nouveau_mot_de_passe"
                    aria-label="Afficher ou masquer le mot de passe"
                >👁</button>
            </div>

            <!-- Champ de confirmation du mot de passe -->
            <label for="confirmer_mot_de_passe">Confirmer le mot de passe</label>
            <div class="password-wrapper">
                <input
                    type="password"
                    id="confirmer_mot_de_passe"
                    name="confirmer_mot_de_passe"
                    required
                    minlength="8"
                    autocomplete="new-password"
                >
                <button
                    type="button"
                    class="toggle-password"
                    data-target="confirmer_mot_de_passe"
                    aria-label="Afficher ou masquer la confirmation du mot de passe"
                >👁</button>
            </div>

            <!-- Bouton d'enregistrement -->
            <button type="submit">Enregistrer</button>
        </form>
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