jQuery(document).ready(function ($) {
  var input = $("#search-faq");

  // Initialisation d'Awesomplete avec une liste vide
  var awesomplete = new Awesomplete(input[0], {
    list: [],
  });

  function recordClick(title, url, searchTerm = "") {
    $.ajax({
      url: faqAjax.ajaxurl,
      type: "POST",
      data: {
        action: "record_faq_click",
        url: url,
        title: title,
        searchTerm: searchTerm,
      },
      success: function (response) {
        console.log("Click enregistré avec succès");
      },
      error: function (error) {
        console.error("Erreur lors de l'enregistrement du clic :", error);
      },
    });
  }

  // Fonction pour mettre à jour les suggestions d'auto-complétion sans recréer Awesomplete
  function updateAutocompleteSuggestions(searchTerm) {
    $.ajax({
      url: faqAjax.ajaxurl,
      type: "POST",
      data: {
        action: "get_faq",
        searchTerm: searchTerm,
      },
      success: function (data) {
        console.log("Réponse reçue:", data);
        var faqs = JSON.parse(data);
        var titlesToUrlsMap = {};
        faqs.forEach(function (faq) {
          titlesToUrlsMap[faq.label] = faq.value;
        });

        // Mise à jour de la liste de suggestions d'Awesomplete
        awesomplete.list = faqs.map(function (faq) {
          return { label: faq.label, value: faq.value };
        });

        // Réinitialisation de la gestion de la sélection d'une suggestion
        awesomplete.replace = function (suggestion) {
          // Appel la fonction pour enregistrer le clic et charger le contenu
          recordClick(suggestion.label, suggestion.value, searchTerm);

          // Charge le contenu de l'article directement sans redirection
          loadArticleContent(suggestion.value);
        };
      },
    });
  }

  function loadArticleContent(url) {
    $.ajax({
      url: faqAjax.ajaxurl,
      type: "POST",
      data: {
        action: "get_faq_content_by_url",
        url: url,
      },
      success: function (response) {
        $("#faq-articles-container").html(response); // Affiche le contenu dans le conteneur
      },
      error: function (error) {
        console.error("Erreur lors du chargement de l'article :", error);
      },
    });
  }

  // Écouteur d'événements pour la saisie dans le champ de recherche
  input.on("input", function () {
    updateAutocompleteSuggestions(this.value);
  });
});

//Gestion de la catégorie de la FAQ et de l'affichage dans le front
jQuery(document).ready(function ($) {
  $("a[data-category-id]").click(function (e) {
    e.preventDefault(); // Empêcher le comportement par défaut du lien
    var categoryId = $(this).data("category-id"); // Récupérer l'ID de la catégorie

    $.ajax({
      url: faqAjax.ajaxurl,
      type: "POST",
      data: {
        action: "get_faq_by_category",
        categoryId: categoryId,
      },
      success: function (response) {
        $("#faq-articles-container").html(response); // Mettre à jour le contenu de la page avec les articles récupérés
      },
    });
  });
});

jQuery(document).ready(function ($) {
  // Gestionnaire unifié pour la soumission du formulaire de recherche
  $("#faq-search-form").submit(function (e) {
    e.preventDefault(); // Empêche le formulaire de recharger la page

    var searchTerm = $("#search-faq").val().trim(); // Récupère le terme de recherche, en supprimant les espaces de début et de fin

    // Vérification pour s'assurer que le terme de recherche n'est pas vide
    if (searchTerm === "") {
      alert("Veuillez entrer un terme de recherche.");
      return; // Arrête l'exécution si le champ de recherche est vide
    }

    // Enregistrement du terme de recherche et recherche des résultats en une seule opération
    recordSearchTermAndDisplayResults(searchTerm);
  });

  // Fonction pour enregistrer le terme de recherche et afficher les résultats
  function recordSearchTermAndDisplayResults(searchTerm) {
    // Première requête AJAX pour enregistrer le terme de recherche
    $.ajax({
      url: faqAjax.ajaxurl,
      type: "POST",
      data: {
        action: "record_search_term",
        searchTerm: searchTerm,
      },
      complete: function () {
        // Peu importe le résultat de l'enregistrement, lancez la recherche
        $.ajax({
          url: faqAjax.ajaxurl,
          type: "POST",
          data: {
            action: "get_faq", 
            searchTerm: searchTerm,
          },
          dataType: "json",
          success: function (faqs) {
            var html = "";
            if (faqs && faqs.length > 0) {
              faqs.forEach(function (faq) {
                // Modifiez cette ligne pour inclure le contenu de chaque FAQ
                html +=
                  '<div class="accordion-header">' +
                  faq.label +
                  "</a></div>" +
                  '<div class="accordion-body"><p>' +
                  faq.content +
                  "</p></div>";
              });
            } else {
              html = "<p>Aucun résultat trouvé.</p>";
            }
            $("#faq-articles-container").html(html);
          },
          error: function () {
            $("#faq-articles-container").html(
              "<p>Erreur lors de la recherche. Veuillez réessayer.</p>"
            );
          },
        });
      },
      error: function (error) {
        console.error(
          "Erreur lors de l'enregistrement du terme de recherche :",
          error
        );
      },
    });
  }
});

document.addEventListener("DOMContentLoaded", function () {
  document.body.addEventListener("click", function (e) {
    // Vérifiez si l'élément cliqué est un en-tête d'accordéon
    if (e.target && e.target.classList.contains("accordion-header")) {
      var accBody = e.target.nextElementSibling;
      // Toggle la visibilité du corps de l'accordéon
      if (accBody.style.display === "block") {
        accBody.style.display = "none";
      } else {
        closeAllAccordionItems();
        accBody.style.display = "block";
      }
    }
  });
});

// Fonction pour fermer tous les corps d'accordéon
function closeAllAccordionItems() {
  document.querySelectorAll(".accordion-body").forEach(function (item) {
    item.style.display = "none";
  });
}