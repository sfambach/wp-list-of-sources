<?php
/**
 * Plugin Name: WP List of Sources
 * Description: Automatically extracts and displays a list of used links, pictures, and tables in the current post with flexible block settings.
 * Version: 1.2.0
 * Author: Ihr Name
 * Text Domain: wp-list-of-sources
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Removes http://, https:// and optional www.
*/
function getCleanDomainAndPath($url) {
    return preg_replace('/^https?:\/\/(www\.)?/', '', $url);
}

/** check if url is already in a given array
*/
function isUrlInArray(array $links_data, string $url): bool {
    // Extracts all 'url' values and checks if the given URL exists
    return in_array($url, array_column($links_data, 'url'));
}

function wpls_load_textdomain() {
    load_plugin_textdomain( 'wp-list-of-sources', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'wpls_load_textdomain' );

function wpls_editor_styles() {
    echo '<style>
        .wpls-minimal-input .components-base-control__field { margin-bottom: 0 !important; }
        .wpls-minimal-input input.components-text-control__input,
        .wpls-minimal-input select.components-select-control__input { 
            height: 28px !important; min-height: 28px !important; padding: 2px 8px !important; font-size: 13px !important; 
        }
    </style>';
}
add_action( 'admin_head', 'wpls_editor_styles' );

function wpls_frontend_styles() {
    echo '<style>
        html { scroll-behavior: smooth; }
        .wpls-sources-list a, .wp-block-sources-table a { text-decoration: none; }
        .wpls-sources-list a:hover, .wp-block-sources-table a:hover { text-decoration: underline; }
    </style>';
}
add_action( 'wp_head', 'wpls_frontend_styles' );

function wpls_register_sources_blocks() {
    register_block_type( 'wpls/sources-table', [
        'editor_script'   => 'wpls-blocks-js',
        'render_callback' => 'wpls_render_sources_table',
        'attributes'      => [
            'titleSources'   => [ 'type' => 'string', 'default' => 'Quellen' ],
            'titleImages'    => [ 'type' => 'string', 'default' => 'Bilder' ],
            'titleTables'    => [ 'type' => 'string', 'default' => 'Tabellen' ],
            'titleFiles'     => [ 'type' => 'string', 'default' => 'Dateien' ],
            'headingTag'     => [ 'type' => 'string', 'default' => 'h3' ],
            'displayFormat'  => [ 'type' => 'string', 'default' => 'table' ],
            'stripUrlPrefix' => [ 'type' => 'boolean', 'default' => true ],
            'align'          => [ 'type' => 'string', 'default' => '' ],
            'className'      => [ 'type' => 'string', 'default' => '' ],
        ],
    ]);
    if ( function_exists( 'register_block_style' ) ) {
        register_block_style( 'wpls/sources-table', [ 'name' => 'default', 'label' => __( 'Default', 'wp-list-of-sources' ), 'is_default' => true ] );
        register_block_style( 'wpls/sources-table', [ 'name' => 'stripes', 'label' => __( 'Stripes', 'wp-list-of-sources' ) ] );
    }
}
add_action( 'init', 'wpls_register_sources_blocks' );

function wpls_enqueue_block_editor_assets() {
    wp_enqueue_script( 'wpls-blocks-js', plugins_url( 'blocks.js', __FILE__ ), [ 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-data', 'wp-block-editor', 'wp-i18n' ], filemtime( plugin_dir_path( __FILE__ ) . 'blocks.js' ) );
    wp_set_script_translations( 'wpls-blocks-js', 'wp-list-of-sources', plugin_dir_path( __FILE__ ) . '/languages' );
}
add_action( 'enqueue_block_editor_assets', 'wpls_enqueue_block_editor_assets' );

function wpls_sort_sources($a, $b) {
    // FIX: Statt zu raten ("hat der Titel sich vom Fallback-Domainnamen unterschieden?"),
    // wird jetzt ein explizites 'has_title'-Flag verwendet, das beim Einlesen der Links/Bilder
    // gesetzt wird. Das ist zuverlässig, weil ein bereinigter Domain-Fallback (kein echter Titel)
    // sich sonst leicht als "echter Titel" tarnen konnte.
    $a_has_title = !empty($a['has_title']);
    $b_has_title = !empty($b['has_title']);

    if ($a_has_title && !$b_has_title) return -1; // Einträge mit echtem Titel/Text nach oben
    if (!$a_has_title && $b_has_title) return 1;  // Einträge ohne Titel/Text nach unten
    return strcasecmp($a['title'], $b['title']);   // Alphabetischer Fallback (case-insensitive)
}

// Hilfsfunktion: Eliminiert doppelte URLs und bevorzugt Einträge mit Titel (da bereits vorsortiert)
function wpls_filter_unique_urls($sources_array) {
    if (empty($sources_array)) return [];

    // FIX: Dedupliziert jetzt über eine NORMALISIERTE URL (ohne http/https/www, ohne
    // abschließenden Slash), damit http://example.com, https://example.com und
    // https://www.example.com/ als derselbe Link erkannt werden. Da die Liste vorher
    // sortiert wurde (Einträge mit echtem Titel zuerst), gewinnt bei Duplikaten automatisch
    // die Variante mit Titel.
    $seen = [];
    $result = [];
    foreach ($sources_array as $item) {
        $key = isset($item['norm_url']) ? $item['norm_url'] : strtolower($item['url']);
        if (isset($seen[$key])) { continue; }
        $seen[$key] = true;
        $result[] = $item;
    }
    return $result;
}

/** Normalisiert eine URL für den Duplikat-Vergleich: kleingeschrieben, ohne http(s)/www, ohne Slash am Ende. */
function wpls_normalize_url_for_dedupe($url) {
    return strtolower(rtrim(getCleanDomainAndPath($url), '/'));
}

function wpls_render_sources_table( $attributes, $content ) {
    $post_id = false;
    if (defined('REST_REQUEST') && REST_REQUEST && !empty($_GET['post_id'])) { $post_id = intval($_GET['post_id']); }
    if (!$post_id) { $post_id = get_the_ID(); }
    $post = $post_id ? get_post($post_id) : null;
    $is_template_preview = ( ! $post || $post->post_type === 'wp_block' || $post->post_type === 'wp_template' || $post_id === 0 );

    $title_sources = !empty($attributes['titleSources']) ? esc_html($attributes['titleSources']) : 'Quellen';
    $title_images  = !empty($attributes['titleImages']) ? esc_html($attributes['titleImages']) : 'Bilder';
    $title_tables  = !empty($attributes['titleTables']) ? esc_html($attributes['titleTables']) : 'Tabellen';
    $title_files   = !empty($attributes['titleFiles']) ? esc_html($attributes['titleFiles']) : 'Dateien';
    $display_format = !empty($attributes['displayFormat']) ? $attributes['displayFormat'] : 'table';
    // FIX: Schalter zum Entfernen von http(s):// und www. aus den angezeigten Link-Labels (Default: an)
    $strip_url_prefix = array_key_exists('stripUrlPrefix', $attributes) ? (bool) $attributes['stripUrlPrefix'] : true;
    $allowed_tags  = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div'];
    $heading_tag   = in_array($attributes['headingTag'], $allowed_tags) ? $attributes['headingTag'] : 'h3';
    $table_style_class = ( !empty($attributes['className']) && strpos( $attributes['className'], 'is-style-stripes' ) !== false ) ? 'is-style-stripes' : '';
    // Datei-Endungen, die als eigene "Dateien"-Kategorie statt bei den normalen Quellen gelistet werden
    $file_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'rar', '7z', 'csv', 'txt'];

    $wrapper_classes = [ 'wp-block-table', 'wp-block-sources-table' ];
    if ( ! empty( $attributes['align'] ) ) $wrapper_classes[] = 'align' . $attributes['align'];
    if ( ! empty( $attributes['className'] ) ) $wrapper_classes[] = $attributes['className'];
    $wrapper_class_str = implode( ' ', array_map( 'sanitize_html_class', $wrapper_classes ) );

    if ( $is_template_preview ) {
        return wpls_render_template_dummy_preview($wrapper_class_str, $title_sources, $title_images, $title_tables, $title_files, $heading_tag, $table_style_class, $display_format);
    }

    // FIX: Cache-Key enthält jetzt eine Versionsnummer (Zeitstempel), die bei jedem Speichern
    // des Beitrags hochgezählt wird (siehe wpls_clear_post_transients). Dadurch wird der alte
    // Cache-Eintrag beim Speichern automatisch "ungültig", ohne dass er aktiv gelöscht werden
    // muss – das funktioniert zuverlässig auch dann, wenn die Seite einen persistenten
    // Object-Cache (Redis/Memcached) nutzt, wo ein direktes Löschen in der Datenbank ins Leere läuft.
    $content_version = get_post_meta( $post_id, '_wpls_content_version', true );
    if ( empty( $content_version ) ) { $content_version = '0'; }
    $cache_key = 'wpls_cache_' . $post_id . '_' . $content_version . '_' . md5(serialize($attributes));
    $cached_output = get_transient($cache_key);
    if ( $cached_output !== false ) { return $cached_output; }

    $html = $post->post_content;
    if (empty(trim($html))) { return '<p style="font-style:italic; color:#666;">' . esc_html__( 'No content found to analyze.', 'wp-list-of-sources' ) . '</p>'; }

    // BESSERER FIX: Vorherigen Zustand speichern & Interne LibXML-Fehler aktivieren
    $previous_error_state = libxml_use_internal_errors(true);
    
    $dom = new DOMDocument();
    
    // HTML mit HTML5-Kompatibilitätsflags laden, um Gutenberg-Kommentarfehler abzufangen
    $dom->loadHTML(
        '<?xml encoding="utf-8" ?>' . trim($html), 
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    
    // Fehlerpuffer sofort leeren und alten Zustand wiederherstellen
    libxml_clear_errors();
    libxml_use_internal_errors($previous_error_state);

    $links_data = []; $images_data = []; $tables_data = [];

    $files_data = [];
    $links = $dom->getElementsByTagName('a');
    foreach ($links as $link) {
        $url = $link->getAttribute('href'); 
        $title = trim($link->getAttribute('title')); 
        $text = trim($link->textContent);
        
        // leere URLs und Anker entfernen
        if (empty($url) || strpos($url, '#') === 0) continue;
        
        if ( true === WP_DEBUG ) {
            echo ' '.$url.' ';
        }

        // Datei-Erkennung schon hier, da Datei-Links eine andere Titel-Priorität bekommen
        $path_only = parse_url($url, PHP_URL_PATH);
        $extension = $path_only ? strtolower(pathinfo($path_only, PATHINFO_EXTENSION)) : '';
        $is_file = in_array($extension, $file_extensions, true);
        
        // FIX: has_title merkt sich, ob es einen ECHTEN Titel/Linktext gab (für die Sortierung),
        // statt das hinterher aus dem Ergebnis-String zu raten.
        $has_real_title = false;
        if ( $is_file ) {
            // FIX: Bei Datei-Links ist der sichtbare Linktext oft nur ein generischer Klicktext
            // ("Herunterladen", "Download" ...) und identisch für alle Dateien - damit lassen sich
            // einzelne Dateien nicht unterscheiden. Deshalb hat hier das title-Attribut Vorrang,
            // danach der tatsächliche Dateiname aus der URL statt des Linktexts.
            if (!empty($title)) {
                $final_title = $title;
                $has_real_title = true;
            } else {
                $filename = $path_only ? urldecode(basename($path_only)) : '';
                $final_title = !empty($filename) ? $filename : $url;
            }
        } else if (!empty($title)) {
            $final_title = $title;
            $has_real_title = true;
        } else if (!empty($text)) {
            $final_title = $text;
            $has_real_title = true;
        } else {
            $final_title = getCleanDomainAndPath($url);
        }
        
        $final_title = trim($final_title);

        // FIX: schaltbares Entfernen von http(s)/www aus dem angezeigten Label – greift sowohl
        // beim Domain-Fallback als auch, wenn der Linktext selbst nur die rohe URL ist.
        if ( $strip_url_prefix && preg_match('#^https?://#i', $final_title) ) {
            $final_title = getCleanDomainAndPath($final_title);
        }

        $norm_url = wpls_normalize_url_for_dedupe($url);
        $entry = [
            'url'       => esc_url($url),
            'title'     => esc_html($final_title),
            'has_title' => $has_real_title,
            'norm_url'  => $norm_url,
        ];

        // FIX: Datei-Links (PDF, DOCX, ZIP, ...) landen in einer eigenen "Dateien"-Kategorie
        if ( $is_file ) {
            $files_data[] = $entry;
        } else {
            $links_data[] = $entry;
        }
    }
    usort($links_data, 'wpls_sort_sources');
    $links_data = wpls_filter_unique_urls($links_data);
    usort($files_data, 'wpls_sort_sources');
    $files_data = wpls_filter_unique_urls($files_data);

    $images = $dom->getElementsByTagName('img');
    foreach ($images as $img) {
        $url = $img->getAttribute('src'); $alt = trim($img->getAttribute('alt')); $title = trim($img->getAttribute('title'));
        if (empty($url)) continue;
        $has_real_title = false;
        if (empty($alt) && empty($title)) {
            $final_title = basename(parse_url($url, PHP_URL_PATH));
            if (empty($final_title)) { $final_title = $url; }
        } else {
            $final_title = !empty($alt) ? $alt : $title;
            $has_real_title = true;
        }
        $images_data[] = [
            'url'       => esc_url($url),
            'title'     => esc_html($final_title),
            'has_title' => $has_real_title,
            'norm_url'  => wpls_normalize_url_for_dedupe($url),
        ];
    }
    usort($images_data, 'wpls_sort_sources');
    $images_data = wpls_filter_unique_urls($images_data);

    $tables = $dom->getElementsByTagName('table');
    $table_count = 1;
    foreach ($tables as $table) {
        $t_title = ''; $anchor_id = $table->getAttribute('id'); $parent = $table->parentNode;
        if (empty($anchor_id) && $parent && $parent->nodeName === 'figure') { $anchor_id = $parent->getAttribute('id'); }
        if ($parent && $parent->nodeName === 'figure') {
            $figcaptions = $parent->getElementsByTagName('figcaption');
            if ($figcaptions->length > 0) { $t_title = trim($figcaptions->item(0)->textContent); }
        }
        if (empty($t_title)) {
            $captions = $table->getElementsByTagName('caption');
            if ($captions->length > 0) { $t_title = trim($captions->item(0)->textContent); }
        }
        if (empty($t_title)) { $t_title = sprintf(__('Table %d', 'wp-list-of-sources'), $table_count); }
        $tables_data[] = [ 'title' => esc_html($t_title), 'anchor_id' => esc_attr($anchor_id) ];
        $table_count++;
    }
    
    $output = '<div class="' . $wrapper_class_str . '" style="margin-top: 30px; font-family: sans-serif;">';
    $sections = [
        ['title' => $title_sources, 'data' => $links_data, 'type' => 'links', 'empty' => __('Keine Links in diesem Beitrag gefunden.', 'wp-list-of-sources')],
        ['title' => $title_images,  'data' => $images_data, 'type' => 'images', 'empty' => __('Keine Bilder in diesem Beitrag gefunden.', 'wp-list-of-sources')],
        ['title' => $title_tables,  'data' => $tables_data, 'type' => 'tables', 'empty' => __('Keine Tabellen in diesem Beitrag gefunden.', 'wp-list-of-sources')],
        // NEU: eigene Kategorie für Datei-Links (PDF, DOCX, ZIP, ...), getrennt von normalen Quellen
        ['title' => $title_files,  'data' => $files_data, 'type' => 'files', 'empty' => __('Keine Dateien in diesem Beitrag gefunden.', 'wp-list-of-sources')]
    ];
    global $wpls_generated_anchors;

    foreach ($sections as $section) {
        $output .= sprintf('<%1$s class="wp-block-heading" style="margin-top:25px; margin-bottom:10px;">%2$s</%1$s>', $heading_tag, $section['title']);
        if (!empty($section['data'])) {
            if ($display_format === 'list') { $output .= '<ul class="wpls-sources-list" style="margin:0 0 20px 0; padding-left:20px;">'; }
            else { $output .= sprintf('<table class="%s"><tbody>', $table_style_class); }

            foreach ($section['data'] as $index => $item) {
                $item_html = '';
                if ($section['type'] === 'tables') {
                    $final_anchor = (!empty($wpls_generated_anchors[$index])) ? $wpls_generated_anchors[$index] : (!empty($item['anchor_id']) ? $item['anchor_id'] : '');
                    if (empty($final_anchor)) { $final_anchor = 'wpls-table-' . ($index + 1); }
                    $item_html = sprintf('<a href="#%s">%s</a>', $final_anchor, $item['title']);
                } else { $item_html = sprintf('<a href="%s" target="_blank" rel="noopener">%s</a>', $item['url'], $item['title']); }

                if ($display_format === 'list') { $output .= sprintf('<li style="margin-bottom:5px;">%s</li>', $item_html); }
                else { $output .= sprintf('<tr><td>%s</td></tr>', $item_html); }
            }
            if ($display_format === 'list') { $output .= '</ul>'; } else { $output .= '</tbody></table>'; }
        } else { $output .= sprintf('<p style="font-style:italic; color:#888; margin-bottom:20px;">%s</p>', $section['empty']); }
    }
    $output .= '</div>';
    set_transient($cache_key, $output, 12 * HOUR_IN_SECONDS);
    return $output;
}

function wpls_render_template_dummy_preview($wrapper_class, $ts, $ti, $tt, $tf, $tag, $style, $display_format = 'table') {
    $output = '<div class="' . $wrapper_class . '" style="border: 1px dashed #ccc; padding: 15px; background: #fafafa; font-family: sans-serif;">';
    $output .= '<span style="display:block; font-size:11px; color:#999; text-transform:uppercase; margin-bottom:10px;">' . esc_html__('Table Live Preview (Template Mode):', 'wp-list-of-sources') . '</span>';
    $output .= sprintf('<%1$s class="wp-block-heading" style="margin: 0 0 5px 0;">%2$s</%1$s>', $tag, $ts);
    if ($display_format === 'list') { $output .= '<ul style="margin-bottom:15px; padding-left:20px;"><li><a href="#">Beispiel-Quelle mit Titel</a></li><li><a href="#">example.com</a></li></ul>'; }
    else { $output .= sprintf('<table class="%s" style="margin-bottom:15px;"><tbody><tr><td><a href="#">Beispiel-Quelle mit Titel</a></td></tr><tr><td><a href="#">example.com</a></td></tr></tbody></table>', $style); }
    $output .= sprintf('<%1$s class="wp-block-heading" style="margin: 0 0 5px 0;">%2$s</%1$s>', $tag, $ti);
    if ($display_format === 'list') { $output .= '<ul style="margin-bottom:15px; padding-left:20px;"><li><a href="#">Schönes Hintergrundbild</a></li></ul>'; }
    else { $output .= sprintf('<table class="%s" style="margin-bottom:15px;"><tbody><tr><td><a href="#">Schönes Hintergrundbild</a></td></tr></tbody></table>', $style); }
    $output .= sprintf('<%1$s class="wp-block-heading" style="margin: 0 0 5px 0;">%2$s</%1$s>', $tag, $tt);
    if ($display_format === 'list') { $output .= '<ul style="margin-bottom:15px; padding-left:20px;"><li>Tabelle 1 (Statistik)</li></ul>'; }
    else { $output .= sprintf('<table class="%s" style="margin-bottom:15px;"><tbody><tr><td>Tabelle 1 (Statistik)</td></tr></tbody></table>', $style); }
    $output .= sprintf('<%1$s class="wp-block-heading" style="margin: 0 0 5px 0;">%2$s</%1$s>', $tag, $tf);
    if ($display_format === 'list') { $output .= '<ul style="padding-left:20px;"><li><a href="#">beispiel-datei.pdf</a></li></ul>'; }
    else { $output .= sprintf('<table class="%s"><tbody><tr><td><a href="#">beispiel-datei.pdf</a></td></tr></tbody></table>', $style); }
    $output .= '</div>'; return $output;
}

global $wpls_generated_anchors; $wpls_generated_anchors = [];
function wpls_inject_anchors_and_scan( $content ) {
    global $wpls_generated_anchors; if ( empty( trim( $content ) ) || is_feed() ) { return $content; }
    libxml_use_internal_errors( true ); $dom = new DOMDocument();
    $dom->loadHTML( '<?xml encoding="utf-8" ?><div>' . $content . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
    libxml_clear_errors();
    $tables = $dom->getElementsByTagName( 'table' ); $table_count = 1; $current_anchors = [];
    foreach ( $tables as $table ) {
        $anchor_id = $table->getAttribute( 'id' ); $parent = $table->parentNode;
        if ( empty( $anchor_id ) && $parent && $parent->nodeName === 'figure' ) { $anchor_id = $parent->getAttribute( 'id' ); }
        if ( empty( $anchor_id ) ) { $anchor_id = 'wpls-table-' . $table_count; $table->setAttribute( 'id', $anchor_id ); }
        $current_anchors[] = $anchor_id; $table_count++;
    }
    $wpls_generated_anchors = $current_anchors; $updated_html = $dom->saveHTML();
    $updated_html = str_replace( array('<?xml encoding="utf-8" ?>', '<div>', '</div>'), '', $updated_html );
    return $updated_html;
}
add_filter( 'the_content', 'wpls_inject_anchors_and_scan', 5 );

function wpls_clear_post_transients( $post_id ) {
    // FIX: Statt Transient-Zeilen per SQL direkt aus wp_options zu löschen (was bei aktivem
    // persistentem Object-Cache wie Redis/Memcached ins Leere läuft, weil Transients dann dort
    // und nicht in der Datenbank liegen), wird einfach eine neue Versionsnummer gesetzt.
    // Diese fließt in den Cache-Key ein, wodurch der alte Cache-Eintrag beim nächsten Aufruf
    // automatisch ignoriert wird – unabhängig vom verwendeten Cache-Backend.
    update_post_meta( $post_id, '_wpls_content_version', (string) time() );
}
add_action( 'save_post', 'wpls_clear_post_transients' );
