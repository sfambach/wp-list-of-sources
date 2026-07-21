( function( blocks, element, blockEditor, components, data, i18n ) {
    var el = element.createElement;
    var TextControl = components.TextControl;
    var SelectControl = components.SelectControl;
    var PanelBody = components.PanelBody;

    var ToggleControl = components.ToggleControl;

    var InspectorControls = blockEditor.InspectorControls;
    var BlockControls = blockEditor.BlockControls;
    var BlockAlignmentControl = blockEditor.BlockAlignmentControl;
    // FIX: HeadingLevelDropdown war eine interne/private WP-Komponente, die in vielen
    // WP-Versionen unter diesem Namen gar nicht öffentlich existiert - dadurch verschwand
    // die Überschriften-Einstellung komplett. Ersetzt durch ein robustes SelectControl
    // weiter unten im Inspector-Panel.

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
            titleFiles: { type: 'string', default: 'Dateien' },
            headingTag: { type: 'string', default: 'h3' },
            displayFormat: { type: 'string', default: 'table' },
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

            var blocksContentHash = data.useSelect( function( select ) {
                var editor = select( 'core/editor' );
                return editor ? editor.getEditedPostContent() : '';
            }, [] );

            // FIX: Die Vorschau rendert serverseitig den GESPEICHERTEN Beitragsinhalt, nicht den
            // gerade im Editor getippten Text. Ein reiner Text-Änderungs-Trigger (blocksContentHash)
            // löst zwar beim Tippen einen Refresh aus, der aber noch die alten (ungespeicherten)
            // Daten liefert. Deshalb wird hier zusätzlich erkannt, wann ein Speichervorgang fertig
            // ist, und dann ein weiterer Refresh erzwungen - der dann die frisch gespeicherten
            // Bilder/Links tatsächlich anzeigt, ohne dass ein kompletter Seiten-Reload nötig ist.
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
                    // Speichervorgang ist gerade abgeschlossen -> Vorschau neu anfragen
                    setRefreshToken( function( t ) { return t + 1; } );
                }
                wasSavingRef.current = isSaving;
            }, [ isSaving ] );

            var queryArgs = { trigger: ( blocksContentHash ? blocksContentHash.length : 0 ) + '-' + refreshToken };
            if ( currentPostId ) { queryArgs.post_id = currentPostId; }

            var headingOptions = [ 1, 2, 3, 4, 5, 6 ].map( function( level ) {
                return { label: 'H' + level, value: 'h' + level };
            } );

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
                        } ),
                        el( TextControl, {
                            label: __( 'Title for Files', 'wp-list-of-sources' ),
                            value: attributes.titleFiles,
                            onChange: function( value ) { setAttributes( { titleFiles: value } ); }
                        } )
                    ),
                    
                    el( PanelBody, { title: __( 'Design & Formatting', 'wp-list-of-sources' ), initialOpen: true },
                        // FIX: robuster Ersatz für die verschwundene HeadingLevelDropdown-Einstellung
                        el( SelectControl, {
                            label: __( 'Heading Level', 'wp-list-of-sources' ),
                            value: attributes.headingTag,
                            options: headingOptions,
                            onChange: function( value ) { setAttributes( { headingTag: value } ); }
                        } ),
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
