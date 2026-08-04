<?
/**
 * Class PriceHelper
 */
class PriceHelper {
	public $debugData = [];

    public function __construct () {

    }

    public static function getPriceType () {
		// сделать потом в таблице. непонятно пока стоит ли
        $arPrice = ["RU", "BY", "PL", "YA", "OS", "WB"];

        return $arPrice;
    }
}
?>
