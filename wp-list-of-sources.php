<?php
/**
 * Plugin Name: WP List of Sources
 * Description: Automatically extracts and displays links, images, tables, or files from the current post. Add one block per source type.
 * Version: 1.0.1
 * Author: Stefan Fambach
 * Text Domain: wp-list-of-sources
 * Domain Path: /languages
 * GitHub Plugin URI: sfambach/wp-list-of-sources
 * Primary Branch: main
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------

function wpls_load_textdomain() {
    load_plugin_textdomain( 'wp-list-of-sources', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'wpls_load_textdomain' );

// ---------------------------------------------------------------------------
// Settings
// ---------------------------------------------------------------------------

function wpls_get_default_settings() {
    return [
        'file_extensions' => implode( ', ', wpls_get_default_file_extensions() ),
        'cache_duration'  => 12,
        'display_format'  => 'list',
    ];
}

function wpls_get_settings() {
    $stored = get_option( 'wpls_settings', [] );
    return wp_parse_args( $stored, wpls_get_default_settings() );
}

function wpls_register_settings() {
    register_setting( 'wpls_settings_group', 'wpls_settings', [ 'sanitize_callback' => 'wpls_sanitize_settings' ] );
}
add_action( 'admin_init', 'wpls_register_settings' );

function wpls_sanitize_settings( $input ) {
    $defaults = wpls_get_default_settings();
    $output   = [];

    $extensions = isset( $input['file_extensions'] ) ? (string) $input['file_extensions'] : '';
    $extensions = array_filter( array_map( 'trim', explode( ',', strtolower( $extensions ) ) ) );
    $extensions = array_map(
        function ( $ext ) {
            return preg_replace( '/[^a-z0-9]/', '', $ext );
        },
        $extensions
    );
    $extensions            = array_filter( $extensions );
    $output['file_extensions'] = ! empty( $extensions ) ? implode( ', ', $extensions ) : $defaults['file_extensions'];

    $duration                = isset( $input['cache_duration'] ) ? intval( $input['cache_duration'] ) : $defaults['cache_duration'];
    $output['cache_duration'] = $duration > 0 ? $duration : $defaults['cache_duration'];

    $display_format         = isset( $input['display_format'] ) ? (string) $input['display_format'] : $defaults['display_format'];
    $output['display_format'] = in_array( $display_format, [ 'table', 'list' ], true ) ? $display_format : $defaults['display_format'];

    return $output;
}

function wpls_add_settings_page() {
    add_options_page(
        __( 'List of Sources', 'wp-list-of-sources' ),
        __( 'List of Sources', 'wp-list-of-sources' ),
        'manage_options',
        'wpls-settings',
        'wpls_render_settings_page'
    );
}
add_action( 'admin_menu', 'wpls_add_settings_page' );

function wpls_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $settings = wpls_get_settings();
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'List of Sources', 'wp-list-of-sources' ); ?></h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'wpls_settings_group' ); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="wpls_file_extensions"><?php esc_html_e( 'File extensions', 'wp-list-of-sources' ); ?></label>
                    </th>
                    <td>
                        <input
                            type="text"
                            id="wpls_file_extensions"
                            name="wpls_settings[file_extensions]"
                            value="<?php echo esc_attr( $settings['file_extensions'] ); ?>"
                            class="regular-text"
                        />
                        <p class="description">
                            <?php esc_html_e( 'Comma-separated list of file extensions treated as the "Files" source type.', 'wp-list-of-sources' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="wpls_cache_duration"><?php esc_html_e( 'Cache duration (hours)', 'wp-list-of-sources' ); ?></label>
                    </th>
                    <td>
                        <input
                            type="number"
                            min="1"
                            id="wpls_cache_duration"
                            name="wpls_settings[cache_duration]"
                            value="<?php echo esc_attr( $settings['cache_duration'] ); ?>"
                            class="small-text"
                        />
                        <p class="description">
                            <?php esc_html_e( 'How long a rendered block is cached before it is rebuilt from post content.', 'wp-list-of-sources' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="wpls_display_format"><?php esc_html_e( 'Default display format', 'wp-list-of-sources' ); ?></label>
                    </th>
                    <td>
                        <select id="wpls_display_format" name="wpls_settings[display_format]">
                            <option value="list" <?php selected( $settings['display_format'], 'list' ); ?>><?php esc_html_e( 'List', 'wp-list-of-sources' ); ?></option>
                            <option value="table" <?php selected( $settings['display_format'], 'table' ); ?>><?php esc_html_e( 'Table', 'wp-list-of-sources' ); ?></option>
                        </select>
                        <p class="description">
                            <?php esc_html_e( 'Default display format for newly inserted blocks.', 'wp-list-of-sources' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

function wpls_editor_styles() {
    echo '<style>
        .wpls-minimal-input .components-base-control__field { margin-bottom: 0 !important; }
        .wpls-minimal-input input.components-text-control__input,
        .wpls-minimal-input select.components-select-control__input {
            height: 28px !important; min-height: 28px !important; padding: 2px 8px !important; font-size: 13px !important;
        }
        .wpls-jump-button {
            background: none; border: 1px solid #ccc; border-radius: 3px; cursor: pointer;
            font-size: 12px; line-height: 1; padding: 1px 5px; vertical-align: middle; color: #555;
        }
        .wpls-jump-button:hover { background: #f0f0f0; }
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
    $settings = wpls_get_settings();

    register_block_type(
        'wpls/sources-table',
        [
            'editor_script'   => 'wpls-blocks-js',
            'render_callback' => 'wpls_render_sources_table',
            'attributes'      => [
                'sourceType'     => [ 'type' => 'string', 'default' => 'links' ],
                'displayFormat'  => [ 'type' => 'string', 'default' => $settings['display_format'] ],
                'stripUrlPrefix' => [ 'type' => 'boolean', 'default' => true ],
                'align'          => [ 'type' => 'string', 'default' => '' ],
                'className'      => [ 'type' => 'string', 'default' => '' ],
            ],
        ]
    );

    if ( function_exists( 'register_block_style' ) ) {
        register_block_style( 'wpls/sources-table', [ 'name' => 'default', 'label' => __( 'Default', 'wp-list-of-sources' ), 'is_default' => true ] );
        register_block_style( 'wpls/sources-table', [ 'name' => 'stripes', 'label' => __( 'Stripes', 'wp-list-of-sources' ) ] );
    }
}
add_action( 'init', 'wpls_register_sources_blocks' );

function wpls_enqueue_block_editor_assets() {
    wp_enqueue_script(
        'wpls-blocks-js',
        plugins_url( 'blocks.js', __FILE__ ),
        [ 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-data', 'wp-block-editor', 'wp-i18n' ],
        filemtime( plugin_dir_path( __FILE__ ) . 'blocks.js' )
    );
    wp_set_script_translations( 'wpls-blocks-js', 'wp-list-of-sources', plugin_dir_path( __FILE__ ) . '/languages' );
}
add_action( 'enqueue_block_editor_assets', 'wpls_enqueue_block_editor_assets' );

function wpls_clear_post_transients( $post_id ) {
    update_post_meta( $post_id, '_wpls_content_version', (string) time() );
}
add_action( 'save_post', 'wpls_clear_post_transients' );

global $wpls_generated_anchors;
$wpls_generated_anchors = [];

function wpls_inject_anchors_and_scan( $content ) {
    global $wpls_generated_anchors;

    if ( empty( trim( $content ) ) || is_feed() ) {
        return $content;
    }

    libxml_use_internal_errors( true );
    $dom = new DOMDocument();
    $dom->loadHTML( '<?xml encoding="utf-8" ?><div>' . $content . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
    libxml_clear_errors();

    $tables        = $dom->getElementsByTagName( 'table' );
    $table_count   = 1;
    $current_anchors = [];

    foreach ( $tables as $table ) {
        $anchor_id = $table->getAttribute( 'id' );
        $parent    = $table->parentNode;

        if ( empty( $anchor_id ) && $parent && $parent->nodeName === 'figure' ) {
            $anchor_id = $parent->getAttribute( 'id' );
        }
        if ( empty( $anchor_id ) ) {
            $anchor_id = 'wpls-table-' . $table_count;
            $table->setAttribute( 'id', $anchor_id );
        }

        $current_anchors[] = $anchor_id;
        $table_count++;
    }

    $wpls_generated_anchors = $current_anchors;
    $updated_html           = $dom->saveHTML();
    $updated_html           = str_replace( [ '<?xml encoding="utf-8" ?>', '<div>', '</div>' ], '', $updated_html );

    return $updated_html;
}
add_filter( 'the_content', 'wpls_inject_anchors_and_scan', 5 );

// ---------------------------------------------------------------------------
// Utilities
// ---------------------------------------------------------------------------

function wpls_get_source_types() {
    return [ 'links', 'images', 'tables', 'files' ];
}

function wpls_get_default_file_extensions() {
    return [ 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'rar', '7z', 'csv', 'txt', 'bin' ];
}

function wpls_get_file_extensions() {
    $settings = wpls_get_settings();
    $list     = array_filter( array_map( 'trim', explode( ',', $settings['file_extensions'] ) ) );

    return ! empty( $list ) ? $list : wpls_get_default_file_extensions();
}

function wpls_get_clean_domain_and_path( $url ) {
    return preg_replace( '/^https?:\/\/(www\.)?/', '', $url );
}

function wpls_normalize_url_for_dedupe( $url ) {
    return strtolower( rtrim( wpls_get_clean_domain_and_path( $url ), '/' ) );
}

function wpls_sort_sources( $a, $b ) {
    $a_has_title = ! empty( $a['has_title'] );
    $b_has_title = ! empty( $b['has_title'] );

    if ( $a_has_title && ! $b_has_title ) {
        return -1;
    }
    if ( ! $a_has_title && $b_has_title ) {
        return 1;
    }

    return strcasecmp( $a['title'], $b['title'] );
}

function wpls_filter_unique_urls( $sources_array ) {
    if ( empty( $sources_array ) ) {
        return [];
    }

    $seen   = [];
    $result = [];

    foreach ( $sources_array as $item ) {
        $key = isset( $item['norm_url'] ) ? $item['norm_url'] : strtolower( $item['url'] );
        if ( isset( $seen[ $key ] ) ) {
            continue;
        }
        $seen[ $key ] = true;
        $result[]     = $item;
    }

    return $result;
}

function wpls_finalize_source_list( array $items ) {
    usort( $items, 'wpls_sort_sources' );
    return wpls_filter_unique_urls( $items );
}

function wpls_get_figcaption_text( DOMNode $node ) {
    $figure = $node->parentNode;

    while ( $figure && $figure->nodeName !== 'figure' ) {
        if ( in_array( $figure->nodeName, [ 'table', 'td', 'tr', 'tbody', 'thead' ], true ) ) {
            return '';
        }
        $figure = $figure->parentNode;
    }

    if ( ! $figure ) {
        return '';
    }

    $figcaptions = $figure->getElementsByTagName( 'figcaption' );

    return $figcaptions->length > 0 ? trim( $figcaptions->item( 0 )->textContent ) : '';
}

function wpls_is_file_url( $url ) {
    $path      = parse_url( $url, PHP_URL_PATH );
    $extension = $path ? strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) : '';

    return in_array( $extension, wpls_get_file_extensions(), true );
}

function wpls_create_dom_from_html( $html ) {
    $previous_error_state = libxml_use_internal_errors( true );
    $dom                  = new DOMDocument();

    $dom->loadHTML(
        '<?xml encoding="utf-8" ?>' . trim( $html ),
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );

    libxml_clear_errors();
    libxml_use_internal_errors( $previous_error_state );

    return $dom;
}

function wpls_get_image_block_names() {
    return apply_filters( 'wpls_image_block_names', [ 'core/image', 'core/gallery', 'core/media-text', 'core/cover' ] );
}

function wpls_get_file_block_names() {
    return apply_filters( 'wpls_file_block_names', [ 'core/file' ] );
}

function wpls_get_block_html( array $block ) {
    $html  = '';
    $index = 0;

    foreach ( $block['innerContent'] as $chunk ) {
        if ( is_string( $chunk ) ) {
            $html .= $chunk;
        } elseif ( isset( $block['innerBlocks'][ $index ] ) ) {
            $html .= wpls_get_block_html( $block['innerBlocks'][ $index++ ] );
        }
    }

    return $html;
}

function wpls_split_blocks_by_type( array $blocks, array &$buckets ) {
    foreach ( $blocks as $block ) {
        $name = isset( $block['blockName'] ) ? $block['blockName'] : '';

        if ( in_array( $name, wpls_get_image_block_names(), true ) ) {
            $buckets['images'] .= wpls_get_block_html( $block );
            continue;
        }
        if ( in_array( $name, wpls_get_file_block_names(), true ) ) {
            $buckets['files'] .= wpls_get_block_html( $block );
            continue;
        }

        $index = 0;
        foreach ( $block['innerContent'] as $chunk ) {
            if ( is_string( $chunk ) ) {
                $buckets['other'] .= $chunk;
            } elseif ( isset( $block['innerBlocks'][ $index ] ) ) {
                wpls_split_blocks_by_type( [ $block['innerBlocks'][ $index++ ] ], $buckets );
            }
        }
    }
}

/**
 * Splits post content into HTML of image blocks, file blocks and everything else.
 * Classic content without blocks ends up completely in "other".
 */
