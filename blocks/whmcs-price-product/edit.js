/**
 * WHMCS Product Price Block — Edit Component
 *
 * Uses ServerSideRender to show a live preview of the actual rendered output
 * directly in the block editor — same data path as the frontend.
 *
 * @package    WHMCS_Price
 * @subpackage Blocks
 * @since      2.7.0
 */

import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	SelectControl,
	CheckboxControl,
	RadioControl,
	ToggleControl,
	Placeholder,
	Spinner,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

const BILLING_CYCLE_OPTIONS = [
	{ label: __( 'Monthly (1m)', 'whmcs-price' ),       value: '1m' },
	{ label: __( 'Quarterly (3m)', 'whmcs-price' ),     value: '3m' },
	{ label: __( 'Semi-Annually (6m)', 'whmcs-price' ), value: '6m' },
	{ label: __( 'Annually (1y)', 'whmcs-price' ),      value: '1y' },
	{ label: __( 'Biennially (2y)', 'whmcs-price' ),    value: '2y' },
	{ label: __( 'Triennially (3y)', 'whmcs-price' ),   value: '3y' },
];

const SHOW_OPTIONS = [
	{ label: __( 'Name', 'whmcs-price' ),        value: 'name' },
	{ label: __( 'Description', 'whmcs-price' ), value: 'description' },
	{ label: __( 'Price', 'whmcs-price' ),       value: 'price' },
	{ label: __( 'Setup Fee', 'whmcs-price' ),   value: 'setupfee' },
];

const DISPLAY_STYLE_OPTIONS = [
	{ label: __( 'Table (Classic)', 'whmcs-price' ), value: 'table' },
	{ label: __( 'Cards', 'whmcs-price' ),           value: 'cards' },
	{ label: __( 'Pricing Grid', 'whmcs-price' ),    value: 'grid' },
];

const PER_PERIOD_OPTIONS = [
	{ label: __( 'Disabled', 'whmcs-price' ),                            value: '' },
	{ label: __( 'Per month — e.g. $99/yr ($8.25/mo)', 'whmcs-price' ), value: 'month' },
	{ label: __( 'Per week — e.g. $99/yr ($1.90/wk)', 'whmcs-price' ),  value: 'week' },
	{ label: __( 'Per day — e.g. $99/yr ($0.27/day)', 'whmcs-price' ),  value: 'day' },
];

/**
 * Static preview shown in the block inserter (block.json "example").
 *
 * Uses the same markup and classes as render.php's "cards" style so the
 * frontend stylesheet applies, but with placeholder data — hovering the
 * block in the inserter must never trigger a request to WHMCS.
 *
 * @since 2.10.0
 */
function InserterPreview() {
	const plans = [
		{ name: __( 'Starter', 'whmcs-price' ), price: '4.99', description: __( 'For personal sites and blogs.', 'whmcs-price' ) },
		{ name: __( 'Business', 'whmcs-price' ), price: '9.99', description: __( 'For growing businesses.', 'whmcs-price' ) },
		{ name: __( 'Pro', 'whmcs-price' ), price: '19.99', description: __( 'For high-traffic stores.', 'whmcs-price' ) },
	];

	return (
		<div className="whmcs-product-display whmcs-product-display--cards">
			<div className="whmcs-product-cards">
				{ plans.map( ( plan ) => (
					<article className="whmcs-product-card" key={ plan.name }>
						<div className="whmcs-product-card__name">
							<h3 className="whmcs-product-card__title">{ plan.name }</h3>
						</div>
						<div className="whmcs-product-card__price">
							<span className="whmcs-product-card__price-value">{ plan.price }</span>
						</div>
						<div className="whmcs-product-card__description">
							<p className="whmcs-product-card__description">{ plan.description }</p>
						</div>
					</article>
				) ) }
			</div>
		</div>
	);
}

