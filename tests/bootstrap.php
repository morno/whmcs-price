<?php
/**
 * PHPUnit bootstrap for Mornolink for WHMCS.
 *
 * Unit tests run without a WordPress install or database: WordPress functions
 * are replaced by Brain Monkey stubs backed by an in-memory store (see
 * tests/TestCase.php). This keeps the suite fast and runnable on any PHP
 * version in the CI matrix.
 *
 * @package WHMCS_Price\Tests
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'WHMCS_PRICE_VERSION', 'tests' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once __DIR__ . '/stubs/wp-classes.php';
require_once __DIR__ . '/TestCase.php';

$whmcs_price_root = dirname( __DIR__ );
require_once $whmcs_price_root . '/includes/class-whmcs-api.php';
require_once $whmcs_price_root . '/includes/class-whmcs-price-helpers.php';
require_once $whmcs_price_root . '/includes/rate-limit.php';
