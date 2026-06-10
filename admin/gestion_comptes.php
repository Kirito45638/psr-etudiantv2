<?php
session_start();
require_once("../config/conf_server.php");

// Vérifie que l'utilisateur est connecté
if (!isset($_SESSION["id"])) {
    header("Location: ../index.php");
    exit();
}

// Vérifie que l'utilisateur connecté est administrateur
if ($_SESSION["role"] !== "admin") {
    die("Accès refusé.");
}

// Variables utiles pour l'affichage
$message = "";
$erreur = "";
$rolesAutorises = ["eleve", "personnel", "cuisinier", "admin"];
$ongletActif = $_GET["tab"] ?? "ajouter";

// Nettoie une valeur reçue
function nettoyer($valeur) {
    return trim($valeur ?? "");
}

// Vérifie qu'un rôle fait partie de la liste autorisée
function roleValide($role, $rolesAutorises) {
    return in_array($role, $rolesAutorises, true);
}

// Construit les paramètres d'URL pour la pagination et les onglets
function buildQuery($tab, $extra = []) {
    $params = array_merge(["tab" => $tab], $extra);
    return http_build_query($params);
}

// Ajoute une action dans les logs administrateur
function ajouterLogAdmin($conn, $adminId, $action, $utilisateurCible) {
    $sqlLog = "INSERT INTO logs_admin (admin_id, action, utilisateur_cible)
               VALUES (:admin_id, :action, :utilisateur_cible)";
    $stmtLog = $conn->prepare($sqlLog);
    $stmtLog->execute([
        ":admin_id" => $adminId,
        ":action" => $action,
        ":utilisateur_cible" => $utilisateurCible
    ]);
}

// Génère un mot de passe temporaire aléatoire
function genererMotDePasseTemporaire($longueur = 8) {
    $caracteres = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $motDePasse = '';
    $max = strlen($caracteres) - 1;

    for ($i = 0; $i < $longueur; $i++) {
        $motDePasse .= $caracteres[random_int(0, $max)];
    }

    return $motDePasse;
}

