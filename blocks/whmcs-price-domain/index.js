/**
 * WHMCS Domain Price Block — Registration
 *
 * Registers the block with WordPress by importing edit and metadata.
 *
 * @package    WHMCS_Price
 * @subpackage Blocks
 * @since      2.3.0
 */

import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit: Edit,
} );