function wpls_split_content_by_block( $content ) {
    $buckets = [ 'images' => '', 'files' => '', 'other' => '' ];

    if ( ! has_blocks( $content ) ) {
        $buckets['other'] = $content;
        return $buckets;
    }

    wpls_split_blocks_by_type( parse_blocks( $content ), $buckets );

    return $buckets;
}

function wpls_get_attachment_id_from_img( DOMElement $img ) {
    if ( preg_match( '/\bwp-image-(\d+)\b/', $img->getAttribute( 'class' ), $matches ) ) {
        return (int) $matches[1];
    }

    return (int) $img->getAttribute( 'data-id' );
}

function wpls_sanitize_source_type( $source_type ) {
    $allowed = wpls_get_source_types();
    return in_array( $source_type, $allowed, true ) ? $source_type : 'links';
}

function wpls_get_empty_message( $source_type ) {
    $messages = [
        'links'  => __( 'No links found in this post.', 'wp-list-of-sources' ),
        'images' => __( 'No images found in this post.', 'wp-list-of-sources' ),
        'tables' => __( 'No tables found in this post.', 'wp-list-of-sources' ),
        'files'  => __( 'No files found in this post.', 'wp-list-of-sources' ),
    ];

    return isset( $messages[ $source_type ] ) ? $messages[ $source_type ] : $messages['links'];
}