// Gestion des actions envoyées en POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    // Ajout d'un utilisateur
    if ($action === "ajouter") {
        $nom = nettoyer($_POST["nom"]);
        $prenom = nettoyer($_POST["prenom"]);
        $email = nettoyer($_POST["email"]);
        $login = nettoyer($_POST["login"]);
        $role = nettoyer($_POST["role"]);
        $ongletActif = "ajouter";

        if ($nom !== "" && $prenom !== "" && $email !== "" && $login !== "" && $role !== "") {
            if (!roleValide($role, $rolesAutorises)) {
                $erreur = "Rôle invalide.";
            } else {
                // Vérifie que le login ou l'email n'existe pas déjà
                $sqlCheck = "SELECT id FROM utilisateurs WHERE login = :login OR email = :email LIMIT 1";
                $stmtCheck = $conn->prepare($sqlCheck);
                $stmtCheck->execute([
                    ":login" => $login,
                    ":email" => $email
                ]);

                if ($stmtCheck->fetch()) {
                    $erreur = "Le login ou l'email existe déjà.";
                } else {
                    try {
                        $conn->beginTransaction();

                        // Génère le mot de passe temporaire et le hash
                        $motDePasseTemporaire = genererMotDePasseTemporaire(8);
                        $motDePasseHash = password_hash($motDePasseTemporaire, PASSWORD_DEFAULT);

                        // Insère le nouvel utilisateur
                        $sqlInsert = "INSERT INTO utilisateurs (nom, prenom, email, login, mot_de_passe, role, points_fidelite, actif, premiere_connexion)
                                      VALUES (:nom, :prenom, :email, :login, :mot_de_passe, :role, 0, 1, 1)";
                        $stmtInsert = $conn->prepare($sqlInsert);
                        $stmtInsert->execute([
                            ":nom" => $nom,
                            ":prenom" => $prenom,
                            ":email" => $email,
                            ":login" => $login,
                            ":mot_de_passe" => $motDePasseHash,
                            ":role" => $role
                        ]);

                        // Ajoute le log admin
                        ajouterLogAdmin($conn, $_SESSION["id"], "Ajout utilisateur", $login);

                        $conn->commit();
                        $message = "Utilisateur ajouté avec succès. Mot de passe temporaire : " . $motDePasseTemporaire;
                    } catch (Exception $e) {
                        if ($conn->inTransaction()) {
                            $conn->rollBack();
                        }
                        $erreur = "Erreur lors de l'ajout de l'utilisateur.";
                    }
                }
            }
        } else {
            $erreur = "Veuillez remplir tous les champs.";
        }
    }

    // Modification d'un utilisateur
    if ($action === "modifier") {
        $id = (int)($_POST["id"] ?? 0);
        $nom = nettoyer($_POST["nom"]);
        $prenom = nettoyer($_POST["prenom"]);
        $email = nettoyer($_POST["email"]);
        $login = nettoyer($_POST["login"]);
        $role = nettoyer($_POST["role"]);
        $ongletActif = "modifier";

        if ($id > 0 && $nom !== "" && $prenom !== "" && $email !== "" && $login !== "" && $role !== "") {
            if (!roleValide($role, $rolesAutorises)) {
                $erreur = "Rôle invalide.";
            } else {
                // Vérifie que le login ou l'email n'appartient pas à un autre utilisateur
                $sqlCheck = "SELECT id FROM utilisateurs
                             WHERE (login = :login OR email = :email) AND id != :id
                             LIMIT 1";
                $stmtCheck = $conn->prepare($sqlCheck);
                $stmtCheck->execute([
                    ":login" => $login,
                    ":email" => $email,
                    ":id" => $id
                ]);

                if ($stmtCheck->fetch()) {
                    $erreur = "Le login ou l'email existe déjà pour un autre utilisateur.";
                } else {
                    try {
                        $conn->beginTransaction();

                        // Met à jour les informations de l'utilisateur
                        $sqlUpdate = "UPDATE utilisateurs
                                      SET nom = :nom,
                                          prenom = :prenom,
                                          email = :email,
                                          login = :login,
                                          role = :role
                                      WHERE id = :id";
                        $stmtUpdate = $conn->prepare($sqlUpdate);
                        $stmtUpdate->execute([
                            ":nom" => $nom,
                            ":prenom" => $prenom,
                            ":email" => $email,
                            ":login" => $login,
                            ":role" => $role,
                            ":id" => $id
                        ]);

                        // Ajoute le log admin
                        ajouterLogAdmin($conn, $_SESSION["id"], "Modification utilisateur", $login);

                        $conn->commit();
                        $message = "Utilisateur modifié avec succès.";
                    } catch (Exception $e) {
                        if ($conn->inTransaction()) {
                            $conn->rollBack();
                        }
                        $erreur = "Erreur lors de la modification.";
                    }
                }
            }
        } else {
            $erreur = "Veuillez remplir tous les champs pour la modification.";
        }
    }

    // Désactivation ou réactivation d'un compte
    if ($action === "toggle_actif") {
        $id = (int)($_POST["id"] ?? 0);
        $nouvelEtat = (int)($_POST["nouvel_etat"] ?? -1);
        $loginCible = nettoyer($_POST["login_cible"] ?? "");
        $ongletActif = "desactiver";

        if ($id <= 0 || ($nouvelEtat !== 0 && $nouvelEtat !== 1)) {
            $erreur = "Demande invalide.";
        } elseif ($id === (int)$_SESSION["id"]) {
            $erreur = "Vous ne pouvez pas désactiver ou réactiver votre propre compte.";
        } else {
            try {
                $conn->beginTransaction();

                // Met à jour l'état du compte
                $sqlToggle = "UPDATE utilisateurs SET actif = :actif WHERE id = :id";
                $stmtToggle = $conn->prepare($sqlToggle);
                $stmtToggle->execute([
                    ":actif" => $nouvelEtat,
                    ":id" => $id
                ]);

                // Ajoute le log admin
                $actionLog = $nouvelEtat === 0 ? "Désactivation compte" : "Réactivation compte";
                ajouterLogAdmin($conn, $_SESSION["id"], $actionLog, $loginCible);

                $conn->commit();
                $message = $nouvelEtat === 0 ? "Compte désactivé avec succès." : "Compte réactivé avec succès.";
            } catch (Exception $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                $erreur = "Erreur lors du changement d'état du compte.";
            }
        }
    }

    // Suppression d'un utilisateur
    if ($action === "supprimer") {
        $id = (int)($_POST["id"] ?? 0);
        $loginCible = nettoyer($_POST["login_cible"] ?? "");
        $ongletActif = "supprimer";

        if ($id <= 0) {
            $erreur = "Utilisateur invalide.";
        } elseif ($id === (int)$_SESSION["id"]) {
            $erreur = "Vous ne pouvez pas supprimer votre propre compte.";
        } else {
            try {
                $conn->beginTransaction();

                // Vérifie si l'utilisateur possède déjà des réservations
                $sqlCheckReservations = "SELECT COUNT(*) FROM reservations WHERE id_utilisateur = :id";
                $stmtCheckReservations = $conn->prepare($sqlCheckReservations);
                $stmtCheckReservations->execute([":id" => $id]);
                $nbReservations = (int)$stmtCheckReservations->fetchColumn();

                if ($nbReservations > 0) {
                    $conn->rollBack();
                    $erreur = "Suppression impossible : cet utilisateur possède déjà des réservations. Désactivez le compte à la place.";
                } else {
                    // Supprime le compte
                    $sqlDelete = "DELETE FROM utilisateurs WHERE id = :id";
                    $stmtDelete = $conn->prepare($sqlDelete);
                    $stmtDelete->execute([":id" => $id]);

                    // Ajoute le log admin
                    ajouterLogAdmin($conn, $_SESSION["id"], "Suppression utilisateur", $loginCible);

                    $conn->commit();
                    $message = "Utilisateur supprimé avec succès.";
                }
            } catch (PDOException $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                $erreur = "Suppression impossible : des données liées existent déjà pour cet utilisateur.";
            }
        }
    }
}

