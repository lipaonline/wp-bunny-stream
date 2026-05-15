( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { useState, useEffect, createElement: el, Fragment } = wp.element;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, ToggleControl, TextControl, ComboboxControl, SelectControl, Placeholder, Spinner } = wp.components;
	const { __ } = wp.i18n;
	const apiFetch = wp.apiFetch;

	const useVideoSearch = ( query ) => {
		const [ options, setOptions ] = useState( [] );
		const [ loading, setLoading ] = useState( false );

		useEffect( () => {
			let cancelled = false;
			setLoading( true );

			const params = new URLSearchParams( {
				per_page: 20,
				_fields: 'id,title',
				orderby: query ? 'relevance' : 'date',
				order: 'desc'
			} );
			if ( query ) params.append( 'search', query );

			apiFetch( { path: `/wp/v2/bunny_video?${ params.toString() }` } )
				.then( ( posts ) => {
					if ( cancelled ) return;
					setOptions( posts.map( ( p ) => ( {
						value: p.id,
						label: ( p.title && p.title.rendered ) || `(no title #${ p.id })`
					} ) ) );
				} )
				.catch( () => { if ( ! cancelled ) setOptions( [] ); } )
				.finally( () => { if ( ! cancelled ) setLoading( false ); } );

			return () => { cancelled = true; };
		}, [ query ] );

		return { options, loading };
	};

	const VideoPicker = ( { value, onChange } ) => {
		const [ filter, setFilter ] = useState( '' );
		const { options, loading } = useVideoSearch( filter );

		const augmented = [ ...options ];
		if ( value && ! augmented.some( ( o ) => o.value === value ) ) {
			augmented.unshift( { value, label: `#${ value } …` } );
		}

		return el( Fragment, {},
			el( ComboboxControl, {
				label: __( 'Bunny video', 'wp-bunny-stream' ),
				value: value || null,
				options: augmented,
				onFilterValueChange: ( v ) => setFilter( v || '' ),
				onChange: ( v ) => onChange( parseInt( v, 10 ) || 0 ),
				__experimentalRenderItem: undefined
			} ),
			loading && el( 'p', { style: { margin: '4px 0', color: '#646970' } }, el( Spinner ), ' ', __( 'Searching…', 'wp-bunny-stream' ) )
		);
	};

	registerBlockType( 'wpbs/bunny-video', {
		edit: ( props ) => {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps();

			return el( Fragment, {},
				el( InspectorControls, {},
					el( PanelBody, { title: __( 'Player options', 'wp-bunny-stream' ), initialOpen: true },
						el( ToggleControl, { label: __( 'Autoplay', 'wp-bunny-stream' ), checked: !! attributes.autoplay, onChange: ( v ) => setAttributes( { autoplay: v } ) } ),
						el( ToggleControl, { label: __( 'Loop', 'wp-bunny-stream' ), checked: !! attributes.loop, onChange: ( v ) => setAttributes( { loop: v } ) } ),
						el( ToggleControl, { label: __( 'Muted', 'wp-bunny-stream' ), checked: !! attributes.muted, onChange: ( v ) => setAttributes( { muted: v } ) } ),
						el( ToggleControl, { label: __( 'Preload', 'wp-bunny-stream' ), checked: !! attributes.preload, onChange: ( v ) => setAttributes( { preload: v } ) } ),
						el( TextControl, { label: __( 'Start at (s)', 'wp-bunny-stream' ), type: 'number', value: attributes.startAt || '', onChange: ( v ) => setAttributes( { startAt: parseInt( v, 10 ) || 0 } ) } ),
						el( TextControl, { label: __( 'Accent color (#hex)', 'wp-bunny-stream' ), value: attributes.color || '', onChange: ( v ) => setAttributes( { color: v } ) } ),
						el( SelectControl, {
							label: __( 'Aspect ratio', 'wp-bunny-stream' ),
							help: __( 'Auto = use the video\'s real dimensions.', 'wp-bunny-stream' ),
							value: attributes.ratio || '',
							options: [
								{ value: '',      label: __( 'Auto (from video)', 'wp-bunny-stream' ) },
								{ value: '16:9',  label: '16:9 (landscape)' },
								{ value: '9:16',  label: '9:16 (portrait / reels)' },
								{ value: '1:1',   label: '1:1 (square)' },
								{ value: '4:3',   label: '4:3' },
								{ value: '21:9',  label: '21:9 (cinematic)' },
								{ value: '4:5',   label: '4:5 (Instagram portrait)' }
							],
							onChange: ( v ) => setAttributes( { ratio: v } )
						} )
					)
				),
				el( 'div', blockProps,
					el( Placeholder, {
						icon: 'video-alt3',
						label: __( 'Bunny Video', 'wp-bunny-stream' ),
						instructions: __( 'Start typing to find a video from your Bunny Video library.', 'wp-bunny-stream' )
					},
						el( VideoPicker, {
							value: attributes.videoId || null,
							onChange: ( v ) => setAttributes( { videoId: v } )
						} )
					)
				)
			);
		},
		save: () => null
	} );
} )( window.wp );
