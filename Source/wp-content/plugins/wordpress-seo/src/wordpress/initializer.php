<?php
/**
 * Integration interface definition.
 *
 * @package Yoast\YoastSEO\WordPress
 */

namespace Yoast\WP\Free\WordPress;

/**
 * An interface for registering integrations with WordPress
 */
interface Initializer extends Loadable {

	/**
	 * Runs this initializer.
	 *
	 * @return void
	 */
	public function initialize();
}