// Gestion de la recherche et de la pagination
$recherche = nettoyer($_GET["q"] ?? "");
$page = isset($_GET["page"]) && ctype_digit($_GET["page"]) ? (int)$_GET["page"] : 1;
$page = max($page, 1);
$parPage = 8;
$offset = ($page - 1) * $parPage;

$where = "";
$params = [];

// Ajoute la recherche si un mot-clé est présent
if ($recherche !== "") {
    $where = " WHERE nom LIKE :recherche
               OR prenom LIKE :recherche
               OR email LIKE :recherche
               OR login LIKE :recherche
               OR role LIKE :recherche";
    $params[":recherche"] = "%" . $recherche . "%";
}

// Compte le nombre total d'utilisateurs
$sqlCount = "SELECT COUNT(*) FROM utilisateurs" . $where;
$stmtCount = $conn->prepare($sqlCount);
$stmtCount->execute($params);
$totalUtilisateurs = (int)$stmtCount->fetchColumn();
$totalPages = max(1, (int)ceil($totalUtilisateurs / $parPage));

// Recalcule la page si elle dépasse le total
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $parPage;
}

// Récupère les utilisateurs pour le tableau
$sqlUtilisateurs = "SELECT id, nom, prenom, email, login, role, points_fidelite, actif
                    FROM utilisateurs" . $where . "
                    ORDER BY actif DESC, nom ASC, prenom ASC
                    LIMIT :limit OFFSET :offset";
$stmtUtilisateurs = $conn->prepare($sqlUtilisateurs);

foreach ($params as $cle => $valeur) {
    $stmtUtilisateurs->bindValue($cle, $valeur, PDO::PARAM_STR);
}
$stmtUtilisateurs->bindValue(":limit", $parPage, PDO::PARAM_INT);
$stmtUtilisateurs->bindValue(":offset", $offset, PDO::PARAM_INT);
$stmtUtilisateurs->execute();
$utilisateurs = $stmtUtilisateurs->fetchAll(PDO::FETCH_ASSOC);

// Récupère l'utilisateur à modifier si un id est présent
$utilisateurAModifier = null;
if (isset($_GET["edit_id"]) && ctype_digit($_GET["edit_id"])) {
    $editId = (int)$_GET["edit_id"];

    $sqlEdit = "SELECT id, nom, prenom, email, login, role, actif
                FROM utilisateurs
                WHERE id = :id
                LIMIT 1";
    $stmtEdit = $conn->prepare($sqlEdit);
    $stmtEdit->execute([":id" => $editId]);
    $utilisateurAModifier = $stmtEdit->fetch(PDO::FETCH_ASSOC);

    if ($utilisateurAModifier) {
        $ongletActif = "modifier";
    }
}

