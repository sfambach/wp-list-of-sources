<?php
/**
 * Plugin Name: Gutenberg Block Template Boilerplate
 * Description: Starter template for multi-block plugins with template preview support.
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: wp-my-plugin
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// i18n Textdomain laden
function wpm_load_textdomain() {
    load_plugin_textdomain( 'wp-my-plugin', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'wpm_load_textdomain' );

// CSS für flache, einzeilige Eingabefelder im Editor
function wpm_editor_styles() {
    echo '<style>
        .wpm-minimal-input .components-base-control__field { margin-bottom: 0 !important; }
        .wpm-minimal-input input.components-text-control__input { 
            height: 28px !important; 
            min-height: 28px !important; 
            padding: 2px 8px !important; 
            font-size: 13px !important; 
        }
    </style>';
}
add_action( 'admin_head', 'wpm_editor_styles' );

// Blöcke registrieren
function wpm_register_template_blocks() {
    // BLOCK 1: Einzeiliger Datensammler (Nur im Backend sichtbar)
    register_block_type( 'wpm/input-item', [
        'editor_script' => 'wpm-blocks-js',
        'attributes'      => [
            'fieldOne' => [ 'type' => 'string', 'default' => '' ],
            'fieldTwo' => [ 'type' => 'string', 'default' => '' ],
        ],
    ]);

    // BLOCK 2: Der Ausgabe-Block (Dynamic Render)
    register_block_type( 'wpm/display-block', [
        'editor_script'   => 'wpm-blocks-js',
        'render_callback' => 'wpm_render_display_block',
        'attributes'      => [
            'toggleOption' => [ 'type' => 'boolean', 'default' => true ],
            'align'        => [ 'type' => 'string', 'default' => '' ],
            'className'    => [ 'type' => 'string', 'default' => '' ],
        ],
    ]);
}
add_action( 'init', 'wpm_register_template_blocks' );

// JS-Skripte verknüpfen
function wpm_enqueue_block_editor_assets() {
    wp_enqueue_script(
        'wpm-blocks-js',
        plugins_url( 'blocks.js', __FILE__ ),
        [ 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-data', 'wp-block-editor', 'wp-i18n' ],
        filemtime( plugin_dir_path( __FILE__ ) . 'blocks.js' )
    );
    wp_set_script_translations( 'wpm-blocks-js', 'wp-my-plugin', plugin_dir_path( __FILE__ ) . 'languages' );
}
add_action( 'enqueue_block_editor_assets', 'wpm_enqueue_block_editor_assets' );

// PHP-Rendering Engine (Sicher gegen ID-Verlust im Template-Editor)
function wpm_render_display_block( $attributes, $content ) {
    $post_id = false;
    if (defined('REST_REQUEST') && REST_REQUEST && !empty($_GET['post_id'])) {
        $post_id = intval($_GET['post_id']);
    }
    if (!$post_id) {
        $post_id = get_the_ID();
    }

    $post = $post_id ? get_post($post_id) : null;
    $is_template_preview = ( ! $post || $post->post_type === 'wp_block' || $post->post_type === 'wp_template' || $post_id === 0 );

    // Ausgabe-Wrapper generieren
    $classes = [ 'wpm-display-wrapper' ];
    if ( ! empty( $attributes['align'] ) ) $classes[] = 'align' . $attributes['align'];
    if ( ! empty( $attributes['className'] ) ) $classes[] = 'attributes';
    $class_str = implode( ' ', array_map( 'sanitize_html_class', $classes ) );

    $output = '<div class="' . $class_str . '">';
    
    if ( $is_template_preview ) {
        // HIER STEHT IHRE DESIGN-VORSCHAU FÜR DEN SITE-EDITOR / VORLAGEN
        $output .= '<div style="padding:15px; border:1px dashed #ccc; background:#fafafa;">';
        $output .= '<h4>' . esc_html__( 'Template Preview Mode', 'wp-my-plugin' ) . '</h4>';
        $output .= '<p>This is how the block looks inside the template editor.</p>';
        $output .= '</div>';
    } else {
        // HIER STEHT DIE ECHTE FRONTEND-AUSGABE FÜR BEITRÄGE
        $output .= '<div class="wpm-frontend-content">';
        $output .= '<h3>' . esc_html__( 'Live Content', 'wp-my-plugin' ) . '</h3>';
        
        // Schleifen-Beispiel: Durchsucht den Post-Content nach Input-Blöcken
        $blocks = parse_blocks( $post->post_content );
        foreach ( $blocks as $block ) {
            if ( $block['blockName'] === 'wpm/input-item' ) {
                $f1 = !empty($block['attrs']['fieldOne']) ? esc_html($block['attrs']['fieldOne']) : '';
                $output .= sprintf('<p>Data captured: %s</p>', $f1);
            }
        }
        $output .= '</div>';
    }

    $output .= '</div>';
    return $output;
}
