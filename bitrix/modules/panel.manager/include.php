<?
global $DB, $MESS, $APPLICATION;

use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Panel\Manager\Manager;

//use Panel\Manager\Service\PriceUpdateService;
include("function.php");
include("constants.php");
$arClasses = array(
//    "CPanelModel"    => "classes/general/model.php",
	"CPanelContent"				=> "classes/models/content.php",
	"CPanelUtils"				=> "classes/models/utils.php",
	"CPanelProduct"				=> "classes/models/product.php",
	"MParserAPI"				=> "classes/general/api_marketparser.php",
	"OrderService"				=> "classes/general/orderservice.php",
	"YandexApi"					=> "classes/general/yandex_api.php",
	"CPanelBrand"				=> "classes/models/brand.php",
	"CPanelCourier"				=> "classes/models/courier.php",
	"CPanelEmployee"			=> "classes/models/employee.php",
	"CPanelCurrency"			=> "classes/models/currency.php",
	"CPanelSupplier"			=> "classes/models/supplier.php",
	"CPanelPricelist"			=> "classes/models/pricelist.php",
	"CPanelAnalysis"			=> "classes/models/analysis.php",
	"CPanelDiscount"			=> "classes/models/discount.php",
	"Metric"			=> "classes/metric/main.php",

	"CPriceUpdate"				=> "classes/general/updateprice.php",
	"CYandexParser"				=> "classes/general/parse_yandex.php",
	"CCeneoParser"				=> "classes/general/parse_ceneo.php",
	"COnlinerParser"			=> "classes/general/parse_onliner.php",

	"CNokogiri"					=> "classes/general/nokogiri.php",
	"CCeneoParserURI"			=> "classes/general/parse_ceneo_url.php",

	"CWbParserURI"				=> "classes/general/parse_wb_url.php",

	"CYandexParserURI"			=> "classes/general/parse_yandex_url.php",
//	"YaMarketParser"			=> "classes/general/parse_market_yandex.php",
	"PWexchange"				=> "classes/general/pw.php",
	"MoyskladAPI"				=> "classes/general/api_moysklad.php",
	"SFTPConnection"			=> "classes/general/sftp.php",

	"DBPanel"			=> "classes/panel/db.php",
	"TGNotifier" => "classes/general/TGNotifier.php",
/*	"ShippingType"				=> "classes/models/ShippingType.php",
	"PreShippingDetails"		=> "classes/models/PreShippingDetails.php",
	"PreOrder"					=> "classes/models/PreOrder.php",
	"PaymentType"				=> "classes/models/PaymentType.php",
	"PreOrderStatus"			=> "classes/models/PreOrderStatus.php",
	"OrderError"				=> "classes/models/OrderError.php",
	"ContactDetailsModel"		=> "classes/models/ContactDetailsModel.php",
	"CPanelSet"					=> "classes/general/settings.php",
	"CalculationService"		=> "classes/general/calculationService.php",
	"CPanelManager"				=> "classes/general/panelManager.php",
	"OrderService"				=> "classes/general/orderservice.php",
	"PreOrderService"			=> "classes/general/preorderservice.php",*/
);

CModule::AddAutoloadClasses("panel.manager", $arClasses);
/*
function panel.manager_autoload($className) {
    if (strpos($className, 'Manager\\') === 0) {
        $file = __DIR__ . '/lib/' . str_replace('\\', '/', substr($className, 13)) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
}

spl_autoload_register('panel.manager_autoload');
*/

// временно
/*$dirs = [
    'lib',
    'lib/Config', 
    'lib/Market',
    'lib/Services'
];

foreach ($dirs as $dir) {
    $fullPath = __DIR__ . '/' . $dir;
    
    if (is_dir($fullPath)) {
        $files = scandir($fullPath);
        foreach ($files as $file) {
            if (preg_match('/\.php$/i', $file)) {
                require_once $fullPath . '/' . $file;
            }
        }
    }
}*/


class PanelManager
{
    const MODULE_ID = 'panel.manager';
    
    public static function getPriceManager()
    {
        return new Manager();
    }
    public static function getOrderReservedManager()
    {
		$manager = new Manager();
        return $manager->orderReserved();
    }
}


IncludeModuleLangFile(__FILE__);
