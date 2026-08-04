<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$chunkSize = $arParams["CHUNK_SIZE"] ?? 100;
?>

<div class="admin-users-remove">
    <div class="admin-panel">
        <div class="admin-panel__title">
            Массовое удаление пользователей
        </div>
        
        <div class="admin-panel__content">
            <div class="form-group">
                <label class="form-label">
                    ID пользователей (каждый ID с новой строки)
                </label>
                <textarea 
                    id="userIdsList" 
                    class="form-textarea"
                    rows="15"
                    placeholder="123&#10;456&#10;789"
                ></textarea>
                <div class="form-hint">
                    Введите ID пользователей для удаления (каждый с новой строки)
                </div>
            </div>
            
            <div class="form-group">
                <button id="deleteButton" class="btn btn-danger">
                    <span class="btn-text">Удалить пользователей</span>
                    <span class="btn-loader" style="display: none;">
                        <i class="loader-icon"></i>
                    </span>
                </button>
                <button id="clearButton" class="btn btn-default">
                    Очистить
                </button>
            </div>
        </div>
    </div>
    
    <div id="progressPanel" class="progress-panel" style="display: none;">
        <div class="progress-panel__title">Процесс удаления</div>
        
        <div class="progress-stats">
            <div class="stats-row">
                <span class="stats-label">Обработано:</span>
                <span id="processedCount" class="stats-value">0</span>
            </div>
            <div class="stats-row">
                <span class="stats-label">Удалено:</span>
                <span id="deletedCount" class="stats-value stats-success">0</span>
            </div>
            <div class="stats-row">
                <span class="stats-label">Ошибки:</span>
                <span id="errorsCount" class="stats-value stats-error">0</span>
            </div>
            <div class="stats-row">
                <span class="stats-label">Всего:</span>
                <span id="totalCount" class="stats-value">0</span>
            </div>
        </div>
        
        <div class="progress-bar-container">
            <div id="progressBar" class="progress-bar" style="width: 0%"></div>
        </div>
        
        <div id="errorsList" class="errors-list" style="display: none;">
            <div class="errors-title">Ошибки удаления:</div>
            <div id="errorsContent" class="errors-content"></div>
        </div>
        
        <div id="successMessage" class="success-message" style="display: none;">
            ✓ Удаление пользователей успешно завершено!
        </div>
    </div>
</div>

<script>
    BX.message({
        CHUNK_SIZE: '<?= CUtil::JSEscape($chunkSize) ?>'
    });
</script>