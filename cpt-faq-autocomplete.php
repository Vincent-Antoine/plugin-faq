<?php
/*
Plugin Name: CPT FAQ Autocomplete
Description: Ajoute l'auto-complétion pour le CPT FAQ en utilisant Fuse + permet d'enregistrer les clics sur les FAQ + enregistre les termes de recherche + gère les synonymes
Version: 1.0
Author: TW - Vincent
*/

// Sécurité pour éviter l'exécution directe du script PHP
if (!defined('ABSPATH')) exit;

// Enqueue des scripts et styles nécessaires
function cpt_faq_enqueue_scripts()
{
    // Enregistrement des styles
    wp_enqueue_style('custom-style', plugins_url('css/style.css', __FILE__), array(), '1.0', 'all');
    wp_enqueue_style('bootstrap-css', 'https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css');
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css');

    // Enregistrement des scripts
    wp_enqueue_script('popper-js', 'https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js', array('jquery'), false, true);
    wp_enqueue_script('bootstrap-js', 'https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js', array('jquery', 'popper-js'), false, true);
    wp_enqueue_script('awesomplete-js', 'https://cdnjs.cloudflare.com/ajax/libs/awesomplete/1.1.5/awesomplete.min.js', array('jquery'), false, true);

    // Enregistrement du script principal avec dépendances
    wp_enqueue_script('cpt-faq-autocomplete-script', plugins_url('js/cpt-faq-autocomplete.js', __FILE__), array('jquery', 'awesomplete-js'), null, true);

    // Localisation des variables pour le script principal
    wp_localize_script('cpt-faq-autocomplete-script', 'faqAjax', array('ajaxurl' => admin_url('admin-ajax.php')));
}

// Ajout de l'action pour enregistrer les scripts et les styles
add_action('wp_enqueue_scripts', 'cpt_faq_enqueue_scripts');


//Crée une table dans la BDD qui enregistre les synonymes
function create_table_register_synonymes()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'faq_synonyms';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        term varchar(255) NOT NULL,
        synonyms varchar(255) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'create_table_register_synonymes');


function cpt_faq_search_form_shortcode($atts)
{
    $atts = shortcode_atts(
        array(
            'placeholder' => 'Cherchez dans les FAQ...',
            // 'button_label' => 'Rechercher',
        ),
        $atts,
        'cpt_faq_search_form'

    );

    $placeholder = isset($atts['placeholder']) ? $atts['placeholder'] : 'Cherchez dans les FAQ...';


    // Partie initiale de la barre de recherche
    $form_html = '<form id="faq-search-form" action="' . esc_url(home_url('/')) . '" method="get" class="custom-form">
    <input type="text" id="search" placeholder="' . esc_attr($placeholder) . '" class="custom-searchbar">
    <ul id="resultsList"></ul>
    </form>';

    // Ajout des catégories sous la barre de recherche
    $categories_html = '<ul style="padding: 0; list-style-type: none;" class"list-custom-categorie">';

    // Ajout du bouton "Toutes les catégories"
    $categories_html .= '<li style="display: inline-block; margin-right: 1%;"><a href="#" data-category-id="all">Tout</a></li>';

    $faq_categories = get_categories(array(
        'taxonomy' => 'category',
        'object_type' => 'faq',
    ));
    foreach ($faq_categories as $category) {
        $categories_html .= '<li style="display: inline-block; margin-right: 1%;"><a href="#" data-category-id="' . $category->term_id . '">' . $category->name . '</a></li>';
    }
    $categories_html .= '</ul>';

    // Concaténation des deux parties HTML
    $form_html .= $categories_html;

    return $form_html;
}
add_shortcode('cpt_faq_search_form', 'cpt_faq_search_form_shortcode');


