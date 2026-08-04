<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
?>

<div id="price-monitoring" class="price-monitoring">

    <div class="monitoring-header">
        <div class="price-type-selector">
            <label>Тип цены:</label>
            <select id="price-type-select">
                <?php foreach ($arResult['PRICE_TYPES'] as $key => $name): ?>
                    <option value="<?= $key ?>" 
                        <?= ($key == $arResult['PRICE_TYPE_ACTIVE']) ? 'selected' : '' ?>>
                        <?= $name ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="monitoring-actions">
            <button class="btn btn-settings" onclick="openMonitoringSettings()">
                Настройки мониторинга ⚙️
            </button>
            <button class="btn btn-competitors" onclick="openCompetitorsSettings()">
                Настройки конкурентов ⚙️
            </button>
        </div>
		

    </div>

	<div class="monitoring-filters">
		<div class="filter-group">
			<label>Артикул:</label>
			<input type="text" id="filter-article" 
				   value="<?= htmlspecialcharsbx($arResult['FILTER_VALUES']['article']) ?>" 
				   placeholder="Поиск по артикулу">
		</div>

		<div class="filter-group">
			<label>Неконкурентная цена</label>
			<input type="checkbox" id="filter-uncompetitive-price" 
				   value="Y" <?= ($arResult['FILTER_VALUES']['uncompetitive_price'] == 'Y') ? 'checked' : '' ?>>
		</div>
		<button class="btn btn-primary" onclick="applyFilters()">Применить</button>
		<button class="btn btn-secondary" onclick="resetFilters()">Сбросить</button>
	</div>
    <div class="monitoring-info">
        <div class="total-count" id="total-count"></div>
        <div class="total-uncompetive-count" id="total-uncompetive-count"></div>
        <div class="loading-indicator" id="loading-indicator" style="display: none;">
            Загрузка данных...
        </div>
    </div>

    <div class="monitoring-table-container" id="table-container"></div>

    <div class="monitoring-pagination" id="pagination-container"></div>
</div>

<?if ($arResult['COMPETITORS']): ?>
	<?foreach ($arResult['COMPETITORS'] as $competitorName): ?>
		<?
		$fileLog = $_SERVER['DOCUMENT_ROOT'] . "/upload/competitor_prices/{$competitorName}.log";
		if (file_exists($fileLog)) {
			echo "<p>" . file_get_contents($fileLog) . "</p>";
		}
		?>
	<?endforeach?>
<? endif ?>
<div id="monitoring-settings-modal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 900px;">
        <span class="close" onclick="closeModal('monitoring-settings-modal')">&times;</span>
        <h3>Настройки мониторинга</h3>

        <!-- Селектор типа цены -->
        <div class="form-group">
            <label for="settings-price-type-select">Тип цены для настроек:</label>
            <select id="settings-price-type-select" class="form-control">
                <?php foreach ($arResult['PRICE_TYPES'] as $key => $name): ?>
                    <option value="<?= $key ?>"><?= $name ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Общие настройки -->
        <div class="form-group">
            <label for="settings-margin">Наценка в %:</label>
            <input type="number" step="1" id="settings-margin" class="form-control" placeholder="-1">
        </div>
        
        <div class="form-group">
            <label for="settings-min-margin-rub">Минимальная наценка в руб.:</label>
            <input type="number" step="10" id="settings-min-margin-rub" class="form-control" placeholder="0">
        </div>

        <div class="form-group">
            <label for="settings-min-margin-percent">Минимальная наценка в %:</label>
            <input type="number" step="1" id="settings-min-margin-percent" class="form-control" placeholder="0">
        </div>

        <div class="form-group">
            <label for="settings-take-market-prices">Учитывать цены конкурентов:</label>
            <input type="checkbox" id="settings-take-market-prices" class="" value="Y">
        </div>
		
        <div class="form-group">
            <label for="settings-apply-rrp">Применять РРЦ, если КЦ выше:</label>
            <input type="checkbox" id="settings-apply-rrp" class="" value="Y">
        </div>
		
        <div class="form-group">
            <label for="settings-apply-min-margin">Мин.наценка, если КЦ ниже мин.порога:</label>
			<input type="checkbox" id="settings-apply-min-margin" class="" value="Y">
        </div>

        <div class="form-group">
            <label for="settings-min-margin-fail-percent">Минимальная наценка в %:<br><small>(если не удалось установить КЦ)</small></label>
			
            <input type="number" step="1" id="settings-min-margin-fail-percent" class="form-control" placeholder="0">
        </div>
		
        <div class="form-group">
            <label for="settings-max-margin-percent">Максимальная наценка в %:</label>
            <input type="number" step="1" id="settings-max-margin-percent" class="form-control" placeholder="0">
        </div>

        <div class="form-group">
            <label for="settings-co-invest">Соинвест, %:</label>
            <input type="number" step="1" id="settings-co-invest" class="form-control" placeholder="0">
        </div>

        <div class="form-group">
            <label for="settings-mp-commission">Комиссия маркетплейса, %:</label>
            <input type="number" step="1" id="settings-mp-commission" class="form-control" placeholder="0">
        </div>
		
        <!-- Скидки по брендам -->
        <div class="form-group">
            <h5>Дифференцированные скидки по брендам</h5>
            <div id="brand-discounts-container">
                <!-- Контейнер для правил скидок -->
            </div>
            <button type="button" class="btn btn-small" onclick="addBrandDiscountRule()" style="margin-top: 10px;">
                + Добавить правило скидки
            </button>
        </div>

        <div class="form-actions" style="margin-top: 20px;">
            <button class="btn btn-primary" onclick="saveMonitoringSettings()">Сохранить настройки</button>
            <button class="btn btn-secondary" onclick="closeModal('monitoring-settings-modal')">Отмена</button>
        </div>
    </div>
