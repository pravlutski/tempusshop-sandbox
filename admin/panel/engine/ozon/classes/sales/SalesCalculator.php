<?php

class SalesCalculator
{
  public static function calculateMargin( float $price, int $com, float $cost ):float
  {
    // Маржинальность, %
    $margin = ( $price * (1 - $com / 100) - $cost ) / $cost;
    return round( $margin * 100, 2 );
  }

  public static function calculateProfit( float $price, int $com, float $cost  ):float
  {
    // Маржа, руб.
    $profit = $price * (1 - $com / 100) - $cost;
    return round( $profit, 2 );
  }

  public static function calculateDiscount( float $maxActionPrice, float $itemPrice ):float
  {
    // Скидка, которую мы даем, чтобы достичь максимальной цены вхождения
    $discount = ( 1 - $maxActionPrice / $itemPrice ) * 100;
    return round( $discount, 2 );
  }

  public static function calculateElasticPrice( array $item, array $sale ):int
	{
		if ( !isset($item['price_min_elastic']) || !isset($item['price_max_elastic']) ) return $item['max_action_price'];
    if ( $item['min_boost'] == 0 || $item['max_boost'] == 0 ) return $item['max_action_price'];
    if ( empty($sale['boost']) ) return $item['max_action_price'];

		$minBoost = $item['min_boost'];
		$maxBoost = $item['max_boost'];
		$wishedBoostPercent = intval( $sale['boost'] );

		$priceMin = $item['price_min_elastic'];
		$priceMax = $item['price_max_elastic'];

		$diffPrice = $priceMin - $priceMax;
		$diffBoost = $maxBoost - $minBoost;

		$boostPercent = $diffPrice / $diffBoost;
		$needBoost = $wishedBoostPercent - $minBoost;

		$wishedBoostPrice = $boostPercent * $needBoost;

		$actionPrice = $priceMin - $wishedBoostPrice;

		return intval( round( $actionPrice ) );
	}
}
 ?>