function wpls_get_wrapper_class_string( array $attributes ) {
    $wrapper_classes = [ 'wp-block-table', 'wp-block-sources-table' ];

    if ( ! empty( $attributes['align'] ) ) {
        $wrapper_classes[] = 'align' . $attributes['align'];
    }
    if ( ! empty( $attributes['className'] ) ) {
        $wrapper_classes[] = $attributes['className'];
    }

    return implode( ' ', array_map( 'sanitize_html_class', $wrapper_classes ) );
}

function wpls_get_table_style_class( array $attributes ) {
    return ( ! empty( $attributes['className'] ) && strpos( $attributes['className'], 'is-style-stripes' ) !== false )
        ? 'is-style-stripes'
        : '';
}

// ---------------------------------------------------------------------------
// Data collection
// ---------------------------------------------------------------------------

function wpls_build_anchor_entry( DOMElement $link, $strip_url_prefix, $as_file, $from_file_block = false ) {
    $url  = $link->getAttribute( 'href' );
    $title = trim( $link->getAttribute( 'title' ) );
    $text  = trim( $link->textContent );

    if ( empty( $url ) || strpos( $url, '#' ) === 0 ) {
        return null;
    }

    if ( ! $from_file_block && wpls_is_file_url( $url ) !== $as_file ) {
        return null;
    }

    if ( ! $as_file && $link->getElementsByTagName( 'img' )->length > 0 ) {
        return null;
    }

    $path_only      = parse_url( $url, PHP_URL_PATH );
    $has_real_title = false;

    if ( $as_file ) {
        $caption = wpls_get_figcaption_text( $link );

        if ( ! empty( $caption ) ) {
            $final_title    = $caption;
            $has_real_title = true;
        } elseif ( ! empty( $title ) ) {
            $final_title    = $title;
            $has_real_title = true;
        } elseif ( $from_file_block && ! empty( $text ) ) {
            $final_title    = $text;
            $has_real_title = true;
        } else {
            $filename    = $path_only ? urldecode( basename( $path_only ) ) : '';
            $final_title = ! empty( $filename ) ? $filename : $url;
        }
    } elseif ( ! empty( $title ) ) {
        $final_title    = $title;
        $has_real_title = true;
    } elseif ( ! empty( $text ) ) {
        $final_title    = $text;
        $has_real_title = true;
    } else {
        $final_title = wpls_get_clean_domain_and_path( $url );
    }

    $final_title = trim( $final_title );

    if ( $strip_url_prefix && preg_match( '#^https?://#i', $final_title ) ) {
        $final_title = wpls_get_clean_domain_and_path( $final_title );
    }

    return [
        'url'       => esc_url( $url ),
        'title'     => esc_html( $final_title ),
        'has_title' => $has_real_title,
        'norm_url'  => wpls_normalize_url_for_dedupe( $url ),
    ];
}

