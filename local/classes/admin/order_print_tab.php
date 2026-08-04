<?php
//require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");

use Bitrix\Main\Context;
use Bitrix\Main\Loader;

global $USER;
global $APPLICATION;

Loader::includeModule('sale');

$request = Context::getCurrent()->getRequest();
$orderId = (int)$request->get('ID');

// Проверяем права доступа
/*if (!$USER->IsAdmin() && !$USER->CanDoOperation('view_other_settings')) {
    $APPLICATION->AuthForm("Доступ запрещен");
}
*/
if (!class_exists('OrderPrintManager')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/local/classes/OrderPrintManager.php';
}

if ($request->get('action') === 'delete_record' && check_bitrix_sessid()) {
    $recordId = (int)$request->get('record_id');
    $recordType = $request->get('record_type');
    
    if ($USER->IsAdmin()) {
		if (!$recordType || !in_array($recordType, ['scan', 'view'])) {
			$recordType = 'scan';
		}
		
		if ($recordType == 'scan') {
			if (OrderPrintManager::deletePrintRecord($recordId)) {
				LocalRedirect($APPLICATION->GetCurPageParam('', array('action', 'record_id', 'sessid')));
			} else {
				ShowError('Ошибка при удалении записи');
			}
		} else {
			if (OrderPrintManager::deleteVeiwRecord($recordId)) {
				LocalRedirect($APPLICATION->GetCurPageParam('', array('action', 'record_id', 'sessid')));
			} else {
				ShowError('Ошибка при удалении записи');
			}
		}

    }
}

// Получаем историю печати
$printHistory = OrderPrintManager::getPrintHistory($orderId);
$printCount = OrderPrintManager::getPrintCount($orderId);
//prent($printHistory);
$viewHistory = OrderPrintManager::getViewHistory($orderId);
?>
<div class="order-print-tab">

    <?php if (!empty($printHistory)): ?>
    <div class="adm-info-message">
        <h3>История печати стикеров</h3>
        <p>Всего распечатано: <strong><?= $printCount ?> раз(а)</strong></p>
    </div>
    <div class="adm-list-table-wrap">
        <table class="adm-list-table">
            <thead>
                <tr class="adm-list-table-header">
                    <th>Дата и время</th>
                    <th>Пользователь</th>
                    <th>Тип сканирования</th>
                    <th>ID товара</th>
                    <th>Номер</th>
					<th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($printHistory as $item): ?>
                <tr>
                    <td><?= FormatDate("d.m.Y H:i:s", strtotime($item['TIMESTAMP'])) ?></td>
                    <td>
                        <?= htmlspecialcharsbx($item['NAME'] . ' ' . $item['LAST_NAME']) ?>
                        <?php if ($item['LOGIN']): ?>
                        (<?= htmlspecialcharsbx($item['LOGIN']) ?>)
                        <?php endif; ?>
                    </td>
                    <td><?= $item['TYPE_SCAN'] ?></td>
                    <td><?= $item['PRODUCT_ID'] ?></td>
                    <td><?= $item['NUMBER_ID'] ?></td>
                    <td>
                        <button class="adm-btn adm-btn-red delete-record-btn" 
                                data-record-id="<?= $item['ID'] ?>"
								data-record-type="scan"
                                title="Удалить запись">
                            Удалить
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="adm-info-message">
        Стикеры для этого заказа еще не печатались
    </div>
    <?php endif; ?>
    <?php if (!empty($viewHistory)): ?>

    <div class="adm-info-message">
        <h3>История просмотренных</h3>
    </div>
    <div class="adm-list-table-wrap">
        <table class="adm-list-table">
            <thead>
                <tr class="adm-list-table-header">
                    <th>Дата и время</th>
                    <th>Пользователь</th>
                    <th>ID товара</th>
					<th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($viewHistory as $item): ?>
                <tr>
                    <td><?= FormatDate("d.m.Y H:i:s", strtotime($item['TIMESTAMP'])) ?></td>
                    <td>
                        <?= htmlspecialcharsbx($item['NAME'] . ' ' . $item['LAST_NAME']) ?>
                        <?php if ($item['LOGIN']): ?>
                        (<?= htmlspecialcharsbx($item['LOGIN']) ?>)
                        <?php endif; ?>
                    </td>
                    <td><?= $item['PRODUCT_ID'] ?></td>
                    <td>
                        <button class="adm-btn adm-btn-red delete-record-btn" 
                                data-record-id="<?= $item['ID'] ?>"
                                data-record-type="view"
                                title="Удалить запись">
                            Удалить
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="adm-info-message">
        Стикеры для этого заказа не в просмотренных
    </div>
    <?php endif; ?>
