<?php
/**
 * Shortcode Generator
 *
 * Adds a "Shortcode Generator" tab to the WHMCS Price settings page.
 * Provides a simple UI where admins can fill in fields and get the
 * correct [whmcs] shortcode syntax — no need to memorise attributes.
 *
 * This is a pure admin UI feature — no database writes, no hooks beyond
 * the settings page tab system. All output is generated client-side via
 * a small inline JavaScript snippet.
 *
 * @package    WHMCS_Price
 * @subpackage Admin
 * @since      2.9.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render the Shortcode Generator tab content.
 *
 * Called from WHMCSPrice::whmcs_price_admin_page() when the 'generator'
 * tab is active. Must be hooked into the settings page render logic.
 *
 * @since 2.9.0
 * @return void
 */
function whmcs_price_render_shortcode_generator(): void {
	?>
	<div class="whmcs-price-generator" style="max-width:780px;">

		<h2 style="margin-top:0;"><?php esc_html_e( 'Shortcode Generator', 'whmcs-price' ); ?></h2>
		<p class="description" style="margin-bottom:20px;">
			<?php esc_html_e( 'Fill in the fields below and copy the generated shortcode. You can paste it into any post, page, or text widget.', 'whmcs-price' ); ?>
		</p>

		<!-- Tabs inside generator -->
		<div id="wgp-tabs" style="margin-bottom:20px;">
			<label style="margin-right:16px;">
				<input type="radio" name="wgp_mode" value="product" checked> <?php esc_html_e( 'Product Price', 'whmcs-price' ); ?>
			</label>
			<label>
				<input type="radio" name="wgp_mode" value="domain"> <?php esc_html_e( 'Domain Price', 'whmcs-price' ); ?>
			</label>
		</div>

		<!-- Product fields -->
		<div id="wgp-product">
			<table class="form-table" role="presentation" style="width:auto;">
				<tr>
					<th scope="row"><label for="wgp_pid"><?php esc_html_e( 'Product ID(s)', 'whmcs-price' ); ?></label></th>
					<td>
						<input type="text" id="wgp_pid" class="regular-text" placeholder="1" style="width:200px;">
						<p class="description"><?php esc_html_e( 'Single ID or comma-separated: 1,2,3', 'whmcs-price' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wgp_bc"><?php esc_html_e( 'Billing Cycle', 'whmcs-price' ); ?></label></th>
					<td>
						<select id="wgp_bc">
							<option value="1m"><?php esc_html_e( 'Monthly', 'whmcs-price' ); ?></option>
							<option value="3m"><?php esc_html_e( 'Quarterly', 'whmcs-price' ); ?></option>
							<option value="6m"><?php esc_html_e( 'Semi-annually', 'whmcs-price' ); ?></option>
							<option value="1y" selected><?php esc_html_e( 'Annually', 'whmcs-price' ); ?></option>
							<option value="2y"><?php esc_html_e( 'Biennially', 'whmcs-price' ); ?></option>
							<option value="3y"><?php esc_html_e( 'Triennially', 'whmcs-price' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Show columns', 'whmcs-price' ); ?></th>
					<td>
						<label><input type="checkbox" class="wgp_show" value="name" checked> <?php esc_html_e( 'Name', 'whmcs-price' ); ?></label>&nbsp;&nbsp;
						<label><input type="checkbox" class="wgp_show" value="description"> <?php esc_html_e( 'Description', 'whmcs-price' ); ?></label>&nbsp;&nbsp;
						<label><input type="checkbox" class="wgp_show" value="price" checked> <?php esc_html_e( 'Price', 'whmcs-price' ); ?></label>&nbsp;&nbsp;
						<label><input type="checkbox" class="wgp_show" value="setupfee"> <?php esc_html_e( 'Setup Fee', 'whmcs-price' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wgp_per"><?php esc_html_e( 'Per-period breakdown', 'whmcs-price' ); ?></label></th>
					<td>
						<select id="wgp_per">
							<option value=""><?php esc_html_e( 'None', 'whmcs-price' ); ?></option>
							<option value="month"><?php esc_html_e( 'Per month', 'whmcs-price' ); ?></option>
							<option value="week"><?php esc_html_e( 'Per week', 'whmcs-price' ); ?></option>
							<option value="day"><?php esc_html_e( 'Per day', 'whmcs-price' ); ?></option>
						</select>
					</td>
				</tr>
			</table>
		</div>

		<!-- Domain fields -->
		<div id="wgp-domain" style="display:none;">
			<table class="form-table" role="presentation" style="width:auto;">
				<tr>
					<th scope="row"><label for="wgp_tld"><?php esc_html_e( 'TLD', 'whmcs-price' ); ?></label></th>
					<td>
						<input type="text" id="wgp_tld" class="regular-text" placeholder="se" style="width:120px;">
						<p class="description"><?php esc_html_e( 'Without the dot. Leave blank for all TLDs.', 'whmcs-price' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wgp_type"><?php esc_html_e( 'Type', 'whmcs-price' ); ?></label></th>
					<td>
						<select id="wgp_type">
							<option value="register"><?php esc_html_e( 'Register', 'whmcs-price' ); ?></option>
							<option value="renew"><?php esc_html_e( 'Renew', 'whmcs-price' ); ?></option>
							<option value="transfer"><?php esc_html_e( 'Transfer', 'whmcs-price' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wgp_reg"><?php esc_html_e( 'Registration period', 'whmcs-price' ); ?></label></th>
					<td>
						<select id="wgp_reg">
							<?php for ( $i = 1; $i <= 10; $i++ ) : ?>
								<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $i, 1 ); ?>>
									<?php
									/* translators: %d: number of years */
									echo esc_html( sprintf( _n( '%d year', '%d years', $i, 'whmcs-price' ), $i ) );
									?>
								</option>
							<?php endfor; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wgp_show_domain"><?php esc_html_e( 'Show types', 'whmcs-price' ); ?></label></th>
					<td>
						<select id="wgp_show_domain">
							<option value=""><?php esc_html_e( 'Single type (use Type above)', 'whmcs-price' ); ?></option>
							<option value="register,renew"><?php esc_html_e( 'Register + Renew', 'whmcs-price' ); ?></option>
							<option value="register,renew,transfer"><?php esc_html_e( 'Register + Renew + Transfer', 'whmcs-price' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wgp_dper"><?php esc_html_e( 'Per-period breakdown', 'whmcs-price' ); ?></label></th>
					<td>
						<select id="wgp_dper">
							<option value=""><?php esc_html_e( 'None', 'whmcs-price' ); ?></option>
							<option value="month"><?php esc_html_e( 'Per month', 'whmcs-price' ); ?></option>
							<option value="week"><?php esc_html_e( 'Per week', 'whmcs-price' ); ?></option>
							<option value="day"><?php esc_html_e( 'Per day', 'whmcs-price' ); ?></option>
						</select>
					</td>
				</tr>
			</table>
		</div>

		<!-- Output -->
		<div style="margin-top:24px;">
			<label for="wgp_output" style="display:block;font-weight:600;margin-bottom:6px;">
				<?php esc_html_e( 'Generated shortcode', 'whmcs-price' ); ?>
			</label>
			<div style="display:flex;gap:8px;align-items:center;">
				<input type="text" id="wgp_output" class="large-text code" readonly
					style="font-family:monospace;background:#f0f0f1;cursor:pointer;"
					onclick="this.select();" value='[whmcs]'>
				<button type="button" class="button button-primary" id="wgp_copy">
					<?php esc_html_e( 'Copy', 'whmcs-price' ); ?>
				</button>
			</div>
			<span id="wgp_copied" style="display:none;color:green;margin-left:8px;font-size:13px;">
				✓ <?php esc_html_e( 'Copied!', 'whmcs-price' ); ?>
			</span>
		</div>

	</div>

	<script>
	(function() {
		var isProduct = true;

		function update() {
			var sc = '[whmcs';
			if (isProduct) {
				var pid = document.getElementById('wgp_pid').value.trim();
				var bc  = document.getElementById('wgp_bc').value;
				var show = Array.from(document.querySelectorAll('.wgp_show:checked')).map(function(e){return e.value;}).join(',');
				var per = document.getElementById('wgp_per').value;
				if (pid) sc += ' pid="' + pid + '"';
				if (bc)  sc += ' bc="' + bc + '"';
				if (show && show !== 'name,price') sc += ' show="' + show + '"';
				if (per) sc += ' per="' + per + '"';
			} else {
				var tld  = document.getElementById('wgp_tld').value.trim().replace(/^\./, '');
				var type = document.getElementById('wgp_type').value;
				var reg  = document.getElementById('wgp_reg').value;
				var showD = document.getElementById('wgp_show_domain').value;
				var dper  = document.getElementById('wgp_dper').value;
				if (tld) sc += ' tld="' + tld + '"';
				if (showD) {
					sc += ' show="' + showD + '"';
				} else if (type !== 'register') {
					sc += ' type="' + type + '"';
				}
				if (reg && reg !== '1') sc += ' reg="' + reg + '"';
				if (dper) sc += ' per="' + dper + '"';
			}
			sc += ']';
			document.getElementById('wgp_output').value = sc;
		}

		document.querySelectorAll('[name="wgp_mode"]').forEach(function(r) {
			r.addEventListener('change', function() {
				isProduct = this.value === 'product';
				document.getElementById('wgp-product').style.display = isProduct ? '' : 'none';
				document.getElementById('wgp-domain').style.display  = isProduct ? 'none' : '';
				update();
			});
		});

		['wgp_pid','wgp_bc','wgp_per','wgp_tld','wgp_type','wgp_reg','wgp_show_domain','wgp_dper'].forEach(function(id) {
			var el = document.getElementById(id);
			if (el) el.addEventListener('change', update);
			if (el && el.tagName === 'INPUT') el.addEventListener('input', update);
		});
		document.querySelectorAll('.wgp_show').forEach(function(cb) {
			cb.addEventListener('change', update);
		});

		document.getElementById('wgp_copy').addEventListener('click', function() {
			var out = document.getElementById('wgp_output');
			out.select();
			navigator.clipboard ? navigator.clipboard.writeText(out.value) : document.execCommand('copy');
			var copied = document.getElementById('wgp_copied');
			copied.style.display = 'inline';
			setTimeout(function(){ copied.style.display = 'none'; }, 2000);
		});

		update();
	})();
	</script>
	<?php
}
