<?php

namespace SpringDevs\Pathao\Facades;

use SpringDevs\Pathao\Services\PathaoApiService;

/**
 * PathaoAPI facade.
 *
 * @method static \StdClass get_cities()
 * @method static \StdClass get_stores()
 * @method static \StdClass get_zones(int $city_id)
 * @method static \StdClass get_areas(int $zone_id)
 * @method static \StdClass send_order(int $order, $args=[])
 * @method static \StdClass price_calculation(array $args)
 * @method static \StdClass generate_tokens(array $args)
 * @method static \StdClass refresh_tokens()
 */
class PathaoAPI {

	/**
	 * Call method from PathaoAPI service.
	 *
	 * @param string $name method name.
	 * @param array  $arguments arguments.
	 *
	 * @return mixed
	 */
	public static function __callStatic( $name, $arguments ) {
		return ( new PathaoApiService() )->$name( ...$arguments );
	}
}