function wpls_collect_links_data( DOMDocument $dom, $strip_url_prefix ) {
    $data = [];

    foreach ( $dom->getElementsByTagName( 'a' ) as $link ) {
        $entry = wpls_build_anchor_entry( $link, $strip_url_prefix, false );
        if ( $entry ) {
            $data[] = $entry;
        }
    }

    return wpls_finalize_source_list( $data );
}

function wpls_collect_files_data( DOMDocument $file_blocks_dom, DOMDocument $other_dom, $strip_url_prefix ) {
    $data = [];

    foreach ( $file_blocks_dom->getElementsByTagName( 'a' ) as $link ) {
        if ( strpos( ' ' . $link->getAttribute( 'class' ) . ' ', ' wp-block-file__button ' ) !== false ) {
            continue;
        }
        $entry = wpls_build_anchor_entry( $link, $strip_url_prefix, true, true );
        if ( $entry ) {
            $data[] = $entry;
        }
    }

    foreach ( $other_dom->getElementsByTagName( 'a' ) as $link ) {
        $entry = wpls_build_anchor_entry( $link, $strip_url_prefix, true );
        if ( $entry ) {
            $data[] = $entry;
        }
    }

    return wpls_finalize_source_list( $data );
}

function wpls_collect_images_data( DOMDocument $dom ) {
    $data = [];

    foreach ( $dom->getElementsByTagName( 'img' ) as $img ) {
        $url       = $img->getAttribute( 'src' );
        $alt       = trim( $img->getAttribute( 'alt' ) );
        $title     = trim( $img->getAttribute( 'title' ) );
        $caption   = wpls_get_figcaption_text( $img );

        if ( empty( $url ) ) {
            continue;
        }

        $has_real_title = false;
        $attachment_id  = wpls_get_attachment_id_from_img( $img );

        if ( empty( $title ) && $attachment_id > 0 ) {
            $title = trim( get_the_title( $attachment_id ) );
        }

        if ( ! empty( $title ) ) {
            $final_title    = $title;
            $has_real_title = true;
        } elseif ( ! empty( $caption ) ) {
            $final_title    = $caption;
            $has_real_title = true;
        } elseif ( ! empty( $alt ) ) {
            $final_title    = $alt;
            $has_real_title = true;
        } else {
            $final_title = basename( parse_url( $url, PHP_URL_PATH ) );
            if ( empty( $final_title ) ) {
                $final_title = $url;
            }
        }

        $data[] = [
            'url'       => esc_url( $url ),
            'title'     => esc_html( $final_title ),
            'has_title' => $has_real_title,
            'norm_url'  => wpls_normalize_url_for_dedupe( $url ),
        ];
    }

    return wpls_finalize_source_list( $data );
}

