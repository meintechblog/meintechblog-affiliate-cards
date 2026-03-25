( function ( blocks, blockEditor, components, element, i18n ) {
    const el = element.createElement;
    const registerBlockType = blocks.registerBlockType;
    const createBlock = blocks.createBlock;
    const InspectorControls = blockEditor.InspectorControls;
    const useBlockProps = blockEditor.useBlockProps;
    const useEffect = element.useEffect;
    const useRef = element.useRef;
    const PanelBody = components.PanelBody;
    const TextControl = components.TextControl;
    const TextareaControl = components.TextareaControl;
    const SelectControl = components.SelectControl;
    const Button = components.Button;
    const TOKEN_PATTERN = /^amazon:([A-Z0-9]{10})$/;
    const HYDRATION_ENDPOINT = 'mtb-affiliate-cards/v1/item';
    const PRODUCTS_ENDPOINT = 'mtb-affiliate-cards/v1/products';
    const BADGE_OPTIONS = [
        { label: 'Automatisch', value: 'auto' },
        { label: 'Im Video verwendet', value: 'video' },
        { label: 'Passend zu diesem Setup', value: 'setup' }
    ];
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

    function isValidAsin( value ) {
        return /^[A-Z0-9]{10}$/.test( String( value || '' ).trim().toUpperCase() );
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
                hydratedAsin: asin,
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
                loadError: message,
                hydratedAsin: ''
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
        const images = Array.isArray( attributes.images ) && attributes.images.length
            ? attributes.images
            : ( item.image_url ? [ item.image_url ] : [] );
        const maxImageIndex = images.length > 0 ? images.length - 1 : 0;
        const selectedImageIndex = Math.max(
            0,
            Math.min(
                maxImageIndex,
                Number.isFinite( attributes.selectedImageIndex ) ? attributes.selectedImageIndex : 0
            )
        );
        const activeImageUrl = images[ selectedImageIndex ] || item.image_url || '';
        const baseTitle = attributes.amazonTitle || item.title || item.asin || 'Neue Karte';
        const previewTitle = item.titleOverride || baseTitle;
        const editableTitle = item.titleOverride || previewTitle;
        const ctaLabel = attributes.ctaLabel || 'Preis auf Amazon checken';
        const detailUrl = item.detail_url || attributes.detailUrl || '';
        const isLoading = attributes.loadState === 'loading';
        const hasError = attributes.loadState === 'error';
        const currentAsin = item.asin ? String( item.asin ).trim().toUpperCase() : '';
        const hydrationAsinRef = useRef( currentAsin );
        const [ products, setProducts ] = element.useState( [] );
        const [ productsLoaded, setProductsLoaded ] = element.useState( false );

        useEffect( function () {
            if ( productsLoaded ) { return; }
            var root = ( window.wpApiSettings && window.wpApiSettings.root )
                ? window.wpApiSettings.root.replace( /\/+$/, '' )
                : '/wp-json';
            var headers = {};
            if ( window.wpApiSettings && window.wpApiSettings.nonce ) {
                headers[ 'X-WP-Nonce' ] = window.wpApiSettings.nonce;
            }
            window.fetch( root + '/' + PRODUCTS_ENDPOINT + '?limit=50', {
                credentials: 'same-origin',
                headers: headers
            } )
                .then( function ( r ) { return r.ok ? r.json() : []; } )
                .then( function ( data ) {
                    setProducts( Array.isArray( data ) ? data : [] );
                    setProductsLoaded( true );
                } )
                .catch( function () { setProductsLoaded( true ); } );
        }, [] );

        function updateItem( key, value ) {
            props.setAttributes( {
                items: [ Object.assign( {}, item, { [ key ]: value } ) ]
            } );
        }

        useEffect( function () {
            if (
                ! window.wp ||
                ! window.wp.data ||
                ! window.wp.data.select ||
                ! window.wp.data.dispatch
            ) {
                return;
            }

            const asin = currentAsin;
            const previousAsin = hydrationAsinRef.current;
            hydrationAsinRef.current = asin;

            if ( ! isValidAsin( asin ) ) {
                return;
            }

            const hasHydratedData = attributes.hydratedAsin === asin && Boolean(
                attributes.amazonTitle ||
                ( Array.isArray( attributes.images ) && attributes.images.length ) ||
                attributes.detailUrl ||
                item.image_url ||
                item.detail_url
            );

            const asinChanged = previousAsin !== '' && previousAsin !== asin;
            if ( ! asinChanged && ( hasHydratedData || attributes.loadState === 'loading' ) ) {
                return;
            }

            const editorSelect = window.wp.data.select( 'core/block-editor' );
            const editorDispatch = window.wp.data.dispatch( 'core/block-editor' );
            const postSelect = window.wp.data.select( 'core/editor' );

            const nextItem = Object.assign( {}, item, {
                asin: asin,
                title: asin,
                image_url: '',
                detail_url: ''
            } );

            props.setAttributes( {
                amazonTitle: '',
                detailUrl: '',
                images: [],
                selectedImageIndex: 0,
                loadState: 'loading',
                loadError: '',
                hydratedAsin: '',
                items: [ nextItem ]
            } );

            hydrateAffiliateBlock(
                editorSelect,
                editorDispatch,
                props.clientId,
                asin,
                postSelect && postSelect.getCurrentPostId ? postSelect.getCurrentPostId() : 0
            );
        }, [ currentAsin ] );

        function updateBadgeMode( value ) {
            props.setAttributes( { badgeMode: value } );
        }

        function selectImage( nextIndex ) {
            if ( ! images.length ) {
                return;
            }

            const normalizedIndex = ( nextIndex + images.length ) % images.length;
            props.setAttributes( {
                selectedImageIndex: normalizedIndex,
                images: images,
                items: [ Object.assign( {}, item, { image_url: images[ normalizedIndex ] || item.image_url || '' } ) ]
            } );
        }

        function retryHydration() {
            if (
                ! window.wp ||
                ! window.wp.data ||
                ! window.wp.data.select ||
                ! window.wp.data.dispatch
            ) {
                return;
            }

            const asin = item.asin ? String( item.asin ).trim().toUpperCase() : '';
            if ( ! asin ) {
                return;
            }

            const editorSelect = window.wp.data.select( 'core/block-editor' );
            const editorDispatch = window.wp.data.dispatch( 'core/block-editor' );
            const postSelect = window.wp.data.select( 'core/editor' );

            props.setAttributes( { loadState: 'loading', loadError: '' } );
            hydrateAffiliateBlock(
                editorSelect,
                editorDispatch,
                props.clientId,
                asin,
                postSelect && postSelect.getCurrentPostId ? postSelect.getCurrentPostId() : 0
            );
        }

        function renderChevron( direction ) {
            return el(
                'span',
                { className: 'mtb-affiliate-cards-editor__image-arrow-icon', 'aria-hidden': true },
                el(
                    'svg',
                    { viewBox: '0 0 20 20', width: 16, height: 16, focusable: false },
                    el( 'path', {
                        d: direction === 'left' ? 'M12.5 4.5L7 10l5.5 5.5' : 'M7.5 4.5L13 10l-5.5 5.5',
                        fill: 'none',
                        stroke: 'currentColor',
                        'stroke-width': 2.2,
                        'stroke-linecap': 'round',
                        'stroke-linejoin': 'round'
                    } )
                )
            );
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
                    el( TextControl, {
                        label: i18n.__( 'Produkt-ASIN', 'meintechblog-affiliate-cards' ),
                        value: item.asin || '',
                        onChange: function ( value ) {
                            updateItem( 'asin', value.trim().toUpperCase() );
                        }
                    } ),
                    el( SelectControl, {
                        label: i18n.__( 'Badge-Modus', 'meintechblog-affiliate-cards' ),
                        value: attributes.badgeMode || 'auto',
                        options: BADGE_OPTIONS,
                        onChange: updateBadgeMode
                    } ),
                    el( TextControl, {
                        label: i18n.__( 'CTA-Text', 'meintechblog-affiliate-cards' ),
                        value: attributes.ctaLabel || 'Preis auf Amazon checken',
                        onChange: function ( value ) {
                            props.setAttributes( { ctaLabel: value } );
                        }
                    } ),
                    el(
                        Button,
                        { isSecondary: true, onClick: retryHydration },
                        i18n.__( 'Produktdaten neu laden', 'meintechblog-affiliate-cards' )
                    )
                )
            ),
                el(
                    'div',
                    blockProps,
                    el( 'h3', {}, 'Affiliate Card' ),
                items.length > 1 && el(
                    'p',
                    { className: 'mtb-affiliate-cards-editor__warning' },
                    'Dieser Alt-Block enthält mehrere Produkte. Bitte in einzelne Affiliate Cards aufteilen.'
                ),
                    el(
                        'div',
                        { className: 'mtb-affiliate-cards-editor__item' },
                        el(
                            'div',
                            { className: 'mtb-affiliate-cards-editor__card' },
                        el(
                            'div',
                            { className: 'mtb-affiliate-cards-editor__head' },
                            el(
                                'div',
                                { className: 'mtb-affiliate-cards-editor__head-meta' },
                                el( TextControl, {
                                    label: 'ASIN',
                                    className: 'mtb-affiliate-cards-editor__asin-input',
                                    value: item.asin || '',
                                    onChange: function ( value ) {
                                        updateItem( 'asin', value.trim().toUpperCase() );
                                    }
                                } ),
                                el(
                                    'div',
                                    { className: 'mtb-affiliate-cards-editor__badge-area' },
                                    el( SelectControl, {
                                        label: 'Badge über dem Bild',
                                        value: attributes.badgeMode || 'auto',
                                        options: BADGE_OPTIONS,
                                        onChange: updateBadgeMode
                                    } )
                                )
                            )
                        ),
                        el(
                            'div',
                            { className: 'mtb-affiliate-cards-editor__media-area' },
                            isLoading && el(
                                'div',
                                { className: 'mtb-affiliate-cards-editor__state mtb-affiliate-cards-editor__state--loading' },
                                el( 'span', { className: 'mtb-affiliate-cards-editor__skeleton mtb-affiliate-cards-editor__skeleton--image' } )
                            ),
                            ! isLoading && images.length > 0 && el(
                                'div',
                                { className: 'mtb-affiliate-cards-editor__image' },
                                el(
                                    'div',
                                    { className: 'mtb-affiliate-cards-editor__image-controls' },
                                    el(
                                        Button,
                                        { isSecondary: true, className: 'mtb-affiliate-cards-editor__image-arrow', 'aria-label': 'Vorheriges Bild', onClick: function () { selectImage( selectedImageIndex - 1 ); }, disabled: images.length < 2 },
                                        renderChevron( 'left' )
                                    ),
                                    el( 'span', { className: 'mtb-affiliate-cards-editor__image-index' }, 'Bild ' + ( selectedImageIndex + 1 ) + ' / ' + images.length ),
                                    el(
                                        Button,
                                        { isSecondary: true, className: 'mtb-affiliate-cards-editor__image-arrow', 'aria-label': 'Nächstes Bild', onClick: function () { selectImage( selectedImageIndex + 1 ); }, disabled: images.length < 2 },
                                        renderChevron( 'right' )
                                    )
                                ),
                                el( 'img', { src: activeImageUrl, alt: previewTitle } )
                            ),
                            ! isLoading && ! images.length && el(
                                'div',
                                { className: 'mtb-affiliate-cards-editor__state mtb-affiliate-cards-editor__state--empty' },
                                'Noch kein Produktbild geladen'
                            )
                        ),
                        el(
                            'div',
                            { className: 'mtb-affiliate-cards-editor__body-area' },
                            el( TextControl, {
                                label: 'Titel',
                                className: 'mtb-affiliate-cards-editor__title-input',
                                value: editableTitle,
                                onChange: function ( value ) {
                                    updateItem( 'titleOverride', value === baseTitle ? '' : value );
                                }
                            } ),
                            el( TextareaControl, {
                                label: 'Beschreibung',
                                className: 'mtb-affiliate-cards-editor__benefit-input',
                                value: item.benefit || '',
                                onChange: function ( value ) {
                                    updateItem( 'benefit', value );
                                }
                            } ),
                            isLoading && el(
                                'div',
                                { className: 'mtb-affiliate-cards-editor__state mtb-affiliate-cards-editor__state--loading' },
                                el( 'span', { className: 'mtb-affiliate-cards-editor__skeleton mtb-affiliate-cards-editor__skeleton--line' } ),
                                el( 'span', { className: 'mtb-affiliate-cards-editor__skeleton mtb-affiliate-cards-editor__skeleton--line mtb-affiliate-cards-editor__skeleton--line-short' } ),
                                el( 'span', { className: 'mtb-affiliate-cards-editor__state-copy' }, 'Produktdaten werden geladen...' )
                            ),
                            hasError && el(
                                'div',
                                { className: 'mtb-affiliate-cards-editor__state mtb-affiliate-cards-editor__state--error' },
                                el( 'span', { className: 'mtb-affiliate-cards-editor__state-copy' }, attributes.loadError || 'Produktdaten konnten nicht geladen werden.' ),
                                el(
                                    Button,
                                    { isSecondary: true, onClick: retryHydration },
                                    'Produktdaten neu laden'
                                )
                            ),
                            el(
                                'a',
                                {
                                    className: 'mtb-affiliate-cards-editor__cta-preview mtb-affiliate-cards-editor__cta-link',
                                    href: detailUrl || '#',
                                    target: '_blank',
                                    rel: 'nofollow noopener sponsored'
                                },
                                el( 'span', { className: 'mtb-affiliate-cards-editor__cta-label' }, ctaLabel ),
                                el( 'span', { className: 'mtb-affiliate-cards-editor__cta-meta' }, 'Affiliate-Link' )
                            )
                        )
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