// Récupère les 10 dernières actions administrateur
$sqlLogs = "SELECT l.action, l.utilisateur_cible, l.date_action,
                   u.nom AS admin_nom, u.prenom AS admin_prenom
            FROM logs_admin l
            INNER JOIN utilisateurs u ON l.admin_id = u.id
            ORDER BY l.date_action DESC
            LIMIT 10";
$stmtLogs = $conn->prepare($sqlLogs);
$stmtLogs->execute();
$logsAdmin = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des comptes</title>
    <link rel="stylesheet" href="../accueil.css">
    <link rel="stylesheet" href="gestion_comptes.css">
</head>
<body>
    <!-- Conteneur principal -->
    <div class="page-wrapper">
        <!-- Titre de la page -->
        <h1>Gestion des comptes</h1>
        <p class="subtitle">Ajout, modification, activation, désactivation, suppression et suivi des actions administrateur.</p>

        <!-- Message de succès -->
        <?php if (!empty($message)) : ?>
            <p class="message-success"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <!-- Message d'erreur -->
        <?php if (!empty($erreur)) : ?>
            <p class="message-error"><?php echo htmlspecialchars($erreur); ?></p>
        <?php endif; ?>

        <!-- Boutons de navigation entre les onglets -->
        <div class="tabs">
            <button class="tab-btn <?php echo ($ongletActif === 'ajouter') ? 'active' : ''; ?>" type="button" data-tab="ajouter">Ajouter</button>
            <button class="tab-btn <?php echo ($ongletActif === 'modifier') ? 'active' : ''; ?>" type="button" data-tab="modifier">Modifier</button>
            <button class="tab-btn <?php echo ($ongletActif === 'desactiver') ? 'active' : ''; ?>" type="button" data-tab="desactiver">Désactiver / Réactiver</button>
            <button class="tab-btn <?php echo ($ongletActif === 'supprimer') ? 'active' : ''; ?>" type="button" data-tab="supprimer">Supprimer</button>
        </div>

        <!-- Formulaire de recherche -->
        <div class="search-box">
            <form method="GET" action="">
                <input type="hidden" name="tab" value="<?php echo htmlspecialchars($ongletActif); ?>">
                <input type="text" name="q" placeholder="Rechercher par nom, prénom, email, login, rôle..." value="<?php echo htmlspecialchars($recherche); ?>">
                <button type="submit">Rechercher</button>
                <a class="reset-link" href="?tab=<?php echo urlencode($ongletActif); ?>">Réinitialiser</a>
            </form>
        </div>

        <!-- Onglet ajout -->
        <div id="ajouter" class="tab-content <?php echo ($ongletActif === 'ajouter') ? 'active' : ''; ?>">
            <h2>Ajouter un utilisateur</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="ajouter">

                <label for="nom">Nom :</label>
                <input type="text" name="nom" id="nom" required>

                <label for="prenom">Prénom :</label>
                <input type="text" name="prenom" id="prenom" required>

                <label for="email">Email :</label>
                <input type="email" name="email" id="email" required>

                <label for="login">Login :</label>
                <input type="text" name="login" id="login" required>

                <label for="role">Rôle :</label>
                <select name="role" id="role" required>
                    <option value="">-- Choisir un rôle --</option>
                    <option value="eleve">Élève</option>
                    <option value="personnel">Personnel</option>
                    <option value="cuisinier">Cuisinier</option>
                    <option value="admin">Admin</option>
                </select>

                <button type="submit">Ajouter l'utilisateur</button>
            </form>
        </div>

        <!-- Onglet modification -->
        <div id="modifier" class="tab-content <?php echo ($ongletActif === 'modifier') ? 'active' : ''; ?>">
            <h2>Modifier un utilisateur</h2>

            <!-- Tableau des utilisateurs à modifier -->
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Prénom</th>
                            <th>Email</th>
                            <th>Login</th>
                            <th>Rôle</th>
                            <th>État</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($utilisateurs)) : ?>
                            <?php foreach ($utilisateurs as $u) : ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($u["nom"]); ?></td>
                                    <td><?php echo htmlspecialchars($u["prenom"]); ?></td>
                                    <td><?php echo htmlspecialchars($u["email"]); ?></td>
                                    <td><?php echo htmlspecialchars($u["login"]); ?></td>
                                    <td><?php echo htmlspecialchars($u["role"]); ?></td>
                                    <td>
                                        <?php if ((int)$u["actif"] === 1) : ?>
                                            <span class="badge-actif">Actif</span>
                                        <?php else : ?>
                                            <span class="badge-inactif">Inactif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions-cell">
                                        <a class="small-btn btn-edit" href="?<?php echo buildQuery('modifier', ['q' => $recherche, 'page' => $page, 'edit_id' => (int)$u['id']]); ?>">Modifier</a>
                                        <a class="small-btn btn-reset" href="reset_password.php?login=<?php echo urlencode($u['login']); ?>">Réinitialiser MDP</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr><td colspan="7">Aucun utilisateur trouvé.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Formulaire de modification -->
            <?php if ($utilisateurAModifier) : ?>
                <div class="form-block">
                    <h3>Formulaire de modification</h3>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="modifier">
                        <input type="hidden" name="id" value="<?php echo (int)$utilisateurAModifier["id"]; ?>">

                        <label for="mod_nom">Nom :</label>
                        <input type="text" name="nom" id="mod_nom" value="<?php echo htmlspecialchars($utilisateurAModifier["nom"]); ?>" required>

                        <label for="mod_prenom">Prénom :</label>
                        <input type="text" name="prenom" id="mod_prenom" value="<?php echo htmlspecialchars($utilisateurAModifier["prenom"]); ?>" required>

                        <label for="mod_email">Email :</label>
                        <input type="email" name="email" id="mod_email" value="<?php echo htmlspecialchars($utilisateurAModifier["email"]); ?>" required>

                        <label for="mod_login">Login :</label>
                        <input type="text" name="login" id="mod_login" value="<?php echo htmlspecialchars($utilisateurAModifier["login"]); ?>" required>

                        <label for="mod_role">Rôle :</label>
                        <select name="role" id="mod_role" required>
                            <?php foreach ($rolesAutorises as $role) : ?>
                                <option value="<?php echo htmlspecialchars($role); ?>" <?php echo ($utilisateurAModifier["role"] === $role) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($role); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <button type="submit">Enregistrer les modifications</button>
                    </form>
                </div>
            <?php else : ?>
                <p class="helper-text">Sélectionnez un utilisateur dans le tableau pour le modifier.</p>
            <?php endif; ?>
        </div>

        <!-- Onglet désactivation / réactivation -->
        <div id="desactiver" class="tab-content <?php echo ($ongletActif === 'desactiver') ? 'active' : ''; ?>">
            <h2>Désactiver / Réactiver un compte</h2>

            <!-- Tableau des comptes -->
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Prénom</th>
                            <th>Login</th>
                            <th>Rôle</th>
                            <th>État</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($utilisateurs)) : ?>
                            <?php foreach ($utilisateurs as $u) : ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($u["nom"]); ?></td>
                                    <td><?php echo htmlspecialchars($u["prenom"]); ?></td>
                                    <td><?php echo htmlspecialchars($u["login"]); ?></td>
                                    <td><?php echo htmlspecialchars($u["role"]); ?></td>
                                    <td>
                                        <?php if ((int)$u["actif"] === 1) : ?>
                                            <span class="badge-actif">Actif</span>
                                        <?php else : ?>
                                            <span class="badge-inactif">Inactif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions-cell">
                                        <?php if ((int)$u["id"] !== (int)$_SESSION["id"]) : ?>
                                            <form method="POST" class="inline-form" action="">
                                                <input type="hidden" name="action" value="toggle_actif">
                                                <input type="hidden" name="id" value="<?php echo (int)$u["id"]; ?>">
                                                <input type="hidden" name="login_cible" value="<?php echo htmlspecialchars($u["login"]); ?>">
                                                <input type="hidden" name="nouvel_etat" value="<?php echo ((int)$u["actif"] === 1) ? 0 : 1; ?>">
                                                <button type="submit" class="small-btn <?php echo ((int)$u["actif"] === 1) ? 'btn-disable' : 'btn-enable'; ?>">
                                                    <?php echo ((int)$u["actif"] === 1) ? 'Désactiver' : 'Réactiver'; ?>
                                                </button>
                                            </form>
                                            <a class="small-btn btn-reset" href="reset_password.php?login=<?php echo urlencode($u['login']); ?>">Réinitialiser MDP</a>
                                        <?php else : ?>
                                            <span class="self-tag">Compte connecté</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr><td colspan="6">Aucun utilisateur trouvé.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Onglet suppression -->
        <div id="supprimer" class="tab-content <?php echo ($ongletActif === 'supprimer') ? 'active' : ''; ?>">
            <h2>Supprimer un compte</h2>

            <!-- Message d'information -->
            <div class="info-box">
                La suppression est bloquée si l'utilisateur possède déjà des réservations liées.
            </div>

            <!-- Tableau des comptes à supprimer -->
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Prénom</th>
                            <th>Login</th>
                            <th>Rôle</th>
                            <th>Points fidélité</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($utilisateurs)) : ?>
                            <?php foreach ($utilisateurs as $u) : ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($u["nom"]); ?></td>
                                    <td><?php echo htmlspecialchars($u["prenom"]); ?></td>
                                    <td><?php echo htmlspecialchars($u["login"]); ?></td>
                                    <td><?php echo htmlspecialchars($u["role"]); ?></td>
                                    <td><?php echo (int)$u["points_fidelite"]; ?></td>
                                    <td class="actions-cell">
                                        <?php if ((int)$u["id"] !== (int)$_SESSION["id"]) : ?>
                                            <form method="POST" class="inline-form" action="" onsubmit="return confirm('Confirmer la suppression de ce compte ?');">
                                                <input type="hidden" name="action" value="supprimer">
                                                <input type="hidden" name="id" value="<?php echo (int)$u["id"]; ?>">
                                                <input type="hidden" name="login_cible" value="<?php echo htmlspecialchars($u["login"]); ?>">
                                                <button type="submit" class="small-btn btn-delete">Supprimer</button>
                                            </form>
                                        <?php else : ?>
                                            <span class="self-tag">Compte connecté</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr><td colspan="6">Aucun utilisateur trouvé.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Section des logs admin -->
        <div class="logs-section">
            <h2>Dernières actions administrateur</h2>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Administrateur</th>
                            <th>Action</th>
                            <th>Utilisateur cible</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($logsAdmin)) : ?>
                            <?php foreach ($logsAdmin as $log) : ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($log["admin_prenom"] . " " . $log["admin_nom"]); ?></td>
                                    <td><?php echo htmlspecialchars($log["action"]); ?></td>
                                    <td><?php echo htmlspecialchars($log["utilisateur_cible"]); ?></td>
                                    <td><?php echo htmlspecialchars($log["date_action"]); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr><td colspan="4">Aucune action enregistrée.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1) : ?>
            <div class="pagination">
                <?php if ($page > 1) : ?>
                    <a href="?<?php echo buildQuery($ongletActif, ['q' => $recherche, 'page' => $page - 1]); ?>">&laquo; Précédent</a>
                <?php endif; ?>

                <span>Page <?php echo $page; ?> / <?php echo $totalPages; ?></span>

                <?php if ($page < $totalPages) : ?>
                    <a href="?<?php echo buildQuery($ongletActif, ['q' => $recherche, 'page' => $page + 1]); ?>">Suivant &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Lien de retour -->
        <div class="bottom-actions">
            <a class="back-link" href="../accueil.php">Retour à l'accueil</a>
        </div>
    </div>

    <script>
        // Gestion de l'affichage des onglets sans recharger la page
        const buttons = document.querySelectorAll('.tab-btn');
        const contents = document.querySelectorAll('.tab-content');

        buttons.forEach(button => {
            button.addEventListener('click', () => {
                const tab = button.getAttribute('data-tab');

                buttons.forEach(btn => btn.classList.remove('active'));
                contents.forEach(content => content.classList.remove('active'));

                button.classList.add('active');
                document.getElementById(tab).classList.add('active');

                const url = new URL(window.location);
                url.searchParams.set('tab', tab);
                url.searchParams.delete('edit_id');
                window.history.replaceState({}, '', url);
            });
        });
    </script>
</body>
</html>