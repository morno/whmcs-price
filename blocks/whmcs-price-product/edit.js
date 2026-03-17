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

export default function Edit( { attributes, setAttributes } ) {
	const { pid, billingCycle, show, displayStyle, perPeriod } = attributes;
	const blockProps = useBlockProps();

	function handleShowToggle( value, checked ) {
		const updated = checked
			? [ ...show, value ]
			: show.filter( ( v ) => v !== value );
		setAttributes( { show: updated } );
	}

	const hasConfig = pid.trim() !== '';

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
