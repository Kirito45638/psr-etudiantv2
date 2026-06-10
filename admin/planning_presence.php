<?php
session_start();
require_once '../config/conf_server.php';

if (!isset($_SESSION['id'])) {
    header('Location: ../index.php');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    die('Accès refusé.');
}

$message = '';
$erreur = '';

function nettoyer($valeur) {
    return trim($valeur ?? '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'ajouter') {
        $idUtilisateur = (int)($_POST['id_utilisateur'] ?? 0);
        $datePresence = nettoyer($_POST['date_presence'] ?? '');
        $statutPresence = nettoyer($_POST['statut_presence'] ?? '');
        $commentaire = nettoyer($_POST['commentaire'] ?? '');

        $statutsAutorises = ['present', 'absent', 'indisponible'];

        if ($idUtilisateur > 0 && !empty($datePresence) && in_array($statutPresence, $statutsAutorises, true)) {
            try {
                $sqlCheck = "SELECT id FROM utilisateurs WHERE id = :id LIMIT 1";
                $stmtCheck = $conn->prepare($sqlCheck);
                $stmtCheck->execute(['id' => $idUtilisateur]);

                if (!$stmtCheck->fetch(PDO::FETCH_ASSOC)) {
                    $erreur = "Utilisateur introuvable.";
                } else {
                    $sqlDoublon = "SELECT id FROM planning_presence 
                                   WHERE id_utilisateur = :id_utilisateur AND date_presence = :date_presence 
                                   LIMIT 1";
                    $stmtDoublon = $conn->prepare($sqlDoublon);
                    $stmtDoublon->execute([
                        'id_utilisateur' => $idUtilisateur,
                        'date_presence' => $datePresence
                    ]);

                    if ($stmtDoublon->fetch(PDO::FETCH_ASSOC)) {
                        $erreur = "Une présence existe déjà pour cet utilisateur à cette date.";
                    } else {
                        $sqlInsert = "INSERT INTO planning_presence (id_utilisateur, date_presence, statut_presence, commentaire)
                                      VALUES (:id_utilisateur, :date_presence, :statut_presence, :commentaire)";
                        $stmtInsert = $conn->prepare($sqlInsert);
                        $stmtInsert->execute([
                            'id_utilisateur' => $idUtilisateur,
                            'date_presence' => $datePresence,
                            'statut_presence' => $statutPresence,
                            'commentaire' => $commentaire !== '' ? $commentaire : null
                        ]);

                        $message = "Présence ajoutée avec succès.";
                    }
                }
            } catch (PDOException $e) {
                $erreur = "Erreur lors de l'ajout : " . $e->getMessage();
            }
        } else {
            $erreur = "Veuillez remplir correctement tous les champs.";
        }
    }

    if ($action === 'supprimer') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id > 0) {
            try {
                $sqlDelete = "DELETE FROM planning_presence WHERE id = :id";
                $stmtDelete = $conn->prepare($sqlDelete);
                $stmtDelete->execute(['id' => $id]);

                $message = "Présence supprimée avec succès.";
            } catch (PDOException $e) {
                $erreur = "Erreur lors de la suppression.";
            }
        } else {
            $erreur = "Identifiant invalide.";
        }
    }
}

try {
    $sqlUtilisateurs = "SELECT id, nom, prenom, role 
                        FROM utilisateurs 
                        ORDER BY nom ASC, prenom ASC";
    $stmtUtilisateurs = $conn->prepare($sqlUtilisateurs);
    $stmtUtilisateurs->execute();
    $utilisateurs = $stmtUtilisateurs->fetchAll(PDO::FETCH_ASSOC);

    $sqlPlanning = "SELECT p.id, p.date_presence, p.statut_presence, p.commentaire,
                           u.nom, u.prenom, u.role
                    FROM planning_presence p
                    INNER JOIN utilisateurs u ON p.id_utilisateur = u.id
                    ORDER BY p.date_presence DESC, u.nom ASC, u.prenom ASC";
    $stmtPlanning = $conn->prepare($sqlPlanning);
    $stmtPlanning->execute();
    $planning = $stmtPlanning->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $erreur = "Erreur lors du chargement des données.";
    $utilisateurs = [];
    $planning = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planning de présence</title>
    <link rel="stylesheet" href="../accueil.css">
    <link rel="stylesheet" href="planning_presence.css">
</head>
<body>
    <div class="page-wrapper">
        <h1>Gestion du planning de présence</h1>
        <p class="subtitle">Ajout, consultation et suppression des présences des utilisateurs.</p>

        <?php if (!empty($message)) : ?>
            <p class="message-success"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <?php if (!empty($erreur)) : ?>
            <p class="message-error"><?php echo htmlspecialchars($erreur); ?></p>
        <?php endif; ?>

        <div class="form-block">
            <h2>Ajouter une présence</h2>

            <form method="POST" action="">
                <input type="hidden" name="action" value="ajouter">

                <label for="id_utilisateur">Utilisateur</label>
                <select name="id_utilisateur" id="id_utilisateur" required>
                    <option value="">-- Sélectionner un utilisateur --</option>
                    <?php foreach ($utilisateurs as $u) : ?>
                        <option value="<?php echo (int)$u['id']; ?>">
                            <?php echo htmlspecialchars($u['prenom'] . ' ' . $u['nom'] . ' (' . $u['role'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="date_presence">Date de présence</label>
                <input type="date" name="date_presence" id="date_presence" required>

                <label for="statut_presence">Statut</label>
                <select name="statut_presence" id="statut_presence" required>
                    <option value="present">Présent</option>
                    <option value="absent">Absent</option>
                    <option value="indisponible">Indisponible</option>
                </select>

                <label for="commentaire">Commentaire</label>
                <textarea name="commentaire" id="commentaire" rows="4" placeholder="Commentaire facultatif..."></textarea>

                <button type="submit">Ajouter la présence</button>
            </form>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Prénom</th>
                        <th>Rôle</th>
                        <th>Date</th>
                        <th>Statut</th>
                        <th>Commentaire</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($planning)) : ?>
                        <?php foreach ($planning as $ligne) : ?>
                            <tr>
                                <td><?php echo htmlspecialchars($ligne['nom']); ?></td>
                                <td><?php echo htmlspecialchars($ligne['prenom']); ?></td>
                                <td><?php echo htmlspecialchars($ligne['role']); ?></td>
                                <td><?php echo htmlspecialchars($ligne['date_presence']); ?></td>
                                <td>
                                    <?php if ($ligne['statut_presence'] === 'present') : ?>
                                        <span class="badge-present">Présent</span>
                                    <?php elseif ($ligne['statut_presence'] === 'absent') : ?>
                                        <span class="badge-absent">Absent</span>
                                    <?php else : ?>
                                        <span class="badge-indisponible">Indisponible</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($ligne['commentaire'] ?? ''); ?></td>
                                <td>
                                    <form method="POST" action="" onsubmit="return confirm('Confirmer la suppression de cette présence ?');">
                                        <input type="hidden" name="action" value="supprimer">
                                        <input type="hidden" name="id" value="<?php echo (int)$ligne['id']; ?>">
                                        <button type="submit" class="btn-delete">Supprimer</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="7">Aucune présence enregistrée.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="bottom-actions">
            <a class="back-link" href="../accueil.php">Retour à l'accueil</a>
        </div>
    </div>
</body>
</html>