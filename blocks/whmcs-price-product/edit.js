/**
 * WHMCS Product Price Block — Edit Component
 *
 * Renders the block editor UI with InspectorControls for configuration.
 * Includes display style selector for different visual presentations.
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
	CheckboxControl,
	Placeholder,
	RadioControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Billing cycle options — mirrors the map in both shortcode.php and render.php.
 */
const BILLING_CYCLE_OPTIONS = [
	{ label: __( 'Monthly (1m)', 'whmcs-price' ),      value: '1m' },
	{ label: __( 'Quarterly (3m)', 'whmcs-price' ),    value: '3m' },
	{ label: __( 'Semi-Annually (6m)', 'whmcs-price' ), value: '6m' },
	{ label: __( 'Annually (1y)', 'whmcs-price' ),     value: '1y' },
	{ label: __( 'Biennially (2y)', 'whmcs-price' ),   value: '2y' },
	{ label: __( 'Triennially (3y)', 'whmcs-price' ),  value: '3y' },
];

/**
 * Available columns that can be toggled on/off.
 */
const SHOW_OPTIONS = [
	{ label: __( 'Name', 'whmcs-price' ),        value: 'name' },
	{ label: __( 'Description', 'whmcs-price' ), value: 'description' },
	{ label: __( 'Price', 'whmcs-price' ),       value: 'price' },
];

/**
 * Display style options for visual presentation.
 */
const DISPLAY_STYLE_OPTIONS = [
	{ label: __( 'Table (Classic)', 'whmcs-price' ), value: 'table' },
	{ label: __( 'Cards', 'whmcs-price' ),           value: 'cards' },
	{ label: __( 'Pricing Grid', 'whmcs-price' ),    value: 'grid' },
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
	const { pid, billingCycle, show, displayStyle } = attributes;
	const blockProps = useBlockProps();

	/**
	 * Toggle a column value in the `show` array attribute.
	 *
	 * @param {string}  value   Column key (name, description, price).
	 * @param {boolean} checked Whether the checkbox is checked.
	 */
	function handleShowToggle( value, checked ) {
		const updated = checked
			? [ ...show, value ]
			: show.filter( ( v ) => v !== value );
		setAttributes( { show: updated } );
	}

	const hasConfig = pid.trim() !== '';

	return (
		<>
			{ /* Sidebar controls */ }
			<InspectorControls>
				<PanelBody
					title={ __( 'Product Settings', 'whmcs-price' ) }
					initialOpen={ true }
				>
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

				<PanelBody
					title={ __( 'Display Columns', 'whmcs-price' ) }
					initialOpen={ true }
				>
					{ SHOW_OPTIONS.map( ( option ) => (
						<CheckboxControl
							key={ option.value }
							label={ option.label }
							checked={ show.includes( option.value ) }
							onChange={ ( checked ) =>
								handleShowToggle( option.value, checked )
							}
						/>
					) ) }
				</PanelBody>

				<PanelBody
					title={ __( 'Display Style', 'whmcs-price' ) }
					initialOpen={ false }
				>
					<RadioControl
						label={ __( 'Choose how to display products', 'whmcs-price' ) }
						selected={ displayStyle }
						options={ DISPLAY_STYLE_OPTIONS }
						onChange={ ( val ) => setAttributes( { displayStyle: val } ) }
					/>
				</PanelBody>
			</InspectorControls>

			{ /* Canvas preview */ }
			<div { ...blockProps }>
				{ ! hasConfig ? (
					<Placeholder
						icon="tag"
						label={ __( 'WHMCS Product Price', 'whmcs-price' ) }
						instructions={ __(
							'Configure this block using the settings panel. Click the block, then open the Settings panel (⚙) in the top-right corner of the editor.',
							'whmcs-price'
						) }
					>
						<div className="whmcs-block-setup-hint">
							{ __( '👆 Click this block → then click ⚙ Settings in the top-right corner', 'whmcs-price' ) }
						</div>
					</Placeholder>
				) : (
					<div className="whmcs-block-editor-preview">
						<span className="whmcs-block-editor-preview__icon">🏷️</span>
						<span className="whmcs-block-editor-preview__label">
							{ __( 'WHMCS Product Price', 'whmcs-price' ) }
						</span>
						<span className="whmcs-block-editor-preview__meta">
							{ sprintf(
								/* translators: 1: product ID(s), 2: billing cycle, 3: display style */
								__( 'PID: %1$s — Cycle: %2$s — Style: %3$s', 'whmcs-price' ),
								pid,
								billingCycle,
								displayStyle
							) }
						</span>
					</div>
				) }
			</div>
		</>
	);
}