</div>

<div id="competitors-settings-modal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 800px;">
        <span class="close" onclick="closeModal('competitors-settings-modal')">&times;</span>
        <h3>Управление конкурентами</h3>
        
        <div class="competitors-list" id="competitors-list-container">
            <!-- Список будет загружен через AJAX -->
        </div>
        
        <button class="btn btn-primary" onclick="createNewCompetitor()" style="margin-top: 15px;">
            + Создать нового конкурента
        </button>
        
        <div id="competitor-detail" style="display: none; margin-top: 20px; border-top: 1px solid #ddd; padding-top: 20px;">
            <h4 id="competitor-detail-title">Редактирование конкурента</h4>
            
            <input type="hidden" id="edit-competitor-id" value="">
            
            <div class="form-group">
                <label>Название конкурента:</label>
                <input type="text" id="edit-competitor-name" class="form-control">
            </div>
			
            <div class="form-group">
                <span>Автопарсер:</span>
                <input type="checkbox" id="edit-competitor-autoparse" style="width: auto;" value="Y">
            </div>

            <div class="form-group">
                <label>Тип цены:</label>
                <select id="edit-competitor-price-type" class="form-control">
                    <?php foreach ($arResult['PRICE_TYPES'] as $key => $name): ?>
                        <option value="<?= $key ?>"><?= $name ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
    
			<div class="form-group">
				<label>Имя файла для парсинга:</label>
				<input type="text" id="edit-parsing-filename" class="form-control" >
				<small>Имя файла в определенной директории для автоматического парсинга</small>
			</div>
	
            <div class="form-group">
                <label>Список соответствий артикулов:</label>
                <textarea id="edit-competitor-mappings" rows="4" class="form-control" 
                          placeholder="наш_артикул;артикул_конкурента&#10=Пример: ABC123;XYZ789"></textarea>
                <small>По одному соответствию на строку в формате: наш_артикул;артикул_конкурента</small>
            </div>
            
            <div class="form-group">
                <label>Бренды, исключенные из контроля:</label>
                <select id="edit-excluded-brands" multiple class="form-control" style="height: 120px;">
                    <?php foreach ($arResult['BRANDS'] as $brand): ?>
                        <option value='<?=$brand['ID']?>'><?=$brand['NAME']?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" onclick="saveCompetitorData()">Сохранить</button>
                <button class="btn btn-secondary" onclick="cancelCompetitorEdit()">Отмена</button>
                <button class="btn btn-danger" onclick="deleteCurrentCompetitor()" style="float: right;">Удалить</button>
            </div>
        </div>
    </div>
</div>

<div id="create-competitor-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close" onclick="closeModal('create-competitor-modal')">&times;</span>
        <h3>Создание нового конкурента</h3>
        
        <div class="form-group">
            <label>Название конкурента:</label>
            <input type="text" id="new-competitor-name" class="form-control" placeholder="Введите название">
        </div>
        
        <div class="form-group">
            <label>Тип цены:</label>
            <select id="new-competitor-price-type" class="form-control">
                <?php foreach ($arResult['PRICE_TYPES'] as $key => $name): ?>
                    <option value="<?= $key ?>"><?= $name ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Имя файла для парсинга:</label>
            <input type="text" id="new-parsing-filename" class="form-control" >
        </div>
		
        <div class="form-actions">
            <button class="btn btn-primary" onclick="saveNewCompetitor()">Создать</button>
            <button class="btn btn-secondary" onclick="closeModal('create-competitor-modal')">Отмена</button>
        </div>
    </div>
</div>