function wpls_collect_tables_data( DOMDocument $dom ) {
    $data        = [];
    $table_count = 1;

    foreach ( $dom->getElementsByTagName( 'table' ) as $table ) {
        $t_title   = '';
        $anchor_id = $table->getAttribute( 'id' );
        $parent    = $table->parentNode;

        if ( empty( $anchor_id ) && $parent && $parent->nodeName === 'figure' ) {
            $anchor_id = $parent->getAttribute( 'id' );
        }
        if ( $parent && $parent->nodeName === 'figure' ) {
            $figcaptions = $parent->getElementsByTagName( 'figcaption' );
            if ( $figcaptions->length > 0 ) {
                $t_title = trim( $figcaptions->item( 0 )->textContent );
            }
        }
        if ( empty( $t_title ) ) {
            $captions = $table->getElementsByTagName( 'caption' );
            if ( $captions->length > 0 ) {
                $t_title = trim( $captions->item( 0 )->textContent );
            }
        }
        if ( empty( $t_title ) ) {
            $t_title = sprintf( __( 'Table %d', 'wp-list-of-sources' ), $table_count );
        }

        $data[] = [
            'title'     => esc_html( $t_title ),
            'anchor_id' => esc_attr( $anchor_id ),
        ];
        $table_count++;
    }

    return $data;
}