</div>
<div id="confirmDeleteModal" style="display: none;" class="adm-modal">
    <div class="adm-modal-dialog">
        <div class="adm-modal-header">
            <div class="adm-modal-title">Подтверждение удаления</div>
        </div>
        <div class="adm-modal-content">
            <p>Вы уверены, что хотите удалить эту запись из истории печати?</p>
        </div>
        <div class="adm-modal-footer">
            <button class="adm-btn adm-btn-primary" id="confirmDeleteBtn">Удалить</button>
            <button class="adm-btn" id="cancelDeleteBtn">Отмена</button>
        </div>
    </div>
</div>
<style>
.order-print-tab {
    padding: 20px;
}
.adm-info-message{
    width: 100%;
}
.adm-btn-green {
    background: #4caf50 !important;
    border-color: #4caf50 !important;
    color: white !important;
}
.adm-btn-green:hover {
    background: #45a049 !important;
}
.adm-btn-red {
    background: #f44336 !important;
    border-color: #f44336 !important;
    color: white !important;
}
.adm-btn-red:hover {
    background: #d32f2f !important;
}
.adm-list-table{
    text-align: left;
}

/* Стили для модального окна */
.adm-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
}
.adm-modal-dialog {
    background: white;
    border-radius: 4px;
    min-width: 400px;
    max-width: 500px;
}
.adm-modal-header {
    padding: 15px 20px;
    border-bottom: 1px solid #e0e0e0;
}
.adm-modal-title {
    font-weight: bold;
    font-size: 16px;
}
.adm-modal-content {
    padding: 20px;
}
.adm-modal-footer {
    padding: 15px 20px;
    border-top: 1px solid #e0e0e0;
    text-align: right;
}
.adm-modal-footer .adm-btn {
    margin-left: 10px;
}
.delete-record-btn{
    color: black !important;
    font-size: 12px;
    font-weight: normal;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    let recordToDelete = null;
    let recordToDeleteType = null;
    const modal = document.getElementById('confirmDeleteModal');
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    const cancelBtn = document.getElementById('cancelDeleteBtn');
    
    // Обработчики для кнопок удаления
    document.querySelectorAll('.delete-record-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            recordToDelete = this.getAttribute('data-record-id');
            recordToDeleteType = this.getAttribute('data-record-type') ?? 'scan';
            modal.style.display = 'flex';
        });
    });
    
    // Подтверждение удаления
    confirmBtn.addEventListener('click', function() {
        if (recordToDelete && recordToDeleteType) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = window.location.href;
            
            const recordIdInput = document.createElement('input');
            recordIdInput.type = 'hidden';
            recordIdInput.name = 'record_id';
            recordIdInput.value = recordToDelete;
			
            const recordTypeInput = document.createElement('input');
            recordTypeInput.type = 'hidden';
            recordTypeInput.name = 'record_type';
            recordTypeInput.value = recordToDeleteType;
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete_record';
            
            const sessidInput = document.createElement('input');
            sessidInput.type = 'hidden';
            sessidInput.name = 'sessid';
            sessidInput.value = BX.bitrix_sessid();
            
            form.appendChild(recordIdInput);
            form.appendChild(recordTypeInput);
            form.appendChild(actionInput);
            form.appendChild(sessidInput);
            
            document.body.appendChild(form);
            form.submit();
        }
    });
    
    // Отмена удаления
    cancelBtn.addEventListener('click', function() {
        modal.style.display = 'none';
        recordToDelete = null;
    });
    
    // Закрытие по клику вне модального окна
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.style.display = 'none';
            recordToDelete = null;
        }
    });
});
</script>
<?php
//require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");