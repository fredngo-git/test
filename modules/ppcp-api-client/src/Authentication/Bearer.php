<?php
/**
 * The bearer interface.
 *
 * @package WooCommerce\MecomPaypal\ApiClient\Authentication
 */

declare(strict_types=1);

namespace WooCommerce\MecomPaypal\ApiClient\Authentication;

use WooCommerce\MecomPaypal\ApiClient\Entity\Token;

/**
 * Interface Bearer
 */
interface Bearer {

	/**
	 * Returns the bearer.
	 *
	 * @return Token
	 */
	public function bearer(): Token;
}
