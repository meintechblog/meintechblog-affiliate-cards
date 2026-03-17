( function ( blocks, blockEditor, components, element, i18n ) {
    const el = element.createElement;
    const registerBlockType = blocks.registerBlockType;
    const createBlock = blocks.createBlock;
    const InspectorControls = blockEditor.InspectorControls;
    const useBlockProps = blockEditor.useBlockProps;
    const PanelBody = components.PanelBody;
    const TextControl = components.TextControl;
    const TextareaControl = components.TextareaControl;
    const SelectControl = components.SelectControl;
    const Button = components.Button;
    const TOKEN_PATTERN = /^amazon:([A-Z0-9]{10})$/;
    let isHandlingTokenReplacement = false;

    function normalizeParagraphContent( content ) {
        return String( content || '' )
            .replace( /<[^>]+>/g, '' )
            .replace( /&nbsp;/g, ' ' )
            .trim();
    }

    function extractAmazonToken( content ) {
        const match = normalizeParagraphContent( content ).match( TOKEN_PATTERN );
        return match ? match[ 1 ] : null;
    }

    function flattenBlocks( blockList ) {
        return ( blockList || [] ).reduce( function ( acc, block ) {
            acc.push( block );
            if ( block && Array.isArray( block.innerBlocks ) && block.innerBlocks.length ) {
                acc = acc.concat( flattenBlocks( block.innerBlocks ) );
            }
            return acc;
        }, [] );
    }

    function collectExistingAsins( blockList ) {
        return flattenBlocks( blockList ).reduce( function ( asins, block ) {
            if ( ! block || block.name !== 'meintechblog/affiliate-cards' ) {
                return asins;
            }

            ( block.attributes.items || [] ).forEach( function ( item ) {
                const asin = item && item.asin ? String( item.asin ).trim() : '';
                if ( asin ) {
                    asins.add( asin );
                }
            } );
            return asins;
        }, new Set() );
    }

    function installAmazonParagraphTrigger() {
        if (
            ! window.wp ||
            ! window.wp.data ||
            ! window.wp.data.select ||
            ! window.wp.data.dispatch ||
            ! window.wp.domReady
        ) {
            return;
        }

        window.wp.domReady( function () {
            window.wp.data.subscribe( function () {
                if ( isHandlingTokenReplacement ) {
                    return;
                }

                const editorSelect = window.wp.data.select( 'core/block-editor' );
                const editorDispatch = window.wp.data.dispatch( 'core/block-editor' );

                if ( ! editorSelect || ! editorDispatch ) {
                    return;
                }

                const allBlocks = flattenBlocks( editorSelect.getBlocks() );
                const selectedClientId = editorSelect.getSelectedBlockClientId();
                const tokenBlock = allBlocks.find( function ( block ) {
                    return (
                        block &&
                        block.name === 'core/paragraph' &&
                        block.clientId !== selectedClientId &&
                        extractAmazonToken( block.attributes && block.attributes.content )
                    );
                } );

                if ( ! tokenBlock ) {
                    return;
                }

                const asin = extractAmazonToken( tokenBlock.attributes && tokenBlock.attributes.content );
                if ( ! asin ) {
                    return;
                }

                isHandlingTokenReplacement = true;

                try {
                    const existingAsins = collectExistingAsins(
                        allBlocks.filter( function ( block ) {
                            return block.clientId !== tokenBlock.clientId;
                        } )
                    );

                    if ( existingAsins.has( asin ) ) {
                        editorDispatch.removeBlocks( [ tokenBlock.clientId ], false );
                        if ( window.wp.data.dispatch( 'core/notices' ) ) {
                            window.wp.data.dispatch( 'core/notices' ).createNotice(
                                'info',
                                'Dieses Amazon-Produkt ist in diesem Beitrag bereits vorhanden.',
                                { type: 'snackbar', isDismissible: true }
                            );
                        }
                    } else {
                        editorDispatch.replaceBlocks(
                            tokenBlock.clientId,
                            createBlock( 'meintechblog/affiliate-cards', {
                                items: [ { asin: asin } ],
                                badgeMode: 'auto',
                                ctaLabel: 'Preis auf Amazon checken',
                                autoShortenTitles: true
                            } )
                        );
                    }
                } finally {
                    window.setTimeout( function () {
                        isHandlingTokenReplacement = false;
                    }, 0 );
                }
            } );
        } );
    }

    installAmazonParagraphTrigger();

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
