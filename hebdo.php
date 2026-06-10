<?php
session_start();
require_once("config/conf_server.php");

// Vérifie que l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: index.php');
    exit();
}

// Liste des rôles autorisés à modifier le menu
$rolesAutorisesEdition = ['admin', 'cuisinier'];

// Vérifie si l'utilisateur connecté a le droit de modifier
$peutModifierMenu = isset($_SESSION['role']) && in_array($_SESSION['role'], $rolesAutorisesEdition);

// Variables pour afficher les messages
$message = "";
$erreur = "";

// Traitement de la modification d'un menu
if ($peutModifierMenu && isset($_POST['modifier_menu'])) {
    $id = (int)($_POST['id'] ?? 0);
    $entree = trim($_POST['entree'] ?? '');
    $plat = trim($_POST['plat'] ?? '');
    $dessert = trim($_POST['dessert'] ?? '');
    $fromage_yaourt = trim($_POST['fromage_yaourt'] ?? '');
    $pain = trim($_POST['pain'] ?? '');

    // Vérifie que tous les champs sont remplis
    if ($id > 0 && $entree !== '' && $plat !== '' && $dessert !== '' && $fromage_yaourt !== '' && $pain !== '') {
        $sqlUpdate = "UPDATE menus_hebdo 
                      SET entree = :entree,
                          plat = :plat,
                          dessert = :dessert,
                          fromage_yaourt = :fromage_yaourt,
                          pain = :pain
                      WHERE id = :id";
        $stmtUpdate = $conn->prepare($sqlUpdate);
        $ok = $stmtUpdate->execute([
            ':entree' => $entree,
            ':plat' => $plat,
            ':dessert' => $dessert,
            ':fromage_yaourt' => $fromage_yaourt,
            ':pain' => $pain,
            ':id' => $id
        ]);

        // Redirection après succès
        if ($ok) {
            header("Location: hebdo.php?success=1");
            exit();
        } else {
            $erreur = "Erreur lors de la mise à jour du menu.";
        }
    } else {
        $erreur = "Tous les champs doivent être remplis.";
    }
}

// Message après modification réussie
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = "Le menu a bien été modifié.";
}