<script>
const PriceMonitor = {
    currentPage: <?= $arParams['CURRENT_PAGE'] ?>,
    sortField: '<?= $arParams['SORT_FIELD'] ?>',
    sortOrder: '<?= $arParams['SORT_ORDER'] ?>',
    priceType: '<?= $arResult['PRICE_TYPE_ACTIVE'] ?>',
    filters: {
        article: '<?= $arResult['FILTER_VALUES']['article'] ?>',
        uncompetitive_price: '<?= $arResult['FILTER_VALUES']['uncompetitive_price'] ?>'
    },

    loadTableData: function() {
        const loadingIndicator = document.getElementById('loading-indicator');
        const tableContainer = document.getElementById('table-container');
        const paginationContainer = document.getElementById('pagination-container');
        const totalCount = document.getElementById('total-count');
        const totalUncompetiveCount = document.getElementById('total-uncompetive-count');

        if (loadingIndicator) loadingIndicator.style.display = 'block';
        if (tableContainer) tableContainer.style.opacity = '0.5';

        const formData = new FormData();
        formData.append('ajax', 'Y');
        formData.append('sessid', BX.bitrix_sessid());
        formData.append('action', 'update_table');
        formData.append('price_type', this.priceType);
        formData.append('page', this.currentPage);
        formData.append('sort', this.sortField);
        formData.append('order', this.sortOrder);
        formData.append('filter_article', this.filters.article);
		formData.append('filter_uncompetitive_price', this.filters.uncompetitive_price);

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (tableContainer) tableContainer.innerHTML = data.html;
                if (paginationContainer) paginationContainer.innerHTML = this.renderPagination(data.pagination);
                if (totalCount) totalCount.innerHTML = `Найдено записей: ${data.total_count}`;
                if (totalUncompetiveCount) totalUncompetiveCount.innerHTML = `Неконкурентных цен: ${data.total_uncompetive_count}`;
                
                this.initTableSorting();
                this.updateUrl();
            } else {
                console.error('Error loading data:', data.error);
            }
        })
        .catch(error => {
            console.error('Request failed:', error);
        })
        .finally(() => {
            if (loadingIndicator) loadingIndicator.style.display = 'none';
            if (tableContainer) tableContainer.style.opacity = '1';
        });
    },

    renderPagination: function(pagination) {
        if (pagination.total_pages <= 1) return '';

        let html = '<div class="pagination">';
        
        // Предыдущая страница
        if (pagination.current_page > 1) {
            html += `<button class="page-btn" onclick="PriceMonitor.goToPage(${pagination.current_page - 1})">‹</button>`;
        }

        // Номера страниц
        const startPage = Math.max(1, pagination.current_page - 2);
        const endPage = Math.min(pagination.total_pages, startPage + 4);

        for (let i = startPage; i <= endPage; i++) {
            const active = i === pagination.current_page ? ' active' : '';
            html += `<button class="page-btn${active}" onclick="PriceMonitor.goToPage(${i})">${i}</button>`;
        }

        // Следующая страница
        if (pagination.current_page < pagination.total_pages) {
            html += `<button class="page-btn" onclick="PriceMonitor.goToPage(${pagination.current_page + 1})">›</button>`;
        }

        html += '</div>';
        return html;
    },

    // Переход на страницу
    goToPage: function(page) {
        this.currentPage = page;
        this.loadTableData();
    },

    // Сортировка
    sortTable: function(field) {
        if (this.sortField === field) {
            this.sortOrder = this.sortOrder === 'asc' ? 'desc' : 'asc';
        } else {
            this.sortField = field;
            this.sortOrder = 'asc';
        }
        this.currentPage = 1;
        this.loadTableData();
    },

    // Применение фильтров
    applyFilters: function() {
        this.filters.article = document.getElementById('filter-article').value;
		this.filters.uncompetitive_price = document.getElementById('filter-uncompetitive-price').checked ? 'Y' : '';
        this.currentPage = 1;
        this.loadTableData();
    },

    // Сброс фильтров
    resetFilters: function() {
        document.getElementById('filter-article').value = '';
        document.getElementById('filter-uncompetitive-price').checked = false;
        this.filters.article = '';
        this.currentPage = 1;
        this.loadTableData();
    },

    // Инициализация сортировки таблицы
    initTableSorting: function() {
        const sortableHeaders = document.querySelectorAll('.sortable');
        sortableHeaders.forEach(header => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', () => {
                const sortField = header.dataset.sort;
                this.sortTable(sortField);
            });
        });
    },

    // Обновление URL
    updateUrl: function() {
        const url = new URL(window.location.href);
        url.searchParams.set('price_type', this.priceType);
        url.searchParams.set('page', this.currentPage);
        url.searchParams.set('sort', this.sortField);
        url.searchParams.set('order', this.sortOrder);
        
        if (this.filters.article) {
            url.searchParams.set('filter_article', this.filters.article);
        } else {
            url.searchParams.delete('filter_article');
        }
        
        if (this.filters.uncompetitive_price) {
            url.searchParams.set('filter_uncompetitive_price', this.filters.uncompetitive_price);
        } else {
            url.searchParams.delete('filter_uncompetitive_price');
        }
		
        window.history.replaceState({}, '', url.toString());
    }
};

