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

export default function Edit( { attributes, setAttributes } ) {
	const { tld, transactionType, regPeriod, showAll, displayStyle } = attributes;
	const blockProps = useBlockProps();

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
