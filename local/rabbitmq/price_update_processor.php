<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";

set_time_limit(600);
ini_set('display_errors', '1');
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

if(
    !CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") || 
    !CModule::IncludeModule("panel.manager") || !CModule::IncludeModule("sale") || 
    !CModule::IncludeModule("currency") || !CModule::IncludeModule("catalog")
) {
    echo "Failed to include modules\n";
    exit(1);
}

$logger = new TsLogger("rabbitmq/price_update/");

try {
    if ($argc < 2) {
        $logger->log("ERROR", "Нет данных для обработки");
        exit(1);
    }
    
    $jsonData = $argv[1];
    $data = json_decode($jsonData, true);
    
    if (empty($data) || empty($data['articles'])) {
        $logger->log("ERROR", "Нет артикулов для обновления", $data);
        exit(1);
    }
    
    $logger->log("LOG", "Начинаем обработку", [
        'orders' => $data['orders'],
        'articles' => count($data['articles']),
        'products' => count($data['product_ids'])
    ]);
    
    // Обновление резервов
	$orderReserved = PanelManager::getOrderReservedManager();
	$orderReserved->updateReserved();

	$logger->log("LOG", "Резервы обновлены");
    
    // Обновление цен
    Panel\Manager\Service\CatalogPriceService::updatePrices('all', $data['articles']);
    $logger->log("LOG", "Цены обновлены для " . count($data['articles']) . " артикулов");
    
    // Обновление свойств товаров
    if (!empty($data['product_ids'])) {
        Panel\Manager\Service\CatalogPriceService::updatePriceProps($data['product_ids']);
        $logger->log("LOG", "Свойства обновлены для " . count($data['product_ids']) . " товаров");
    }
    
    $logger->log("LOG", "Обработка завершена успешно", [
        'orders' => $data['orders'],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    $logger->log("ERROR", "Ошибка обработки", [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    exit(1);
}
?>