//Création d'un shortcode pour afficher les FAQs en resultats
function cpt_faq_search_results_shortcode($atts)
{
    $atts = shortcode_atts(
        array(
            'title' => 'Résultats de la recherche',
        ),
        $atts,
        'cpt_faq_search_results'
    );

    $results_html = '<div id="faq-articles-container">
    </div>';

    return $results_html;
}
add_shortcode('cpt_faq_search_results', 'cpt_faq_search_results_shortcode');


// Ajoute un menu dans l'administration pour le tableau de bord de l'extension
function cpt_faq_add_admin_menu()
{
    add_menu_page('CPT FAQ Dashboard', 'CPT FAQ', 'manage_options', 'cpt-faq-dashboard', 'cpt_faq_dashboard_content', 'dashicons-welcome-learn-more', 6);
    add_submenu_page('cpt-faq-dashboard', 'Search Terms', 'Search Terms', 'manage_options', 'cpt-faq-search-terms', 'cpt_faq_search_terms_content');
    add_submenu_page('cpt-faq-dashboard', 'Gérer les Synonymes', 'Synonymes', 'manage_options', 'cpt-faq-synonyms', 'cpt_faq_manage_synonyms');
    add_submenu_page('cpt-faq-dashboard', 'FAQ Documentation', 'Documentation', 'manage_options', 'cpt-faq-documentation-view', 'cpt_faq_documentation_content');
}
add_action('admin_menu', 'cpt_faq_add_admin_menu');

function cpt_faq_documentation_content()
{
    echo '<div class="wrap">';
    echo '<h1>Documentation du Plugin FAQ</h1>';
    include('documentation.php');
    echo '</div>';
}

// Récupère les titres des FAQs pour l'auto-complétion
function get_faq()
{
    global $wpdb;
    $searchTerm = isset($_POST['searchTerm']) ? $_POST['searchTerm'] : '';

    // Récupération des synonymes
    $synonymResults = $wpdb->get_row($wpdb->prepare(
        "SELECT term, synonyms FROM {$wpdb->prefix}faq_synonyms WHERE term = %s OR FIND_IN_SET(%s, synonyms)",
        $searchTerm,
        $searchTerm
    ));

    $terms = [$searchTerm]; // Inclure le terme de recherche original

    // Ajout des synonymes à la liste des termes de recherche
    if ($synonymResults && !empty($synonymResults->synonyms)) {
        $synonyms = explode(',', $synonymResults->synonyms);
        $terms = array_merge($terms, $synonyms);
        if ($searchTerm != $synonymResults->term) {
            $terms[] = $synonymResults->term; // Inclure également le terme principal si nécessaire
        }
    }

    $faqs = [];
    foreach ($terms as $term) {
        // Utilisation de LIKE pour une correspondance partielle - Approche de base pour la recherche floue
        $likeTerm = '%' . $wpdb->esc_like(trim($term)) . '%';

        $posts = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_title, post_content, guid FROM {$wpdb->posts} WHERE post_type = 'faq' AND post_status = 'publish' AND (post_title LIKE %s OR post_content LIKE %s)",
            $likeTerm,
            $likeTerm
        ));

        foreach ($posts as $post) {
            if (!array_key_exists($post->ID, $faqs)) { // Éviter les doublons
                $faqs[$post->ID] = [
                    'label' => $post->post_title,
                    'value' => get_permalink($post->ID),
                    'content' => apply_filters('the_content', $post->post_content) // Assurer un formatage correct
                ];
            }
        }
    }

    wp_reset_postdata();
    echo json_encode(array_values($faqs));
    wp_die();
}

add_action('wp_ajax_nopriv_get_faq', 'get_faq');
add_action('wp_ajax_get_faq', 'get_faq');

