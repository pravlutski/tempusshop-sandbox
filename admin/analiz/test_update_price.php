<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Генерация csv Название модели - производитель - XML_ID");

use Bitrix\Main\Loader;

?>
<div class="content wrap">
  
<?
$service = PanelManager::getPriceManager();
$arPrices = $service->getTypePrices(); 
//prent($arPrices);
$arPriceID = array_column($arPrices, 'id');
//prent($arPriceID);

$selectedPrice = isset($_REQUEST['price_type']) && in_array($_REQUEST['price_type'], $arPriceID) 
    ? $_REQUEST['price_type'] 
    : 'RU';

CModule::IncludeModule("crm_courier");
CModule::IncludeModule("intaro.retailcrm");
CModule::IncludeModule("sale");
CModule::IncludeModule('maxyss.wb');
CModule::IncludeModule('maxyss.ozon');
CModule::IncludeModule('yandex.market');
CModule::IncludeModule('aspro.smartseo');

Loader::includeModule('panel.manager');

$result = [];
$hasResults = false;

if (isset($_REQUEST['update_prices']) && $_REQUEST['update_prices'] == 'Y') {
	
	
    //$result = $service->updatePrices($selectedPrice);
    $servicePrice = $service->updatePriceService($selectedPrice, 'debug');
	

	if ($_REQUEST['article']) {
		$tmpArticle = explode("\r\n", $_REQUEST['article']);
		//prent($tmpArticle);die;
		//prent($_REQUEST['article']);
		//$tmpArticle = trim($_REQUEST['article']);
		$filter = [
			'article' => $tmpArticle
		];
		$servicePrice->market->setPriceFilter($filter);
		
		
	}

	if ($_REQUEST['force_priority_supplier'] && $_REQUEST['force_priority_supplier'] == 'Y') {
		$servicePrice->market->setOption('force_priority_supplier', true);
	}

	if ($_REQUEST['only_view']) {
		$servicePrice->market->setOption($_REQUEST['only_view'], true);
	}
	if ($_REQUEST['skip_market_required']) {
		$servicePrice->market->setOption('market_required', false);
	}
	
    $result = $servicePrice->updatePrices();

	//$servicePrice->market->setOption($_REQUEST['skip_check_reserve'], true);
    //$result2 = $servicePrice->getMinPurchasePrice();
	//prent($result2);
	$hasResults = true;
}
?>
<?if($result['error']):?>
	<p style="color: red;"><?=$result['error']?></p>
<?endif?>
<form method="post" action="" style="margin-bottom: 20px; padding: 15px; background-color: #f5f5f5; border-radius: 5px;">
    <div style="display: flex; align-items: center; gap: 10px;">
        <label for="price_type" style="font-weight: bold;">Выберите тип цены:</label>
        <select name="price_type" id="price_type" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
            <?php foreach ($arPrices as $price): ?>
                <option value="<?= $price['id'] ?>" <?= ($selectedPrice == $price['id']) ? 'selected' : '' ?>>
                    <?= $price['name'] ?>
                </option>
            <?php endforeach; ?>
        </select>
		<span>Список артикулов</span>
        <textarea name="article" rows="5" cols="33"><?=$_REQUEST['article']?></textarea>
        <input type="hidden" name="update_prices" value="Y">
    </div>
	<div style="align-items: center; gap: 10px;">
		<?/*<select name="only_view" id="only_view" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
			<option value="">-- фильтр --</option>
			<option value="only_rrc" <?= ($_REQUEST['only_view'] == 'only_rrc') ? 'selected' : '' ?>>Только РРЦ</option>
			<option value="only_cp" <?= ($_REQUEST['only_view'] == 'only_cp') ? 'selected' : '' ?>>Только КЦ</option>
        </select>*/?>
		<p><input type="checkbox" name="force_priority_supplier" value="Y" <?if($_REQUEST['force_priority_supplier'] == 'Y'):?>checked<?endif?>>Учитывать приоритет поставщика</p>
		<p><input type="checkbox" name="skip_market_required" value="Y" <?if($_REQUEST['skip_market_required'] == 'Y'):?>checked<?endif?>>Пропускать галку обязательные цены конкурентов</p>
		<button type="submit" style="padding: 8px 20px; background-color: #0066cc; color: white; border: none; border-radius: 4px; cursor: pointer;">
            Обновить цены
        </button>
    </div>
