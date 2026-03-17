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
    const HYDRATION_ENDPOINT = 'mtb-affiliate-cards/v1/item';
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

    function buildHydrationUrl( asin, postId ) {
        const root = ( window.wpApiSettings && window.wpApiSettings.root ) ? window.wpApiSettings.root : '/wp-json/';
        const trimmedRoot = root.replace( /\/+$/, '' );
        let url = trimmedRoot + '/' + HYDRATION_ENDPOINT + '?asin=' + encodeURIComponent( asin );
        if ( postId ) {
            url += '&postId=' + encodeURIComponent( String( postId ) );
        }
        return url;
    }

    function normalizeHydratedImages( payload ) {
        if ( Array.isArray( payload.images ) && payload.images.length ) {
            return payload.images.filter( function ( value ) {
                return typeof value === 'string' && value.length > 0;
            } );
        }

        if ( typeof payload.imageUrl === 'string' && payload.imageUrl ) {
            return [ payload.imageUrl ];
        }

        return [];
    }

    function getLiveHydrationBlock( editorSelect, clientId, asin ) {
        if ( ! editorSelect || ! editorSelect.getBlock ) {
            return null;
        }

        const liveBlock = editorSelect.getBlock( clientId );
        if ( ! liveBlock || liveBlock.name !== 'meintechblog/affiliate-cards' ) {
            return null;
        }

        const liveItems = Array.isArray( liveBlock.attributes && liveBlock.attributes.items )
            ? liveBlock.attributes.items
            : [];
        const currentAsin = liveItems[ 0 ] && liveItems[ 0 ].asin
            ? String( liveItems[ 0 ].asin ).trim().toUpperCase()
            : '';

        if ( currentAsin !== asin ) {
            return null;
        }

        return liveBlock;
    }

    function hydrateAffiliateBlock( editorSelect, editorDispatch, clientId, asin, postId ) {
        const headers = {};
        if ( window.wpApiSettings && window.wpApiSettings.nonce ) {
            headers[ 'X-WP-Nonce' ] = window.wpApiSettings.nonce;
        }

        return window.fetch( buildHydrationUrl( asin, postId ), {
            method: 'GET',
            credentials: 'same-origin',
            headers: headers
        } ).then( function ( response ) {
            return response.json().then( function ( payload ) {
                if ( ! response.ok ) {
                    const message = payload && payload.message ? payload.message : 'Hydration failed';
                    throw new Error( message );
                }
                return payload;
            } );
        } ).then( function ( payload ) {
            const images = normalizeHydratedImages( payload );
            const title = payload && payload.title ? payload.title : asin;
            const detailUrl = payload && payload.detailUrl ? payload.detailUrl : '';
            const suggestedBadgeMode = payload && ( payload.suggestedBadgeMode === 'video' || payload.suggestedBadgeMode === 'setup' )
                ? payload.suggestedBadgeMode
                : 'auto';
            const suggestedBenefit = payload && payload.suggestedBenefit ? payload.suggestedBenefit : '';
            const liveBlock = getLiveHydrationBlock( editorSelect, clientId, asin );
            if ( ! liveBlock ) {
                return;
            }
            const attrs = liveBlock.attributes || {};
            if ( attrs.loadState !== 'loading' ) {
                return;
            }

            const currentItems = Array.isArray( attrs.items ) && attrs.items.length
                ? attrs.items
                : [ { asin: asin } ];
            const currentItem = Object.assign( {}, currentItems[ 0 ] || {}, { asin: asin } );
            const nextItem = Object.assign( {}, currentItem );

            if ( ( ! currentItem.title || currentItem.title === asin ) && title ) {
                nextItem.title = title;
            }
            if ( ! currentItem.detail_url && detailUrl ) {
                nextItem.detail_url = detailUrl;
            }
            if ( ! currentItem.image_url && images[ 0 ] ) {
                nextItem.image_url = images[ 0 ];
            }
            if ( ! currentItem.benefit && suggestedBenefit ) {
                nextItem.benefit = suggestedBenefit;
            }

            const nextAttributes = {
                loadState: 'ready',
                loadError: '',
                items: [ nextItem ]
            };

            if ( ! attrs.amazonTitle ) {
                nextAttributes.amazonTitle = title;
            }
            if ( ! attrs.detailUrl && detailUrl ) {
                nextAttributes.detailUrl = detailUrl;
            }
            if ( ( ! Array.isArray( attrs.images ) || ! attrs.images.length ) && images.length ) {
                nextAttributes.images = images;
                nextAttributes.selectedImageIndex = 0;
            }
            if ( ! attrs.badgeMode || attrs.badgeMode === 'auto' ) {
                nextAttributes.badgeMode = suggestedBadgeMode;
            }

            editorDispatch.updateBlockAttributes( clientId, nextAttributes );
        } ).catch( function ( error ) {
            const message = error && error.message ? error.message : 'Hydration failed';
            const liveBlock = getLiveHydrationBlock( editorSelect, clientId, asin );
            if ( ! liveBlock ) {
                return;
            }
            const attrs = liveBlock.attributes || {};
            if ( attrs.loadState !== 'loading' ) {
                return;
            }
            editorDispatch.updateBlockAttributes( clientId, {
                loadState: 'error',
                loadError: message
            } );
        } );
    }

    function installAmazonParagraphTrigger() {
        if (
            ! window.wp ||
            ! window.wp.data ||
            ! window.wp.data.select ||
            ! window.wp.data.dispatch ||
            ! window.wp.domReady ||
            ! window.fetch
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
                const postSelect = window.wp.data.select( 'core/editor' );

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
                        const replacementBlock = createBlock( 'meintechblog/affiliate-cards', {
                            items: [ { asin: asin } ],
                            badgeMode: 'auto',
                            ctaLabel: 'Preis auf Amazon checken',
                            autoShortenTitles: true,
                            loadState: 'loading',
                            loadError: ''
                        } );

                        editorDispatch.replaceBlocks(
                            tokenBlock.clientId,
                            replacementBlock
                        );

                        hydrateAffiliateBlock(
                            editorSelect,
                            editorDispatch,
                            replacementBlock.clientId,
                            asin,
                            postSelect && postSelect.getCurrentPostId ? postSelect.getCurrentPostId() : 0
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
        const item = items[ 0 ] || { asin: '', benefit: '', titleOverride: '' };
        const blockProps = useBlockProps( { className: 'mtb-affiliate-cards-editor' } );

        function updateItem( key, value ) {
            props.setAttributes( {
                items: [ Object.assign( {}, item, { [ key ]: value } ) ]
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
                el( 'h3', {}, 'Affiliate Card' ),
                attributes.loadState === 'loading' && el(
                    'p',
                    { className: 'mtb-affiliate-cards-editor__status' },
                    'Produktdaten werden geladen...'
                ),
                attributes.loadState === 'error' && el(
                    'p',
                    { className: 'mtb-affiliate-cards-editor__warning' },
                    attributes.loadError || 'Produktdaten konnten nicht geladen werden.'
                ),
                items.length > 1 && el(
                    'p',
                    { className: 'mtb-affiliate-cards-editor__warning' },
                    'Dieser Alt-Block enthält mehrere Produkte. Bitte in einzelne Affiliate Cards aufteilen.'
                ),
                el(
                    'div',
                    { className: 'mtb-affiliate-cards-editor__item' },
                    el( TextControl, {
                        label: 'ASIN',
                        value: item.asin || '',
                        onChange: function ( value ) {
                            updateItem( 'asin', value );
                        }
                    } ),
                    el( TextControl, {
                        label: 'Kurztitel überschreiben',
                        value: item.titleOverride || '',
                        onChange: function ( value ) {
                            updateItem( 'titleOverride', value );
                        }
                    } ),
                    el( TextareaControl, {
                        label: 'Nutzenzeile',
                        value: item.benefit || '',
                        onChange: function ( value ) {
                            updateItem( 'benefit', value );
                        }
                    } ),
                    el(
                        'div',
                        { className: 'mtb-affiliate-cards-editor__preview' },
                        el( 'strong', {}, item.titleOverride || item.asin || 'Neue Karte' ),
                        el( 'p', {}, item.benefit || 'Nutzenzeile erscheint hier in der Vorschau.' )
                    )
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
