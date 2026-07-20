( function( blocks, element, blockEditor, components, data, i18n ) {
    var el = element.createElement;
    var TextControl = components.TextControl;
    var ToggleControl = components.ToggleControl;
    var PanelBody = components.PanelBody;

    var InspectorControls = blockEditor.InspectorControls;
    var BlockControls = blockEditor.BlockControls;
    var BlockAlignmentControl = blockEditor.BlockAlignmentControl;
    
    var __ = i18n.__;

    /**
     * BLOCK 1: Minimalistischer Daten-Input-Block (Backend Only)
     */
    blocks.registerBlockType( 'wpm/input-item', {
        title: __( 'Input Item (Backend Only)', 'wp-my-plugin' ),
        icon: 'edit',
        category: 'common',
        attributes: {
            fieldOne: { type: 'string', default: '' },
            fieldTwo: { type: 'string', default: '' }
        },
        edit: function( props ) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;

            return el( 'div', { 
                style: { 
                    display: 'flex', alignItems: 'center', gap: '8px', padding: '2px 6px', 
                    backgroundColor: '#f5f5f5', marginBottom: '4px', borderRadius: '2px', borderLeft: '3px solid #007cba'
                } 
            },
                el( 'div', { style: { width: '100px' }, className: 'wpm-minimal-input' },
                    el( TextControl, {
                        value: attributes.fieldOne,
                        onChange: function( value ) { setAttributes( { fieldOne: value } ); },
                        placeholder: __( 'Field 1', 'wp-my-plugin' )
                    } )
                ),
                el( 'div', { style: { flex: '1' }, className: 'wpm-minimal-input' },
                    el( TextControl, {
                        value: attributes.fieldTwo,
                        onChange: function( value ) { setAttributes( { fieldTwo: value } ); },
                        placeholder: __( 'Field 2 (Description...)', 'wp-my-plugin' )
                    } )
                )
            );
        },
        save: function() { return null; }
    } );

    /**
     * BLOCK 2: Ausgabe-Block mit sicherer Vorschau in Vorlagen
     */
    blocks.registerBlockType( 'wpm/display-block', {
        title: __( 'Display Block', 'wp-my-plugin' ),
        icon: 'visibility',
        category: 'common',
        supports: {
            align: [ 'left', 'center', 'right', 'wide', 'full' ],
            className: true,
            styles: true
        },
        attributes: {
            toggleOption: { type: 'boolean', default: true },
            align: { type: 'string', default: '' },
            className: { type: 'string', default: '' }
        },
        edit: function( props ) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;

            var editorSelect = data.select( 'core/editor' );
            var currentPostId = editorSelect ? editorSelect.getCurrentPostId() : null;

            // Zwingt ServerSideRender bei Inhaltsänderungen im Editor in Echtzeit zum Neuladen
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
                    el( PanelBody, { title: __( 'Block Settings', 'wp-my-plugin' ), initialOpen: true },
                        el( ToggleControl, {
                            label: __( 'Enable Option', 'wp-my-plugin' ),
                            checked: attributes.toggleOption,
                            onChange: function( value ) { setAttributes( { toggleOption: value } ); }
                        } )
                    )
                ),

                el( 'div', { key: 'preview', style: { border: '1px dashed #ccc', padding: '10px', backgroundColor: '#fafafa' } },
                    el( 'span', { style: { display: 'block', fontSize: '11px', color: '#999', marginBottom: '5px', textTransform: 'uppercase' } }, __( 'Live Preview:', 'wp-my-plugin' ) ),
                    el( wp.serverSideRender, {
                        block: 'wpm/display-block',
                        attributes: attributes,
                        urlQueryArgs: queryArgs
                    } )
                )
            ];
        },
        save: function() { return null; }
    } );

} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.data, window.wp.i18n );
