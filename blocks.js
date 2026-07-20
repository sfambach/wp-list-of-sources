( function( blocks, element, blockEditor, components, data, i18n ) {
    var el = element.createElement;
    var TextControl = components.TextControl;
    var SelectControl = components.SelectControl;
    var PanelBody = components.PanelBody;

    var InspectorControls = blockEditor.InspectorControls;
    var BlockControls = blockEditor.BlockControls;
    var BlockAlignmentControl = blockEditor.BlockAlignmentControl;
    
    var __ = i18n.__;

    blocks.registerBlockType( 'wpls/sources-table', {
        title: __( 'List of Sources Table', 'wp-list-of-sources' ),
        icon: 'editor-table',
        category: 'common',
        supports: {
            align: [ 'left', 'center', 'right', 'wide', 'full' ],
            className: true,
            styles: true // Aktiviert die nativen runden WP-Stil-Vorschau-Buttons
        },
        attributes: {
            titleSources: { type: 'string', default: 'Quellen' },
            titleImages: { type: 'string', default: 'Bilder' },
            titleTables: { type: 'string', default: 'Tabellen' },
            headingTag: { type: 'string', default: 'h3' },
            align: { type: 'string', default: '' },
            className: { type: 'string', default: '' }
        },
        edit: function( props ) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;

            var editorSelect = data.select( 'core/editor' );
            var currentPostId = editorSelect ? editorSelect.getCurrentPostId() : null;

            var blocksContentHash = data.useSelect( function( select ) {
                var editor = select( 'core/editor' );
                return editor ? editor.getEditedPostContent() : '';
            }, [] );

            var queryArgs = { trigger: blocksContentHash ? blocksContentHash.length : 0 };
            if ( currentPostId ) { queryArgs.post_id = currentPostId; }

            return [
                el( BlockControls, { key: 'controls' },
                    el( BlockAlignmentControl, {
                        value: attributes.align,
                        onChange: function( nextAlign ) { setAttributes( { align: nextAlign } ); }
                    } )
                ),

                el( InspectorControls, { key: 'inspector' },
                    el( PanelBody, { title: __( 'Section Titles', 'wp-list-of-sources' ), initialOpen: true },
                        el( TextControl, {
                            label: __( 'Title for Sources', 'wp-list-of-sources' ),
                            value: attributes.titleSources,
                            onChange: function( value ) { setAttributes( { titleSources: value } ); }
                        } ),
                        el( TextControl, {
                            label: __( 'Title for Images', 'wp-list-of-sources' ),
                            value: attributes.titleImages,
                            onChange: function( value ) { setAttributes( { titleImages: value } ); }
                        } ),
                        el( TextControl, {
                            label: __( 'Title for Tables', 'wp-list-of-sources' ),
                            value: attributes.titleTables,
                            onChange: function( value ) { setAttributes( { titleTables: value } ); }
                        } )
                    ),
                    
                    el( PanelBody, { title: __( 'Design & Formatting', 'wp-list-of-sources' ), initialOpen: true },
                        el( SelectControl, {
                            label: __( 'Heading Tag', 'wp-list-of-sources' ),
                            value: attributes.headingTag,
                            options: [
                                { label: 'Heading 2 (h2)', value: 'h2' },
                                { label: 'Heading 3 (h3)', value: 'h3' },
                                { label: 'Heading 4 (h4)', value: 'h4' },
                                { label: 'Heading 5 (h5)', value: 'h5' },
                                { label: 'Heading 6 (h6)', value: 'h6' },
                                { label: 'Paragraph (p)', value: 'p' },
                                { label: 'Division (div)', value: 'div' }
                            ],
                            onChange: function( value ) { setAttributes( { headingTag: value } ); }
                        } )
                    )
                ),

                el( 'div', { key: 'preview' },
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

} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.data, window.wp.i18n );
