<?php
/**
 * PayPal order repository.
 *
 * @package WooCommerce\MecomPaypal\ApiClient\Repository
 */

declare(strict_types=1);

namespace WooCommerce\MecomPaypal\ApiClient\Repository;

use WC_Order;
use WooCommerce\MecomPaypal\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\MecomPaypal\ApiClient\Entity\Order;
use WooCommerce\MecomPaypal\ApiClient\Exception\RuntimeException;
//use WooCommerce\MecomPaypal\WcGateway\Gateway\PayPalGateway;

/**
 * Class OrderRepository
 */
class OrderRepository {

	/**
	 * The order endpoint.
	 *
	 * @var OrderEndpoint
	 */
	protected $order_endpoint;

	/**
	 * OrderRepository constructor.
	 *
	 * @param OrderEndpoint $order_endpoint The order endpoint.
	 */
	public function __construct( OrderEndpoint $order_endpoint ) {
		$this->order_endpoint = $order_endpoint;
	}

	/**
	 * Gets a PayPal order for the given WooCommerce order.
	 *
	 * @param WC_Order $wc_order The WooCommerce order.
	 * @return Order The PayPal order.
	 * @throws RuntimeException When there is a problem getting the PayPal order.
	 */
	public function for_wc_order( WC_Order $wc_order ): Order {
//		$paypal_order_id = $wc_order->get_meta( PayPalGateway::ORDER_ID_META_KEY ); MECOM
		$paypal_order_id = $wc_order->get_meta( '' );// MECOM
		if ( ! $paypal_order_id ) {
			throw new RuntimeException( 'PayPal order ID not found in meta.' );
		}

		return $this->order_endpoint->order( $paypal_order_id );
	}
}