// Récupère tous les menus de la semaine
$sql = "SELECT * FROM menus_hebdo
        ORDER BY FIELD(jour_semaine, 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi'),
                 FIELD(type_repas, 'standard', 'vegetarien', 'sans-porc')";
$stmt = $conn->prepare($sql);
$stmt->execute();
$menus = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Regroupe les menus par jour
$menusParJour = [];
foreach ($menus as $menu) {
    $jour = $menu['jour_semaine'];
    $menusParJour[$jour][] = $menu;
}

// Formate le type de repas pour l'affichage
function formatTypeRepas($type) {
    switch ($type) {
        case 'standard':
            return 'Standard';
        case 'vegetarien':
            return 'Végétarien';
        case 'sans-porc':
            return 'Sans porc';
        default:
            return ucfirst($type);
    }
}

// Correspondance entre les jours anglais et français
$joursSemaine = [
    'Monday' => 'Lundi',
    'Tuesday' => 'Mardi',
    'Wednesday' => 'Mercredi',
    'Thursday' => 'Jeudi',
    'Friday' => 'Vendredi'
];

// Détermine automatiquement le jour actuel
$jourActuelAnglais = date('l');
$jourActif = isset($joursSemaine[$jourActuelAnglais]) ? $joursSemaine[$jourActuelAnglais] : 'Lundi';

// Si le jour actuel n'existe pas dans les menus, prend le premier disponible
if (!array_key_exists($jourActif, $menusParJour) && !empty($menusParJour)) {
    $jourActif = array_key_first($menusParJour);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu hebdomadaire</title>
    <link rel="stylesheet" href="hebdo.css">
</head>
<body>
    <!-- Bouton de retour vers l'accueil -->
    <div class="button-container top-actions">
        <button class="button-9" onclick="window.location.href='accueil.php'" type="button">Accueil</button>
    </div>

    <!-- Contenu principal -->
    <div class="content">
        <!-- Titre de la page -->
        <h1>Menu hebdomadaire</h1>
        <p class="subtitle">Le menu du jour est affiché automatiquement. Vous pouvez changer de jour avec les onglets.</p>

        <!-- Message de succès -->
        <?php if (!empty($message)) : ?>
            <p class="success-message"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <!-- Message d'erreur -->
        <?php if (!empty($erreur)) : ?>
            <p class="error-message"><?php echo htmlspecialchars($erreur); ?></p>
        <?php endif; ?>

        <!-- Vérifie qu'il existe des menus -->
        <?php if (!empty($menusParJour)) : ?>
            <!-- Onglets des jours -->
            <div class="tabs">
                <?php foreach ($menusParJour as $jour => $repasDuJour) : ?>
                    <button class="tab-btn <?php echo ($jour === $jourActif) ? 'active' : ''; ?>" type="button" data-day="<?php echo htmlspecialchars($jour); ?>">
                        <?php echo htmlspecialchars($jour); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- Contenu de chaque jour -->
            <?php foreach ($menusParJour as $jour => $repasDuJour) : ?>
                <div class="tab-content <?php echo ($jour === $jourActif) ? 'active' : ''; ?>" id="<?php echo htmlspecialchars($jour); ?>">
                    <h2 class="jour-title"><?php echo htmlspecialchars($jour); ?></h2>

                    <!-- Cartes des repas du jour -->
                    <div class="meals-container">
                        <?php foreach ($repasDuJour as $repas) : ?>
                            <div class="meal-card">
                                <h2>
                                    <?php echo formatTypeRepas($repas['type_repas']); ?>
                                    <span class="meal-type <?php echo htmlspecialchars($repas['type_repas']); ?>">
                                        <?php echo formatTypeRepas($repas['type_repas']); ?>
                                    </span>
                                </h2>

                                <div class="meal-item">
                                    <h3>Entrée</h3>
                                    <p><?php echo htmlspecialchars($repas['entree']); ?></p>
                                </div>

                                <div class="meal-item">
                                    <h3>Plat</h3>
                                    <p><?php echo htmlspecialchars($repas['plat']); ?></p>
                                </div>

                                <div class="meal-item">
                                    <h3>Dessert</h3>
                                    <p><?php echo htmlspecialchars($repas['dessert']); ?></p>
                                </div>

                                <div class="meal-item">
                                    <h3>Fromage / Yaourt</h3>
                                    <p><?php echo htmlspecialchars($repas['fromage_yaourt']); ?></p>
                                </div>

                                <div class="meal-item">
                                    <h3>Pain</h3>
                                    <p><?php echo htmlspecialchars($repas['pain']); ?></p>
                                </div>

                                <!-- Bouton visible seulement pour admin et cuisinier -->
                                <?php if ($peutModifierMenu): ?>
                                    <button class="edit-btn" type="button"
                                        onclick="openEditModal(
                                            '<?php echo $repas['id']; ?>',
                                            '<?php echo htmlspecialchars($repas['jour_semaine'], ENT_QUOTES); ?>',
                                            '<?php echo htmlspecialchars(formatTypeRepas($repas['type_repas']), ENT_QUOTES); ?>',
                                            '<?php echo htmlspecialchars($repas['entree'], ENT_QUOTES); ?>',
                                            '<?php echo htmlspecialchars($repas['plat'], ENT_QUOTES); ?>',
                                            '<?php echo htmlspecialchars($repas['dessert'], ENT_QUOTES); ?>',
                                            '<?php echo htmlspecialchars($repas['fromage_yaourt'], ENT_QUOTES); ?>',
                                            '<?php echo htmlspecialchars($repas['pain'], ENT_QUOTES); ?>'
                                        )">
                                        Modifier
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

        <?php else : ?>
            <!-- Message si aucun menu n'existe -->
            <p>Aucun menu hebdomadaire n'est disponible pour le moment.</p>
        <?php endif; ?>
    </div>

    <!-- Fenêtre modale de modification -->
    <?php if ($peutModifierMenu): ?>
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2>Modifier le menu</h2>

            <!-- Formulaire de modification -->
            <form method="post">
                <input type="hidden" name="id" id="edit-id">

                <p><strong>Jour :</strong> <span id="edit-jour"></span></p>
                <p><strong>Type :</strong> <span id="edit-type"></span></p>

                <label for="edit-entree">Entrée</label>
                <input type="text" name="entree" id="edit-entree" required>

                <label for="edit-plat">Plat</label>
                <input type="text" name="plat" id="edit-plat" required>

                <label for="edit-dessert">Dessert</label>
                <input type="text" name="dessert" id="edit-dessert" required>

                <label for="edit-fromage_yaourt">Fromage / Yaourt</label>
                <input type="text" name="fromage_yaourt" id="edit-fromage_yaourt" required>

                <label for="edit-pain">Pain</label>
                <input type="text" name="pain" id="edit-pain" required>

                <button type="submit" name="modifier_menu" class="button-9">Enregistrer les modifications</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        // Gestion des onglets des jours
        const buttons = document.querySelectorAll('.tab-btn');
        const contents = document.querySelectorAll('.tab-content');

        buttons.forEach(button => {
            button.addEventListener('click', () => {
                const day = button.getAttribute('data-day');

                buttons.forEach(btn => btn.classList.remove('active'));
                contents.forEach(content => content.classList.remove('active'));

                button.classList.add('active');
                document.getElementById(day).classList.add('active');
            });
        });

        // Ouvre la fenêtre de modification avec les données du repas
        function openEditModal(id, jour, type, entree, plat, dessert, fromageYaourt, pain) {
            document.getElementById('edit-id').value = id;
            document.getElementById('edit-jour').textContent = jour;
            document.getElementById('edit-type').textContent = type;
            document.getElementById('edit-entree').value = entree;
            document.getElementById('edit-plat').value = plat;
            document.getElementById('edit-dessert').value = dessert;
            document.getElementById('edit-fromage_yaourt').value = fromageYaourt;
            document.getElementById('edit-pain').value = pain;
            document.getElementById('editModal').style.display = 'flex';
        }

        // Ferme la fenêtre de modification
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // Ferme la fenêtre si on clique en dehors
        window.addEventListener('click', function(e) {
            const modal = document.getElementById('editModal');
            if (modal && e.target === modal) {
                closeEditModal();
            }
        });
    </script>
</body>
</html>