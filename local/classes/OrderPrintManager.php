<?php

class OrderPrintManager
{
    const TABLE_NAME = 'ci_order_print';
    const TABLE_VIEW_NAME = 'ci_order_print_scan';
    
    /**
     * Добавляет запись о печати заказа
     */
    public static function addPrintRecord($orderId, $userId, $typeScan = 'manual', $productId = false, $numberID = false, $controlMark = '')
    {
        global $DB;
        
        $fields = [
            'ORDER_ID' => (int)$orderId,
            'USER_ID' => (int)$userId,
            'TYPE_SCAN' => "'" . addslashes($typeScan) . "'",
        ];
        if ($productId) {
			$fields['PRODUCT_ID'] = (int)$productId;
		}
        if ($numberID) {
			$fields['NUMBER_ID'] = (int)$numberID;
		}
        if ($controlMark && strlen($controlMark) > 0) {
			$fields['CONTROL_MARK'] = "'" . addslashes($controlMark) . "'";
		}
        return $DB->Insert(self::TABLE_NAME, $fields);
    }
    
    /**
     * Получает историю печати для заказа
     */
    public static function getPrintHistory($orderId, $productId = false)
    {
        global $DB;
        $arOrderIDs = [];
		if (is_int($orderId) || is_string($orderId)) {
			$arOrderIDs[] = intval($orderId);
		} elseif (is_array($orderId)) {
			$arOrderIDs = $orderId;
		}
		
		if (!$arOrderIDs) return [];
		
		$where = "op.ORDER_ID IN ('" . implode("','", $arOrderIDs) . "')";
		if ($productId) {
			$where .= " AND op.PRODUCT_ID = '" . $productId . "' ";
		}
		
        $sql = "SELECT op.*, u.LOGIN, u.NAME, u.LAST_NAME 
                FROM " . self::TABLE_NAME . " op
                LEFT JOIN b_user u ON op.USER_ID = u.ID
                WHERE {$where}
                ORDER BY op.TIMESTAMP DESC";
        
        $result = $DB->Query($sql);
        $history = [];
        
        while ($item = $result->Fetch()) {
            $history[] = $item;
        }
        
        return $history;
    }
    
    /**
     * Получает историю печати для заказа
     */
    public static function getViewHistory($orderId, $productId = false)
    {
        global $DB;
        $arOrderIDs = [];
		if (is_int($orderId) || is_string($orderId)) {
			$arOrderIDs[] = intval($orderId);
		} elseif (is_array($orderId)) {
			$arOrderIDs = $orderId;
		}
		
		if (!$arOrderIDs) return [];
		
		$where = "op.ORDER_ID IN ('" . implode("','", $arOrderIDs) . "')";
		if ($productId) {
			$where .= " AND op.PRODUCT_ID = '" . $productId . "' ";
		}
		
        $sql = "SELECT op.*, u.LOGIN, u.NAME, u.LAST_NAME 
                FROM " . self::TABLE_VIEW_NAME . " op
                LEFT JOIN b_user u ON op.USER_ID = u.ID
                WHERE {$where}
                ORDER BY op.TIMESTAMP DESC";
        
        $result = $DB->Query($sql);
        $history = [];
        
        while ($item = $result->Fetch()) {
            $history[] = $item;
        }
        
        return $history;
    }
	
    /**
     * Получает историю печати c группировкой по всем полям
     */
    public static function getPrintHistoryGroup($orderId, $productId = false)
    {
        global $DB;
        $arOrderIDs = [];
		if (is_int($orderId) || is_string($orderId)) {
			$arOrderIDs[] = intval($orderId);
		} elseif (is_array($orderId)) {
			$arOrderIDs = $orderId;
		}
		
		if (!$arOrderIDs) return [];
		
		$where = "op.ORDER_ID IN ('" . implode("','", $arOrderIDs) . "')";
		if ($productId) {
			$where .= " AND op.PRODUCT_ID = '" . $productId . "' ";
		}
		
        $sql = "SELECT op.*, u.LOGIN, u.NAME, u.LAST_NAME 
                FROM " . self::TABLE_NAME . " op
                LEFT JOIN b_user u ON op.USER_ID = u.ID
                WHERE {$where}
                GROUP BY op.ORDER_ID, op.PRODUCT_ID, op.NUMBER_ID";
        
        $result = $DB->Query($sql);
        $history = [];
        
        while ($item = $result->Fetch()) {
            $history[] = $item;
        }
        
        return $history;
    }
    /**
     * Получает количество печатей для заказа
     */
    public static function getPrintCount($orderId)
    {
        global $DB;
        
        $sql = "SELECT COUNT(*) as CNT FROM " . self::TABLE_NAME . " 
                WHERE ORDER_ID = " . (int)$orderId;
        
        $result = $DB->Query($sql);
        $data = $result->Fetch();
        
        return $data['CNT'];
    }
	
	public static function deletePrintRecord($recordId)
	{
		global $DB, $USER;
		
		$recordId = (int)$recordId;
		if ($recordId <= 0) {
			return false;
		}
		
		$existingRecord = $DB->Query("SELECT ID FROM " . self::TABLE_NAME . " WHERE ID = " . $recordId);
		if (!$existingRecord->Fetch()) {
			return false;
		}
		
		$result = $DB->Query("DELETE FROM " . self::TABLE_NAME . " WHERE ID = " . $recordId);
		
		if ($result) {
			
		}
		
		return $result !== false;
	}
	
	public static function deleteVeiwRecord($recordId)
	{
		global $DB, $USER;
		
		$recordId = (int)$recordId;
		if ($recordId <= 0) {
			return false;
		}
		
		$existingRecord = $DB->Query("SELECT ID FROM " . self::TABLE_VIEW_NAME . " WHERE ID = " . $recordId);
		if (!$existingRecord->Fetch()) {
			return false;
		}
		
		$result = $DB->Query("DELETE FROM " . self::TABLE_VIEW_NAME . " WHERE ID = " . $recordId);
		
		if ($result) {
			
		}
		
		return $result !== false;
	}
}