let currentSettingsPriceType = '<?= $arResult['PRICE_TYPE_ACTIVE'] ?>';

document.addEventListener('DOMContentLoaded', function() {
    // Загрузка начальных данных
    PriceMonitor.loadTableData();

    // Обработчик изменения типа цены
    document.getElementById('price-type-select').addEventListener('change', function() {
        PriceMonitor.priceType = this.value;
        PriceMonitor.currentPage = 1;
        PriceMonitor.loadTableData();
    });

    // Обработчики фильтров
    document.getElementById('filter-article').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            PriceMonitor.applyFilters();
        }
    });

});

function applyFilters() {
    PriceMonitor.applyFilters();
}

function resetFilters() {
    PriceMonitor.resetFilters();
}

function openMonitoringSettings() {
    document.getElementById('monitoring-settings-modal').style.display = 'flex';
    
    // Устанавливаем текущий тип цены из основного селектора
    const settingsPriceSelect = document.getElementById('settings-price-type-select');
    const mainPriceSelect = document.getElementById('price-type-select');
    settingsPriceSelect.value = mainPriceSelect.value;
    
    loadMonitoringSettings();
}

function loadMonitoringSettings() {
    const container = document.getElementById('brand-discounts-container');
    if (!container) return;

    // Получаем выбранный тип цены из селектора
    const priceTypeSelect = document.getElementById('settings-price-type-select');
    const selectedPriceType = priceTypeSelect.value;
    
    container.innerHTML = '<div class="loading">Загрузка настроек для ' + getPriceTypeName(selectedPriceType) + '...</div>';

    const formData = new FormData();
    formData.append('ajax', 'Y');
    formData.append('sessid', BX.bitrix_sessid());
    formData.append('action', 'get_monitoring_settings');
    formData.append('price_type', selectedPriceType); // Используем выбранный тип цены

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderMonitoringSettings(data.settings);
        } else {
            container.innerHTML = '<div class="error">Ошибка загрузки настроек</div>';
        }
    })
    .catch(error => {
        console.error('Error loading monitoring settings:', error);
        container.innerHTML = '<div class="error">Ошибка загрузки</div>';
    });
}

function renderMonitoringSettings(settings) {
    // Заполняем общие настройки
    document.getElementById('settings-margin').value = settings.margin || -1;
    document.getElementById('settings-min-margin-percent').value = settings.min_margin_percent || 0;
    document.getElementById('settings-min-margin-rub').value = settings.min_margin_rub || 0;
    document.getElementById('settings-max-margin-percent').value = settings.max_margin_percent || 0;
    document.getElementById('settings-co-invest').value = settings.co_invest || 0;
    document.getElementById('settings-mp-commission').value = settings.mp_commission || 0;
    document.getElementById('settings-apply-min-margin').checked = settings.apply_min_margin || false;
    document.getElementById('settings-apply-rrp').checked = settings.apply_rrp || false;
    document.getElementById('settings-take-market-prices').checked = settings.take_market_prices || false;
    document.getElementById('settings-min-margin-fail-percent').value = settings.min_margin_fail_percent || 0;

    renderBrandDiscountsSettings(settings);
}

function renderBrandDiscountsSettings(settings) {
    const container = document.getElementById('brand-discounts-container');
    const brandDiscounts = settings.brand_discounts || [];
    
    container.innerHTML = '';
    
    if (brandDiscounts.length === 0) {
        addBrandDiscountRule();
    } else {
        brandDiscounts.forEach((rule, index) => {
            addBrandDiscountRule(rule, index);
        });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const settingsPriceSelect = document.getElementById('settings-price-type-select');
    if (settingsPriceSelect) {
        settingsPriceSelect.addEventListener('change', function() {
            loadMonitoringSettings();
        });
    }
});

function saveMonitoringSettings() {
    const priceTypeSelect = document.getElementById('settings-price-type-select');
    const selectedPriceType = priceTypeSelect.value;
    
    const brandDiscounts = collectBrandDiscounts();
    
    const formData = new FormData();
    formData.append('ajax', 'Y');
    formData.append('sessid', BX.bitrix_sessid());
    formData.append('action', 'save_monitoring_settings');
    formData.append('price_type', selectedPriceType); // Используем выбранный тип цены
    formData.append('margin', document.getElementById('settings-margin').value);
    formData.append('min_margin_percent', document.getElementById('settings-min-margin-percent').value);
    formData.append('min_margin_rub', document.getElementById('settings-min-margin-rub').value);
    formData.append('max_margin_percent', document.getElementById('settings-max-margin-percent').value);
    formData.append('co_invest', document.getElementById('settings-co-invest').value);
    formData.append('mp_commission', document.getElementById('settings-mp-commission').value);
    formData.append('min_margin_fail_percent', document.getElementById('settings-min-margin-fail-percent').value);
	
	if (document.getElementById('settings-apply-rrp').checked) {
		formData.append('apply_rrp', 'Y');
	} else {
		formData.append('apply_rrp', 'N');
	}
	if (document.getElementById('settings-take-market-prices').checked) {
		formData.append('take_market_prices', 'Y');
	} else {
		formData.append('take_market_prices', 'N');
	}
	
	if (document.getElementById('settings-apply-min-margin').checked) {
		formData.append('apply_min_margin', 'Y');
	} else {
		formData.append('apply_min_margin', 'N');
	}
	
    formData.append('brand_discounts', JSON.stringify(brandDiscounts));

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Настройки сохранены для типа цены: ' + getPriceTypeName(selectedPriceType));
            closeModal('monitoring-settings-modal');
        } else {
            alert('Ошибка сохранения: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error saving monitoring settings:', error);
        alert('Ошибка сохранения настроек');
    });
}

