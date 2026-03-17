<?php
/**
 * The Money factory.
 *
 * @package WooCommerce\MecomPaypal\ApiClient\Factory
 */

declare(strict_types=1);

namespace WooCommerce\MecomPaypal\ApiClient\Factory;

use stdClass;
use WooCommerce\MecomPaypal\ApiClient\Entity\Money;
use WooCommerce\MecomPaypal\ApiClient\Exception\RuntimeException;

/**
 * Class MoneyFactory
 */
class MoneyFactory {

	/**
	 * Returns a Money object based off a PayPal Response.
	 *
	 * @param stdClass $data The JSON object.
	 *
	 * @return Money
	 * @throws RuntimeException When JSON object is malformed.
	 */
	public function from_paypal_response( stdClass $data ): Money {
		if ( ! isset( $data->value ) || ! is_numeric( $data->value ) ) {
			throw new RuntimeException( 'No money value given' );
		}
		if ( ! isset( $data->currency_code ) ) {
			throw new RuntimeException( 'No currency given' );
		}

		return new Money( (float) $data->value, $data->currency_code );
	}
}
