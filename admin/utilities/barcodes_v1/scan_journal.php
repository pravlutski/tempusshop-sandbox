<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetPageProperty("description", "Журнал сканирования");
$APPLICATION->SetPageProperty("title", "Журнал сканирования");

// Подключаем необходимые классы
use Bitrix\Main\Application;
global $DB;

if (!class_exists('OrderService')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/local/classes/OrderService.php';
}
?>

<style>
    * {
        box-sizing: border-box;
    }

    .scan-journal {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, 'Fira Sans', sans-serif;
        background: #f5f5f5;
        min-height: 100vh;
        padding: 20px;
        margin: -20px;
    }

    /* Заголовок */
    .page-header {
        background: white;
        padding: 16px 24px;
        border-bottom: 1px solid #e0e0e0;
        margin-bottom: 20px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }

    .page-title {
        font-size: 20px;
        font-weight: 400;
        color: #2c3e50;
        margin: 0;
        letter-spacing: 0.3px;
    }

    .page-title:before {
        margin-right: 10px;
        font-size: 18px;
        opacity: 0.7;
    }

    /* Контейнер с таблицей */
    .table-container {
        background: white;
        border-radius: 4px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        overflow: hidden;
    }

    /* Таблица */
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }

    .data-table th {
        background: #f8f9fa;
        color: #495057;
        font-weight: 500;
        padding: 14px 16px;
        text-align: left;
        border-bottom: 2px solid #dee2e6;
        white-space: nowrap;
        text-transform: uppercase;
        font-size: 11px;
        letter-spacing: 0.3px;
    }

    .data-table td {
        padding: 12px 16px;
        border-bottom: 1px solid #e9ecef;
        color: #212529;
        vertical-align: middle;
    }

    .data-table tbody tr {
        transition: background-color 0.1s ease;
    }

    .data-table tbody tr:hover {
        background-color: #f8f9fc;
    }

    .data-table tbody tr:last-child td {
        border-bottom: none;
    }

    /* Ссылка на заказ */
    .order-link {
        color: #0066c0;
        text-decoration: none;
        font-weight: 500;
    }

    .order-link:hover {
        text-decoration: underline;
        color: #004b8f;
    }

    .order-number {
        font-weight: 500;
    }

    /* ID модели */
    .product-id {
        font-family: 'Consolas', 'Monaco', monospace;
        background: #f1f3f5;
        padding: 4px 8px;
        border-radius: 2px;
        font-size: 12px;
        color: #495057;
    }

    /* Дата */
    .date-cell {
        font-family: 'Consolas', 'Monaco', monospace;
        color: #495057;
        white-space: nowrap;
    }

    /* Пользователь */
    .user-cell {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .user-initials {
        width: 28px;
        height: 28px;
        background: #e9ecef;
        color: #495057;
        border-radius: 2px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: 500;
        text-transform: uppercase;
    }

    .user-name {
        color: #212529;
    }

    /* Кнопка удаления */
    .delete-btn {
        background: none;
        border: 1px solid #dc3545;
        color: #dc3545;
        padding: 6px 14px;
        border-radius: 2px;
        font-size: 12px;
        cursor: pointer;
        transition: all 0.1s ease;
        font-weight: 400;
    }

    .delete-btn:hover {
        background: #dc3545;
        color: white;
    }

    .delete-btn:active {
        opacity: 0.8;
    }

    /* Пустое состояние */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: white;
        border-radius: 4px;
    }

    .empty-icon {
        font-size: 48px;
        opacity: 0.3;
        margin-bottom: 16px;
    }

    .empty-title {
        font-size: 16px;
        font-weight: 400;
        color: #6c757d;
        margin: 0 0 8px 0;
    }

    .empty-desc {
        font-size: 13px;
        color: #adb5bd;
        margin: 0;
    }

    /* Статус заказа (можно добавить если нужно) */
    .order-status {
        display: inline-block;
        padding: 3px 8px;
        background: #e9ecef;
        border-radius: 2px;
        font-size: 11px;
        color: #495057;
    }

    /* Тосты уведомлений */
    .toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
    }

    .toast {
        background: white;
        border-left: 3px solid;
        padding: 12px 20px;
        margin-bottom: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.12);
        font-size: 13px;
        min-width: 280px;
        display: flex;
        align-items: center;
        gap: 10px;
        animation: slideIn 0.2s ease;
    }

    .toast.success {
        border-left-color: #28a745;
    }

    .toast.error {
        border-left-color: #dc3545;
    }

    .toast-icon {
        font-size: 16px;
    }

    .toast.success .toast-icon {
        color: #28a745;
    }

    .toast.error .toast-icon {
        color: #dc3545;
    }

    .toast-message {
        color: #212529;
        flex: 1;
    }

    .toast-close {
        background: none;
        border: none;
        color: #adb5bd;
        cursor: pointer;
        font-size: 16px;
        padding: 0 4px;
    }

    .toast-close:hover {
        color: #495057;
    }

    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    /* Оверлей загрузки */
    .loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255,255,255,0.7);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 9998;
    }

    .loading-spinner {
        width: 30px;
        height: 30px;
        border: 2px solid #e9ecef;
        border-top-color: #0066c0;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    /* Дополнительная информация (можно добавить сверху таблицы) */
    .table-info {
        background: #f8f9fa;
        padding: 10px 16px;
        border-bottom: 1px solid #e9ecef;
        font-size: 12px;
        color: #6c757d;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .total-count {
        font-weight: 500;
        color: #212529;
    }

    /* Адаптивность */
    @media (max-width: 768px) {
        .data-table {
            font-size: 12px;
        }
        
        .data-table th,
        .data-table td {
            padding: 10px 12px;
        }
        
        .delete-btn {
            padding: 4px 10px;
        }
    }
</style>

<?
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    global $APPLICATION;
    
    $APPLICATION->RestartBuffer();
    
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'delete' && isset($_POST['record_id'])) {
        $recordId = (int)$_POST['record_id'];
        
        try {
            if (!class_exists('OrderPrintManager')) {
                require_once $_SERVER['DOCUMENT_ROOT'] . '/local/classes/OrderPrintManager.php';
            }
            
            OrderPrintManager::deleteVeiwRecord($recordId);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        die();
    }
}

