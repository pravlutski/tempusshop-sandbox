<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

use Bitrix\Main\Loader;
use Bitrix\Main\Application;

if (!$USER->IsAdmin()) {
    $APPLICATION->AuthForm('Доступ запрещен');
}

if (!Loader::includeModule('panel.manager')) {
    require_once Application::getDocumentRoot() . '/bitrix/modules/main/include/prolog_admin_after.php';
    ShowError('Модуль panel.manager не установлен');
    require_once Application::getDocumentRoot() . '/bitrix/modules/main/include/epilog_admin.php';
    exit;
}

require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php');
?>

<div class="container market-config-container">
    <div class="header">
        <h1>📊 Управление типами цен</h1>
        <button class="btn btn-primary" onclick="openModal()">➕ Добавить тип цены</button>
    </div>
    
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>SORT</th>
                    <th>Код</th>
                    <th>Активность</th>
                    <th>Название</th>
                    <th>Валюта</th>
                    <th>Курс</th>
                    <th>Приоритеты</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody id="configsList">
                <tr>
                    <td colspan="9" style="text-align: center;">Загрузка...</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Модальное окно для редактирования -->
<div id="modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Добавление типа цены</h2>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="configForm">
                <input type="hidden" id="originalCode" name="originalCode">
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="required">Код</label>
                        <input type="text" id="code" name="code" required>
                        <div class="info-text">Уникальный идентификатор (например: RU, BY, WB)</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Название</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group form-group-3">
                        <label>SORT</label>
                        <input type="number" id="sort" name="sort" value="500">
                    </div>
                    
                    <div class="form-group form-group-3">
                        <label>Активность</label>
                        <select id="active" name="active">
                            <option value="Y">Да</option>
                            <option value="N">Нет</option>
                        </select>
                    </div>
                    <div class="form-group form-group-3">
                        <label>SITE_ID</label>
                        <select id="site_id" name="site_id">
                            <option value="s1">s1</option>
                            <option value="s2">s2</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="required">Валюта</label>
                        <select id="currency" name="currency" required>
                            <option value="RUB">RUB - Российский рубль</option>
                            <option value="BYN">BYN - Белорусский рубль</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Курс</label>
                        <input type="number" id="rate" name="rate" step="0.0001" value="1.0000">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Округление цены</label>
                        <select id="roundPrice" name="roundPrice">
                            <option value="-1">-1 - Округление вниз</option>
                            <option value="0">0 - Без округления</option>
                            <option value="1">1 - Округление вверх</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>ID типа цены (Битрикс)</label>
                        <input type="number" id="priceTypeId" name="priceTypeId">
                    </div>
                    <div class="form-group">
                        <label>Свойство цены (Битрикс)</label>
                        <input type="text" id="propertyPrice" name="propertyPrice">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>ID источника (Битрикс)</label>
                        <input type="number" id="tradingPlatformId" name="tradingPlatformId">
                    </div>
                    
                    <div class="form-group">
                        <label>URL магазина</label>
                        <input type="text" id="url" name="url">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="required">Колонка цены</label>
                        <input type="text" id="columnPrice" name="columnPrice" required>
                        <div class="info-text">Название колонки в таблице ci_price_catalog</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Колонка цены со скидкой</label>
                        <input type="text" id="columnDiscountPrice" name="columnDiscountPrice" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="required">Колонка активности</label>
                        <input type="text" id="columnActive" name="columnActive" required>
                        <div class="info-text">Название колонки в таблице ci_price</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Код опции обновления</label>
                        <input type="text" id="optionUpdate" name="optionUpdate">
                    </div>
					
                    <div class="form-group">
                        <label>Код опции статус парсинга</label>
                        <input type="text" id="optionStatusParser" name="optionStatusParser">
                        <div class="info-text">Если в опции не "end" то updatePrice пропустит обновление</div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Таблица себестоимости FBO</label>
                        <input type="text" id="tblSebesFbo" name="tblSebesFbo">
                    </div>
                    
                    <div class="form-group">
                        <label>Таблица цены FBO</label>
                        <input type="text" id="tblPriceFbo" name="tblPriceFbo">
                    </div>
                </div>
                
                <!-- Секция приоритетов складов -->
                <div class="priority-section">
                    <div class="priority-header">
                        <h3>🏪 Приоритеты складов</h3>
                        <button type="button" class="btn btn-primary btn-sm" onclick="addPriorityRow()">➕ Добавить склад</button>
                    </div>
                    
                    <div class="info-text" style="margin-bottom: 10px;">
                        ⚡ Приоритет: чем меньше число, тем выше приоритет
                    </div>
                    
                    <table class="priority-table">
                        <thead>
                            <tr>
                                <th width="60%">Склад</th>
                                <th width="20%">Приоритет</th>
                                <th width="20%">Действия</th>
                            </tr>
                        </thead>
                        <tbody id="prioritiesList">
                            <tr>
                                <td colspan="3" style="text-align: center; color: #9ca3af;">
                                    Нет добавленных приоритетов
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal()">Отмена</button>
            <button class="btn btn-primary" onclick="saveConfig()">Сохранить</button>
        </div>
    </div>
