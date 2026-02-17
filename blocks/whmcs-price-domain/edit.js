/**
 * WHMCS Domain Price Block — Edit Component
 *
 * Renders the block editor UI with InspectorControls for configuration.
 * Supports empty TLD (shows all available TLDs from WHMCS) and display styles.
 *
 * @package    WHMCS_Price
 * @subpackage Blocks
 * @since      2.3.0
 */

import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	SelectControl,
	ToggleControl,
	RadioControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Transaction type options — mirrors the shortcode's `type` attribute.
 * Only shown when showAll is false.
 */
const TRANSACTION_TYPE_OPTIONS = [
	{ label: __( 'Register', 'whmcs-price' ),  value: 'register' },
	{ label: __( 'Renew', 'whmcs-price' ),     value: 'renew' },
	{ label: __( 'Transfer', 'whmcs-price' ),  value: 'transfer' },
];

/**
 * Registration period options — mirrors the shortcode's `reg` attribute (1y–10y).
 */
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

/**
 * Display style options for visual presentation.
 */
const DISPLAY_STYLE_OPTIONS = [
	{ label: __( 'Table (Classic)', 'whmcs-price' ), value: 'table' },
	{ label: __( 'Badge', 'whmcs-price' ),           value: 'badge' },
	{ label: __( 'Inline', 'whmcs-price' ),          value: 'inline' },
];

/**
 * Edit component — renders the block in the editor.
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Current block attributes.
 * @param {Function} props.setAttributes Attribute setter.
 * @return {JSX.Element} The editor UI.
 */
export default function Edit( { attributes, setAttributes } ) {
	const { tld, transactionType, regPeriod, showAll, displayStyle } = attributes;
	const blockProps = useBlockProps();

	return (
		<>
			{ /* Sidebar controls */ }
			<InspectorControls>
				<PanelBody
					title={ __( 'Domain Settings', 'whmcs-price' ) }
					initialOpen={ true }
				>
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
					{ /* Only show type selector when not showing all */ }
					{ ! showAll && (
						<SelectControl
							label={ __( 'Transaction Type', 'whmcs-price' ) }
							value={ transactionType }
							options={ TRANSACTION_TYPE_OPTIONS }
							onChange={ ( val ) => setAttributes( { transactionType: val } ) }
						/>
					) }
				</PanelBody>

				<PanelBody
					title={ __( 'Display Style', 'whmcs-price' ) }
					initialOpen={ false }
				>
					<RadioControl
						label={ __( 'Choose how to display pricing', 'whmcs-price' ) }
						selected={ displayStyle }
						options={ DISPLAY_STYLE_OPTIONS }
						onChange={ ( val ) => setAttributes( { displayStyle: val } ) }
					/>
				</PanelBody>
			</InspectorControls>

			{ /* Canvas preview */ }
			<div { ...blockProps }>
				<div className="whmcs-block-editor-preview">
					<span className="whmcs-block-editor-preview__icon">🌐</span>
					<span className="whmcs-block-editor-preview__label">
						{ __( 'WHMCS Domain Price', 'whmcs-price' ) }
					</span>
					<span className="whmcs-block-editor-preview__meta">
						{ tld.trim() === ''
							? __( 'All TLDs — configure in ⚙ Settings', 'whmcs-price' )
							: showAll
								? sprintf(
									/* translators: 1: TLD, 2: registration period, 3: display style */
									__( '.%1$s — Register/Renew/Transfer — %2$s — Style: %3$s', 'whmcs-price' ),
									tld,
									regPeriod,
									displayStyle
								)
								: sprintf(
									/* translators: 1: TLD, 2: transaction type, 3: registration period, 4: display style */
									__( '.%1$s — %2$s — %3$s — Style: %4$s', 'whmcs-price' ),
									tld,
									transactionType,
									regPeriod,
									displayStyle
								)
						}
					</span>
				</div>
			</div>
		</>
	);
}