$website = 's1';
$arFilter = array(
    //"LID" => $website,
    "STATUS_ID" => array("SE", "TA", "CO", "CL"),
    "!CANCELED" => "Y",
);

$objService = new OrderService;
$objService->getPropOrderFlg = false;
$orders = $objService->getOrderCache(array("DATE_INSERT" => "DESC"), $arFilter);

$orderIds = $arOrder = [];
foreach ($orders as $order) {
    $orderIds[] = $order['ID'];
	$arOrder[$order['ID']] = $order;
}

$arTradePlatform = [];

if (is_array($orderIds) && count($orderIds) > 0) {
	$strSql = "SELECT ORDER_ID, TRADING_PLATFORM_ID FROM b_sale_tp_order WHERE ORDER_ID IN ('" . implode("','", $orderIds) . "')";
	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	
	while ($row = $results->Fetch()) {
		$arTradePlatform[$row["ORDER_ID"]] = $row["TRADING_PLATFORM_ID"];
	}
}

$strSql = "SELECT * FROM b_sale_tp";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);

while ($row = $results->Fetch()) {
	$arResult["TRADING_LIST"][$row["ID"]] = $row["NAME"];
}
		
$connection = Application::getConnection();
$scans = array();
$userIds = array();

if (!empty($orderIds)) {
    $sql = "SELECT s.*, e.NAME as PRODUCT_NAME 
            FROM ci_order_print_scan s
            LEFT JOIN b_iblock_element e ON s.PRODUCT_ID = e.ID
            WHERE s.ORDER_ID IN (" . implode(',', $orderIds) . ") 
            ORDER BY s.TIMESTAMP ASC";
    
    $records = $connection->query($sql);
    while ($record = $records->fetch()) {
        $scans[] = $record;
        if (!in_array($record['USER_ID'], $userIds)) {
            $userIds[] = $record['USER_ID'];
        }
    }
}

$users = array();
if (!empty($userIds)) {
    $rsUsers = CUser::GetList(
        ($by = "ID"), 
        ($order = "ASC"),
        array("ID" => implode('|', $userIds))
    );
    
    while ($arUser = $rsUsers->Fetch()) {
        $userName = trim($arUser['NAME'] . ' ' . $arUser['LAST_NAME']);
        if (empty($userName)) {
            $userName = $arUser['LOGIN'];
        }
        
        $userInitials = mb_substr($arUser['NAME'], 0, 1) . mb_substr($arUser['LAST_NAME'], 0, 1);
        if (empty(trim($userInitials))) {
            $userInitials = mb_substr($arUser['LOGIN'], 0, 2);
        }
        
        $users[$arUser['ID']] = array(
            'NAME' => $userName,
            'INITIALS' => strtoupper($userInitials)
        );
    }
}
?>