function openCompetitorsSettings() {
    document.getElementById('competitors-settings-modal').style.display = 'flex';
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(event) {
            if (event.target === this) {
                closeModal(this.id);
            }
        });
    });
});

document.getElementById('monitoring-settings-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
	
    const brandDiscounts = collectBrandDiscounts();
    console.log('Sending brand discounts:', brandDiscounts);
	
	formData.append('brand_discounts', JSON.stringify(brandDiscounts));
    formData.append('ajax', 'Y');
    formData.append('sessid', BX.bitrix_sessid());
    formData.append('action', 'save_monitoring_settings');

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderCompetitorsList(data.competitors || []);
        } else {
            container.innerHTML = '<div class="error">Ошибка загрузки списка конкурентов</div>';
        }
    })
    .catch(error => {
        console.error('Error loading competitors:', error);
        container.innerHTML = '<div class="error">Ошибка загрузки</div>';
    });
    /*BX.ajax.runComponentAction('custom:price.monitoring', 'updateMonitoringSettings', {
        mode: 'class',
        data: Object.fromEntries(formData)
    }).then(function(response) {
        if (response.data.success) {
            alert('Настройки сохранены');
            closeModal('monitoring-settings-modal');
        }
    });*/
});

// Загрузка списка конкурентов
function loadCompetitorsList() {
    const container = document.getElementById('competitors-list-container');
    if (!container) return;

    container.innerHTML = '<div class="loading">Загрузка списка конкурентов...</div>';

    const formData = new FormData();
    formData.append('ajax', 'Y');
    formData.append('sessid', BX.bitrix_sessid());
    formData.append('action', 'get_competitors_list');

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderCompetitorsList(data.competitors || []);
        } else {
            container.innerHTML = '<div class="error">Ошибка загрузки списка конкурентов</div>';
        }
    })
    .catch(error => {
        console.error('Error loading competitors:', error);
        container.innerHTML = '<div class="error">Ошибка загрузки</div>';
    });
}

function getPriceTypeName(type) {
    const types = {
        'ru': 'RU Сайт',
        'by': 'BY Сайт',
        'os': 'OZON', 
        'yandex': 'Яндекс Маркет'
    };
    return types[type] || type;
}

function renderCompetitorsList(competitors) {
    const container = document.getElementById('competitors-list-container');
    
    if (!competitors || competitors.length === 0) {
        container.innerHTML = '<div class="no-data">Нет сохраненных конкурентов</div>';
        return;
    }

    let html = '<div class="competitors-list">';
    competitors.forEach(competitor => {
        const hasFile = competitor.PARSING_FILENAME && competitor.PARSING_FILENAME.trim() !== '';
        const fileStatus = hasFile ? 
            `<a href="/upload/competitor_prices/${competitor.PARSING_FILENAME}"><span class="file-status has-file" title="Файл: ${competitor.PARSING_FILENAME}">📁</span></a>` : 
            `<span class="file-status no-file" title="Файл не указан">❌</span>`;
        
        html += `
            <div class="competitor-item">
                <div class="competitor-info">
                    <strong>${competitor.NAME}</strong>
                    <span class="competitor-type">${getPriceTypeName(competitor.PRICE_TYPE)}</span>
                    ${fileStatus}
                    ${competitor.AUTO_PARSE == 'Y' ? `
                        <span class="competitor-type">Автопарсер</span>
                    ` : ''}
					<strong>${competitor.LAST_PARSE}</strong>
                </div>
                <div class="competitor-actions">
                    <button class="btn btn-small" onclick="editCompetitor(${competitor.ID}, '${competitor.NAME}', '${competitor.PRICE_TYPE}')">
                        ⚙️ Редактировать
                    </button>
                    ${hasFile ? `
                        <button class="btn btn-small btn-parse" onclick="runSingleParser(${competitor.ID}, '${competitor.NAME}')">
                            🔄 Парсить
                        </button>
                    ` : ''}
                    <button class="btn btn-small btn-danger" onclick="deleteCompetitor(${competitor.ID}, '${competitor.NAME}')">
                        🗑️ Удалить
                    </button>
                </div>
            </div>
        `;
    });
    html += '</div>';
    
    container.innerHTML = html;
}