</div>

<script>
let warehouses = [];

async function loadWarehouses() {
    try {
        const response = await fetch('/bitrix/admin/market_config_ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=getWarehouses'
        });
        const result = await response.json();
        if (result.success) {
            warehouses = result.data;
        }
    } catch (error) {
        console.error('Ошибка загрузки складов:', error);
    }
}

async function loadConfigs() {
    try {
        const response = await fetch('/bitrix/admin/market_config_ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=getConfigs'
        });
        const result = await response.json();
        
        if (result.success) {
            renderConfigs(result.data);
        } else {
            showToast(result.error || 'Ошибка загрузки', 'error');
        }
    } catch (error) {
        console.error('Ошибка:', error);
        showToast('Ошибка загрузки данных', 'error');
    }
}

// Отображение списка конфигов
function renderConfigs(configs) {
    const tbody = document.getElementById('configsList');
    
    if (!configs || configs.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" style="text-align: center;">Нет данных</td></tr>';
        return;
    }
    
    tbody.innerHTML = configs.map(config => {
        // Генерируем HTML для приоритетов
        const prioritiesHtml = renderPrioritiesBadges(config.PRIORITIES || []);
        
        return `
            <tr>
                <td>${config.SORT || 500}</td>
                <td><strong>${escapeHtml(config.PRYCE_TYPE)}</strong></td>
                <td>
                    <span class="active-badge ${config.ACTIVE === 'Y' ? 'active-y' : 'active-n'}">
                        ${config.ACTIVE === 'Y' ? 'Активен' : 'Неактивен'}
                    </span>
                </td>
                <td>${escapeHtml(config.NAME)}</td>
                <td>${config.CURRENCY}</td>
                <td>${parseFloat(config.RATE).toFixed(4)}</td>
                <td class="priorities-cell">${prioritiesHtml}</td>
                <td class="actions">
                    <button class="btn btn-primary btn-sm" onclick="editConfig('${config.PRYCE_TYPE}')">✏️</button>
                    <button class="btn btn-danger btn-sm" onclick="deleteConfig('${config.PRYCE_TYPE}')">🗑️</button>
                </td>
            </tr>
        `;
    }).join('');
}
function renderPrioritiesBadges(priorities) {
    if (!priorities || priorities.length === 0) {
        return '<span class="empty-priorities">—</span>';
    }
    
    // Сортируем по приоритету
    const sorted = [...priorities].sort((a, b) => a.PRIORITY - b.PRIORITY);
    
    // Находим склад с наивысшим приоритетом (минимальное число)
    const topPriority = sorted[0];
    
    // Ограничиваем количество отображаемых складов (показываем все, но можно ограничить)
    const displayPriorities = sorted; // Показываем все
    
    // Создаем HTML для каждого приоритета
    const badges = displayPriorities.map(priority => {
        // Находим название склада
        const warehouse = warehouses.find(w => w.ID == priority.WAREHOUSE_ID);
        const warehouseName = warehouse ? warehouse.NAME : `Склад ${priority.WAREHOUSE_ID}`;
        
        // Определяем класс для badge в зависимости от приоритета
        let priorityClass = 'priority-item';
        if (priority.PRIORITY <= 10) {
            priorityClass += ' priority-high';
        } else if (priority.PRIORITY <= 50) {
            priorityClass += ' priority-medium';
        } else {
            priorityClass += ' priority-low';
        }
        
        // Добавляем звездочку для наивысшего приоритета
        const isTop = priority.PRIORITY === topPriority.PRIORITY;
        const starIcon = isTop ? ' ⭐' : '';
        
        return `
            <div class="${priorityClass}" title="Приоритет: ${priority.PRIORITY}">
                <span class="warehouse-name">${escapeHtml(warehouseName)}</span>
                <span class="priority-value">${priority.PRIORITY}${starIcon}</span>
            </div>
        `;
    }).join('');
    
    return `
        <div class="priorities-wrapper">
            <div class="priorities-list">
                ${badges}
            </div>
            <div class="priorities-count">Всего: ${priorities.length}</div>
        </div>
    `;
}
// Открытие модального окна
function openModal() {
    document.getElementById('modal').classList.add('active');
    document.getElementById('modalTitle').textContent = 'Добавление типа цены';
    document.getElementById('configForm').reset();
    document.getElementById('originalCode').value = '';
    document.getElementById('code').disabled = false;
    document.getElementById('prioritiesList').innerHTML = `
        <tr>
            <td colspan="3" style="text-align: center; color: #9ca3af;">
                Нет добавленных приоритетов
            </td>
        </tr>
    `;
    // Значения по умолчанию
    document.getElementById('sort').value = 500;
    document.getElementById('active').value = 'Y';
    document.getElementById('site_id').value = 's1';
    document.getElementById('currency').value = 'RUB';
    document.getElementById('rate').value = 1.0000;
    document.getElementById('roundPrice').value = 0;
}

