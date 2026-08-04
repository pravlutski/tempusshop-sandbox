<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

/** @var array $arParams */
/** @var array $arResult */
/** @global CMain $APPLICATION */
/** @global CUser $USER */
/** @global CDatabase $DB */
/** @var CBitrixComponentTemplate $this */
/** @var string $templateName */
/** @var string $templateFile */
/** @var string $templateFolder */
/** @var string $componentPath */
/** @var CBitrixComponent $component */

//$this->addExternalCss(SITE_TEMPLATE_PATH . '/css/main.css');
?>
<div class="returns-mp-wrapper">
    <div class="returns-mp-container">
        <h1 class="returns-mp-title">Возвраты покупателей</h1>
		<div class="returns-mp-tabs">
			<button type="button" class="tab-btn active" data-tab="s1">RU</button>
			<button type="button" class="tab-btn" data-tab="s2">BY</button>
		</div>
        <div class="returns-mp-search-section">
            <div class="search-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="orderNumber">Номер заказа</label>
                        <input type="text" id="orderNumber" name="orderNumber" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="barcode">ШК товара</label>
                        <input type="text" id="barcode" name="barcode" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="article">Артикул</label>
                        <input type="text" id="article" name="article" class="form-control">
                        <input type="hidden" id="product_id" name="product_id">
						<button id="saveBarcodeBtn" class="btn btn-secondary">Сохранить ШК</button>
                        <div class="validation-status"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="salesChannel">Канал продаж</label>
                        <select id="salesChannel" name="salesChannel" class="form-control"></select>
                    </div>
                </div>
                
                <button type="button" id="findButton" class="btn btn-primary">Найти</button>
            </div>
        </div>

        <div class="returns-mp-results-section" style="display: none;">
			<div class="order-info">
				<h4>Результат поиска</h4>
				<div id="orderDetails"></div>
			</div>
			
			<div class="params-return" style="width: 150px;">
				<label for="warehouse">Склад:</label>
				<select id="warehouse" class="form-control"></select>
			</div>
			
			<div class="params-return">
				<label for="commentReturn">Комментарий:</label>
				<input id="commentReturn" name="commentReturn" class="form-control" value="">
			</div>
            <button type="button" id="processButton" class="btn btn-success">Провести</button>
			<div class='pre-submit-text'>
				<span>Заказ будет переведен в статус</span>
				<select id="orderStatusSelect" class="form-control" style="width: auto; display: inline-block; margin-left: 5px;">
					<option value="NZ">Отказ на этапе доставки</option>
					<option value="F">Выполнен</option>
				</select>
			</div>
        </div>

        <div class="returns-mp-log-section">
            <h3>История действий</h3>
            <div class="log-entries"></div>
        </div>
    </div>
</div>
<!-- Кнопка открытия настроек -->
<button type="button" id="settingsButton" class="btn btn-settings">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="3"></circle>
        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
    </svg>
    Настройки
</button>

<!-- Модальное окно настроек -->
<div id="settingsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Настройки возвратов</h2>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body">
            <form id="settingsForm">
                <div class="settings-section">
                    <h3>Настройки</h3>
                    <div class="form-group">
						<table class="table">
							<thead>
								<tr>
									<th style="width: 200px;">Канал продаж</th>
									<th style="">ID пользователя</th>
								</tr>
							</thead>
							<tbody id="settingsSalesChannels"></tbody>
						</table>
                    </div>
                </div>
                
                <div class="settings-section">
                    <h3>Дополнительные настройки</h3>
                    <?/*<div class="form-group checkbox-group">
                        <input type="checkbox" id="autoCreateReturn" name="autoCreateReturn">
                        <label for="autoCreateReturn">Автоматически создавать возврат после поиска</label>
                    </div>
                    <div class="form-group checkbox-group">
                        <input type="checkbox" id="enableNotifications" name="enableNotifications" checked>
                        <label for="enableNotifications">Включить уведомления</label>
                    </div>
                    <div class="form-group">
                        <label for="logRetentionDays">Хранить логи (дней)</label>
                        <input type="number" id="logRetentionDays" name="logRetentionDays" class="form-control" value="30" min="1" max="365">
                    </div>*/?>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" id="saveSettingsBtn" class="btn btn-primary">Сохранить</button>
            <button type="button" id="cancelSettingsBtn" class="btn btn-default">Отмена</button>
        </div>
    </div>
</div>