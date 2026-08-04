<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();
use Bitrix\Main\Loader;

if(!Loader::includeModule('panel.manager')) return;

$supplier = new CPanelSupplier;
$brand = new CPanelBrand;
$pricelist = new CPanelPricelist;
$analysis = new CPanelAnalysis;

$brand_list = $brand->getList();
usort($brand_list, function($a, $b) {
    return $a['name'] <=> $b['name'];
});

$arResult["SUPPLIER_LIST"] = $supplier->getList(['opt_supplier' => 'N']);
$arResult["BRAND_LIST"] = $brand_list;
$arResult["PRICELIST"] = $pricelist->getList();
$arResult["CONTROL_RRC"] = json_decode(CProSet::getOption("CONTROL_RRC"), true);

$service = PanelManager::getPriceManager();
$arResult["TYPE_PRICES"] = $service->getTypePrices(); 
$arResult["TYPE_PRICES_ID"] = array_column($arResult["TYPE_PRICES"], 'id');

$this->IncludeComponentTemplate();
?>