// Закрытие модального окна
function closeModal() {
    document.getElementById('modal').classList.remove('active');
}

// Редактирование конфига
async function editConfig(code) {
    try {
        const response = await fetch('/bitrix/admin/market_config_ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=getConfig&code=${encodeURIComponent(code)}`
        });
        const result = await response.json();
        
        if (result.success && result.data) {
            const config = result.data;
            
            document.getElementById('modalTitle').textContent = `Редактирование: ${config.PRYCE_TYPE}`;
            document.getElementById('originalCode').value = config.PRYCE_TYPE;
            document.getElementById('code').value = config.PRYCE_TYPE;
            document.getElementById('code').disabled = true;
            document.getElementById('name').value = config.NAME || '';
            document.getElementById('sort').value = config.SORT || 500;
            document.getElementById('active').value = config.ACTIVE || 'Y';
            document.getElementById('site_id').value = config.SITE_ID || 's1';
            document.getElementById('currency').value = config.CURRENCY || 'RUB';
            document.getElementById('rate').value = config.RATE || 1.0;
            document.getElementById('roundPrice').value = config.ROUND_PRICE || 0;
            document.getElementById('priceTypeId').value = config.PRICE_TYPE_ID || '';
            document.getElementById('propertyPrice').value = config.PROPERTY_PRICE || '';
            document.getElementById('tradingPlatformId').value = config.TRADING_PLATFORM_ID || '';
            document.getElementById('url').value = config.URL || '';
            document.getElementById('columnPrice').value = config.COLUMN_PRICE || '';
            document.getElementById('columnDiscountPrice').value = config.COLUMN_DISCOUNT_PRICE || '';
            document.getElementById('columnActive').value = config.COLUMN_ACTIVE || '';
            document.getElementById('optionUpdate').value = config.OPTION_UPDATE || '';
            document.getElementById('optionStatusParser').value = config.OPTION_STATUS_PARSER || '';
            document.getElementById('tblSebesFbo').value = config.TBL_SEBES_FBO || '';
            document.getElementById('tblPriceFbo').value = config.TBL_PRICE_FBO || '';
            
            // Отображаем приоритеты

            renderPriorities(config.PRIORITIES || []);
            
            document.getElementById('modal').classList.add('active');
        } else {
            showToast('Ошибка загрузки конфига', 'error');
        }
    } catch (error) {
        console.error('Ошибка:', error);
        showToast('Ошибка загрузки данных', 'error');
    }
}