export default function Edit( { attributes, setAttributes } ) {
	const { pid, billingCycle, show, displayStyle, perPeriod, preview, currency, cycleToggle, toggleCycles } = attributes;
	const blockProps = useBlockProps();

	if ( preview ) {
		return (
			<div { ...blockProps }>
				<InserterPreview />
			</div>
		);
	}

	function handleShowToggle( value, checked ) {
		const updated = checked
			? [ ...show, value ]
			: show.filter( ( v ) => v !== value );
		setAttributes( { show: updated } );
	}

	function handleToggleCycle( value, checked ) {
		const current = Array.isArray( toggleCycles ) ? toggleCycles : [];
		const updated = checked
			? [ ...current, value ]
			: current.filter( ( v ) => v !== value );
		// Keep the canonical order (shortest cycle first), like render.php does.
		setAttributes( {
			toggleCycles: BILLING_CYCLE_OPTIONS.map( ( o ) => o.value ).filter( ( v ) => updated.includes( v ) ),
		} );
	}

	const hasConfig = pid.trim() !== '';
	const selectedCycles = Array.isArray( toggleCycles ) ? toggleCycles : [];

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Product Settings', 'whmcs-price' ) } initialOpen={ true }>
					<TextControl
						label={ __( 'Product ID(s)', 'whmcs-price' ) }
						help={ __( 'Comma-separated WHMCS Product IDs, e.g. 1,2,3', 'whmcs-price' ) }
						value={ pid }
						onChange={ ( val ) => setAttributes( { pid: val } ) }
					/>
					<SelectControl
						label={ __( 'Billing Cycle', 'whmcs-price' ) }
						value={ billingCycle }
						options={ BILLING_CYCLE_OPTIONS }
						onChange={ ( val ) => setAttributes( { billingCycle: val } ) }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Display Columns', 'whmcs-price' ) } initialOpen={ true }>
					{ SHOW_OPTIONS.map( ( option ) => (
						<CheckboxControl
							key={ option.value }
							label={ option.label }
							checked={ show.includes( option.value ) }
							onChange={ ( checked ) => handleShowToggle( option.value, checked ) }
						/>
					) ) }
				</PanelBody>

				<PanelBody title={ __( 'Display Style', 'whmcs-price' ) } initialOpen={ false }>
					<RadioControl
						label={ __( 'Choose how to display products', 'whmcs-price' ) }
						selected={ displayStyle }
						options={ DISPLAY_STYLE_OPTIONS }
						onChange={ ( val ) => setAttributes( { displayStyle: val } ) }
					/>
					<SelectControl
						label={ __( 'Per-Period Breakdown', 'whmcs-price' ) }
						help={ __( 'Show the price divided by period alongside the full price.', 'whmcs-price' ) }
						value={ perPeriod }
						options={ PER_PERIOD_OPTIONS }
						onChange={ ( val ) => setAttributes( { perPeriod: val } ) }
					/>
					{ displayStyle !== 'table' && (
						<>
							<ToggleControl
								__nextHasNoMarginBottom
								label={ __( 'Billing period switcher', 'whmcs-price' ) }
								help={ __( 'Let visitors switch billing cycle without reloading the page. The billing cycle in Product Settings is shown first.', 'whmcs-price' ) }
								checked={ !! cycleToggle }
								onChange={ ( val ) => setAttributes( { cycleToggle: val } ) }
							/>
							{ cycleToggle && (
								<fieldset>
									<legend>{ __( 'Cycles to offer (choose at least two)', 'whmcs-price' ) }</legend>
									{ BILLING_CYCLE_OPTIONS.map( ( option ) => (
										<CheckboxControl
											key={ option.value }
											label={ option.label }
											checked={ selectedCycles.includes( option.value ) }
											onChange={ ( checked ) => handleToggleCycle( option.value, checked ) }
										/>
									) ) }
								</fieldset>
							) }
						</>
					) }
				</PanelBody>
				<PanelBody title={ __( 'Currency', 'whmcs-price' ) } initialOpen={ false }>
					<TextControl
						label={ __( 'WHMCS currency ID', 'whmcs-price' ) }
						help={ __( 'Optional. Leave empty to use the currency set for the page language, or the WHMCS default currency.', 'whmcs-price' ) }
						type="number"
						min={ 0 }
						value={ currency || '' }
						onChange={ ( val ) => setAttributes( { currency: val.replace( /[^0-9]/g, '' ) } ) }
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				{ ! hasConfig ? (
					<Placeholder
						icon="tag"
						label={ __( 'WHMCS Product Price', 'whmcs-price' ) }
						instructions={ __( 'Enter a Product ID in the settings panel to show a live price preview.', 'whmcs-price' ) }
					/>
				) : (
					<ServerSideRender
						block="whmcs-price/product"
						attributes={ attributes }
						LoadingResponsePlaceholder={ () => (
							<div style={ { padding: '16px', display: 'flex', alignItems: 'center', gap: '8px' } }>
								<Spinner />
								{ __( 'Loading WHMCS prices…', 'whmcs-price' ) }
							</div>
						) }
					/>
				) }
			</div>
		</>
	);
}