function wpls_collect_source_data( $source_type, $html, $strip_url_prefix ) {
    switch ( $source_type ) {
        case 'images':
            return wpls_collect_images_data( wpls_create_dom_from_html( $html ) );
        case 'tables':
            return wpls_collect_tables_data( wpls_create_dom_from_html( $html ) );
    }

    $buckets   = wpls_split_content_by_block( $html );
    $other_dom = wpls_create_dom_from_html( $buckets['other'] );

    if ( $source_type === 'files' ) {
        return wpls_collect_files_data( wpls_create_dom_from_html( $buckets['files'] ), $other_dom, $strip_url_prefix );
    }

    return wpls_collect_links_data( $other_dom, $strip_url_prefix );
}

// ---------------------------------------------------------------------------
// Rendering
// ---------------------------------------------------------------------------

function wpls_render_url_source_item( array $item, $is_editor_preview = false ) {
    $link = sprintf(
        '<a href="%s" target="_blank" rel="noopener">%s</a>',
        $item['url'],
        $item['title']
    );

    if ( ! $is_editor_preview ) {
        return $link;
    }

    return $link . sprintf(
        ' <button type="button" class="wpls-jump-button" data-wpls-jump-url="%s" title="%s">&#8635;</button>',
        esc_attr( $item['url'] ),
        esc_attr__( 'Zum Block im Editor springen', 'wp-list-of-sources' )
    );
}

function wpls_render_table_source_item( array $item, $index ) {
    global $wpls_generated_anchors;

    $final_anchor = ! empty( $wpls_generated_anchors[ $index ] )
        ? $wpls_generated_anchors[ $index ]
        : ( ! empty( $item['anchor_id'] ) ? $item['anchor_id'] : '' );

    if ( empty( $final_anchor ) ) {
        $final_anchor = 'wpls-table-' . ( $index + 1 );
    }

    return sprintf( '<a href="#%s">%s</a>', $final_anchor, $item['title'] );
}