// Отображение приоритетов
function renderPriorities(priorities) {
    const tbody = document.getElementById('prioritiesList');
    
    if (!priorities || priorities.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="3" style="text-align: center; color: #9ca3af;">
                    Нет добавленных приоритетов
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = priorities.map(priority => `
        <tr data-id="${priority.ID || ''}">
            <td>
                <select class="warehouse-select" style="width: 100%;" data-id="${priority.ID || 'new'}_warehouse">
                    ${getWarehouseOptions(priority.WAREHOUSE_ID)}
                </select>
            </td>
            <td>
                <input type="number" class="priority-input" value="${parseFloat(priority.PRIORITY).toFixed(2)}" step="0.01" 
                       data-id="${priority.ID || 'new'}_priority" style="width: 80%;">
            </td>
            <td>
                <div class="priority-actions">
                    <button type="button" class="btn btn-danger btn-icon" onclick="deletePriorityRow(this)">🗑️</button>
                </div>
            </td>
        </tr>
    `).join('');
}

// Добавление строки приоритета
function addPriorityRow() {
    const tbody = document.getElementById('prioritiesList');
    
    // Убираем сообщение "Нет данных"
    if (tbody.children.length === 1 && tbody.children[0].innerText.includes('Нет добавленных приоритетов')) {
        tbody.innerHTML = '';
    }
    
    const newRow = document.createElement('tr');
    newRow.innerHTML = `
        <td>
            <select class="warehouse-select" style="width: 100%;" data-id="new_warehouse">
                ${getWarehouseOptions(null)}
            </select>
        </td>
        <td>
            <input type="number" class="priority-input" value="10.00" step="0.01" data-id="new_priority" style="width: 80%;">
        </td>
        <td>
            <div class="priority-actions">
                <button type="button" class="btn btn-danger btn-icon" onclick="deletePriorityRow(this)">🗑️</button>
            </div>
        </td>
    `;
    tbody.appendChild(newRow);
}

// Удаление строки приоритета
function deletePriorityRow(button) {
    const row = button.closest('tr');
    const id = row.getAttribute('data-id');
    
    // Если это существующая запись в БД, отправляем запрос на удаление
    if (id && !isNaN(parseInt(id)) && parseInt(id) > 0) {
        deletePriorityFromDB(id);
    }
    
    row.remove();
    
    // Если не осталось строк, показываем сообщение
    const tbody = document.getElementById('prioritiesList');
    if (tbody.children.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="3" style="text-align: center; color: #9ca3af;">
                    Нет добавленных приоритетов
                </td>
            </tr>
        `;
    }
}

// Удаление приоритета из БД
async function deletePriorityFromDB(id) {
    try {
        const response = await fetch('/bitrix/admin/market_config_ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=deletePriority&id=${id}`
        });
        const result = await response.json();
        if (result.success) {
            showToast('Приоритет удален', 'success');
        }
    } catch (error) {
        console.error('Ошибка:', error);
    }
}

// Получение опций для выбора склада
function getWarehouseOptions(selectedId) {
    if (warehouses.length === 0) {
        return '<option value="">Загрузка складов...</option>';
    }
    
    let options = '<option value="">-- Выберите склад --</option>';
    options += warehouses.map(warehouse => 
        `<option value="${warehouse.ID}" ${warehouse.ID == selectedId ? 'selected' : ''}>
            ${escapeHtml(warehouse.NAME)} (ID: ${warehouse.ID})
        </option>`
    ).join('');
    
    return options;
}

