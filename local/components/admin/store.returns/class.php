<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

class StoreReturnsComponent extends CBitrixComponent
{
    private $storeConfigs = [
        'nemiga' => [
            'name' => 'Немига',
            'supplier_id' => '149',
            'site_id' => 's2',
            'top_models_site_id' => 's2',
            'iblock_id' => CProSet::IB_CATALOG,
            'active_column' => 'active_by',
            'reserve_column' => 'RESERVED_s2',
            'ms_agent_id' => 'd9f84aa8-9a7e-11ef-0a80-0fc20065d55e',
        ],
        'novokuznetskaya' => [
            'name' => 'Новокузнецкая',
            'supplier_id' => '128',
            'site_id' => 's1',
            'top_models_site_id' => 's1_nzk',
            'iblock_id' => CProSet::IB_CATALOG,
            'active_column' => 'active_ru',
            'reserve_column' => 'RESERVED_s1',
            'ms_agent_id' => '2b831384-f9a1-11ef-0a80-07570009a737',
        ]
    ];

    public function executeComponent()
    {
        global $DB;
        $this->db = $DB;
        
        $this->arResult['CURRENT_STORE'] = $_REQUEST['store'] ?? 'nemiga';
        
        if (!isset($this->storeConfigs[$this->arResult['CURRENT_STORE']])) {
            $this->arResult['CURRENT_STORE'] = 'nemiga';
        }
        
        $config = $this->storeConfigs[$this->arResult['CURRENT_STORE']];
        
        $arStock = $this->getStock($config['supplier_id'], $config['active_column']);
        
        $filteredStock = $this->excludeTopModels($arStock, $config['top_models_site_id']);
        
        $filteredStock = $this->excludeByReserves($filteredStock, $config['reserve_column']);
        
        $filteredStock = $this->checkSupplier($filteredStock, $config['site_id'], $config['ms_agent_id']);
        
        $data = $this->prepareData($filteredStock, $config['iblock_id']);
        
        $this->arResult['RETURNS_LIST'] = $data;
        $this->arResult['STORE_NAME'] = $config['name'];
        
        if (!empty($_REQUEST['export_type'])) {
            $this->exportToFile($_REQUEST['export_type']);
        }
        
        $this->IncludeComponentTemplate();
    }

    private function getStock(string $supplierId, string $activeColumn): array
    {
        $arStock = [];
        $sql = "SELECT * FROM ci_price 
                WHERE {$activeColumn} = 'Y' 
                AND supplier_id = '" . $this->db->ForSql($supplierId) . "'";

        $result = $this->db->Query($sql);
        
        while ($row = $result->Fetch()) {
            $arStock[$row['bitrix_id']] = [
                'BX_ID' => $row['bitrix_id'],
                'ARTICLE' => $row['model'],
                'PRICE' => $row['price'],
                'QUANTITY' => $row['count'],
            ];
        }
        return $arStock;
    }

    private function excludeByReserves(array $models, string $reserveColumn): array
    {
        if (!$models) return [];
        
        $arIds = array_keys($models);
        
        $arReserve = [];
        $sql = "SELECT * FROM ci_reserved 
                WHERE PRODUCT_ID IN ('".implode("','", array_map([$this->db, 'ForSql'], $arIds))."') 
                AND {$reserveColumn} > 0";

        $result = $this->db->Query($sql);
        
        while ($row = $result->Fetch()) {
            $arReserve[$row['PRODUCT_ID']] = [
                'BX_ID' => $row['PRODUCT_ID'],
                'ARTICLE' => $row['ARTICLE'],
                'RESERVED' => $row[$reserveColumn],
            ];
        }
        
        foreach ($models as $k => &$arItem) {
            if (isset($arReserve[$arItem['BX_ID']])) {
                $reserve = $arReserve[$arItem['BX_ID']];
                $arItem['QUANTITY'] -= $reserve['RESERVED'];
                
                if ($arItem['QUANTITY'] <= 0) {
                    unset($models[$k]);
                }
            }
        }
        
        return $models;
    }

    private function excludeTopModels(array $models, string $siteId): array
    {
        $sql = "SELECT bitrix_id FROM ci_top_models 
                WHERE site_id = '" . $this->db->ForSql($siteId) . "'";

        $result = $this->db->Query($sql);
        
        while ($row = $result->Fetch()) {
            if (isset($models[$row['bitrix_id']])) {
                unset($models[$row['bitrix_id']]);
            }
        }
        
        return $models;
    }

