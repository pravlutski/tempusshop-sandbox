<? if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die(); ?>

<h1>Заполнение коробов для поставки <?= htmlspecialcharsbx($arResult['SELECTED_SUPPLY']['name']) ?></h1>

<div id="alerts-container"></div>

<div class="scan-info">
    <div class="scan-status">
        <span class="status-text">Готов к сканированию</span>
        <span class="scan-sound">🔊</span>
    </div>
    <div class="total-items">
		<span class="total-items-count">Всего артикулов: 0</span>
		<span class="total-items-count-sum">Всего товаров: 0</span>
    </div>
</div>

<div class="boxes-container">
    <? foreach ($arResult['BOXES'] as $boxNumber => $boxItems): ?>
	<?$totalQuantity = array_sum(array_column($boxItems, 'quantity'));?>
    <div class="box" data-box-number="<?= $boxNumber ?>">
        <div class="box-header">
            <h3>Короб #<?= $boxNumber ?></h3>
			<div class="box-items-count-wrap">
				<span class="box-items-count">Артикулов: <?= count($boxItems) ?></span>
				<span class="box-items-count-sum">Товаров: <?= $totalQuantity ?></span>
			</div>
        </div>
        
        <div class="scan-area">
            <input type="text" 
                   class="barcode-input" 
                   placeholder="Сканирование"
                   data-box-number="<?= $boxNumber ?>"
                   autocomplete="off">
            <div class="scan-hint">Enter - добавить товар</div>
        </div>
        
        <div class="box-items">
            <? if (!empty($boxItems)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Артикул</th>
                        <th>Кол-во</th>
                        <th>Штрихкод</th>
						<th>Действие</th>
                    </tr>
                </thead>
                <tbody>
                    <? foreach ($boxItems as $item): ?>
                    <tr data-barcode="<?= htmlspecialcharsbx($item['barcode']) ?>">
                        <td class="article"><?= htmlspecialcharsbx($item['article'] ?? 'N/A') ?></td>
                        <td class="quantity"><?= (int)$item['quantity'] ?></td>
                        <td class="barcode"><?= htmlspecialcharsbx($item['barcode']) ?></td>
                        <td class="actions">
                            <button class="btn-remove" 
                                    data-box-number="<?= $boxNumber ?>"
                                    data-barcode="<?= htmlspecialcharsbx($item['barcode']) ?>"
                                    title="Удалить товар">
                                ✕
                            </button>
                        </td>
                    </tr>
                    <? endforeach; ?>
                </tbody>
            </table>
            <? else: ?>
            <div class="empty-box">
                <p>Короб пустой</p>
                <p>Отсканируйте штрихкоды товаров</p>
            </div>
            <? endif; ?>
        </div>
		<button class="btn-box-remove" data-box-number="<?= $boxNumber ?>" title="Удалить короб">✕</button>
    </div>
    <? endforeach; ?>
</div>

<div class="actions">
    <button id="send-boxes" class="btn btn-primary">Отправить данные в <?= htmlspecialcharsbx($arResult['MARKETPLACE'] == 'ozon' ? 'OZON' : 'Wildberries') ?></button>
    <button id="print-barcodes" class="btn btn-print" <?if($_SESSION['FBO_DATA']['button_print_href']):?>data-href="<?=$_SESSION['FBO_DATA']['button_print_href']?>"<?endif?> <?if(!$_SESSION['FBO_DATA']['button_print']):?>disabled<?endif?>>Печать ШК</button>
	<button id="back-to-step1" class="btn btn-secondary">Назад к выбору</button>
</div>