// Редактирование конкурента
function editCompetitor(competitorId, competitorName, priceType) {
    const competitorData = {
        ID: competitorId,
        NAME: competitorName,
        PRICE_TYPE: priceType,
        MAPPING: [],
        SETTINGS: {}
    };
    
    showCompetitorDetail(competitorData);
    
    loadFullCompetitorData(competitorName);
}

function loadFullCompetitorData(competitorName) {
    const formData = new FormData();
    formData.append('ajax', 'Y');
    formData.append('sessid', BX.bitrix_sessid());
    formData.append('action', 'get_competitor_data');
    formData.append('competitor_name', competitorName);

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Обновляем форму полными данными
            showCompetitorDetail(data.competitor);
        } else {
            console.error('Error loading full competitor data:', data.error);
        }
    })
    .catch(error => {
        console.error('Error loading competitor data:', error);
    });
}

function editCompetitorFull(competitorId, competitorName) {
    const loadingIndicator = document.getElementById('competitor-detail');
    loadingIndicator.innerHTML = '<div class="loading">Загрузка данных конкурента...</div>';
    loadingIndicator.style.display = 'block';

    const formData = new FormData();
    formData.append('ajax', 'Y');
    formData.append('sessid', BX.bitrix_sessid());
    formData.append('action', 'get_competitor_data');
    formData.append('competitor_name', competitorName);

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showCompetitorDetail(data.competitor);
        } else {
            loadingIndicator.innerHTML = `<div class="error">Ошибка загрузки данных: ${data.error}</div>`;
        }
    })
    .catch(error => {
        console.error('Error loading competitor data:', error);
        loadingIndicator.innerHTML = '<div class="error">Ошибка загрузки данных</div>';
    });
}

function showCompetitorDetail(competitor) {
    const detailContainer = document.getElementById('competitor-detail');
    
    // Заполняем поля формы
    document.getElementById('edit-competitor-id').value = competitor.ID;
    document.getElementById('edit-competitor-name').value = competitor.NAME;
	if (competitor.AUTO_PARSE == 'Y') {
		document.getElementById('edit-competitor-autoparse').checked = true;
	} else {
		document.getElementById('edit-competitor-autoparse').checked = false;
	}
    console.log('vxcvcxv', competitor);
    document.getElementById('edit-competitor-price-type').value = competitor.PRICE_TYPE;
    document.getElementById('edit-parsing-filename').value = competitor.PARSING_FILENAME || '';
	
    // Преобразуем mapping в текстовый формат
    const mappings = competitor.MAPPING || [];
    document.getElementById('edit-competitor-mappings').value = Array.isArray(mappings) ? 
        mappings.join('\n') : '';
    
    // Заполняем исключенные бренды
    const excludedBrands = document.getElementById('edit-excluded-brands');
    const excludedIds = competitor.SETTINGS?.excluded_brands || [];
	console.log(competitor);
    Array.from(excludedBrands.options).forEach(option => {
        option.selected = excludedIds.includes(parseInt(option.value));
    });

    detailContainer.style.display = 'block';
    
    // Прокручиваем к форме редактирования
    detailContainer.scrollIntoView({ behavior: 'smooth' });
}

// Сохранение настроек конкурента
function saveCompetitorSettings() {
    const detailContainer = document.getElementById('competitor-detail');
    const competitorName = detailContainer.dataset.currentCompetitor;
    
    if (!competitorName) {
        alert('Ошибка: не выбран конкурент');
        return;
    }

    const mappings = document.getElementById('competitor-mappings').value
        .split('\n')
        .map(line => line.trim())
        .filter(line => line.length > 0);
    
    const excludedBrands = Array.from(document.getElementById('excluded-brands').selectedOptions)
        .map(option => parseInt(option.value));

    const formData = new FormData();
    formData.append('ajax', 'Y');
    formData.append('sessid', BX.bitrix_sessid());
    formData.append('action', 'save_competitor_data');
    formData.append('competitor_name', competitorName);
    formData.append('settings[mappings]', JSON.stringify(mappings));
    formData.append('settings[excluded_brands]', JSON.stringify(excluded_brands));

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Настройки конкурента сохранены');
            cancelCompetitorEdit();
        } else {
            alert('Ошибка сохранения: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error saving competitor data:', error);
        alert('Ошибка сохранения данных');
    });
}