<div class="scan-journal">
    <div class="page-header">
        <h1 class="page-title">Журнал сканирования</h1>
    </div>
    
    <? if (empty($scans)): ?>
        <div class="empty-state">
            <div class="empty-icon">📭</div>
            <h3 class="empty-title">Нет записей о сканировании</h3>
            <p class="empty-desc">За выбранный период сканирования отсутствуют</p>
        </div>
    <? else: ?>
        <div class="table-container">
            <div class="table-info">
                <span>Всего записей: <span class="total-count"><?= count($scans) ?></span></span>
            </div>
            
            <table class="data-table" id="scanTable">
                <thead>
                    <tr>
                        <th>№ заказа</th>
                        <th>Сайт</th>
                        <th>Источник</th>
                        <th>Артикул</th>
                        <th>Дата сканирования</th>
                        <th>Пользователь</th>
                        <th style="width: 100px;">Действия</th>
                    </tr>
                </thead>
				<tbody>
					<? foreach ($scans as $scan): 
						$order = $arOrder[$scan['ORDER_ID']];
						$tradingPlatformId = $arTradePlatform[$order["ID"]] ?? '';
						
						$userName = $users[$scan['USER_ID']]['NAME'] ?? 'Неизвестно';
						$userInitials = $users[$scan['USER_ID']]['INITIALS'] ?? '??';
						
						$dateTime = new DateTime($scan['TIMESTAMP']);
						$formattedDate = $dateTime->format('d.m.Y H:i:s');
						
						$productDisplay = !empty($scan['PRODUCT_NAME']) 
							? htmlspecialchars($scan['PRODUCT_NAME']) 
							: '<span style="color: #999;">Товар не найден (ID: ' . $scan['PRODUCT_ID'] . ')</span>';
					?>
						<tr id="scan_row_<?= $scan['ID'] ?>">
							<td>
								<a href="/bitrix/admin/sale_order_view.php?ID=<?= $order['ID'] ?>" 
								   class="order-link" 
								   target="_blank">
									№ <?= $order['ORDER_ID'] ?>
								</a>
							</td>
							<td>
								<?= $order['LID'] ?>
							</td>
							<td>
								<?= $arResult["TRADING_LIST"][$tradingPlatformId] ?>
							</td>
							<td>
								<div style="display: flex; flex-direction: column;">
									<span class="product-id"><?= htmlspecialchars($scan['PRODUCT_ID']) ?></span>
									<span style="font-size: 12px; color: #666; margin-top: 2px;">
										<?= $productDisplay ?>
									</span>
								</div>
							</td>
							<td class="date-cell"><?= $formattedDate ?></td>
							<td>
								<div class="user-cell">
									<span class="user-initials"><?= htmlspecialchars($userInitials) ?></span>
									<span class="user-name"><?= htmlspecialchars($userName) ?></span>
								</div>
							</td>
							<td>
								<button class="delete-btn" onclick="deleteRecord(<?= $scan['ID'] ?>)">
									Удалить
								</button>
							</td>
						</tr>
					<? endforeach; ?>
				</tbody>
            </table>
        </div>
    <? endif; ?>
</div>

<!-- Loading overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
</div>

<!-- Toast container -->
<div class="toast-container" id="toastContainer"></div>

<script>
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    const toastId = 'toast_' + Date.now();
    
    const toast = document.createElement('div');
    toast.className = 'toast ' + type;
    toast.id = toastId;
    toast.innerHTML = `
        <span class="toast-icon">${type === 'success' ? '✓' : '✕'}</span>
        <span class="toast-message">${message}</span>
        <button class="toast-close" onclick="this.parentElement.remove()">×</button>
    `;
    
    container.appendChild(toast);
    
    setTimeout(() => {
        const toastElement = document.getElementById(toastId);
        if (toastElement) {
            toastElement.style.transition = 'opacity 0.2s';
            toastElement.style.opacity = '0';
            setTimeout(() => toastElement.remove(), 200);
        }
    }, 4000);
}

function showLoading() {
    document.getElementById('loadingOverlay').style.display = 'flex';
}

function hideLoading() {
    document.getElementById('loadingOverlay').style.display = 'none';
}

function deleteRecord(recordId) {
    if (!confirm('Удалить запись о сканировании?')) {
        return;
    }
    
    showLoading();
    
    var formData = new FormData();
    formData.append('action', 'delete');
    formData.append('record_id', recordId);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        
        if (data.success) {
            const row = document.getElementById('scan_row_' + recordId);
            if (row) {
                row.style.transition = 'opacity 0.2s';
                row.style.opacity = '0';
                
                setTimeout(() => {
                    row.remove();
                    
                    const tbody = document.querySelector('#scanTable tbody');
                    if (tbody && tbody.children.length === 0) {
                        location.reload();
                    } else {
                        const totalSpan = document.querySelector('.total-count');
                        if (totalSpan) {
                            totalSpan.textContent = tbody.children.length;
                        }
                    }
                }, 200);
            }
            
            showToast('Запись удалена', 'success');
        } else {
            showToast(data.error || 'Ошибка при удалении', 'error');
        }
    })
    .catch(error => {
        hideLoading();
        showToast('Ошибка соединения', 'error');
        console.error('Error:', error);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Добавляем фокус на первую строку для клавиатурной навигации
    const firstRow = document.querySelector('.data-table tbody tr');
    if (firstRow) {
        firstRow.tabIndex = 0;
    }
});
</script>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>