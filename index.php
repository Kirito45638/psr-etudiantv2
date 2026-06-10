<?php
session_start();
require_once("config/conf_server.php");

// Variable pour afficher les messages d'erreur
$erreur = "";

// Si l'utilisateur est déjà connecté, on le redirige directement
if (isset($_SESSION["id"])) {
    if (isset($_SESSION["premiere_connexion"]) && (int)$_SESSION["premiere_connexion"] === 1) {
        header("Location: changer_mdp.php");
        exit();
    } else {
        header("Location: accueil.php");
        exit();
    }
}

// Si le formulaire de connexion est envoyé
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Récupération et nettoyage des champs
    $login = trim($_POST["username"] ?? "");
    $mdp = trim($_POST["password"] ?? "");

    // Vérifie que les champs sont remplis
    if (!empty($login) && !empty($mdp)) {
        // Recherche d'un utilisateur actif avec ce login
        $sql = "SELECT * FROM utilisateurs WHERE login = :login AND actif = 1 LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':login', $login, PDO::PARAM_STR);
        $stmt->execute();

        $utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);

        // Vérifie l'existence de l'utilisateur et le mot de passe
        if ($utilisateur && password_verify($mdp, $utilisateur["mot_de_passe"])) {
            // Stocke les informations utiles dans la session
            $_SESSION["id"] = $utilisateur["id"];
            $_SESSION["nom"] = $utilisateur["nom"];
            $_SESSION["prenom"] = $utilisateur["prenom"];
            $_SESSION["email"] = $utilisateur["email"];
            $_SESSION["login"] = $utilisateur["login"];
            $_SESSION["role"] = $utilisateur["role"];
            $_SESSION["premiere_connexion"] = $utilisateur["premiere_connexion"];

            // Si c'est la première connexion, redirection vers le changement de mot de passe
            if ((int)$utilisateur["premiere_connexion"] === 1) {
                header("Location: changer_mdp.php");
                exit();
            }

            // Sinon, redirection vers la page d'accueil
            header("Location: accueil.php");
            exit();
        } else {
            // Message si les identifiants sont incorrects
            $erreur = "Identifiant ou mot de passe incorrect !";
        }
    } else {
        // Message si un ou plusieurs champs sont vides
        $erreur = "Veuillez remplir tous les champs.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Restaurant PSR EREA</title>

    <style>
        /* Réinitialisation de base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* Style général de la page */
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        /* Boîte principale de connexion */
        .login-container {
            background-color: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0px 10px 30px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 400px;
        }

        /* Titre principal */
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }

        /* Sous-titre */
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }

        /* Bloc contenant chaque champ */
        .form-group {
            margin-bottom: 20px;
        }

        /* Libellés des champs */
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: bold;
        }

        /* Champs de saisie */
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        /* Effet au focus sur les champs */
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #3498db;
        }

        /* Bouton de connexion */
        .btn-login {
            width: 100%;
            padding: 12px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
            font-weight: bold;
        }

        /* Effet au survol du bouton */
        .btn-login:hover {
            background-color: #2980b9;
        }

        /* Message d'erreur */
        .error-message {
            color: #e74c3c;
            text-align: center;
            margin-top: 15px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <!-- Conteneur principal du formulaire de connexion -->
    <div class="login-container">
        <!-- Titre de la page -->
        <h1>Restaurant PSR EREA</h1>

        <!-- Sous-titre explicatif -->
        <p class="subtitle">Connexion au système de réservation</p>

        <!-- Formulaire de connexion envoyé en POST -->
        <form method="POST" action="">
            <div class="form-group">
                <!-- Champ pour saisir l'identifiant -->
                <label for="username">Identifiant :</label>
                <input type="text" id="username" name="username" required autocomplete="username">
            </div>

            <div class="form-group">
                <!-- Champ pour saisir le mot de passe -->
                <label for="password">Mot de passe :</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>

            <!-- Bouton de validation du formulaire -->
            <button type="submit" class="btn-login">Se connecter</button>

            <!-- Affichage du message d'erreur si la connexion échoue -->
            <?php if (!empty($erreur)) : ?>
                <div class="error-message"><?php echo htmlspecialchars($erreur); ?></div>
            <?php endif; ?>
        </form>
    </div>
</body>
</html>