// Сохранение данных конкурента
function saveCompetitorData() {
    const competitorId = document.getElementById('edit-competitor-id').value;
    const competitorName = document.getElementById('edit-competitor-name').value.trim();
    
	let competitorAutoparse = 'N';
	if (document.getElementById('edit-competitor-autoparse').checked) {
		competitorAutoparse = 'Y';
	}
    console.log('ssssssss', competitorAutoparse);
    if (!competitorName) {
        alert('Введите название конкурента');
        return;
    }

    const mappings = document.getElementById('edit-competitor-mappings').value
        .split('\n')
        .map(line => line.trim())
        .filter(line => line.length > 0);

    const excludedBrands = Array.from(document.getElementById('edit-excluded-brands').selectedOptions)
        .map(option => parseInt(option.value));

	const parsingFilename = document.getElementById('edit-parsing-filename').value.trim();
	
    const formData = new FormData();
    formData.append('ajax', 'Y');
    formData.append('sessid', BX.bitrix_sessid());
    formData.append('action', 'save_competitor_data');
    formData.append('id', competitorId);
    formData.append('name', competitorName);
    formData.append('autoparse', competitorAutoparse);
    formData.append('price_type', document.getElementById('edit-competitor-price-type').value);
    formData.append('parsing_filename', parsingFilename);
    formData.append('mappings', JSON.stringify(mappings));
    formData.append('settings', JSON.stringify({
        excluded_brands: excludedBrands
    }));

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Данные конкурента сохранены');
            loadCompetitorsList(); // Перезагружаем список
            cancelCompetitorEdit();
        } else {
            alert('Ошибка сохранения: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error saving competitor data:', error);
        alert('Ошибка сохранения данных');
    });
}

// Создание нового конкурента
function createNewCompetitor() {
    document.getElementById('create-competitor-modal').style.display = 'flex';
}

// Сохранение нового конкурента
function saveNewCompetitor() {
    const competitorName = document.getElementById('new-competitor-name').value.trim();
    
    if (!competitorName) {
        alert('Введите название конкурента');
        return;
    }
	
	const parsingFilename = document.getElementById('new-parsing-filename').value.trim();
	
    const formData = new FormData();
    formData.append('ajax', 'Y');
    formData.append('sessid', BX.bitrix_sessid());
    formData.append('action', 'save_competitor_data');
    formData.append('id', 0);
    formData.append('name', competitorName);
    formData.append('price_type', document.getElementById('new-competitor-price-type').value);
    formData.append('parsing_filename', parsingFilename);
    formData.append('mappings', JSON.stringify([]));
    formData.append('settings', JSON.stringify({ excluded_brands: [] }));

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Новый конкурент создан');
            closeModal('create-competitor-modal');
            document.getElementById('new-competitor-name').value = '';
            loadCompetitorsList(); // Перезагружаем список
        } else {
            alert('Ошибка создания: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error creating competitor:', error);
        alert('Ошибка создания конкурента');
    });
}

function cancelCompetitorEdit() {
    document.getElementById('competitor-detail').style.display = 'none';
    document.getElementById('edit-competitor-id').value = '';
    document.getElementById('edit-competitor-name').value = '';
}

function deleteCompetitor(competitorId, competitorName) {
    if (!confirm(`Вы уверены, что хотите удалить конкурента "${competitorName}"?`)) {
        return;
    }

    const formData = new FormData();
    formData.append('ajax', 'Y');
    formData.append('sessid', BX.bitrix_sessid());
    formData.append('action', 'delete_competitor');
    formData.append('competitor_id', competitorId);

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Конкурент удален');
            loadCompetitorsList(); // Перезагружаем список
        } else {
            alert('Ошибка удаления: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error deleting competitor:', error);
        alert('Ошибка удаления конкурента');
    });
}

function deleteCurrentCompetitor() {
    const competitorId = document.getElementById('edit-competitor-id').value;
    const competitorName = document.getElementById('edit-competitor-name').value;
    
    if (competitorId && competitorName) {
        deleteCompetitor(competitorId, competitorName);
        cancelCompetitorEdit();
    }
}

function openCompetitorsSettings() {
    document.getElementById('competitors-settings-modal').style.display = 'flex';
    loadCompetitorsList();
    cancelCompetitorEdit();
}

function runSingleParser(competitorId, competitorName) {
    if (!confirm(`Запустить парсинг для конкурента "${competitorName}"?`)) {
        return;
    }

    const modal = document.getElementById('competitors-settings-modal');
    const parseButton = modal.querySelector(`[onclick="runSingleParser(${competitorId}, '${competitorName}')"]`);
	
	let originalText = '';
	
    if (parseButton) {
        originalText = parseButton.innerHTML;
        parseButton.innerHTML = '⏳ Парсинг...';
        parseButton.disabled = true;
    } else {
		return;
	}

    const loadingIndicator = document.createElement('div');
    loadingIndicator.className = 'parse-loading';
    loadingIndicator.innerHTML = `<div class="loading-message">Парсинг ${competitorName}...</div>`;
    document.getElementById('competitors-list-container').appendChild(loadingIndicator);

    fetch('/local/cron/parser/competitor.php?competitor_id=' + competitorId)
        .then(response => response.text())
        .then(data => {
            if (loadingIndicator.parentNode) {
                loadingIndicator.parentNode.removeChild(loadingIndicator);
            }
            
            if (parseButton) {
                parseButton.innerHTML = originalText;
                parseButton.disabled = false;
            }
            
            showParseResult(competitorName, data);
        })
        .catch(error => {
            if (loadingIndicator.parentNode) {
                loadingIndicator.parentNode.removeChild(loadingIndicator);
            }
            
            if (parseButton) {
                parseButton.innerHTML = originalText;
                parseButton.disabled = false;
            }
            
            alert('Ошибка при парсинге: ' + error);
        });
}

