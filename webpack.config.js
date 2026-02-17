const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		'whmcs-price-product': [
			'./blocks/whmcs-price-product/index.js',
			'./blocks/whmcs-price-product/styles.css',
		],
		'whmcs-price-domain': [
			'./blocks/whmcs-price-domain/index.js',
			'./blocks/whmcs-price-domain/styles.css',
		],
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'blocks/build' ),
	},
};
