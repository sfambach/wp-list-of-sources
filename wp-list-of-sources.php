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
            'titleSources' => [ 'type' => 'string', 'default' => 'Quellen' ],
            'titleImages'  => [ 'type' => 'string', 'default' => 'Bilder' ],
            'titleTables'  => [ 'type' => 'string', 'default' => 'Tabellen' ],
            'headingTag'   => [ 'type' => 'string', 'default' => 'h3' ],
            'tableStyle'   => [ 'type' => 'string', 'default' => 'default' ], // default oder stripes
            'align'        => [ 'type' => 'string', 'default' => '' ],
            'className'    => [ 'type' => 'string', 'default' => '' ],
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
    
    $table_style_class = ($attributes['tableStyle'] === 'stripes') ? 'is-style-stripes' : '';

    $wrapper_classes = [ 'wp-block-table', 'wp-block-sources-table' ];
    if ( ! empty( $attributes['align'] ) ) $wrapper_classes[] = 'align' . $attributes['align'];
    if ( ! empty( $attributes['className'] ) ) $wrapper_classes[] = $attributes['className'];
    $wrapper_class_str = implode( ' ', array_map( 'sanitize_html_class', $wrapper_classes ) );

    if ( $is_template_preview ) {
        return wpls_render_template_dummy_preview($wrapper_class_str, $title_sources, $title_images, $title_tables, $heading_tag, $table_style_class);
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

       // 3. TABELLEN SCANNEN (Mit ID-Erkennung für Sprunglinks)
    $tables = $dom->getElementsByTagName('table');
    $table_count = 1;
    foreach ($tables as $table) {
        $title = '';
        $anchor_id = '';

        // Prüfen, ob die Tabelle in einer Gutenberg-Figure liegt
        $parent = $table->parentNode;
        if ($parent && $parent->nodeName === 'figure') {
            // ID vom umschließenden Figure-Element holen (Gutenberg Standard für HTML-Anker)
            $anchor_id = $parent->getAttribute('id');
            
            $figcaptions = $parent->getElementsByTagName('figcaption');
            if ($figcaptions->length > 0) {
                $title = trim($figcaptions->item(0)->textContent);
            }
        }

        // Fallback: Wenn das Figure-Element keine ID hatte, prüfen wir das table-Tag selbst
        if (empty($anchor_id)) {
            $anchor_id = $table->getAttribute('id');
        }

        // Fallback auf klassische caption
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
            'anchor_id' => esc_attr($anchor_id) // Speichert die ID falls vorhanden
        ];
        $table_count++;
    }



    // HTML-AUSGABE DER DREI BLOCK-SEKTIONEN (OHNE KOPFZEILEN)
    $output = '<div class="' . $wrapper_class_str . '" style="margin-top: 30px; font-family: sans-serif;">';

    // Sektion 1: Quellen (Links)
    $output .= sprintf('<%1$s class="wpls-section-title">%2$s</%1$s>', $heading_tag, $title_sources);
    if (!empty($links_data)) {
        // Kopfzeile entfernt, Tabelle startet direkt mit tbody
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

    // Sektion 3: Tabellen (Jetzt mit intelligenten Sprunglinks)
    $output .= sprintf('<%1$s class="wpls-section-title" style="margin-top:25px;">%2$s</%1$s>', $heading_tag, $title_tables);
    if (!empty($tables_data)) {
        $output .= sprintf('<table class="%s"><tbody>', $table_style_class);
        foreach ($tables_data as $tab) {
            $output .= '<tr><td>';
            // Wenn eine ID existiert, rendern wir einen echten Sprunglink
            if (!empty($tab['anchor_id'])) {
                $output .= sprintf('<a href="#%s">%s</a>', $tab['anchor_id'], $tab['title']);
            } else {
                // Andernfalls nur den nackten Text
                $output .= $tab['title'];
            }
            $output .= '</td></tr>';
        }
        $output .= '</tbody></table>';
    } else {
        $output .= '<p style="font-style:italic; color:#888;">' . esc_html__('Keine Tabellen in diesem Beitrag gefunden.', 'wp-list-of-sources') . '</p>';
    }


    $output .= '</div>';
    return $output;

}

// Hilfsfunktion: Rendert die flexible Dummy-Vorschau
function wpls_render_template_dummy_preview($wrapper_class, $ts, $ti, $tt, $tag, $style) {
    $output = '<div class="' . $wrapper_class . '" style="border: 1px dashed #ccc; padding: 15px; background: #fafafa; font-family: sans-serif;">';
    $output .= '<span style="display:block; font-size:11px; color:#999; text-transform:uppercase; margin-bottom:10px;">' . esc_html__('Table Live Preview (Template Mode):', 'wp-list-of-sources') . '</span>';
    
    $output .= sprintf('<%1$s style="margin: 0 0 5px 0;">%2$s</%1$s>', $tag, $ts);
    $output .= sprintf('<table class="%s" style="margin-bottom:15px;"><tbody>', $style);
    $output .= '<tr><td><a href="#">' . esc_html__('Beispiel-Quelle mit Titel (Oben sortiert)', 'wp-list-of-sources') . '</a></td></tr>';
    $output .= '<tr><td><a href="#">https://example.com</a></td></tr>';
    $output .= '</tbody></table>';

    $output .= sprintf('<%1$s style="margin: 0 0 5px 0;">%2$s</%1$s>', $tag, $ti);
    $output .= sprintf('<table class="%s" style="margin-bottom:15px;"><tbody>', $style);
    $output .= '<tr><td><a href="#">' . esc_html__('Schönes Hintergrundbild (Alt-Text vorhanden)', 'wp-list-of-sources') . '</a></td></tr>';
    $output .= '<tr><td><a href="#">https://example.com</a></td></tr>';
    $output .= '</tbody></table>';

    $output .= sprintf('<%1$s style="margin: 0 0 5px 0;">%2$s</%1$s>', $tag, $tt);
    $output .= sprintf('<table class="%s"><tbody>', $style);
    $output .= '<tr><td>' . esc_html__('Tabelle 1 (Statistik)', 'wp-list-of-sources') . '</td></tr>';
    $output .= '</tbody></table>';

    $output .= '</div>';

    return $output;
}