function wpls_render_source_item( $source_type, array $item, $index, $is_editor_preview = false ) {
    if ( $source_type === 'tables' ) {
        return wpls_render_table_source_item( $item, $index );
    }

    return wpls_render_url_source_item( $item, $is_editor_preview );
}

function wpls_render_source_items( $source_type, array $data, $display_format, $table_style_class, $is_editor_preview = false ) {
    if ( empty( $data ) ) {
        return sprintf(
            '<p style="font-style:italic; color:#888; margin:0;">%s</p>',
            wpls_get_empty_message( $source_type )
        );
    }

    $output = '';

    if ( $display_format === 'list' ) {
        $output .= '<ul class="wpls-sources-list" style="margin:0; padding-left:20px;">';
        foreach ( $data as $index => $item ) {
            $output .= sprintf(
                '<li style="margin-bottom:5px;">%s</li>',
                wpls_render_source_item( $source_type, $item, $index, $is_editor_preview )
            );
        }
        $output .= '</ul>';
    } else {
        $output .= sprintf( '<table class="%s"><tbody>', $table_style_class );
        foreach ( $data as $index => $item ) {
            $output .= sprintf(
                '<tr><td>%s</td></tr>',
                wpls_render_source_item( $source_type, $item, $index, $is_editor_preview )
            );
        }
        $output .= '</tbody></table>';
    }

    return $output;
}

function wpls_render_links_source( array $data, $display_format, $table_style_class, $is_editor_preview = false ) {
    return wpls_render_source_items( 'links', $data, $display_format, $table_style_class, $is_editor_preview );
}

function wpls_render_images_source( array $data, $display_format, $table_style_class, $is_editor_preview = false ) {
    return wpls_render_source_items( 'images', $data, $display_format, $table_style_class, $is_editor_preview );
}

function wpls_render_tables_source( array $data, $display_format, $table_style_class, $is_editor_preview = false ) {
    return wpls_render_source_items( 'tables', $data, $display_format, $table_style_class, $is_editor_preview );
}

function wpls_render_files_source( array $data, $display_format, $table_style_class, $is_editor_preview = false ) {
    return wpls_render_source_items( 'files', $data, $display_format, $table_style_class, $is_editor_preview );
}

function wpls_render_source_block( $source_type, array $data, $display_format, $table_style_class, $wrapper_class_str, $is_editor_preview = false ) {
    switch ( $source_type ) {
        case 'images':
            $content = wpls_render_images_source( $data, $display_format, $table_style_class, $is_editor_preview );
            break;
        case 'tables':
            $content = wpls_render_tables_source( $data, $display_format, $table_style_class, $is_editor_preview );
            break;
        case 'files':
            $content = wpls_render_files_source( $data, $display_format, $table_style_class, $is_editor_preview );
            break;
        case 'links':
        default:
            $content = wpls_render_links_source( $data, $display_format, $table_style_class, $is_editor_preview );
            break;
    }

    return sprintf(
        '<div class="%s" style="margin-top: 30px; font-family: sans-serif;">%s</div>',
        $wrapper_class_str,
        $content
    );
}

