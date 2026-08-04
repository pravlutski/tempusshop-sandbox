<? if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die(); ?>

<h1>FBO Короба</h1>

<? if ($arResult['ERROR']): ?>
    <div class="alert alert-error"><?= htmlspecialcharsbx($arResult['ERROR']) ?></div>
<? endif; ?>

<div id="alerts-container"></div>

<div class="marketplace-selector">
    <label>Выберите маркетплейс:</label>
    <select id="marketplace-select">
        <option value="">-- Выберите --</option>
        <option value="ozon" <?= ($arResult['MARKETPLACE'] == 'ozon') ? 'selected' : '' ?>>OZON</option>
        <option value="wb" <?= ($arResult['MARKETPLACE'] == 'wb') ? 'selected' : '' ?>>Wildberries</option>
    </select>
</div>

<? if ($arResult['MARKETPLACE']): ?>
<div id="supplies-list" style="display: block;">
    <h2>Выберите поставку:</h2>
    <table class="supplies-table">
        <thead>
            <tr>
                <th>Номер поставки</th>
                <th>Дата отгрузки</th>
                <th>Товаров</th>
                <th>Действие</th>
            </tr>
        </thead>
        <tbody>
            <? foreach ($arResult['SUPPLIES'] as $supply): ?>
                <? if ($supply['marketplace'] == $arResult['MARKETPLACE']): ?>
                <tr>
                    <td><?= htmlspecialcharsbx($supply['name']) ?></td>
                    <td><?= htmlspecialcharsbx($supply['date']) ?></td>
                    <td><?= (int)$supply['items_count'] ?></td>
                    <td>
                        <button class="select-supply" data-supply-id="<?= $supply['id'] ?>">
                            Выбрать
                        </button>
                    </td>
                </tr>
                <? endif; ?>
            <? endforeach; ?>
        </tbody>
    </table>
</div>

<div id="box-count-form" style="display: none;">
    <h2>Количество коробов</h2>
    <!-- УБРАЛИ method и action - форма будет обрабатываться через AJAX -->
    <form id="create-boxes-form">
        <input type="hidden" name="sessid" value="<?= bitrix_sessid() ?>">
        <input type="hidden" name="supply_id" id="selected-supply">
        
        <div class="form-group">
            <label>Количество коробов (1-20):</label>
            <input type="number" name="box_count" min="1" max="20" required class="form-input">
        </div>
        
        <button type="submit" class="btn btn-primary">Добавить короба</button>
    </form>
</div>
<? endif; ?>