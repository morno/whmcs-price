/**
 * Mornolink for WHMCS: billing cycle toggle (Interactivity API).
 *
 * The server already rendered the prices for every cycle; this only changes
 * which one is shown. No requests are made when a visitor switches cycle.
 *
 * @package WHMCS_Price
 * @since   2.10.1
 */
import { store, getContext, getElement } from '@wordpress/interactivity';

store( 'whmcs-price/cycle-toggle', {
	actions: {
		select( event ) {
			const input = event.target;
			if ( ! input || ! input.checked ) {
				return;
			}
			const context = getContext();
			context.selected = input.value;
			context.announce = input.dataset.announce || '';
		},
	},
	callbacks: {
		// The radio group is hidden in the HTML so it never shows without JavaScript.
		reveal() {
			getElement().ref.hidden = false;
		},
	},
} );
