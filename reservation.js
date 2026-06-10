document.addEventListener("DOMContentLoaded", function () {
    // Récupère les champs du formulaire
    const inputDate = document.getElementById("date");
    const selectCreneau = document.getElementById("creneau");

    // Réinitialise la liste des créneaux avec un message par défaut
    function resetCreneaux(message = "Sélectionnez un créneau") {
        selectCreneau.innerHTML = `<option value="">${message}</option>`;
    }

    // Vérifie que les éléments existent bien dans la page
    if (!inputDate || !selectCreneau) {
        console.error("Champs #date ou #creneau introuvables dans le DOM.");
        return;
    }

    // Recharge les créneaux quand l'utilisateur change la date
    inputDate.addEventListener("change", function () {
        const dateChoisie = inputDate.value;

        // Affiche un message temporaire pendant le chargement
        resetCreneaux("Chargement des créneaux...");

        if (!dateChoisie) {
            resetCreneaux("Sélectionnez un créneau");
            return;
        }

        // Appelle le fichier PHP qui renvoie les créneaux disponibles en JSON
        fetch("get_creneaux.php?date=" + encodeURIComponent(dateChoisie))
            .then(response => response.json())
            .then(data => {
                // Vérifie si la réponse serveur est valide
                if (!data.success) {
                    console.error(data.message || "Erreur côté serveur");
                    resetCreneaux("Aucun créneau disponible");
                    return;
                }

                const creneaux = data.creneaux;

                // Réinitialise la liste avant d'ajouter les nouveaux créneaux
                resetCreneaux();

                if (!Array.isArray(creneaux) || creneaux.length === 0) {
                    resetCreneaux("Aucun créneau disponible");
                    return;
                }

                // Ajoute chaque créneau dans le select avec le nombre de places restantes
                creneaux.forEach(creneau => {
                    const option = document.createElement("option");
                    const restantes = parseInt(creneau.restantes, 10);

                    // Valeur envoyée au PHP lors de la soumission du formulaire
                    option.value = creneau.heure;

                    let textePlaces = "";

                    // Adapte l'affichage selon le nombre de places restantes
                    if (restantes === 0) {
                        textePlaces = "Complet";
                        option.disabled = true;
                    } else if (restantes === 1) {
                        textePlaces = "1 place restante";
                    } else {
                        textePlaces = `${restantes} places restantes`;
                    }

                    option.textContent = `${creneau.heure_affichee} - ${textePlaces}`;
                    selectCreneau.appendChild(option);
                });
            })
            .catch(error => {
                // Gère les erreurs de chargement AJAX
                console.error("Erreur lors du chargement des créneaux :", error);
                resetCreneaux("Erreur de chargement");
            });
    });
});