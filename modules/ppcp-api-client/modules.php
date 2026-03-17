<?php
/**
 * The api client module.
 *
 * @package WooCommerce\MecomPaypal\ApiClient
 */

declare(strict_types=1);

namespace WooCommerce\MecomPaypal\ApiClient;

use Dhii\Modular\Module\ModuleInterface;

return function (): ModuleInterface {
	return new ApiModule();
};