function cpt_faq_manage_synonyms()
{
    //Je me connecte a la bonne table dans ma BDD
    global $wpdb;
    $table_name = $wpdb->prefix . 'faq_synonyms';

    // Gestion de la soumission du formulaire
    if (isset($_POST['submit'])) {
        $term = sanitize_text_field($_POST['term']);
        $synonyms = sanitize_text_field($_POST['synonyms']);

        //J'insere ou je met a jour les données dans ma table 'faq_synonyms
        $wpdb->replace(
            $table_name,
            [
                'term' => $term,
                'synonyms' => $synonyms
            ],
            //Je spécifie le format des données
            ['%s', '%s']
        );
    }

    // Gestion de la suppression
    if (isset($_POST['delete']) && isset($_POST['id'])) {
        $id = intval($_POST['id']);
        $wpdb->delete($table_name, ['id' => $id], ['%d']);
    }

    // Affichage du formulaire de gestion des synonymes dans le BO
    echo '<div class="wrap">' .
        '<h1>Gérer les Synonymes</h1>' .
        '<form method="post" action="">' .
        '<table>' .
        '<tr><td>Terme :</td><td><input type="text" name="term" required></td></tr>' .
        '<tr><td>Synonymes :</td><td><input type="text" name="synonyms" placeholder="Séparés par des virgules" required></td></tr>' .
        '</table>' .
        '<input type="submit" name="submit" value="Enregistrer">' .
        '</form>';

    // Liste des synonymes existants
    echo '<h2>Liste des Synonymes</h2>';
    //J'affiche grâce a une recherche en BDD tout les synonymes entrés dans la table 'faq_synonyms' et je convertis chaque enregistrement en objet
    $synonyms = $wpdb->get_results("SELECT * FROM $table_name", OBJECT);

    //Si on trouve des synonymes, on les affiche dans un tableau
    if ($synonyms) {
        echo '<table class="widefat"><thead><tr><th>Terme</th><th>Synonymes</th><th>Actions</th></tr></thead><tbody>';
        foreach ($synonyms as $syn) {
            echo '<tr>';
            echo '<td>' . esc_html($syn->term) . '</td>';
            echo '<td>' . esc_html($syn->synonyms) . '</td>';
            echo '<td>
                    <form method="post" action="">
                        <input type="hidden" name="id" value="' . esc_attr($syn->id) . '">
                        <input type="submit" name="delete" value="Supprimer" onclick="return confirm(\'Êtes-vous sûr de vouloir supprimer ce synonyme ?\');">
                    </form>
                  </td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>Aucun synonyme trouvé.</p>';
    }

    echo '</div>';
}

//Gère l'affichage des clics sur les suggestion via autocompletion dans le tableau de bord et la suppression
function cpt_faq_dashboard_content()
{
    if (isset($_POST['delete_click_nonce']) && wp_verify_nonce($_POST['delete_click_nonce'], 'delete_click_action')) {
        if (isset($_POST['delete_click'])) {
            $clicks = get_option('faq_clicks', array());
            $index = $_POST['delete_click'];
            unset($clicks[$index]);
            update_option('faq_clicks', array_values($clicks));
        } elseif (isset($_POST['delete_all_clicks'])) {
            update_option('faq_clicks', array());
        }
    }

    $clicks = get_option('faq_clicks', array());

    // Regrouper les clics par titre
    $groupedClicks = [];
    foreach ($clicks as $index => $click) {
        $title = $click['title'];
        if (!isset($groupedClicks[$title])) {
            $groupedClicks[$title] = ['clicks' => [$click], 'count' => 1];
        } else {
            $groupedClicks[$title]['clicks'][] = $click;
            $groupedClicks[$title]['count']++;
        }
    }

    echo '<div class="wrap"><h2>CPT FAQ Dashboard</h2>';
    echo '<form action="" method="post">';
    wp_nonce_field('delete_click_action', 'delete_click_nonce');

    echo '<table class="widefat"><thead><tr><th>Titre</th><th>URL</th><th>Date/Heure</th><th>Occurrences</th><th>Actions</th></tr></thead><tbody>';

    foreach ($groupedClicks as $title => $group) {
        // Afficher les détails pour le premier clic du groupe
        $firstClick = $group['clicks'][0];
        echo '<tr>';
        echo '<td>' . esc_html($title) . '</td>';
        echo '<td><a href="' . esc_url($firstClick['url']) . '" target="_blank">' . esc_url($firstClick['url']) . '</a></td>';
        echo '<td>' . esc_html($firstClick['time']) . '</td>';
        echo '<td>' . esc_html($group['count']) . '</td>';
        echo '<td><button type="submit" name="delete_click" value="' . array_search($firstClick, $clicks) . '">Supprimer</button></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '<button type="submit" name="delete_all_clicks" class="button">Supprimer Tout</button>';
    echo '</form>';
    echo '</div>';
}



//Gère l'affichage des termes de recherche dans le tableau de bord et la suppression
function cpt_faq_search_terms_content()
{
    // Vérification et traitement de la suppression
    if (isset($_POST['delete_term_nonce']) && wp_verify_nonce($_POST['delete_term_nonce'], 'delete_term_action')) {
        if (isset($_POST['delete_term'])) {
            $searchTerms = get_option('cpt_faq_search_terms', array());
            unset($searchTerms[$_POST['delete_term']]);
            update_option('cpt_faq_search_terms', array_values($searchTerms));
        } elseif (isset($_POST['delete_all_terms'])) {
            update_option('cpt_faq_search_terms', array());
        }
    }

    $searchTerms = get_option('cpt_faq_search_terms', array());

    // Regrouper les termes par terme de recherche
    $groupedTerms = [];
    foreach ($searchTerms as $index => $term) {
        if (!isset($groupedTerms[$term['term']])) {
            $groupedTerms[$term['term']] = ['terms' => [$term], 'count' => 1];
        } else {
            $groupedTerms[$term['term']]['terms'][] = $term;
            $groupedTerms[$term['term']]['count']++;
        }
    }

    echo '<div class="wrap"><h2>Termes Recherchés</h2>';
    echo '<form action="" method="post">';
    wp_nonce_field('delete_term_action', 'delete_term_nonce');

    echo '<table class="widefat"><thead><tr><th>Terme de Recherche</th><th>Occurrences</th><th>Heure</th><th>Action</th></tr></thead><tbody>';

    foreach ($groupedTerms as $term => $group) {
        // Afficher les détails pour le premier terme du groupe
        $firstTerm = $group['terms'][0];
        echo '<tr>';
        echo '<td>' . esc_html($term) . '</td>';
        echo '<td>' . esc_html($group['count']) . '</td>';
        echo '<td>' . esc_html($firstTerm['time']) . '</td>';
        echo '<td><button type="submit" name="delete_term" value="' . array_search($firstTerm, $searchTerms) . '">Supprimer</button></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '<button type="submit" name="delete_all_terms" class="button">Supprimer Tout</button>';
    echo '</form></div>';
}


// Enregistre un clic sur une FAQ
function record_faq_click()
{
    if (isset($_POST['url'], $_POST['title'])) {
        $url = sanitize_text_field($_POST['url']);
        $title = sanitize_text_field($_POST['title']);
        $time = current_time('mysql');

        $clicks = get_option('faq_clicks', array());
        $clicks[] = array(
            'url' => $url,
            'title' => $title,
            'time' => $time,
        );
        update_option('faq_clicks', $clicks);

        wp_send_json_success(array('message' => 'Click enregistré'));
    }
}
add_action('wp_ajax_record_faq_click', 'record_faq_click');
add_action('wp_ajax_nopriv_record_faq_click', 'record_faq_click');


// Enregistre les termes de recherche sur le back office
function record_search_terms_on_access()
{
    if (is_search()) { // Vérifie si la requête actuelle est une recherche
        $searchTerm = get_query_var('s'); // Récupère le terme de recherche
        $time = current_time('mysql'); // Obtient l'heure actuelle
        $searchTerms = get_option('cpt_faq_search_terms', array()); // Récupère les termes de recherche existants ou initialise un tableau vide

        // Ajoute le nouveau terme de recherche
        $searchTerms[] = array(
            'term' => sanitize_text_field($searchTerm), // Nettoie le terme de recherche pour la sécurité
            'time' => $time
        );

        update_option('cpt_faq_search_terms', $searchTerms); // Met à jour l'option avec le nouveau tableau de termes de recherche
    }
}
add_action('template_redirect', 'record_search_terms_on_access');


//Gestion des catégories qui sont liée au CPT FAQ
add_action('wp_ajax_get_faq_by_category', 'handle_get_faq_by_category');
add_action('wp_ajax_nopriv_get_faq_by_category', 'handle_get_faq_by_category');

function handle_get_faq_by_category()
{
    $response = ''; // Initialiser une variable pour stocker la réponse HTML

    // Vérifier si categoryId est présent et équivaut à 'all'
    if (isset($_POST['categoryId']) && $_POST['categoryId'] === 'all') {
        $args = array(
            'post_type' => 'faq',
            'posts_per_page' => -1, // Pour obtenir tous les posts
        );
    } else {
        $categoryId = $_POST['categoryId'];
        $args = array(
            'post_type' => 'faq',
            'tax_query' => array(
                array(
                    'taxonomy' => 'category', // Assurez-vous que ceci correspond à la taxonomie correcte pour vos FAQs
                    'field'    => 'term_id',
                    'terms'    => $categoryId,
                ),
            ),
        );
    }

    $query = new WP_Query($args);

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            // Utilisez ici la mise en forme spécifique pour chaque FAQ
            $response .= '<div class="accordion-item">';
            $response .= '<div class="accordion-header">' . esc_html(get_the_title()) . '</div>';
            $response .= '<div class="accordion-body">' . apply_filters('the_content', get_the_content()) . '</div>';
            $response .= '</div>';
        }
    } else {
        $response = 'Aucune FAQ trouvée.';
    }

    wp_reset_postdata();
    echo $response; // Envoyer la réponse
    wp_die(); // Arrêter correctement la requête AJAX
}

// Enregistre le terme de recherche
function handle_record_search_term()
{
    if (isset($_POST['searchTerm'])) {
        $search_term = sanitize_text_field($_POST['searchTerm']);
        // Logique pour enregistrer le terme de recherche
        $searchTerms = get_option('cpt_faq_search_terms', array());
        $searchTerms[] = array(
            'term' => $search_term,
            'time' => current_time('mysql'),
        );
        update_option('cpt_faq_search_terms', $searchTerms);

        wp_send_json_success(array('message' => 'Terme de recherche enregistré'));
    } else {
        wp_send_json_error(array('message' => 'Terme de recherche non fourni'));
    }
    wp_die();
}
add_action('wp_ajax_record_search_term', 'handle_record_search_term');
add_action('wp_ajax_nopriv_record_search_term', 'handle_record_search_term');

// Gestion de la récupération du contenu de l'article par URL quand on clique sur la suggestion de l'autocomplétion
function handle_get_faq_content_by_url()
{
    // Initialisation de la variable de réponse
    $response = '';

    // Verifie et nettoie l'URL reçue
    $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';

    // Utilise l'URL pour récupérer le contenu de l'article
    $post_id = url_to_postid($url);
    if ($post_id) {
        $post = get_post($post_id);
        if ($post) {
            $title = esc_html(get_the_title($post_id));
            $content = apply_filters('the_content', $post->post_content);

            // Construction de la réponse
            $response .= '<div class="accordion-header">' . $title . '</div>';
            $response .= '<div class="accordion-body">' . $content . '</div>';
        } else {
            $response = 'Article non trouvé.';
        }
    }

    // Retourne la réponse
    echo $response;

    wp_die();
}
add_action('wp_ajax_get_faq_content_by_url', 'handle_get_faq_content_by_url');
add_action('wp_ajax_nopriv_get_faq_content_by_url', 'handle_get_faq_content_by_url');


if (!function_exists('create_faq_post_type')) {
    function create_faq_post_type()
    {
        register_post_type(
            'faq',
            array(
                'labels' => array(
                    'name' => __('FAQs', 'wp-faq-section-creator'),
                    'singular_name' => __('FAQ', 'wp-faq-section-creator'),
                ),
                'public' => true,
                'has_archive' => true,
                'rewrite' => array('slug' => 'faqs'),
                'show_in_rest' => true, // Pour Gutenberg editor
                'menu_icon' => 'dashicons-editor-help',
                'supports' => array('title', 'editor', 'thumbnail', 'excerpt', 'comments'),
                'taxonomies' => array('category'), // Pour prendre en charge les catégories
            )
        );
    }
    add_action('init', 'create_faq_post_type');
}

//Permet de créer une taxonomie pour les FAQs
if (!function_exists('add_faq_taxonomies')) {
    function add_faq_taxonomies()
    {
        register_taxonomy_for_object_type('category', 'faq');
    }
    add_action('init', 'add_faq_taxonomies');
}

//Permet de créer une page d'archive pour les FAQs
if (!function_exists('add_faq_to_category_archives')) {
    function add_faq_to_category_archives($query)
    {
        if (!is_admin() && $query->is_main_query() && (is_category() || is_tag())) {
            $query->set('post_type', array('post', 'faq')); // Inclut les FAQs dans les archives de catégories/tags
        }
    }
    add_action('pre_get_posts', 'add_faq_to_category_archives');
}


//Permet de refresh les permalinks a chaque création d'une nouvelle FAQ
if (!function_exists('refresh_faq_permalinks_on_publish')) {
    function refresh_faq_permalinks_on_publish($post_id, $post)
    {
        if ('faq' === $post->post_type) {
            flush_rewrite_rules();
        }
    }
    add_action('wp_insert_post', 'refresh_faq_permalinks_on_publish', 10, 2);
}


function enqueue_fuzzy_search_scripts()
{
    global $wpdb;

    // Préparation de la requête pour récupérer le titre et le contenu des posts FAQ
    $query = $wpdb->prepare(
        "SELECT ID, post_title, post_content FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
        'faq',
        'publish'
    );
    $posts = $wpdb->get_results($query);

    if (null === $posts) {
        error_log('Erreur lors de la récupération des FAQs');
        return;
    }

    $data = array_map(function ($post) {
        // Appliquer le filtre 'the_content' pour s'assurer que le contenu est correctement formaté
        $contentFormatted = apply_filters('the_content', $post->post_content);

        return [
            'id' => $post->ID,
            'titre' => esc_html($post->post_title), // Sécurisation de l'affichage
            'contenu' => esc_html(wp_strip_all_tags($contentFormatted)), // Formater et sécuriser le contenu
            'lien' => esc_url(get_permalink($post->ID)), // Sécurisation de l'URL
        ];
    }, $posts);

    // Enregistrement et mise en file des scripts nécessaires
    if (!wp_script_is('fuse-js', 'enqueued')) {
        wp_enqueue_script('fuse-js', 'https://cdn.jsdelivr.net/npm/fuse.js/dist/fuse.min.js', array(), null, true);
    }

    // Remplacer 'path/to/your-custom-script.js' par le chemin réel vers votre script JS personnalisé
    $script_handle = 'custom-fuzzy-search';
    wp_register_script($script_handle, get_template_directory_uri(), array('fuse-js'), null, true);
    wp_enqueue_script($script_handle);

    // Passer les données au script JS
    wp_localize_script($script_handle, 'fuzzyData', array('posts' => $data));
}
add_action('wp_enqueue_scripts', 'enqueue_fuzzy_search_scripts');