// Сохранение конфига
async function saveConfig() {
    const originalCode = document.getElementById('originalCode').value;
    const code = document.getElementById('code').value;
    
    if (!code) {
        showToast('Код обязателен для заполнения', 'error');
        return;
    }
    
    if (!document.getElementById('name').value) {
        showToast('Название обязательно для заполнения', 'error');
        return;
    }
    
    if (!document.getElementById('columnPrice').value) {
        showToast('Колонка цены обязательна для заполнения', 'error');
        return;
    }
    
    // Собираем приоритеты
    const priorities = [];
    const priorityRows = document.querySelectorAll('#prioritiesList tr');
    
    for (let row of priorityRows) {
        if (row.innerText.includes('Нет добавленных приоритетов')) continue;
        
        const warehouseSelect = row.querySelector('.warehouse-select');
        const priorityInput = row.querySelector('.priority-input');
        
        if (warehouseSelect && warehouseSelect.value && priorityInput) {
            priorities.push({
                WAREHOUSE_ID: parseInt(warehouseSelect.value),
                PRIORITY: parseFloat(priorityInput.value),
                ACTIVE: 'Y'
            });
        }
    }
    
    const data = {
        PRYCE_TYPE: code,
        SORT: parseInt(document.getElementById('sort').value) || 500,
        ACTIVE: document.getElementById('active').value,
        NAME: document.getElementById('name').value,
        SITE_ID: document.getElementById('site_id').value,
        ROUND_PRICE: parseInt(document.getElementById('roundPrice').value),
        CURRENCY: document.getElementById('currency').value,
        RATE: parseFloat(document.getElementById('rate').value) || 1.0,
        PRICE_TYPE_ID: document.getElementById('priceTypeId').value ? parseInt(document.getElementById('priceTypeId').value) : null,
        PROPERTY_PRICE: document.getElementById('propertyPrice').value,
		URL: document.getElementById('url').value,
        TRADING_PLATFORM_ID: document.getElementById('tradingPlatformId').value ? parseInt(document.getElementById('tradingPlatformId').value) : null,
        COLUMN_PRICE: document.getElementById('columnPrice').value,
        COLUMN_DISCOUNT_PRICE: document.getElementById('columnDiscountPrice').value,
        COLUMN_ACTIVE: document.getElementById('columnActive').value,
        TBL_SEBES_FBO: document.getElementById('tblSebesFbo').value || null,
        TBL_PRICE_FBO: document.getElementById('tblPriceFbo').value || null,
        OPTION_UPDATE: document.getElementById('optionUpdate').value,
        OPTION_STATUS_PARSER: document.getElementById('optionStatusParser').value,
        PRIORITIES: priorities
    };
    
    try {
        const response = await fetch('/bitrix/admin/market_config_ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=saveConfig&data=${encodeURIComponent(JSON.stringify(data))}`
        });
        const result = await response.json();
        
        if (result.success) {
            showToast('Сохранено успешно', 'success');
            closeModal();
            loadConfigs();
        } else {
            showToast(result.error || 'Ошибка сохранения', 'error');
        }
    } catch (error) {
        console.error('Ошибка:', error);
        showToast('Ошибка сохранения', 'error');
    }
}

// Удаление конфига
async function deleteConfig(code) {
    if (!confirm(`Удалить конфиг "${code}"? Это также удалит все связанные приоритеты.`)) {
        return;
    }
    
    try {
        const response = await fetch('/bitrix/admin/market_config_ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=deleteConfig&code=${encodeURIComponent(code)}`
        });
        const result = await response.json();
        
        if (result.success) {
            showToast('Удалено успешно', 'success');
            loadConfigs();
        } else {
            showToast(result.error || 'Ошибка удаления', 'error');
        }
    } catch (error) {
        console.error('Ошибка:', error);
        showToast('Ошибка удаления', 'error');
    }
}

// Утилиты
function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}

// Инициализация
window.onload = async () => {
    await loadWarehouses();
    await loadConfigs();
};
</script>

