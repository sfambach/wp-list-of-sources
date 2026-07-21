( function( blocks, element, blockEditor, components, data, i18n ) {
    var el = element.createElement;
    var TextControl = components.TextControl;
    var SelectControl = components.SelectControl;
    var PanelBody = components.PanelBody;

    var InspectorControls = blockEditor.InspectorControls;
    var BlockControls = blockEditor.BlockControls;
    var BlockAlignmentControl = blockEditor.BlockAlignmentControl;
    // REPARIERT: HeadingLevelDropdown fehlte hier oben im WP-Import!
    var HeadingLevelDropdown = blockEditor.HeadingLevelDropdown; 
    
    var __ = i18n.__;

    blocks.registerBlockType( 'wpls/sources-table', {
        title: __( 'List of Sources Table', 'wp-list-of-sources' ),
        icon: 'editor-table',
        category: 'common',
        supports: {
            align: [ 'left', 'center', 'right', 'wide', 'full' ],
            className: true,
            styles: true
        },
        attributes: {
            titleSources: { type: 'string', default: 'Quellen' },
            titleImages: { type: 'string', default: 'Bilder' },
            titleTables: { type: 'string', default: 'Tabellen' },
            headingTag: { type: 'string', default: 'h3' },
            displayFormat: { type: 'string', default: 'table' },
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

            // Numerische Stufe für das Dropdown ermitteln (z.B. "h3" -> 3)
            var currentLevel = parseInt( attributes.headingTag.replace( 'h', '' ) ) || 3;

            return [
                el( BlockControls, { key: 'controls' },
                    el( BlockAlignmentControl, {
                        value: attributes.align,
                        onChange: function( nextAlign ) { setAttributes( { align: nextAlign } ); }
                    } ),
                    // REPARIERT: Nutzt jetzt die native WP-Komponente ohne Namespace-Fehler
                    el( HeadingLevelDropdown, {
                        value: currentLevel,
                        levels: [ 1, 2, 3, 4, 5, 6 ],
                        onChange: function( newLevel ) {
                            setAttributes( { headingTag: 'h' + newLevel } );
                        }
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
                            label: __( 'Display Format', 'wp-list-of-sources' ),
                            value: attributes.displayFormat,
                            options: [
                                { label: __( 'Table (Rows)', 'wp-list-of-sources' ), value: 'table' },
                                { label: __( 'Unordered List (Bullets)', 'wp-list-of-sources' ), value: 'list' }
                            ],
                            onChange: function( value ) { setAttributes( { displayFormat: value } ); }
                        } )
                    )
                ),

                el( 'div', { key: 'preview' },
                    el( wp.serverSideRender, {
                        // REPARIERT: Hier stand zuvor fälschlicherweise 'wpc/change-table'
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
