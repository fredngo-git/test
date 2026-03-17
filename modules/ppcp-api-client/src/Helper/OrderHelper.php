<?php
/**
 * PayPal order helper.
 *
 * @package WooCommerce\MecomPaypal\ApiClient\Helper
 */

declare(strict_types=1);

namespace WooCommerce\MecomPaypal\ApiClient\Helper;

use WooCommerce\MecomPaypal\ApiClient\Entity\Order;

/**
 * Class OrderHelper
 */
class OrderHelper {

	/**
	 * Checks if order contains physical goods.
	 *
	 * @param Order $order PayPal order.
	 * @return bool
	 */
	public function contains_physical_goods( Order $order ): bool {
		foreach ( $order->purchase_units() as $unit ) {
			if ( $unit->contains_physical_goods() ) {
				return true;
			}
		}

		return false;
	}
}