    private function checkSupplier(array $models, string $siteId, string $msAgentId): array
    {
        if (!$models) return [];
        //prent($models);
        $arIds = array_keys($models);

        /*$sql = "SELECT PRODUCT_XML_ID, AGENT_ID, QUANTITY FROM ci_ms_history 
                WHERE SITE_ID = '" . $this->db->ForSql($siteId) . "' 
                AND PRODUCT_XML_ID IN ('".implode("','", array_map([$this->db, 'ForSql'], $arIds))."') 
                AND TYPE = 'SUPPLY' 
                GROUP BY PRODUCT_XML_ID, AGENT_ID 
                ORDER BY TIMESTAMP DESC";*/
        
		$sql = "SELECT PRODUCT_XML_ID, AGENT_ID, QUANTITY
		FROM (
			SELECT PRODUCT_XML_ID, AGENT_ID, QUANTITY, TIMESTAMP,
				   ROW_NUMBER() OVER (PARTITION BY PRODUCT_XML_ID ORDER BY TIMESTAMP DESC) as rn
			FROM ci_ms_history
			WHERE SITE_ID = 's2' 
			  AND PRODUCT_XML_ID IN ('".implode("','", array_map([$this->db, 'ForSql'], $arIds))."') 
			  AND TYPE = 'SUPPLY'
		) t
		WHERE rn = 1";
		//prent($sql);
		
        $result = $this->db->Query($sql);
        while ($row = $result->Fetch()) {
            if ($row['AGENT_ID'] != $msAgentId) {
				
				if ($models[$row['PRODUCT_XML_ID']]) {
					$models[$row['PRODUCT_XML_ID']]['QUANTITY'] -= $row['QUANTITY'];
					
					if ($models[$row['PRODUCT_XML_ID']]['QUANTITY'] <= 0) {
						unset($models[$row['PRODUCT_XML_ID']]);
					}
				}

            }
        }

        return $models;
    }
    
    private function prepareData(array $models, int $iblockId): array
    {
        if (!$models) return [];
        
        $arIds = array_keys($models);
        
        if (!\Bitrix\Main\Loader::includeModule('iblock')) {
            return $models;
        }
        
        $arFilter = [
            "IBLOCK_ID" => $iblockId, 
            "ID" => $arIds
        ];
        $arSelect = [
            'ID', 'NAME', 'PREVIEW_PICTURE', 'DETAIL_PICTURE',
        ];
        
        $rs = CIBlockElement::GetList([], $arFilter, false, false, $arSelect);
        while ($arFields = $rs->GetNext()) {
            $path = '';
            if ($arFields['PREVIEW_PICTURE']) {
                $path = CFile::GetPath($arFields['PREVIEW_PICTURE']);
            }
            if (!$path && $arFields['DETAIL_PICTURE']) {
                $path = CFile::GetPath($arFields['DETAIL_PICTURE']);
            }
            
            $models[$arFields['ID']]['PHOTO'] = $path;
            $models[$arFields['ID']]['NAME'] = $arFields['NAME'];
        }
        
        uasort($models, function($a, $b) {
            return strcasecmp($a['NAME'] ?? '', $b['NAME'] ?? '');
        });
        
        return $models;
    }
    
    private function exportToFile(string $type): void
    {
        if (empty($this->arResult['RETURNS_LIST'])) {
            return;
        }
        
        $storePrefix = $this->arResult['CURRENT_STORE'] === 'nemiga' ? 'nemiga' : 'novokuznetskaya';
        $filename = 'returns_' . $storePrefix . '_' . date('Y-m-d');
        
        global $APPLICATION;
        $APPLICATION->RestartBuffer();
        ob_start();

        if ($type === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
            
            $output = fopen('php://output', 'w');
            fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM для UTF-8
            
            fputcsv($output, ['Артикул', 'Количество'], ';');
            
            foreach ($this->arResult['RETURNS_LIST'] as $item) {
                fputcsv($output, [
                    $item['NAME'] ?? '',
                    $item['QUANTITY'] ?? 0
                ], ';');
            }
            
            fclose($output);
        } elseif ($type === 'excel') {
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
            
            echo '<html><head><meta charset="UTF-8"></head><body>';
            echo '<table border="1">';
            echo '<tr><th>Артикул</th><th>Количество</th></tr>';
            
            foreach ($this->arResult['RETURNS_LIST'] as $item) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($item['NAME'] ?? '') . '</td>';
                echo '<td>' . (int)($item['QUANTITY'] ?? 0) . '</td>';
                echo '</tr>';
            }
            
            echo '</table></body></html>';
        }
        ob_end_flush();
        die;
    }
}