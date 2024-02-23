<?php
echo <<<HTML
<div style="font-family: Arial, sans-serif; margin: 20px;">
    <h1 style="color: #333;">Bienvenue sur la Documentation du Plugin CPT FAQ Autocomplete</h1>
    <p>Ce guide rapide vous aidera à démarrer avec les fonctions essentielles du plugin pour enrichir votre site WordPress avec une section FAQ dynamique et interactive.</p>

    <h2 style="color: #0073aa;">Utilisation Basique</h2>
    <p>Le plugin fournit deux shortcodes principaux pour intégrer les fonctionnalités des FAQ directement dans vos pages :</p>
    <ul>
        <li><strong>[cpt_faq_search_form  placeholder="Posez nous votre question..." button_label="Go"]</strong> : Ce shortcode ajoute une barre de recherche des FAQs sur une page. Il permet aux utilisateurs de rechercher des questions fréquemment posées en utilisant l'auto-complétion. Vous pouvez modifier sont contenu interieur ainsi que le texte affiché dans le bouton.</li>
        <li><strong>[cpt_faq_search_results]</strong> : Utilisez ce shortcode pour afficher les résultats de recherche. Il affiche les questions et réponses basées sur la recherche de l'utilisateur.</li>
    </ul>

    <h2 style="color: #0073aa;">Personnalisation du Style</h2>
    <p>Vous pouvez personnaliser l'apparence de vos FAQs en modifiant le CSS. Voici les classes principales :</p>
    <ul>
        <li><strong>accordion-header</strong> : Style pour l'en-tête de chaque question.</li>
        <li><strong>accordion-body</strong> : Style pour le contenu de la réponse.</li>
    </ul>
    <p>Adaptez ces styles dans votre feuille de CSS pour harmoniser l'apparence des accordéons avec le design de votre site.</p>

    <h2 style="color: #0073aa;">Support et Personnalisation Avancée</h2>
    <p>Pour une personnalisation plus poussée ou en cas de questions, référez-vous à la documentation complète incluse dans le dossier de l'extension ou contactez le support technique.</p>
</div>
HTML;
?>
