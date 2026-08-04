<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

/** @var array $arResult */
/** @var CMain $APPLICATION */

$currentUrl = $APPLICATION->GetCurPage();
?>

<div class="returns-container">
    <h1>Возвраты: <?= htmlspecialcharsbx($arResult['STORE_NAME']) ?></h1>
    
    <div class="store-switcher" style="margin-bottom: 20px; padding: 15px; background: #f0f0f0; border-radius: 8px;">
        <strong style="margin-right: 15px;">Выберите магазин:</strong>
        <a href="<?= $currentUrl ?>?store=nemiga" 
           class="store-btn <?= $arResult['CURRENT_STORE'] == 'nemiga' ? 'active' : '' ?>"
           style="display: inline-block; padding: 8px 16px; margin-right: 10px; background: <?= $arResult['CURRENT_STORE'] == 'nemiga' ? '#007bff' : '#e0e0e0' ?>; color: <?= $arResult['CURRENT_STORE'] == 'nemiga' ? 'white' : '#333' ?>; text-decoration: none; border-radius: 4px;">
            Немига (<?= $arResult['CURRENT_STORE'] == 'nemiga' && is_array($arResult['RETURNS_LIST']) ? count($arResult['RETURNS_LIST']) : '?' ?>)
        </a>
        <a href="<?= $currentUrl ?>?store=novokuznetskaya" 
           class="store-btn <?= $arResult['CURRENT_STORE'] == 'novokuznetskaya' ? 'active' : '' ?>"
           style="display: inline-block; padding: 8px 16px; background: <?= $arResult['CURRENT_STORE'] == 'novokuznetskaya' ? '#007bff' : '#e0e0e0' ?>; color: <?= $arResult['CURRENT_STORE'] == 'novokuznetskaya' ? 'white' : '#333' ?>; text-decoration: none; border-radius: 4px;">
            Новокузнецкая (<?= $arResult['CURRENT_STORE'] == 'novokuznetskaya' && is_array($arResult['RETURNS_LIST']) ? count($arResult['RETURNS_LIST']) : '?' ?>)
        </a>
    </div>
    
    <?php if (empty($arResult['RETURNS_LIST'])): ?>
        <p class="alert alert-info">Нет моделей для возврата.</p>
    <?php else: ?>
        <div style="margin-bottom: 15px; padding: 10px; background: #e8f4fd; border-radius: 4px;">
            Найдено моделей: <strong><?= count($arResult['RETURNS_LIST']) ?></strong>
        </div>
        
        <table class="returns-table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f5f5f5;">
                    <th style="padding: 10px; border: 1px solid #ddd; width: 150px;">Фото</th>
                    <th style="padding: 10px; border: 1px solid #ddd;">Артикул</th>
                    <th style="padding: 10px; border: 1px solid #ddd; width: 100px;">Количество</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($arResult['RETURNS_LIST'] as $item): ?>
                <tr>
                    <td class="photo-cell" style="padding: 10px; border: 1px solid #ddd; text-align: center;">
                        <?php if (!empty($item['PHOTO'])): ?>
                            <img src="<?= htmlspecialcharsbx($item['PHOTO']) ?>" 
                                 alt="<?= htmlspecialcharsbx($item['NAME'] ?? '') ?>"
                                 style="height: 150px; max-width: 100%; object-fit: contain;">
                        <?php else: ?>
                            <div class="no-photo" style="height: 150px; display: flex; align-items: center; justify-content: center; background: #f5f5f5;">
                                Нет фото
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="article-cell" style="padding: 10px; border: 1px solid #ddd;">
                        <?= htmlspecialcharsbx($item['NAME'] ?? '') ?>
                    </td>
                    <td class="qty-cell" style="padding: 10px; border: 1px solid #ddd; text-align: center;">
                        <?= (int)($item['QUANTITY'] ?? 0) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="export-section" style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
            <h3 style="margin-top: 0;">Экспорт данных для <?= htmlspecialcharsbx($arResult['STORE_NAME']) ?></h3>
            <div>
                <a href="<?= $currentUrl ?>?store=<?= $arResult['CURRENT_STORE'] ?>&export_type=csv" 
                   class="btn-export" 
                   style="display: inline-block; background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; margin-right: 10px; text-decoration: none;">
                    📥 Экспорт в CSV
                </a>
                
                <a href="<?= $currentUrl ?>?store=<?= $arResult['CURRENT_STORE'] ?>&export_type=excel" 
                   class="btn-export" 
                   style="display: inline-block; background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; text-decoration: none;">
                    📊 Экспорт в Excel
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.store-btn.active {
    font-weight: bold;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.store-btn:hover {
    opacity: 0.9;
}
.returns-table tr:hover {
    background: #f9f9f9;
}
</style>