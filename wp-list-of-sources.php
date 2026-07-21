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
            'titleSources'  => [ 'type' => 'string', 'default' => 'Quellen' ],
            'titleImages'   => [ 'type' => 'string', 'default' => 'Bilder' ],
            'titleTables'   => [ 'type' => 'string', 'default' => 'Tabellen' ],
            'headingTag'    => [ 'type' => 'string', 'default' => 'h3' ],
            'displayFormat' => [ 'type' => 'string', 'default' => 'table' ],
            'align'         => [ 'type' => 'string', 'default' => '' ],
            'className'     => [ 'type' => 'string', 'default' => '' ],
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
    // Prüfen, ob ein echter Titel existiert (der ungleich der nackten URL ist)
    $a_has_title = (!empty($a['title']) && $a['title'] !== $a['url'] && $a['title'] !== '-');
    $b_has_title = (!empty($b['title']) && $b['title'] !== $b['url'] && $b['title'] !== '-');

    if ($a_has_title && !$b_has_title) return -1; // Einträge mit echtem Titel nach oben
    if (!$a_has_title && $b_has_title) return 1;  // Einträge ohne Titel nach unten
    return strcmp($a['title'], $b['title']);       // Alphabetischer Fallback
}

// Hilfsfunktion: Eliminiert doppelte URLs und bevorzugt Einträge mit Titel (da bereits vorsortiert)
function wpls_filter_unique_urls($sources_array) {
    if (empty($sources_array)) return [];

    // Durch die vorherige Sortierung liegt die Variante MIT Titel bereits auf einem kleineren Index.
    // array_intersect_key + array_unique behält strikt den ersten gefundenen Index und wirft den doppelten (ohne Titel) weg!
    $unique = array_intersect_key(
        $sources_array,
        array_unique(array_column($sources_array, 'url'))
    );

    // Indexe neu nummerieren (0, 1, 2...) und zurückgeben
    return array_values($unique);
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
    $display_format = !empty($attributes['displayFormat']) ? $attributes['displayFormat'] : 'table';
    $allowed_tags  = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div'];
    $heading_tag   = in_array($attributes['headingTag'], $allowed_tags) ? $attributes['headingTag'] : 'h3';
    $table_style_class = ( !empty($attributes['className']) && strpos( $attributes['className'], 'is-style-stripes' ) !== false ) ? 'is-style-stripes' : '';

    $wrapper_classes = [ 'wp-block-table', 'wp-block-sources-table' ];
    if ( ! empty( $attributes['align'] ) ) $wrapper_classes[] = 'align' . $attributes['align'];
    if ( ! empty( $attributes['className'] ) ) $wrapper_classes[] = $attributes['className'];
    $wrapper_class_str = implode( ' ', array_map( 'sanitize_html_class', $wrapper_classes ) );

    if ( $is_template_preview ) {
		return wpls_render_template_dummy_preview($wrapper_class_str, $title_sources, $title_images, $title_tables, $heading_tag, $table_style_class, $display_format);
    }

    $cache_key = 'wpls_cache_' . $post_id . '_' . md5(serialize($attributes));
    $cached_output = get_transient($cache_key);
    if ( $cached_output !== false ) { return $cached_output; }

    $html = $post->post_content;
    if (empty(trim($html))) { return '<p style="font-style:italic; color:#666;">' . esc_html__( 'No content found to analyze.', 'wp-list-of-sources' ) . '</p>'; }

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    libxml_clear_errors();

    $links_data = []; $images_data = []; $tables_data = [];

    $links = $dom->getElementsByTagName('a');
    foreach ($links as $link) {
        $url = $link->getAttribute('href'); 
		$title = $link->getAttribute('title'); 
		$text = trim($link->textContent);
		
        // remove empty urls
		if (empty($url) || strpos($url, '#') === 0) continue;
		
		// if url is already in the arry skip this one
		if ( true === WP_DEBUG ) {
			echo ' '.$url.' ';
		}
		// if( isUrlInArray( $link_data, $url ) ) continue;
		
		// Choose the label for the link
		if(!empty($title)){
			$final_title = title;
		} else if(!empty($text)){
			$final_title = $text;
		} else {
			$final_title = getCleanDomainAndPath($url);
		}
		
		$final_title = ""; //trim($final_title);
        $links_data[] = [ 'url' => esc_url($url), 'title' => esc_html($final_title) ];
    }
    usort($links_data, 'wpls_sort_sources');
	$links_data = wpls_filter_unique_urls($links_data);

    $images = $dom->getElementsByTagName('img');
    foreach ($images as $img) {
        $url = $img->getAttribute('src'); $alt = $img->getAttribute('alt'); $title = $img->getAttribute('title');
        if (empty($url)) continue;
        if (empty($alt) && empty($title)) {
            $final_title = basename(parse_url($url, PHP_URL_PATH));
            if (empty($final_title)) { $final_title = $url; }
        } else { $final_title = !empty($alt) ? $alt : $title; }
        $images_data[] = [ 'url' => esc_url($url), 'title' => esc_html($final_title) ];
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
        ['title' => $title_tables,  'data' => $tables_data, 'type' => 'tables', 'empty' => __('Keine Tabellen in diesem Beitrag gefunden.', 'wp-list-of-sources')]
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

function wpls_render_template_dummy_preview($wrapper_class, $ts, $ti, $tt, $tag, $style, $display_format = 'table') {
    $output = '<div class="' . $wrapper_class . '" style="border: 1px dashed #ccc; padding: 15px; background: #fafafa; font-family: sans-serif;">';
    $output .= '<span style="display:block; font-size:11px; color:#999; text-transform:uppercase; margin-bottom:10px;">' . esc_html__('Table Live Preview (Template Mode):', 'wp-list-of-sources') . '</span>';
    $output .= sprintf('<%1$s class="wp-block-heading" style="margin: 0 0 5px 0;">%2$s</%1$s>', $tag, $ts);
    if ($display_format === 'list') { $output .= '<ul style="margin-bottom:15px; padding-left:20px;"><li><a href="#">Beispiel-Quelle mit Titel</a></li><li><a href="#">https://example.com</a></li></ul>'; }
    else { $output .= sprintf('<table class="%s" style="margin-bottom:15px;"><tbody><tr><td><a href="#">Beispiel-Quelle mit Titel</a></td></tr><tr><td><a href="#">https://example.com</a></td></tr></tbody></table>', $style); }
    $output .= sprintf('<%1$s class="wp-block-heading" style="margin: 0 0 5px 0;">%2$s</%1$s>', $tag, $ti);
    if ($display_format === 'list') { $output .= '<ul style="margin-bottom:15px; padding-left:20px;"><li><a href="#">Schönes Hintergrundbild</a></li></ul>'; }
    else { $output .= sprintf('<table class="%s" style="margin-bottom:15px;"><tbody><tr><td><a href="#">Schönes Hintergrundbild</a></td></tr></tbody></table>', $style); }
    $output .= sprintf('<%1$s class="wp-block-heading" style="margin: 0 0 5px 0;">%2$s</%1$s>', $tag, $tt);
    if ($display_format === 'list') { $output .= '<ul style="padding-left:20px;"><li>Tabelle 1 (Statistik)</li></ul>'; }
    else { $output .= sprintf('<table class="%s"><tbody><tr><td>Tabelle 1 (Statistik)</td></tr></tbody></table>', $style); }
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
    global $wpdb;
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_wpls_cache_' . $post_id . '_%', '_transient_timeout_wpls_cache_' . $post_id . '_%' ) );
}
add_action( 'save_post', 'wpls_clear_post_transients' );
