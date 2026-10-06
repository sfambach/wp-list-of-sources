( function( blocks, element, blockEditor, components, data, i18n ) {
    var el = element.createElement;
    var SelectControl = components.SelectControl;
    var PanelBody = components.PanelBody;
    var ToggleControl = components.ToggleControl;

    var InspectorControls = blockEditor.InspectorControls;
    var BlockControls = blockEditor.BlockControls;
    var BlockAlignmentControl = blockEditor.BlockAlignmentControl;

    var __ = i18n.__;

    function flattenBlocks( blockList, acc ) {
        acc = acc || [];
        blockList.forEach( function( block ) {
            acc.push( block );
            if ( block.innerBlocks && block.innerBlocks.length ) {
                flattenBlocks( block.innerBlocks, acc );
            }
        } );
        return acc;
    }

    function getUrlFilename( url ) {
        var clean = url.split( '?' )[ 0 ].split( '#' )[ 0 ];
        try {
            clean = decodeURIComponent( clean );
        } catch ( e ) {}
        var parts = clean.split( '/' );
        return parts[ parts.length - 1 ] || '';
    }

    function blockMatchesUrl( block, url ) {
        var attrs = block.attributes || {};
        if ( Array.isArray( attrs.links ) && attrs.links.some( function( link ) { return link && link.url === url; } ) ) {
            return true;
        }
        for ( var key in attrs ) {
            if ( ! attrs.hasOwnProperty( key ) ) { continue; }
            var value = attrs[ key ];
            if ( typeof value !== 'string' || ! value ) { continue; }
            if ( value === url ) { return true; }
            if ( value.indexOf( url ) !== -1 &&
                ( value.indexOf( 'href="' + url ) !== -1 ||
                  value.indexOf( "href='" + url ) !== -1 ||
                  value.indexOf( 'src="' + url ) !== -1 ||
                  value.indexOf( "src='" + url ) !== -1 ) ) {
                return true;
            }
        }

        // Fallback: the block's stored attributes can drift from the actual
        // markup (e.g. after replacing a media file). Match by filename
        // against the block's serialized HTML instead of the exact URL.
        var filename = getUrlFilename( url );
        if ( filename ) {
            var html = '';
            try {
                html = blocks.serialize( block );
            } catch ( e ) {}
            if ( html && html.toLowerCase().indexOf( filename.toLowerCase() ) !== -1 ) {
                return true;
            }
        }

        return false;
    }

    function findClientIdForUrl( url ) {
        var blocks = data.select( 'core/block-editor' ).getBlocks();
        var all    = flattenBlocks( blocks );
        for ( var i = 0; i < all.length; i++ ) {
            if ( blockMatchesUrl( all[ i ], url ) ) {
                return all[ i ].clientId;
            }
        }
        return null;
    }

    function jumpToBlock( clientId ) {
        if ( ! clientId ) { return; }
        data.dispatch( 'core/block-editor' ).selectBlock( clientId );
        setTimeout( function() {
            var node = document.querySelector( '[data-block="' + clientId + '"]' );
            if ( node && node.scrollIntoView ) {
                node.scrollIntoView( { behavior: 'smooth', block: 'center' } );
            }
        }, 50 );
    }

    blocks.registerBlockType( 'wpls/sources-table', {
        title: __( 'List of Sources', 'wp-list-of-sources' ),
        icon: 'editor-table',
        category: 'common',
        supports: {
            align: [ 'left', 'center', 'right', 'wide', 'full' ],
            className: true,
            styles: true
        },
        attributes: {
            sourceType: { type: 'string', default: 'links' },
            displayFormat: { type: 'string', default: 'list' },
            stripUrlPrefix: { type: 'boolean', default: true },
            align: { type: 'string', default: '' },
            className: { type: 'string', default: '' }
        },
        edit: function( props ) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var useEffect = element.useEffect;
            var useState = element.useState;

            var editorSelect = data.select( 'core/editor' );
            var currentPostId = editorSelect ? editorSelect.getCurrentPostId() : null;

            var isSaving = data.useSelect( function( select ) {
                var editor = select( 'core/editor' );
                return editor ? ( editor.isSavingPost() && ! editor.isAutosavingPost() ) : false;
            }, [] );

            var refreshState = useState( 0 );
            var refreshToken = refreshState[ 0 ];
            var setRefreshToken = refreshState[ 1 ];
            var wasSavingRef = element.useRef( false );

            useEffect( function() {
                if ( wasSavingRef.current && ! isSaving ) {
                    setRefreshToken( function( t ) { return t + 1; } );
                }
                wasSavingRef.current = isSaving;
            }, [ isSaving ] );

            var queryArgs = { trigger: refreshToken };
            if ( currentPostId ) { queryArgs.post_id = currentPostId; }

            var previewRef = element.useRef( null );

            useEffect( function() {
                var container = previewRef.current;
                if ( ! container ) { return; }

                function handleClick( e ) {
                    var button = e.target.closest ? e.target.closest( '.wpls-jump-button' ) : null;
                    if ( ! button || ! container.contains( button ) ) { return; }
                    e.preventDefault();
                    var url = button.getAttribute( 'data-wpls-jump-url' );
                    if ( url ) {
                        jumpToBlock( findClientIdForUrl( url ) );
                    }
                }

                container.addEventListener( 'click', handleClick );
                return function() { container.removeEventListener( 'click', handleClick ); };
            }, [ attributes, refreshToken ] );

            var sourceTypeOptions = [
                { label: __( 'Links', 'wp-list-of-sources' ), value: 'links' },
                { label: __( 'Images', 'wp-list-of-sources' ), value: 'images' },
                { label: __( 'Tables', 'wp-list-of-sources' ), value: 'tables' },
                { label: __( 'Files', 'wp-list-of-sources' ), value: 'files' }
            ];

            return [
                el( BlockControls, { key: 'controls' },
                    el( BlockAlignmentControl, {
                        value: attributes.align,
                        onChange: function( nextAlign ) { setAttributes( { align: nextAlign } ); }
                    } )
                ),

                el( InspectorControls, { key: 'inspector' },
                    el( PanelBody, { title: __( 'Source Settings', 'wp-list-of-sources' ), initialOpen: true },
                        el( SelectControl, {
                            label: __( 'Source Type', 'wp-list-of-sources' ),
                            value: attributes.sourceType,
                            options: sourceTypeOptions,
                            onChange: function( value ) { setAttributes( { sourceType: value } ); }
                        } )
                    ),

                    el( PanelBody, { title: __( 'Display', 'wp-list-of-sources' ), initialOpen: true },
                        el( SelectControl, {
                            label: __( 'Display Format', 'wp-list-of-sources' ),
                            value: attributes.displayFormat,
                            options: [
                                { label: __( 'Table (Rows)', 'wp-list-of-sources' ), value: 'table' },
                                { label: __( 'Unordered List (Bullets)', 'wp-list-of-sources' ), value: 'list' }
                            ],
                            onChange: function( value ) { setAttributes( { displayFormat: value } ); }
                        } ),
                        el( ToggleControl, {
                            label: __( 'Remove http(s):// and www. from labels', 'wp-list-of-sources' ),
                            checked: attributes.stripUrlPrefix,
                            onChange: function( value ) { setAttributes( { stripUrlPrefix: value } ); }
                        } )
                    )
                ),

                el( 'div', { key: 'preview', ref: previewRef },
                    el( wp.serverSideRender, {
                        block: 'wpls/sources-table',
                        attributes: attributes,
                        urlQueryArgs: queryArgs
                    } )
                )
            ];
        },
        save: function() { return null; }
    } );

    blocks.registerBlockType( 'wpls/extra-sources', {
        title: __( 'Additional Sources', 'wp-list-of-sources' ),
        description: __( 'Sources that are not linked in the text. Not shown on the page, only in the List of Sources.', 'wp-list-of-sources' ),
        icon: 'admin-links',
        category: 'common',
        supports: { html: false },
        attributes: {
            links: { type: 'array', default: [] }
        },
        edit: function( props ) {
            var links = props.attributes.links || [];
            var TextControl = components.TextControl;
            var Button = components.Button;

            function update( index, key, value ) {
                var next = links.map( function( link, i ) {
                    if ( i !== index ) { return link; }
                    var copy = { url: link.url || '', title: link.title || '' };
                    copy[ key ] = value;
                    return copy;
                } );
                props.setAttributes( { links: next } );
            }

            function remove( index ) {
                props.setAttributes( { links: links.filter( function( link, i ) { return i !== index; } ) } );
            }

            function add() {
                props.setAttributes( { links: links.concat( [ { url: '', title: '' } ] ) } );
            }

            return el( 'div', { className: 'wpls-extra-sources', style: { border: '1px dashed #ccc', padding: '12px', background: '#fafafa' } },
                el( 'strong', { style: { display: 'block', marginBottom: '4px' } }, __( 'Additional Sources', 'wp-list-of-sources' ) ),
                el( 'p', { style: { fontSize: '12px', color: '#757575', margin: '0 0 8px' } },
                    __( 'Sources that are not linked in the text. Not shown on the page, only in the List of Sources.', 'wp-list-of-sources' )
                ),
                links.map( function( link, index ) {
                    return el( 'div', { key: index, style: { display: 'flex', gap: '8px', alignItems: 'flex-end', marginBottom: '8px' } },
                        el( 'div', { style: { flex: '2' } },
                            el( TextControl, {
                                label: __( 'URL', 'wp-list-of-sources' ),
                                type: 'url',
                                value: link.url || '',
                                onChange: function( value ) { update( index, 'url', value ); },
                                __nextHasNoMarginBottom: true
                            } )
                        ),
                        el( 'div', { style: { flex: '2' } },
                            el( TextControl, {
                                label: __( 'Title', 'wp-list-of-sources' ),
                                value: link.title || '',
                                onChange: function( value ) { update( index, 'title', value ); },
                                __nextHasNoMarginBottom: true
                            } )
                        ),
                        el( Button, {
                            icon: 'trash',
                            label: __( 'Remove source', 'wp-list-of-sources' ),
                            isDestructive: true,
                            onClick: function() { remove( index ); }
                        } )
                    );
                } ),
                el( Button, { variant: 'secondary', icon: 'plus', onClick: add }, __( 'Add source', 'wp-list-of-sources' ) )
            );
        },
        save: function() { return null; }
    } );

} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.data, window.wp.i18n );
