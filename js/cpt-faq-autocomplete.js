document.addEventListener("DOMContentLoaded", function () {
  // Initialisation de Fuse
  const fuseOptions = {
    keys: ["titre", "contenu"],
    threshold: 0.4,
  };
  const fuse = new Fuse(fuzzyData.posts, fuseOptions);

  // Surligne les termes de recherche dans les résultats
  function highlightMatch(text, term) {
    const startIndex = text.toLowerCase().indexOf(term.toLowerCase());
    if (startIndex === -1) return text;

    const endIndex = startIndex + term.length;
    return (
      text.substring(0, startIndex) +
      "<span style='background-color: yellow;'>" +
      text.substring(startIndex, endIndex) +
      "</span>" +
      text.substring(endIndex)
    );
  }

  // Gestionnaire d'événement pour la saisie dans la barre de recherche
  const searchInput = document.getElementById("search");
  const resultsList = document.getElementById("resultsList");

  if (searchInput && resultsList) {
    searchInput.addEventListener("input", function () {
      const searchTerm = this.value;
      const results = fuse.search(searchTerm);
      resultsList.innerHTML = "";

      results.forEach((result) => {
        const li = document.createElement("li");
        const highlightedTitle = highlightMatch(result.item.titre, searchTerm);
        li.innerHTML = `<a href="${result.item.lien}" data-title="${result.item.titre}">${highlightedTitle}</a>`;
        resultsList.appendChild(li);
      });
    });
  } else {
  }

  // Fonction setupClickListener
  function setupClickListener() {
    document.addEventListener("click", function (e) {
      let target = e.target;
      while (target != null && !target.classList.contains("faq-link")) {
        target = target.parentNode;
        if (target === document) {
          break;
        }
      }
      if (target && target.classList.contains("faq-link")) {
        const url = target.href;
        const title = target.getAttribute("data-title");
        const searchTerm = document.getElementById("search").value;
        e.preventDefault();
        recordClick(title, url, searchTerm);
      }
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", setupClickListener);
  } else {
    setupClickListener();
  }

  // Enregistre les clics dans la base de données pour les liens cliqués dans la suggestion
  function recordClick(title, url, searchTerm) {
    jQuery.ajax({
      url: faqAjax.ajaxurl,
      type: "POST",
      data: {
        action: "record_faq_click",
        url: url,
        title: title,
        searchTerm: searchTerm,
      },
      success: function (response) {
        if (response && response.html) {
          $("#faq-articles-container").html(response.html);
        }
      },
    });
    loadArticleContent(url);
  }

  function loadArticleContent(url) {
    jQuery.ajax({
      url: faqAjax.ajaxurl,
      type: "POST",
      data: {
        action: "get_faq_content_by_url",
        url: url,
      },
      success: function (response) {
        jQuery("#faq-articles-container").html(response);
      },
    });
  }

  // Récupère le contenu des FAQ basé sur la catégorie cliquée, puis affiche ce contenu dans un conteneur spécifique sans recharger la page.
  jQuery(document).ready(function ($) {
    $("a[data-category-id]").click(function (e) {
      e.preventDefault();
      var categoryId = $(this).data("category-id");

      $.ajax({
        url: faqAjax.ajaxurl,
        type: "POST",
        data: {
          action: "get_faq_by_category",
          categoryId: categoryId,
        },
        success: function (response) {
          $("#faq-articles-container").html(response);
        },
      });
    });
    $("a[data-category-id='all']").trigger("click");
  });

  // Empêche le formulaire de s'envoyer normalement et traite la recherche via AJAX + enregistre le terme recherché
  jQuery(document).ready(function ($) {
    $("#faq-search-form").submit(function (e) {
      e.preventDefault();

      var searchTerm = $("#search").val().trim();
      if (searchTerm === "") {
        alert("Veuillez entrer un terme de recherche.");
        return;
      }

      recordSearchTermAndDisplayResults(searchTerm);
    });

    function recordSearchTermAndDisplayResults(searchTerm) {
      $.ajax({
        url: faqAjax.ajaxurl,
        type: "POST",
        data: {
          action: "record_search_term",
          searchTerm: searchTerm,
        },
        complete: function () {
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
          });
        },
      });
    }
  });

  // Ajoute la fonction pour fermer les accordéons
  function closeAllAccordionItems() {
    document.querySelectorAll(".accordion-body").forEach(function (item) {
      item.style.display = "none";
    });
  }

  // Ajoute la fonction pour fermer les accordéons
  document.body.addEventListener("click", function (e) {
    if (e.target && e.target.classList.contains("accordion-header")) {
      var accBody = e.target.nextElementSibling;
      if (accBody.style.display === "block") {
        accBody.style.display = "none";
      } else {
        closeAllAccordionItems();
        accBody.style.display = "block";
      }
    }
  });

  // Ajoute la fonction pour fermer la liste de résultats lorsqu'on clique en dehors d'elle
  function setupEventListeners() {
    var ul = document.getElementById("resultsList");
    var searchInput = document.getElementById("search");

    if (ul && searchInput) {
      document.addEventListener("click", function (e) {
        var targetElement = e.target;

        if (!ul.contains(targetElement) && targetElement !== searchInput) {
          ul.style.setProperty("display", "none", "important");
        }
      });

      // Fermer la liste lorsque l'utilisateur clique en dehors de la liste ou sur la zone de recherche
      searchInput.addEventListener("click", function (e) {
        ul.style.removeProperty("display");
      });
    } else {
    }

    // Fermer la liste lorsque l'utilisateur clique sur un lien .faq-link
    document.querySelectorAll(".faq-link").forEach(function (link) {
      link.addEventListener("click", function (e) {
        e.stopPropagation();
      });
    });
  }

  // Appeler setupEventListeners directement si votre script est placé en bas du <body>
  setupEventListeners();

  setupEventListeners();

  // Ajoute des classes aux liens
  function ajouterClasseAuxLiens() {
    const liens = document.querySelectorAll("#resultsList li a");

    liens.forEach((lien) => {
      if (!lien.classList.contains("faq-link")) {
        lien.classList.add("faq-link");
      }
    });
  }

  const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      if (mutation.type === "childList") {
        ajouterClasseAuxLiens();
      }
    });
  });

  const config = { childList: true, subtree: true };

  const targetNode = document.getElementById("resultsList");

  observer.observe(targetNode, config);

  ajouterClasseAuxLiens();
});

document.addEventListener("click", function (event) {
  // Vérifier si l'élément cliqué ou l'un de ses parents a la classe 'faq-link'
  var target = event.target;
  while (target && target !== this) {
    if (target.matches("a.faq-link")) {
      var resultsList = document.getElementById("resultsList");
      if (resultsList) {
        resultsList.setAttribute("style", "display: none !important;");
      }
      break;
    }
    target = target.parentNode;
  }
});

window.addEventListener("resize", adjustWidth);

function adjustWidth() {
  var searchWidth = document.getElementById("search").offsetWidth;
  document.getElementById("resultsList").style.width = searchWidth + "px";
}

// Appel initial pour fixer la largeur
adjustWidth();