<style>
	.container {
		max-width: 1400px;
		margin: 0 auto;
	}
	
	/* Заголовок */
	.header {
		background: white;
		padding: 20px;
		border-radius: 8px;
		margin-bottom: 20px;
		box-shadow: 0 1px 3px rgba(0,0,0,0.1);
		display: flex;
		justify-content: space-between;
		align-items: center;
	}
	
	.header h1 {
		font-size: 24px;
		color: #1f2d3d;
	}
	
	/* Кнопки */
	.btn {
		padding: 8px 16px;
		border-radius: 4px;
		border: none;
		cursor: pointer;
		font-size: 14px;
		transition: all 0.3s;
	}
	
	.btn-primary {
		background: #3b82f6;
		color: white;
	}
	
	.btn-primary:hover {
		background: #2563eb;
	}
	
	.btn-danger {
		background: #ef4444;
		color: white;
	}
	
	.btn-danger:hover {
		background: #dc2626;
	}
	
	.btn-success {
		background: #10b981;
		color: white;
	}
	
	.btn-success:hover {
		background: #059669;
	}
	
	.btn-secondary {
		background: #6b7280;
		color: white;
	}
	
	.btn-secondary:hover {
		background: #4b5563;
	}
	
	.btn-sm {
		padding: 4px 12px;
		font-size: 12px;
	}
	
	/* Таблица */
	.table-container {
		background: white;
		border-radius: 8px;
		overflow-x: auto;
		box-shadow: 0 1px 3px rgba(0,0,0,0.1);
	}
	
	.market-config-container table {
		width: 100%;
		border-collapse: collapse;
	}
	
	.market-config-container th {
		background: #f9fafb;
		padding: 12px;
		text-align: left;
		font-weight: 600;
		color: #374151;
		border-bottom: 1px solid #e5e7eb;
	}
	
	.market-config-container td {
		padding: 12px;
		border-bottom: 1px solid #f3f4f6;
	}
	
	.market-config-container tr:hover {
		background: #f9fafb;
	}
	
	.active-badge {
		display: inline-block;
		padding: 4px 8px;
		border-radius: 4px;
		font-size: 12px;
		font-weight: 500;
	}
	
	.active-y {
		background: #d1fae5;
		color: #065f46;
	}
	
	.active-n {
		background: #fee2e2;
		color: #991b1b;
	}
	
	/* Модальное окно */
	.modal {
		display: none;
		position: fixed;
		top: 0;
		left: 0;
		width: 100%;
		height: 100%;
		background: rgba(0,0,0,0.5);
		z-index: 1000;
		overflow-y: auto;
	}
	
	.modal.active {
		display: flex;
		align-items: center;
		justify-content: center;
	}
	
	.modal-content {
		background: white;
		border-radius: 12px;
		max-width: 800px;
		width: 90%;
		max-height: 90vh;
		overflow-y: auto;
		animation: slideIn 0.3s;
	}
	
	@keyframes slideIn {
		from {
			transform: translateY(-50px);
			opacity: 0;
		}
		to {
			transform: translateY(0);
			opacity: 1;
		}
	}
	
	.modal-header {
		padding: 20px;
		border-bottom: 1px solid #e5e7eb;
		display: flex;
		justify-content: space-between;
		align-items: center;
	}
	
	.modal-header h2 {
		font-size: 20px;
		color: #1f2d3d;
	}
	
	.close {
		font-size: 28px;
		cursor: pointer;
		color: #9ca3af;
	}
	
	.close:hover {
		color: #374151;
	}
	
	.modal-body {
		padding: 20px;
	}
	
	.modal-footer {
		padding: 20px;
		border-top: 1px solid #e5e7eb;
		display: flex;
		justify-content: flex-end;
		gap: 10px;
	}
	
	/* Форма */
	.form-group {
		margin-bottom: 20px;
	}
	.form-group.form-group-3 {
		width: 100%;
		display: contents;
	}
	.form-row {
		display: grid;
		grid-template-columns: repeat(2, 1fr);
		gap: 20px;
		margin-bottom: 20px;
	}
	
	label {
		display: block;
		margin-bottom: 8px;
		font-weight: 500;
		color: #374151;
	}
	
	.required:after {
		content: '*';
		color: #ef4444;
		margin-left: 4px;
	}
	
	input, select, textarea {
		width: 100%;
		padding: 8px 12px;
		border: 1px solid #d1d5db;
		border-radius: 4px;
		font-size: 14px;
	}
	
	input:focus, select:focus {
		outline: none;
		border-color: #3b82f6;
		box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
	}
	input[type=number] {
		width: stretch;
	}
	/* Таблица приоритетов */
	.priority-section {
		margin-top: 30px;
		border-top: 2px solid #e5e7eb;
		padding-top: 20px;
	}
	
	.priority-header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-bottom: 20px;
	}
	
	.priority-table {
		width: 100%;
		margin-top: 10px;
	}
	
	.priority-table th {
		background: #f3f4f6;
		padding: 10px;
	}
	
	.priority-table td {
		padding: 10px;
	}
	
	.priority-actions {
		display: flex;
		gap: 10px;
	}
	
	.btn-icon {
		padding: 4px 8px;
		font-size: 12px;
	}
	
	/* Уведомления */
	.toast {
		position: fixed;
		top: 20px;
		right: 20px;
		background: white;
		padding: 12px 20px;
		border-radius: 8px;
		box-shadow: 0 4px 6px rgba(0,0,0,0.1);
		z-index: 1100;
		animation: slideInRight 0.3s;
	}
	
	@keyframes slideInRight {
		from {
			transform: translateX(100%);
			opacity: 0;
		}
		to {
			transform: translateX(0);
			opacity: 1;
		}
	}
	
	.toast-success {
		border-left: 4px solid #10b981;
	}
	
	.toast-error {
		border-left: 4px solid #ef4444;
	}
	
	/* Действия в таблице */
	.actions {
		display: flex;
		gap: 8px;
	}
	
	/* Информация */
	.info-text {
		font-size: 12px;
		color: #6b7280;
		margin-top: 4px;
	}
	
	.badge {
		display: inline-block;
		padding: 2px 8px;
		border-radius: 12px;
		font-size: 11px;
		font-weight: 500;
	}
	
	.badge-info {
		background: #dbeafe;
		color: #1e40af;
	}
	
	.priorities-cell {
		min-width: 200px;
		max-width: 300px;
	}

	.priorities-wrapper {
		display: flex;
		flex-direction: column;
		gap: 8px;
	}

	.priorities-list {
		display: flex;
		flex-direction: column;
		gap: 4px;
	}

	.priority-item {
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding: 4px 8px;
		border-radius: 4px;
		font-size: 12px;
		background: #f3f4f6;
		transition: all 0.2s;
	}

	.priority-item:hover {
		transform: translateX(2px);
		box-shadow: 0 1px 2px rgba(0,0,0,0.1);
	}

	.warehouse-name {
		flex: 1;
		font-weight: 500;
		color: #374151;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.priority-value {
		font-weight: 600;
		margin-left: 8px;
		padding: 2px 6px;
		border-radius: 12px;
		background: white;
		font-size: 11px;
	}

	.priority-badge {
		/* Базовые стили */
	}

	.priority-high {
		border-left: 3px solid #10b981;
		background: #ecfdf5;
	}

	.priority-high .priority-value {
		color: #059669;
	}

	.priority-medium {
		border-left: 3px solid #f59e0b;
		background: #fffbeb;
	}

	.priority-medium .priority-value {
		color: #d97706;
	}

	.priority-low {
		border-left: 3px solid #ef4444;
		background: #fef2f2;
	}

	.priority-low .priority-value {
		color: #dc2626;
	}

	.empty-priorities {
		color: #9ca3af;
		font-style: italic;
		font-size: 13px;
	}

	.priorities-count {
		font-size: 11px;
		color: #6b7280;
		text-align: right;
		padding-top: 4px;
		border-top: 1px dashed #e5e7eb;
	}

	/* Адаптив для мобильных */
	@media (max-width: 768px) {
		.priorities-cell {
			min-width: 180px;
		}
		
		.priority-item {
			font-size: 11px;
		}
		
		.warehouse-name {
			max-width: 100px;
		}
	}
</style>


<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
?>