</form>

<?
if ($hasResults && !empty($result)) {
    echo '<div style="margin-bottom: 20px; padding: 15px; background-color: #e8f4fd; border-radius: 5px;">';
    echo '<h3>Результаты обновления цен</h3>';
    echo '<p><strong>Всего товаров:</strong> ' . (isset($result['total']) ? $result['total'] : 0) . '</p>';
    echo '<p><strong>Обновлено товаров:</strong> ' . (isset($result['updated']) ? $result['updated'] : 0) . '</p>';
    echo '<p><strong>Тип цен:</strong> ' . htmlspecialchars($selectedPrice) . '</p>';
    echo '</div>';
    //prent($result);
    if (isset($result['logs']) && is_array($result['logs']) && count($result['logs']) > 0) {
        ?>
        <div style="overflow-x: auto;">
            <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 14px;" class="sortable">
                <thead style="background-color: #f2f2f2;">
                    <tr>
                        <th style="text-align: left; padding: 12px;">Артикул</th>
                        <th style="text-align: right; padding: 12px;">Старая цена</th>
                        <th style="text-align: right; padding: 12px;">Новая цена</th>
                        <th style="text-align: right; padding: 12px;" class="numeric">Изменение</th>
                        <th style="text-align: right; padding: 12px;" class="numeric">% Изменения</th>
                        <th style="text-align: left; padding: 12px;">Детали</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($result['logs'] as $log) {
                        $oldPrice = floatval($log['old_price']);
                        $newPrice = floatval($log['new_price']);
                        
                        $change = $newPrice - $oldPrice;
                        $percentChange = 0;
                        
                        if ($oldPrice > 0) {
                            $percentChange = abs(($change / $oldPrice) * 100);
                        } elseif ($newPrice > 0) {
                            $percentChange = 100;
                        }
                        
                        $rowStyle = '';
                        if ($percentChange > 20) {
                            $rowStyle = 'background-color: #ffdddd;';
                        } elseif ($percentChange > 10) {
                            $rowStyle = 'background-color: #fff8dd;';
                        }
                        
                        $formattedOldPrice = number_format($oldPrice, 2, '.', ' ');
                        $formattedNewPrice = number_format($newPrice, 2, '.', ' ');
                        $formattedChange = number_format($change, 2, '.', ' ');
                        $formattedPercent = number_format($percentChange, 2, '.', ' ');
                        
                        $changeSign = ($change > 0) ? '+' : '';
                        $percentSign = ($change > 0) ? '+' : '-';
                        
                        $changeColor = ($change > 0) ? '#cc0000' : (($change < 0) ? '#006600' : '#666666');
                        
                        echo '<tr style="' . $rowStyle . '">';
                        echo '<td style="padding: 10px; font-weight: bold;">' . htmlspecialchars($log['article']) . '</td>';
                        echo '<td style="padding: 10px; text-align: right;">' . $formattedOldPrice . '</td>';
                        echo '<td style="padding: 10px; text-align: right; font-weight: bold;">' . $formattedNewPrice . '</td>';
                        echo '<td style="padding: 10px; text-align: right; color: ' . $changeColor . ';">' . $changeSign . $formattedChange . '</td>';
                        echo '<td style="padding: 10px; text-align: right; color: ' . $changeColor . ';">' . $percentSign . $formattedPercent . '%</td>';
                        echo '<td style="padding: 10px;">' . htmlspecialchars($log['detail_log']) . '</td>';
                        echo '</tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <?php
    } else {
        echo '<p style="padding: 15px; background-color: #f0f0f0; border-radius: 5px;">Нет данных для отображения.</p>';
    }
} elseif (isset($_REQUEST['update_prices'])) {
    echo '<p style="padding: 15px; background-color: #fff3cd; border-radius: 5px; color: #856404;">Не удалось получить результаты обновления цен.</p>';
}
?>

</div>
<style>
    .sortable th {
        cursor: pointer;
        user-select: none;
        position: relative;
        padding-right: 25px !important;
    }
    .sortable th:hover {
        background-color: #e0e0e0 !important;
    }
    .sortable th .sort-indicator {
        position: absolute;
        right: 8px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 12px;
        color: #999;
    }
    .sortable th.active .sort-indicator {
        color: #0066cc;
    }
    .sortable th.asc .sort-indicator::after {
        content: '▲';
    }
    .sortable th.desc .sort-indicator::after {
        content: '▼';
    }
    .sortable th:not(.asc):not(.desc) .sort-indicator::after {
        content: '⇅';
    }
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const table = document.querySelector('.sortable');
    if (!table) return;
    
    const headers = table.querySelectorAll('th');
    let sortDirection = {};
    
    headers.forEach((header, index) => {
        header.addEventListener('click', function() {
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            // Определяем направление сортировки
            if (!sortDirection[index]) {
                sortDirection[index] = 'asc';
            } else if (sortDirection[index] === 'asc') {
                sortDirection[index] = 'desc';
            } else {
                sortDirection[index] = 'asc';
            }
            
            // Сбрасываем индикаторы на всех заголовках
            headers.forEach(h => {
                h.classList.remove('active', 'asc', 'desc');
            });
            
            // Устанавливаем индикатор на текущем заголовке
            this.classList.add('active', sortDirection[index]);
            
            // Получаем тип данных колонки (число или строка)
            const isNumeric = this.classList.contains('numeric');
            
            // Сортировка
            rows.sort((rowA, rowB) => {
                const cellA = rowA.querySelectorAll('td')[index];
                const cellB = rowB.querySelectorAll('td')[index];
                
                let valA = cellA ? cellA.textContent.trim() : '';
                let valB = cellB ? cellB.textContent.trim() : '';
                
                // Удаляем пробелы в числах
                if (isNumeric) {
                    valA = valA.replace(/[^\d.-]/g, '');
                    valB = valB.replace(/[^\d.-]/g, '');
                }
                
                // Определяем тип и сравниваем
                if (isNumeric || (!isNaN(valA) && !isNaN(valB))) {
                    const numA = parseFloat(valA) || 0;
                    const numB = parseFloat(valB) || 0;
                    return sortDirection[index] === 'asc' ? numA - numB : numB - numA;
                } else {
                    return sortDirection[index] === 'asc' 
                        ? valA.localeCompare(valB, 'ru')
                        : valB.localeCompare(valA, 'ru');
                }
            });
            
            // Обновляем таблицу
            rows.forEach(row => tbody.appendChild(row));
        });
    });
});
</script>
<?
//$ar = CPanelPricelist::updateDateDelivery2();
//prent($ar['AVAILABILITY']);
?>        
<div style="overflow-x: auto;">
	<table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 14px;">
		<thead style="background-color: #f2f2f2;">
			<tr>
				<th style="text-align: left; padding: 12px;">Артикул</th>
				<th style="text-align: right; padding: 12px;">Было</th>
				<th style="text-align: right; padding: 12px;">Станет</th>
			</tr>
		</thead>
		<tbody>
		<?foreach($ar['_DELIVERY_JSON'] as $item):?>

			<?php
				echo '<tr style="' . $rowStyle . '">';
				echo '<td style="padding: 10px; font-weight: bold;">' . htmlspecialchars($item['ARTICLE']) . '</td>';
				echo '<td style="padding: 10px; font-weight: bold;"><pre>' . print_r($item['OLD'], true) . '</pre></td>';
				echo '<td style="padding: 10px; font-weight: bold;"><pre>' . print_r($item['NEW'], true) . '</pre></td>';
				echo '</tr>';
			?>
		<?endforeach?>
		</tbody>
	</table>
</div>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>