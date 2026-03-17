<?php
/**
 * The modules Not Found exception.
 *
 * @package WooCommerce\MecomPaypal\ApiClient\Exception
 */

declare(strict_types=1);

namespace WooCommerce\MecomPaypal\ApiClient\Exception;

use Psr\Container\NotFoundExceptionInterface;
use Exception;

/**
 * Class NotFoundException
 */
class NotFoundException extends Exception implements NotFoundExceptionInterface {


}
