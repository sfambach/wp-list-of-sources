( function( blocks, element, blockEditor, components, data, i18n ) {
    var el = element.createElement;
    var SelectControl = components.SelectControl;
    var PanelBody = components.PanelBody;
    var ToggleControl = components.ToggleControl;

    var InspectorControls = blockEditor.InspectorControls;
    var BlockControls = blockEditor.BlockControls;
    var BlockAlignmentControl = blockEditor.BlockAlignmentControl;

    var __ = i18n.__;

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
