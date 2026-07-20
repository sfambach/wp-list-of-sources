<?php
/**
 * Plugin Name: WP List of Sources
 * Description: Automatically extracts and displays a list of used links, pictures, and tables in the current post with flexible block settings.
 * Version: 1.1.0
 * Author: Ihr Name
 * Text Domain: wp-list-of-sources
 * Domain Path: /languages 
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function wpls_load_textdomain() {
    load_plugin_textdomain( 'wp-list-of-sources', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'wpls_load_textdomain' );

function wpls_register_sources_blocks() {
   register_block_type( 'wpls/sources-table', [
        'editor_script'   => 'wpls-blocks-js',
        'render_callback' => 'wpls_render_sources_table',
        'attributes'      => [
            'titleSources'  => [ 'type' => 'string', 'default' => 'Quellen' ],
            'titleImages'   => [ 'type' => 'string', 'default' => 'Bilder' ],
            'titleTables'   => [ 'type' => 'string', 'default' => 'Tabellen' ],
            'headingTag'    => [ 'type' => 'string', 'default' => 'h3' ],
            'displayFormat' => [ 'type' => 'string', 'default' => 'table' ], // 🌟 WICHTIG: Muss exakt so hier stehen!
            'align'         => [ 'type' => 'string', 'default' => '' ],
            'className'     => [ 'type' => 'string', 'default' => '' ],
        ],
    ]);
	// Native WordPress-Stil-Vorschauen hinzufügen
    if ( function_exists( 'register_block_style' ) ) {
        register_block_style( 'wpls/sources-table', [
            'name'         => 'default',
            'label'        => __( 'Default', 'wp-list-of-sources' ),
            'is_default'   => true,
        ] );
        register_block_style( 'wpls/sources-table', [
            'name'         => 'stripes',
            'label'        => __( 'Stripes', 'wp-list-of-sources' ),
        ] );
    }
}
add_action( 'init', 'wpls_register_sources_blocks' );

function wpls_enqueue_block_editor_assets() {
    wp_enqueue_script(
        'wpls-blocks-js',
        plugins_url( 'blocks.js', __FILE__ ),
        [ 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-data', 'wp-block-editor', 'wp-i18n' ],
        filemtime( plugin_dir_path( __FILE__ ) . '/blocks.js' )
    );
    wp_set_script_translations( 'wpls-blocks-js', 'wp-list-of-sources', plugin_dir_path( __FILE__ ) . '/languages' );
}
add_action( 'enqueue_block_editor_assets', 'wpls_enqueue_block_editor_assets' );

// Hilfsfunktion zur Sortierung: Einträge mit Titel/Name nach oben
function wpls_sort_sources($a, $b) {
    $a_has_title = (!empty($a['title']) && $a['title'] !== $a['url']);
    $b_has_title = (!empty($b['title']) && $b['title'] !== $b['url']);
    if ($a_has_title && !$b_has_title) return -1;
    if (!$a_has_title && $b_has_title) return 1;
    return strcmp($a['title'], $b['title']);
}

// PHP-Rendering Engine
function wpls_render_sources_table( $attributes, $content ) {
    $post_id = false;
    if (defined('REST_REQUEST') && REST_REQUEST && !empty($_GET['post_id'])) {
        $post_id = intval($_GET['post_id']);
    }
    if (!$post_id) {
        $post_id = get_the_ID();
    }

    $post = $post_id ? get_post($post_id) : null;
    $is_template_preview = ( ! $post || $post->post_type === 'wp_block' || $post->post_type === 'wp_template' || $post_id === 0 );

    // Attribute sichern und sanitisieren
    $title_sources = !empty($attributes['titleSources']) ? esc_html($attributes['titleSources']) : 'Quellen';
    $title_images  = !empty($attributes['titleImages']) ? esc_html($attributes['titleImages']) : 'Bilder';
    $title_tables  = !empty($attributes['titleTables']) ? esc_html($attributes['titleTables']) : 'Tabellen';
    
    $allowed_tags  = ['h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div'];
    $heading_tag   = in_array($attributes['headingTag'], $allowed_tags) ? $attributes['headingTag'] : 'h3';
    
    $table_style_class = ( !empty($attributes['className']) && strpos( $attributes['className'], 'is-style-stripes' ) !== false ) ? 'is-style-stripes' : '';

    $wrapper_classes = [ 'wp-block-table', 'wp-block-sources-table' ];
    if ( ! empty( $attributes['align'] ) ) $wrapper_classes[] = 'align' . $attributes['align'];
    if ( ! empty( $attributes['className'] ) ) $wrapper_classes[] = $attributes['className'];
    $wrapper_class_str = implode( ' ', array_map( 'sanitize_html_class', $wrapper_classes ) );

	if ( $is_template_preview ) {
        // Holt das aktuelle Format, um es an die Dummy-Vorschau weiterzugeben
        $display_format = !empty($attributes['displayFormat']) ? $attributes['attributes']['displayFormat'] : 'table';
        
        // REPARIERT: $display_format wird jetzt als 7. Argument übergeben!
        return wpls_render_template_dummy_preview($wrapper_class_str, $title_sources, $title_images, $title_tables, $heading_tag, $table_style_class, $display_format);
    }


    $html = $post->post_content;
    if (empty(trim($html))) {
        return '<p style="font-style:italic; color:#666;">' . esc_html__( 'No content found to analyze.', 'wp-list-of-sources' ) . '</p>';
    }

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    libxml_clear_errors();

    $links_data  = [];
    $images_data = [];
    $tables_data = [];

    // 1. LINKS SCANNEN
    $links = $dom->getElementsByTagName('a');
    foreach ($links as $link) {
        $url   = $link->getAttribute('href');
        $title = $link->getAttribute('title');
        $text  = trim($link->textContent);
        if (empty($url) || strpos($url, '#') === 0) continue;
        $final_title = !empty($title) ? $title : (!empty($text) ? $text : $url);
        $links_data[] = [ 'url' => esc_url($url), 'title' => esc_html($final_title) ];
    }
    usort($links_data, 'wpls_sort_sources');

    // 2. BILDER SCANNEN
    $images = $dom->getElementsByTagName('img');
    foreach ($images as $img) {
        $url   = $img->getAttribute('src');
        $alt   = $img->getAttribute('alt');
        $title = $img->getAttribute('title');
        if (empty($url)) continue;
        $final_title = !empty($alt) ? $alt : (!empty($title) ? $title : $url);
        $images_data[] = [ 'url' => esc_url($url), 'title' => esc_html($final_title) ];
    }
    usort($images_data, 'wpls_sort_sources');

       // 3. TABELLEN SCANNEN & ID-INJEKTION (Automatische Sprungmarken-Generierung)
    $tables = $dom->getElementsByTagName('table');
    $table_count = 1;
    foreach ($tables as $table) {
        $title = '';
        $anchor_id = '';

        // Prüfen, ob bereits ein manueller HTML-Anker am table-Tag existiert
        $anchor_id = $table->getAttribute('id');

        // Am umschließenden Element (Gutenberg-figure) nach dem manuellen Anker suchen
        $parent = $table->parentNode;
        if (empty($anchor_id) && $parent && $parent->nodeName === 'figure') {
            $anchor_id = $parent->getAttribute('id');
        }

        // AUTOMATISCHE ID GENERIEREN, falls kein HTML-Anker vom Redakteur gesetzt wurde
        if (empty($anchor_id)) {
            $anchor_id = 'wpls-table-' . $table_count;
            
            // Wir injizieren die ID direkt live in das table-Element für das Frontend
            $table->setAttribute('id', $anchor_id);
        }

        // Titel aus figcaption auslesen
        if ($parent && $parent->nodeName === 'figure') {
            $figcaptions = $parent->getElementsByTagName('figcaption');
            if ($figcaptions->length > 0) {
                $title = trim($figcaptions->item(0)->textContent);
            }
        }

        // Fallback-Titel aus klassischer caption auslesen
        if (empty($title)) {
            $captions = $table->getElementsByTagName('caption');
            if ($captions->length > 0) {
                $title = trim($captions->item(0)->textContent);
            }
        }

        // Letzter Ausweg: Nummerierung
        if (empty($title)) {
            $title = sprintf(__('Table %d', 'wp-list-of-sources'), $table_count);
        }

        $tables_data[] = [
            'title'     => esc_html($title),
            'anchor_id' => esc_attr($anchor_id)
        ];
        $table_count++;
    }

    // Da wir IDs direkt in den DOM-Baum injiziert haben, speichern wir das modifizierte HTML ab,
    // damit die IDs auch im finalen Seiten-Markup landen (Wichtig für das Frontend!)
    $html_with_ids = $dom->saveHTML();

    // HTML-AUSGABE DER DREI SEKTIONEN
    $output = '<div class="' . $wrapper_class_str . '" style="margin-top: 30px; font-family: sans-serif;">';

    // Sektion 1: Quellen (Links)
    $output .= sprintf('<%1$s class="wpls-section-title">%2$s</%1$s>', $heading_tag, $title_sources);
    if (!empty($links_data)) {
        $output .= sprintf('<table class="%s"><tbody>', $table_style_class);
        foreach ($links_data as $link) {
            $output .= sprintf('<tr><td><a href="%s" target="_blank" rel="noopener">%s</a></td></tr>', $link['url'], $link['title']);
        }
        $output .= '</tbody></table>';
    } else {
        $output .= '<p style="font-style:italic; color:#888; margin-bottom:20px;">' . esc_html__('Keine Links in diesem Beitrag gefunden.', 'wp-list-of-sources') . '</p>';
    }

    // Sektion 2: Bilder
    $output .= sprintf('<%1$s class="wpls-section-title" style="margin-top:25px;">%2$s</%1$s>', $heading_tag, $title_images);
    if (!empty($images_data)) {
        $output .= sprintf('<table class="%s"><tbody>', $table_style_class);
        foreach ($images_data as $img) {
            $output .= sprintf('<tr><td><a href="%s" target="_blank" rel="noopener">%s</a></td></tr>', $img['url'], $img['title']);
        }
        $output .= '</tbody></table>';
    } else {
        $output .= '<p style="font-style:italic; color:#888; margin-bottom:20px;">' . esc_html__('Keine Bilder in diesem Beitrag gefunden.', 'wp-list-of-sources') . '</p>';
    }


    // Sektion 3: Tabellen (Zieht die physisch injizierten IDs aus der globalen Variable)
    // Auslesen des gewählten Anzeigeformats (table oder list)
    $display_format = !empty($attributes['displayFormat']) ? $attributes['displayFormat'] : 'table';

    // HTML-AUSGABE DER DREI SEKTIONEN (Flexibel gesteuert nach Tabelle oder Liste)
    $output = '<div class="' . $wrapper_class_str . '" style="margin-top: 30px; font-family: sans-serif;">';

    // Hilfs-Arrays für die kompakte Generierung per Schleife
    $sections = [
        ['title' => $title_sources, 'data' => $links_data, 'type' => 'links', 'empty' => __('Keine Links in diesem Beitrag gefunden.', 'wp-list-of-sources')],
        ['title' => $title_images,  'data' => $images_data, 'type' => 'images', 'empty' => __('Keine Bilder in diesem Beitrag gefunden.', 'wp-list-of-sources')],
        ['title' => $title_tables,  'data' => $tables_data, 'type' => 'tables', 'empty' => __('Keine Tabellen in diesem Beitrag gefunden.', 'wp-list-of-sources')]
    ];

    global $wpls_generated_anchors;

    foreach ($sections as $section) {
        $output .= sprintf('<%1$s class="wpls-section-title" style="margin-top:25px; margin-bottom:10px;">%2$s</%1$s>', $heading_tag, $section['title']);
        
        if (!empty($section['data'])) {
            // Start-Tag je nach gewähltem Format generieren
            if ($display_format === 'list') {
                $output .= '<ul class="wpls-sources-list" style="margin:0 0 20px 0; padding-left:20px;">';
            } else {
                $output .= sprintf('<table class="%s"><tbody>', $table_style_class);
            }

            // Schleife durch alle Einträge der aktuellen Sektion
            foreach ($section['data'] as $index => $item) {
                $item_html = '';

                // A) Sonderfall für die Tabellen-Sektion (Sprunglinks berechnen)
                if ($section['type'] === 'tables') {
                    $final_anchor = (!empty($wpls_generated_anchors[$index])) ? $wpls_generated_anchors[$index] : (!empty($item['anchor_id']) ? $item['anchor_id'] : '');
                    if (empty($final_anchor)) {
                        $final_anchor = 'wpls-table-' . ($index + 1);
                    }
                    $item_html = sprintf('<a href="#%s">%s</a>', $final_anchor, $item['title']);
                } 
                // B) Regelfall für Links und Bilder (Externe Ziel-Links)
                else {
                    $item_html = sprintf('<a href="%s" target="_blank" rel="noopener">%s</a>', $item['url'], $item['title']);
                }

                // HTML verpacken je nach gewählter UI-Einstellung
                if ($display_format === 'list') {
                    $output .= sprintf('<li style="margin-bottom:5px;">%s</li>', $item_html);
                } else {
                    $output .= sprintf('<tr><td>%s</td></tr>', $item_html);
                }
            }

            // End-Tag je nach Formatierung schließen
            if ($display_format === 'list') {
                $output .= '</ul>';
            } else {
                $output .= '</tbody></table>';
            }
        } else {
            $output .= sprintf('<p style="font-style:italic; color:#888; margin-bottom:20px;">%s</p>', $section['empty']);
        }
    }


    $output .= '</div>';
    return $output;

}


// Hilfsfunktion: Rendert die flexible Dummy-Vorschau passend zum Anzeigeformat
function wpls_render_template_dummy_preview($wrapper_class, $ts, $ti, $tt, $tag, $style, $display_format = 'table') {
    $output = '<div class="' . $wrapper_class . '" style="border: 1px dashed #ccc; padding: 15px; background: #fafafa; font-family: sans-serif;">';
    $output .= '<span style="display:block; font-size:11px; color:#999; text-transform:uppercase; margin-bottom:10px;">' . esc_html__('Table Live Preview (Template Mode):', 'wp-list-of-sources') . '</span>';
    
    // Sektion 1
    $output .= sprintf('<%1$s style="margin: 0 0 5px 0;">%2$s</%1$s>', $tag, $ts);
    if ($display_format === 'list') {
        $output .= '<ul style="margin-bottom:15px; padding-left:20px;"><li><a href="#">Beispiel-Quelle mit Titel</a></li><li><a href="#">https://example.com</a></li></ul>';
    } else {
        $output .= sprintf('<table class="%s" style="margin-bottom:15px;"><tbody><tr><td><a href="#">Beispiel-Quelle mit Titel</a></td></tr><tr><td><a href="#">https://example.com</a></td></tr></tbody></table>', $style);
    }

    // Sektion 2
    $output .= sprintf('<%1$s style="margin: 0 0 5px 0;">%2$s</%1$s>', $tag, $ti);
    if ($display_format === 'list') {
        $output .= '<ul style="margin-bottom:15px; padding-left:20px;"><li><a href="#">Schönes Hintergrundbild</a></li></ul>';
    } else {
        $output .= sprintf('<table class="%s" style="margin-bottom:15px;"><tbody><tr><td><a href="#">Schönes Hintergrundbild</a></td></tr></tbody></table>', $style);
    }

    // Sektion 3
    $output .= sprintf('<%1$s style="margin: 0 0 5px 0;">%2$s</%1$s>', $tag, $tt);
    if ($display_format === 'list') {
        $output .= '<ul style="padding-left:20px;"><li>Tabelle 1 (Statistik)</li></ul>';
    } else {
        $output .= sprintf('<table class="%s"><tbody><tr><td>Tabelle 1 (Statistik)</td></tr></tbody></table>', $style);
    }

    $output .= '</div>';
    return $output;
}


// Globale Variable, um die berechneten IDs zwischen Scan und Ausgabe zu übergeben
global $wpls_generated_anchors;
$wpls_generated_anchors = [];

function wpls_inject_anchors_and_scan( $content ) {
    global $wpls_generated_anchors;
    if ( empty( trim( $content ) ) || is_feed() ) {
        return $content;
    }

    libxml_use_internal_errors( true );
    $dom = new DOMDocument();
    // UTF-8 sichern beim Laden des gesamten Contents
    $dom->loadHTML( '<?xml encoding="utf-8" ?><div>' . $content . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
    libxml_clear_errors();

    $tables = $dom->getElementsByTagName( 'table' );
    $table_count = 1;
    $current_anchors = [];

    foreach ( $tables as $table ) {
        $anchor_id = $table->getAttribute( 'id' );
        $parent = $table->parentNode;
        
        if ( empty( $anchor_id ) && $parent && $parent->nodeName === 'figure' ) {
            $anchor_id = $parent->getAttribute( 'id' );
        }

        // Automatische ID vergeben, falls kein HTML-Anker existiert
        if ( empty( $anchor_id ) ) {
            $anchor_id = 'wpls-table-' . $table_count;
            // ID wird jetzt GARANTIERT live in das Tabellen-HTML der Seite injiziert!
            $table->setAttribute( 'id', $anchor_id );
        }

        $current_anchors[] = $anchor_id;
        $table_count++;
    }

    // Die gesammelten IDs global für die Render-Funktion speichern
    $wpls_generated_anchors = $current_anchors;

    // Das modifizierte HTML (inklusive injizierter IDs) zurück an WordPress geben
    $updated_html = $dom->saveHTML();
    // Hilfs-Wrapper entfernen, um das originale Layout nicht zu stören
    $updated_html = str_replace( array('<?xml encoding="utf-8" ?>', '<div>', '</div>'), '', $updated_html );
    return $updated_html;
}
// Dieser Filter sorgt dafür, dass die IDs physisch an den Tabellen im Frontend landen!
add_filter( 'the_content', 'wpls_inject_anchors_and_scan', 5 );

