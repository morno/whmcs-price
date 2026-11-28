/**
 * WHMCS Domain Price Block — Edit Component
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
	ToggleControl,
	RadioControl,
	Spinner,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

const PER_PERIOD_OPTIONS = [
	{ label: __( 'Disabled', 'whmcs-price' ),                            value: '' },
	{ label: __( 'Per month — e.g. $99/yr ($8.25/mo)', 'whmcs-price' ), value: 'month' },
	{ label: __( 'Per week — e.g. $99/yr ($1.90/wk)', 'whmcs-price' ),  value: 'week' },
	{ label: __( 'Per day — e.g. $99/yr ($0.27/day)', 'whmcs-price' ),  value: 'day' },
];

const TRANSACTION_TYPE_OPTIONS = [
	{ label: __( 'Register', 'whmcs-price' ),  value: 'register' },
	{ label: __( 'Renew', 'whmcs-price' ),     value: 'renew' },
	{ label: __( 'Transfer', 'whmcs-price' ),  value: 'transfer' },
];

const REG_PERIOD_OPTIONS = [
	{ label: __( '1 Year', 'whmcs-price' ),   value: '1y' },
	{ label: __( '2 Years', 'whmcs-price' ),  value: '2y' },
	{ label: __( '3 Years', 'whmcs-price' ),  value: '3y' },
	{ label: __( '4 Years', 'whmcs-price' ),  value: '4y' },
	{ label: __( '5 Years', 'whmcs-price' ),  value: '5y' },
	{ label: __( '6 Years', 'whmcs-price' ),  value: '6y' },
	{ label: __( '7 Years', 'whmcs-price' ),  value: '7y' },
	{ label: __( '8 Years', 'whmcs-price' ),  value: '8y' },
	{ label: __( '9 Years', 'whmcs-price' ),  value: '9y' },
	{ label: __( '10 Years', 'whmcs-price' ), value: '10y' },
];

const DISPLAY_STYLE_OPTIONS = [
	{ label: __( 'Table (Classic)', 'whmcs-price' ), value: 'table' },
	{ label: __( 'Badge', 'whmcs-price' ),           value: 'badge' },
	{ label: __( 'Inline', 'whmcs-price' ),          value: 'inline' },
];

/**
 * Static preview shown in the block inserter (block.json "example").
 *
 * Mirrors render.php's "badge" style with placeholder prices so the
 * inserter never triggers a request to WHMCS.
 *
 * @since 2.10.0
 */
function InserterPreview() {
	const rows = [
		{ label: __( 'Register', 'whmcs-price' ), price: '12.99' },
		{ label: __( 'Renew', 'whmcs-price' ), price: '14.99' },
		{ label: __( 'Transfer', 'whmcs-price' ), price: '12.99' },
	];

	return (
		<div className="whmcs-domain-display whmcs-domain-display--badge">
			<div className="whmcs-domain-badges">
				<div className="whmcs-domain-badges__tld">.com</div>
				{ rows.map( ( row ) => (
					<div className="whmcs-domain-badge" key={ row.label }>
						<span className="whmcs-domain-badge__label">{ row.label }</span>
						<span className="whmcs-domain-badge__price">{ row.price }</span>
					</div>
				) ) }
			</div>
		</div>
	);
}

export default function Edit( { attributes, setAttributes } ) {
	const { tld, transactionType, regPeriod, showAll, displayStyle, perPeriod, preview, currency } = attributes;
	const blockProps = useBlockProps();

	if ( preview ) {
		return (
			<div { ...blockProps }>
				<InserterPreview />
			</div>
		);
	}

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Domain Settings', 'whmcs-price' ) } initialOpen={ true }>
					<TextControl
						label={ __( 'TLD', 'whmcs-price' ) }
						help={ __( 'Domain extension without dot, e.g. com, net, se. Leave empty to show all available TLDs.', 'whmcs-price' ) }
						value={ tld }
						onChange={ ( val ) => setAttributes( { tld: val.replace( /^\./, '' ) } ) }
					/>
					<SelectControl
						label={ __( 'Registration Period', 'whmcs-price' ) }
						value={ regPeriod }
						options={ REG_PERIOD_OPTIONS }
						onChange={ ( val ) => setAttributes( { regPeriod: val } ) }
					/>
					<ToggleControl
						label={ __( 'Show all transaction types', 'whmcs-price' ) }
						help={ showAll
							? __( 'Showing Register, Renew and Transfer in a table.', 'whmcs-price' )
							: __( 'Showing a single transaction type.', 'whmcs-price' )
						}
						checked={ showAll }
						onChange={ ( val ) => setAttributes( { showAll: val } ) }
					/>
					{ ! showAll && (
						<SelectControl
							label={ __( 'Transaction Type', 'whmcs-price' ) }
							value={ transactionType }
							options={ TRANSACTION_TYPE_OPTIONS }
							onChange={ ( val ) => setAttributes( { transactionType: val } ) }
						/>
					) }
				</PanelBody>

				<PanelBody title={ __( 'Display Style', 'whmcs-price' ) } initialOpen={ false }>
					<RadioControl
						label={ __( 'Choose how to display pricing', 'whmcs-price' ) }
						selected={ displayStyle }
						options={ DISPLAY_STYLE_OPTIONS }
						onChange={ ( val ) => setAttributes( { displayStyle: val } ) }
					/>
					<SelectControl
						label={ __( 'Per-Period Breakdown', 'whmcs-price' ) }
						help={ __( 'Show the price divided by period alongside the full price.', 'whmcs-price' ) }
						value={ perPeriod || '' }
						options={ PER_PERIOD_OPTIONS }
						onChange={ ( val ) => setAttributes( { perPeriod: val } ) }
					/>
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
				<ServerSideRender
					block="whmcs-price/domain"
					attributes={ attributes }
					LoadingResponsePlaceholder={ () => (
						<div style={ { padding: '16px', display: 'flex', alignItems: 'center', gap: '8px' } }>
							<Spinner />
							{ __( 'Loading WHMCS prices…', 'whmcs-price' ) }
						</div>
					) }
				/>
			</div>
		</>
	);
}
