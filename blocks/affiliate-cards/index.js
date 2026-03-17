( function ( blocks, blockEditor, components, element, i18n ) {
    const el = element.createElement;
    const registerBlockType = blocks.registerBlockType;
    const InspectorControls = blockEditor.InspectorControls;
    const useBlockProps = blockEditor.useBlockProps;
    const PanelBody = components.PanelBody;
    const TextControl = components.TextControl;
    const TextareaControl = components.TextareaControl;
    const SelectControl = components.SelectControl;
    const Button = components.Button;

    function AffiliateCardsEdit( props ) {
        const attributes = props.attributes;
        const items = attributes.items || [];
        const blockProps = useBlockProps( { className: 'mtb-affiliate-cards-editor' } );

        function updateItem( index, key, value ) {
            const nextItems = items.slice();
            nextItems[ index ] = Object.assign( {}, nextItems[ index ], { [ key ]: value } );
            props.setAttributes( { items: nextItems } );
        }

        function addItem() {
            props.setAttributes( {
                items: items.concat( [ { asin: '', benefit: '', titleOverride: '' } ] )
            } );
        }

        function removeItem( index ) {
            props.setAttributes( {
                items: items.filter( function ( _, itemIndex ) {
                    return itemIndex !== index;
                } )
            } );
        }

        return el(
            element.Fragment,
            {},
            el(
                InspectorControls,
                {},
                el(
                    PanelBody,
                    { title: i18n.__( 'Block-Einstellungen', 'meintechblog-affiliate-cards' ), initialOpen: true },
                    el( SelectControl, {
                        label: i18n.__( 'Badge-Modus', 'meintechblog-affiliate-cards' ),
                        value: attributes.badgeMode || 'auto',
                        options: [
                            { label: 'Automatisch', value: 'auto' },
                            { label: 'Immer Im Video verwendet', value: 'video' },
                            { label: 'Immer Passend zu diesem Setup', value: 'setup' }
                        ],
                        onChange: function ( value ) {
                            props.setAttributes( { badgeMode: value } );
                        }
                    } ),
                    el( TextControl, {
                        label: i18n.__( 'CTA-Text', 'meintechblog-affiliate-cards' ),
                        value: attributes.ctaLabel || 'Preis auf Amazon checken',
                        onChange: function ( value ) {
                            props.setAttributes( { ctaLabel: value } );
                        }
                    } )
                )
            ),
            el(
                'div',
                blockProps,
                el( 'h3', {}, 'Affiliate Cards' ),
                items.map( function ( item, index ) {
                    return el(
                        'div',
                        { key: index, className: 'mtb-affiliate-cards-editor__item' },
                        el( TextControl, {
                            label: 'ASIN',
                            value: item.asin || '',
                            onChange: function ( value ) {
                                updateItem( index, 'asin', value );
                            }
                        } ),
                        el( TextControl, {
                            label: 'Kurztitel überschreiben',
                            value: item.titleOverride || '',
                            onChange: function ( value ) {
                                updateItem( index, 'titleOverride', value );
                            }
                        } ),
                        el( TextareaControl, {
                            label: 'Nutzenzeile',
                            value: item.benefit || '',
                            onChange: function ( value ) {
                                updateItem( index, 'benefit', value );
                            }
                        } ),
                        el(
                            'div',
                            { className: 'mtb-affiliate-cards-editor__preview' },
                            el( 'strong', {}, item.titleOverride || item.asin || 'Neue Karte' ),
                            el( 'p', {}, item.benefit || 'Nutzenzeile erscheint hier in der Vorschau.' )
                        ),
                        el(
                            Button,
                            {
                                isDestructive: true,
                                onClick: function () {
                                    removeItem( index );
                                }
                            },
                            'Produkt entfernen'
                        )
                    );
                } ),
                el(
                    Button,
                    {
                        variant: 'primary',
                        onClick: addItem
                    },
                    'Produkt hinzufügen'
                )
            )
        );
    }

    registerBlockType( 'meintechblog/affiliate-cards', {
        edit: AffiliateCardsEdit,
        save: function () {
            return null;
        }
    } );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n );
