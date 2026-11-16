( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { createElement: el, Fragment } = wp.element;
	const { __ } = wp.i18n;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, RangeControl, SelectControl, TextControl } = wp.components;
	const { useSelect } = wp.data;
	const ServerSideRender = wp.serverSideRender;

	const defaultCount =
		window.stBlockConfig && window.stBlockConfig.defaultCount
			? Number( window.stBlockConfig.defaultCount )
			: 5;

	registerBlockType( 'simple-testimonials/list', {
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps();

			const categories = useSelect( function ( select ) {
				const coreStore = select( 'core' );
				if ( ! coreStore || ! coreStore.getEntityRecords ) {
					return null;
				}
				return coreStore.getEntityRecords( 'taxonomy', 'testimonial_category', {
					per_page: -1,
					hide_empty: false,
				} );
			}, [] );

			const categoryOptions = [
				{ label: __( 'All categories', 'simple-testimonials' ), value: '' },
			];

			if ( Array.isArray( categories ) ) {
				categories.forEach( function ( term ) {
					categoryOptions.push( {
						label: term.name + ' (' + term.slug + ')',
						value: term.slug,
					} );
				} );
			}

			const effectiveCount = attributes.count > 0 ? attributes.count : defaultCount;

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{
							title: __( 'Testimonials Settings', 'simple-testimonials' ),
							initialOpen: true,
						},
						el( RangeControl, {
							label: __( 'Number of testimonials', 'simple-testimonials' ),
							help:
								attributes.count === 0
									? __( 'Using plugin default count from Settings.', 'simple-testimonials' )
									: '',
							value: effectiveCount,
							min: 1,
							max: 50,
							allowReset: true,
							resetFallbackValue: 0,
							onChange: function ( value ) {
								setAttributes( { count: value ? Number( value ) : 0 } );
							},
						} ),
						Array.isArray( categories ) && categories.length > 0
							? el( SelectControl, {
									label: __( 'Category', 'simple-testimonials' ),
									value: attributes.category,
									options: categoryOptions,
									onChange: function ( value ) {
										setAttributes( { category: value } );
									},
							  } )
							: null,
						el( TextControl, {
							label: __( 'Category slug', 'simple-testimonials' ),
							help: __( 'Leave empty to display all categories, or enter comma-separated slugs.', 'simple-testimonials' ),
							value: attributes.category,
							onChange: function ( value ) {
								setAttributes( { category: value } );
							},
						} ),
						el( RangeControl, {
							label: __( 'Minimum rating', 'simple-testimonials' ),
							value: attributes.min_rating,
							min: 1,
							max: 5,
							onChange: function ( value ) {
								setAttributes( { min_rating: value ? Number( value ) : 1 } );
							},
						} ),
						el( SelectControl, {
							label: __( 'Layout', 'simple-testimonials' ),
							value: attributes.layout,
							options: [
								{ label: __( 'Grid', 'simple-testimonials' ), value: 'grid' },
								{ label: __( 'List', 'simple-testimonials' ), value: 'list' },
							],
							onChange: function ( value ) {
								setAttributes( { layout: value } );
							},
						} ),
						el( SelectControl, {
							label: __( 'Order by', 'simple-testimonials' ),
							value: attributes.orderby,
							options: [
								{ label: __( 'Date (newest first)', 'simple-testimonials' ), value: 'date' },
								{ label: __( 'Rating (highest first)', 'simple-testimonials' ), value: 'rating' },
								{ label: __( 'Random', 'simple-testimonials' ), value: 'rand' },
							],
							onChange: function ( value ) {
								setAttributes( { orderby: value } );
							},
						} )
					)
				),
				el(
					'div',
					blockProps,
					el( ServerSideRender, {
						block: 'simple-testimonials/list',
						attributes: attributes,
					} )
				)
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
