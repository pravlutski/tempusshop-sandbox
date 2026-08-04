<?
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Panel\Manager\Config\MarketConfigTable;
use Panel\Manager\Config\MarketWarehousePriorityTable;

use Bitrix\Main\Loader;

header('Content-Type: application/json');

// Проверка прав
if (!$USER->IsAdmin()) {
    echo json_encode(['error' => 'Доступ запрещен']);
    die();
}

if (!Loader::includeModule('panel.manager')) {
    echo json_encode(['error' => 'Модуль не установлен']);
    die();
}

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'getConfigs':
        $configs = MarketConfigTable::getList([
            'order' => ['SORT' => 'ASC', 'PRYCE_TYPE' => 'ASC']
        ])->fetchAll();
        
        foreach ($configs as &$config) {
            $config['PRIORITIES'] = array_values(MarketWarehousePriorityTable::getPrioritiesByMarketCode($config['PRYCE_TYPE']));
        }
        
        echo json_encode(['success' => true, 'data' => $configs]);
        break;
        
    case 'getConfig':
        $code = $_REQUEST['code'] ?? '';
        if (!$code) {
            echo json_encode(['error' => 'Не указан код']);
            break;
        }
        
        $config = MarketConfigTable::getList([
            'filter' => ['=PRYCE_TYPE' => $code]
        ])->fetch();
        
        if ($config) {
            $config['PRIORITIES'] = array_values(MarketWarehousePriorityTable::getPrioritiesByMarketCode($code));
        }
        
        echo json_encode(['success' => true, 'data' => $config]);
        break;
        
    case 'saveConfig':
        $data = $_REQUEST['data'] ?? [];
		$data = json_decode($data, true);
        if (empty($data['PRYCE_TYPE'])) {
            echo json_encode(['error' => 'Не указан код']);
            break;
        }
        
        // Проверяем существование
        $exists = MarketConfigTable::getList([
            'filter' => ['=PRYCE_TYPE' => $data['PRYCE_TYPE']],
            'select' => ['ID']
        ])->fetch();
        
        $fields = [
            'SORT' => (int)($data['SORT'] ?? 500),
            'ACTIVE' => ($data['ACTIVE'] ?? 'Y') === 'Y' ? 'Y' : 'N',
            'NAME' => $data['NAME'] ?? '',
            'SITE_ID' => $data['SITE_ID'] ?? '',
            'ROUND_PRICE' => (int)($data['ROUND_PRICE'] ?? 0),
            'CURRENCY' => $data['CURRENCY'] ?? 'RUB',
            'RATE' => (float)($data['RATE'] ?? 1.0),
            'PRICE_TYPE_ID' => !empty($data['PRICE_TYPE_ID']) ? (int)$data['PRICE_TYPE_ID'] : null,
            'PROPERTY_PRICE' => $data['PROPERTY_PRICE'] ?? '',
            'URL' => $data['URL'] ?? '',
            'TRADING_PLATFORM_ID' => !empty($data['TRADING_PLATFORM_ID']) ? (int)$data['TRADING_PLATFORM_ID'] : null,
            'COLUMN_PRICE' => $data['COLUMN_PRICE'] ?? '',
            'COLUMN_DISCOUNT_PRICE' => $data['COLUMN_DISCOUNT_PRICE'] ?? '',
            'COLUMN_ACTIVE' => $data['COLUMN_ACTIVE'] ?? '',
            'TBL_SEBES_FBO' => $data['TBL_SEBES_FBO'] ?? null,
            'TBL_PRICE_FBO' => $data['TBL_PRICE_FBO'] ?? null,
            'OPTION_UPDATE' => $data['OPTION_UPDATE'] ?? '',
            'OPTION_STATUS_PARSER' => $data['OPTION_STATUS_PARSER'] ?? '',
        ];
        
        if ($exists) {
            $result = MarketConfigTable::update($exists['ID'], $fields);
        } else {
            $fields['PRYCE_TYPE'] = $data['PRYCE_TYPE'];
            $result = MarketConfigTable::add($fields);
        }
        MarketConfigTable::clearCache();
        if ($result->isSuccess()) {
            // Сохраняем приоритеты
            if (isset($data['PRIORITIES']) && is_array($data['PRIORITIES'])) {
                MarketWarehousePriorityTable::savePriorities($data['PRYCE_TYPE'], $data['PRIORITIES']);
            }
            echo json_encode(['success' => true, 'message' => 'Сохранено']);
        } else {
            echo json_encode(['error' => implode(', ', $result->getErrorMessages())]);
        }
        break;
        
    case 'deleteConfig':
        $code = $_REQUEST['code'] ?? '';
        if (!$code) {
            echo json_encode(['error' => 'Не указан код']);
            break;
        }
        
        $config = MarketConfigTable::getList([
            'filter' => ['=PRYCE_TYPE' => $code],
            'select' => ['ID']
        ])->fetch();
        
        if ($config) {
            MarketWarehousePriorityTable::deleteByMarketCode($code);
            // Удаляем конфиг
            MarketConfigTable::delete($config['ID']);
			
			MarketConfigTable::clearCache();
            echo json_encode(['success' => true, 'message' => 'Удалено']);
        } else {
            echo json_encode(['error' => 'Конфиг не найден']);
        }
        break;
        
    case 'savePriorities':
        $code = $_REQUEST['code'] ?? '';
        $priorities = $_REQUEST['priorities'] ?? [];
        
        if (!$code) {
            echo json_encode(['error' => 'Не указан код']);
            break;
        }
        
        $result = MarketWarehousePriorityTable::savePriorities($code, $priorities);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Приоритеты сохранены']);
        } else {
            echo json_encode(['error' => 'Ошибка сохранения приоритетов']);
        }
        break;
        
    case 'deletePriority':
        $id = (int)($_REQUEST['id'] ?? 0);
        if (!$id) {
            echo json_encode(['error' => 'Не указан ID']);
            break;
        }
        
        $result = MarketWarehousePriorityTable::delete($id);
        
        if ($result->isSuccess()) {
            echo json_encode(['success' => true, 'message' => 'Приоритет удален']);
        } else {
            echo json_encode(['error' => implode(', ', $result->getErrorMessages())]);
        }
        break;
        
    case 'getWarehouses':
        $warehouses = [];
        
		$supplier = new \CPanelSupplier;
		
		$suppliers = $supplier->getList(['is_warehouse' => 'Y'], ['sort' => 'asc', 'name' => 'asc']);
		
		foreach ($suppliers as $item) {
			$warehouses[] = [
				'ID' => $item['id'],
				'NAME' => $item['name'],
			];
		}
        
        echo json_encode(['success' => true, 'data' => $warehouses]);
        break;
        
    default:
        echo json_encode(['error' => 'Неизвестное действие']);
}