function showParseResult(competitorName, result) {
    const resultDiv = document.createElement('div');
    resultDiv.className = 'parse-result';
    resultDiv.innerHTML = `
        <div class="parse-result-content">
            <h4>Результат парсинга: ${competitorName}</h4>
            <pre>${result}</pre>
            <button class="btn btn-small" onclick="this.parentNode.parentNode.remove()">Закрыть</button>
        </div>
    `;
    
    document.getElementById('competitors-list-container').appendChild(resultDiv);
}
window.addEventListener('click', function(event) {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        if (event.target === modal) {
            closeModal(modal.id);
        }
    });
});

function loadBrandDiscounts(settings) {
    const container = document.getElementById('brand-discounts-container');
    const brandDiscounts = settings.brand_discounts || [];
    
    container.innerHTML = '';
    
    if (brandDiscounts.length === 0) {
        // Добавляем пустое правило по умолчанию
        addBrandDiscountRule();
    } else {
        brandDiscounts.forEach((rule, index) => {
            addBrandDiscountRule(rule, index);
        });
    }
}

function addBrandDiscountRule(rule = {}, index = null) {
    const container = document.getElementById('brand-discounts-container');
    const ruleIndex = index !== null ? index : container.children.length;
    const brands = <?= json_encode($arResult['BRANDS']) ?>;
    
    let brandsOptions = '<option value="">Выберите бренд</option>';
    brands.forEach(brand => {
        const selected = rule.brand_id == brand.ID ? 'selected' : '';
        brandsOptions += `<option value="${brand.ID}" ${selected}>${brand.NAME}</option>`;
    });
    
    const ruleHtml = `
        <div class="discount-rule" data-index="${ruleIndex}" style="margin-bottom: 10px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
            <div class="rule-fields" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <select class="discount-brand form-control" style="width: 200px;">
                    ${brandsOptions}
                </select>
                <input type="number" class="discount-min-price form-control" 
                       value="${rule.min_price || ''}" placeholder="Мин. цена" step="0.01" min="0" style="width: 120px;">
                <span>—</span>
                <input type="number" class="discount-max-price form-control" 
                       value="${rule.max_price || ''}" placeholder="Макс. цена" step="0.01" min="0" style="width: 120px;">
                <input type="number" class="discount-percent form-control" 
                       value="${rule.discount || ''}" placeholder="Скидка %" step="0.1" min="0" max="100" style="width: 100px;">
                <button type="button" class="btn btn-danger btn-small" 
                        onclick="removeBrandDiscountRule(this)">🗑️</button>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', ruleHtml);
}

function removeBrandDiscountRule(button) {
    const rule = button.closest('.discount-rule');
    if (rule) {
        rule.remove();
        reindexDiscountRules();
    }
}

function reindexDiscountRules() {
    const container = document.getElementById('brand-discounts-container');
    const rules = container.querySelectorAll('.discount-rule');
    rules.forEach((rule, newIndex) => {
        rule.setAttribute('data-index', newIndex);
    });
}

function collectBrandDiscounts() {
    const rules = document.querySelectorAll('.discount-rule');
    const brandDiscounts = [];
    
    rules.forEach(rule => {
        const brandSelect = rule.querySelector('.discount-brand');
        const minPriceInput = rule.querySelector('.discount-min-price');
        const maxPriceInput = rule.querySelector('.discount-max-price');
        const discountInput = rule.querySelector('.discount-percent');
        
        const brandId = brandSelect ? brandSelect.value.trim() : '';
        const minPrice = minPriceInput ? minPriceInput.value.trim() : '';
        const maxPrice = maxPriceInput ? maxPriceInput.value.trim() : '';
        const discount = discountInput ? discountInput.value.trim() : '';
        
        if (brandId && minPrice && maxPrice && discount) {
            brandDiscounts.push({
                brand_id: parseInt(brandId),
                brand_name: brandSelect.options[brandSelect.selectedIndex].text,
                min_price: parseFloat(minPrice),
                max_price: parseFloat(maxPrice),
                discount: parseFloat(discount)
            });
        }
    });
    
    console.log('Collected brand discounts:', brandDiscounts);
    return brandDiscounts;
}
</script>