function wpls_render_template_dummy_preview( $wrapper_class, $source_type, $style, $display_format = 'table' ) {
    $samples = [
        'links'  => [ [ 'href' => '#', 'label' => 'Example source with title' ], [ 'href' => '#', 'label' => 'example.com' ] ],
        'images' => [ [ 'href' => '#', 'label' => 'Background image' ] ],
        'tables' => [ [ 'href' => '#', 'label' => 'Table 1 (Statistics)' ] ],
        'files'  => [ [ 'href' => '#', 'label' => 'example-file.pdf' ] ],
    ];
    $items = isset( $samples[ $source_type ] ) ? $samples[ $source_type ] : $samples['links'];

    $output  = '<div class="' . $wrapper_class . '" style="border: 1px dashed #ccc; padding: 15px; background: #fafafa; font-family: sans-serif;">';
    $output .= '<span style="display:block; font-size:11px; color:#999; text-transform:uppercase; margin-bottom:10px;">' . esc_html__( 'Live Preview (Template Mode)', 'wp-list-of-sources' ) . '</span>';

    if ( $display_format === 'list' ) {
        $output .= '<ul style="margin:0; padding-left:20px;">';
        foreach ( $items as $item ) {
            $output .= sprintf( '<li><a href="%s">%s</a></li>', esc_url( $item['href'] ), esc_html( $item['label'] ) );
        }
        $output .= '</ul>';
    } else {
        $output .= sprintf( '<table class="%s"><tbody>', $style );
        foreach ( $items as $item ) {
            $link = $source_type === 'tables'
                ? esc_html( $item['label'] )
                : sprintf( '<a href="%s">%s</a>', esc_url( $item['href'] ), esc_html( $item['label'] ) );
            $output .= sprintf( '<tr><td>%s</td></tr>', $link );
        }
        $output .= '</tbody></table>';
    }

    $output .= '</div>';
    return $output;
}

// ---------------------------------------------------------------------------
// Block render callback
// ---------------------------------------------------------------------------

function wpls_render_sources_table( $attributes, $content ) {
    $post_id = false;
    if ( defined( 'REST_REQUEST' ) && REST_REQUEST && ! empty( $_GET['post_id'] ) ) {
        $post_id = intval( $_GET['post_id'] );
    }
    if ( ! $post_id ) {
        $post_id = get_the_ID();
    }

    $post                = $post_id ? get_post( $post_id ) : null;
    $is_template_preview = ( ! $post || $post->post_type === 'wp_block' || $post->post_type === 'wp_template' || $post_id === 0 );
    $is_editor_preview   = ( defined( 'REST_REQUEST' ) && REST_REQUEST );

    $settings         = wpls_get_settings();
    $source_type      = wpls_sanitize_source_type( ! empty( $attributes['sourceType'] ) ? $attributes['sourceType'] : 'links' );
    $display_format   = ! empty( $attributes['displayFormat'] ) ? $attributes['displayFormat'] : $settings['display_format'];
    $strip_url_prefix = array_key_exists( 'stripUrlPrefix', $attributes ) ? (bool) $attributes['stripUrlPrefix'] : true;
    $wrapper_class    = wpls_get_wrapper_class_string( $attributes );
    $table_style      = wpls_get_table_style_class( $attributes );

    if ( $is_template_preview ) {
        return wpls_render_template_dummy_preview( $wrapper_class, $source_type, $table_style, $display_format );
    }

    $content_version = get_post_meta( $post_id, '_wpls_content_version', true );
    if ( empty( $content_version ) ) {
        $content_version = '0';
    }

    $cache_key = 'wpls_cache_' . $post_id . '_' . $content_version . '_' . md5( serialize( $attributes ) . serialize( $settings ) );

    if ( ! $is_editor_preview ) {
        $cached_output = get_transient( $cache_key );
        if ( $cached_output !== false ) {
            return $cached_output;
        }
    }

    $html = $post->post_content;
    if ( empty( trim( $html ) ) ) {
        return '<p style="font-style:italic; color:#666;">' . esc_html__( 'No content found to analyze.', 'wp-list-of-sources' ) . '</p>';
    }

    $data   = wpls_collect_source_data( $source_type, $html, $strip_url_prefix );
    $output = wpls_render_source_block( $source_type, $data, $display_format, $table_style, $wrapper_class, $is_editor_preview );

    if ( $is_editor_preview ) {
        return $output;
    }

    set_transient( $cache_key, $output, intval( $settings['cache_duration'] ) * HOUR_IN_SECONDS );

    return $output;
}
