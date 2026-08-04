<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
$this->setFrameMode(true);

use Bitrix\Main\Page\Asset;

// Подключаем CSS и JS
Asset::getInstance()->addCss("/bitrix/templates/admin_courier/css/datatables.min.css");
Asset::getInstance()->addCss("/bitrix/templates/admin_courier/css/datepicker.css");
Asset::getInstance()->addCss("/bitrix/templates/admin_courier/css/jquery-ui.min.css");
Asset::getInstance()->addCss($this->GetFolder() . '/style.css');

Asset::getInstance()->addJs("/bitrix/templates/admin_courier/js/jquery-ui.min.js");
Asset::getInstance()->addJs("/bitrix/templates/admin_courier/js/bootstrap.js");
Asset::getInstance()->addJs("/bitrix/templates/admin_panel/js/jquery-ui-timepicker-addon.js");
Asset::getInstance()->addJs("/bitrix/templates/admin_panel/js/moment.min.js");
Asset::getInstance()->addJs("https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js");
Asset::getInstance()->addJs($this->GetFolder() . '/script.js');

// Получаем текущий URL страницы
$currentPageUrl = $GLOBALS['APPLICATION']->GetCurPage();
?>

<div class="barcode-manager-component" data-page-url="<?= $currentPageUrl ?>">
    <h1 class="page-header">Сборка</h1>
    
    <div class="component-controls">
        <div class="row">
            <div class="col-sm-2">
                <a href="/admin/utilities/" class="btn btn-default">Назад</a>
            </div>
            <div class="col-sm-10">
                <!-- Форма загрузки маркетплейсов -->
                <form class="ajax-form" data-action="load_marketplace_orders" style="float: right;">
                    <input type="hidden" name="group-result" value="Y">
                    <input type="text" name="date_to" class="form-control datepicker" 
                           value="<?= date('d.m.Y 14:00') ?>" style="float: left;width: 200px;margin: 10px 20px 0 0;">
                    <select name="cabinet[]" class="form-control select_w" style="width: 110px;" multiple>
                        <option value="WB_WR" selected>WB WR</option>
                        <option value="WB_IP">WB IP</option>
                        <option value="OZON_IP">OZON IP</option>
                    </select>
                    <button type="submit" class="btn btn-primary" style="margin:10px 0 10px 0;">Загрузить</button>
                </form>

                <!-- Форма загрузки Минска -->
                <form class="ajax-form" data-action="load_purchase_orders" style="float: right;">
                    <input type="hidden" name="group-result" value="Y">
                    <button type="submit" class="btn btn-primary" style="margin:10px 30px 10px 0;">Минск</button>
                </form>

                <div class="form-check" style="margin: 17px 0 0 0;float: left;">
                    <input type="checkbox" class="form-check-input" id="group-result" checked>
                    <label class="form-check-label" for="group-result">Сгруппировать</label>
                </div>
            </div>
        </div>
    </div>

    <hr>

    <!-- Форма ручного ввода -->
    <form class="ajax-form" data-action="load_manual_orders">
        <input type="hidden" name="group-result" value="Y">
        <input type="hidden" name="is-yandex" value="N">
        <input type="hidden" name="use-id" value="N">
        
        <div class="row">
            <div class="col-sm-3">
                <textarea name="article_all" class="form-control" style="min-height: 150px;" 
                          placeholder="Введите номера заказов или артикулы"></textarea>
                <button type="submit" class="btn btn-primary" style="margin:10px 0 10px 0;">Загрузить</button>
            </div>
            <div class="col-sm-3">
                <input type="text" class="form-control scan-barcode" name="scan_barcode" 
                       placeholder="Сканирование штрихкода" value="">
            </div>
            <div class="col-sm-3">
                <div id="scan-barcode-history"></div>
            </div>
        </div>
    </form>

    <!-- Блок ошибок -->
    <div id="errors-container" class="alert alert-danger" style="display: none;"></div>

    <!-- Статистика -->
    <div id="stat-line" class="stats-container">
        <span class="all">Строк: 0</span>
        <span class="print" style="margin-left: 20px;">Напечатано стикеров: 0</span>
    </div>

    <!-- Таблица данных -->
    <div class="table-container">
        <table class="table table-striped" id="barcode-table">
            <thead>
                <tr>
                    <th>Фото</th>
                    <th>Артикул</th>
                    <th>ШК</th>
                    <th>Действия</th>
                    <th>Sticker</th>
                    <th>№ заказа</th>
                    <th>Комментарий</th>
                    <th>Склад</th>
                </tr>
            </thead>
            <tbody>
                <!-- Данные будут загружаться через AJAX -->
            </tbody>
        </table>
    </div>

    <!-- Групповые заказы -->
    <div id="group-orders" class="group-orders-panel"></div>

    <!-- Loading overlay -->
    <div class="loading-overlay" style="display: none;">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    const pageUrl = $('.barcode-manager-component').data('page-url');
    
    // Инициализация datepicker
    if (typeof $.fn.datetimepicker === 'function') {
        $('.datepicker').datetimepicker({
            format: "d.m.Y H:i",
            autoclose: true,
            startDate: new Date()
        });
    }

    // Обработка AJAX форм
    $('.ajax-form').on('submit', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const action = form.data('action');
        const formData = form.serialize();
        
        loadData(action, formData);
    });

    // Загрузка данных
    function loadData(action, data) {
        showLoading();
        
        $.ajax({
            url: pageUrl, // Отправляем на текущую страницу
            type: 'POST',
            data: data + '&action=' + action,
            dataType: 'json',
            success: function(response) {
                hideLoading();
                
                if (response.status === 'success') {
                    displayData(response.data);
                    updateStats(response.data.stats);
                    clearErrors();
                } else {
                    showError(response.message);
                }
            },
            error: function(xhr, status, error) {
                hideLoading();
                showError('Ошибка загрузки: ' + error);
                
                // Для отладки
                console.error('AJAX Error:', error);
                console.error('Status:', status);
                console.error('Response:', xhr.responseText);
            }
        });
    }

    // Отображение данных в таблице
    function displayData(data) {
        const tbody = $('#barcode-table tbody');
        tbody.empty();

        if (data.orders && data.orders.length > 0) {
            data.orders.forEach(order => {
				const item = data.items[order.PRODUCT_ID];
                const row = `
                    <tr data-product-id="${order.PRODUCT_ID}" data-article="${order.ARTICLE}">
                        <td><a href="${item.DETAIL_PAGE_URL}" target="_blank"><img data-src="${item.PICTURE_SRC}" class="lazy img-item"></a></td>
                        <td>${order.ARTICLE}</td>
                        <td>
                            <input type="text" class="form-control barcode-input" 
                                   value="${order.BARCODE}" data-product-id="${order.PRODUCT_ID}">
                            <button class="btn btn-sm btn-primary save-barcode" 
                                    data-product-id="${order.PRODUCT_ID}">Save</button>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-primary get-barcode">Наш ШК</button>
                            <button class="btn btn-sm btn-primary print-barcode">Печать</button>
                        </td>
                        <td>${order.STICKER_HTML || ''}</td>
                        <td>${order.ORDER_NUMBER}</td>
                        <td>${order.COMMENTS || ''}</td>
                        <td>${order.WAREHOUSE_INFO || ''}</td>
                    </tr>
                `;
                tbody.append(row);
            });
        }

        initDataTable();
    }

    function initDataTable() {
        if ($.fn.DataTable && $.fn.DataTable.isDataTable('#barcode-table')) {
            $('#barcode-table').DataTable().destroy();
        }
        
        if ($.fn.DataTable) {
            $('#barcode-table').DataTable({
                searching: false,
                paging: false,
                info: false,
                ordering: true,
                language: {
                    decimal: ",",
                    thousands: "."
                }
            });
        }
    }

    // Обработчики действий
    $(document).on('click', '.get-barcode', function() {
        const productId = $(this).closest('tr').data('product-id');
        getBarcode(productId);
    });

    $(document).on('click', '.save-barcode', function() {
        const productId = $(this).data('product-id');
        const barcode = $(this).closest('td').find('.barcode-input').val();
        setBarcode(productId, barcode);
    });

    $(document).on('click', '.print-barcode', function() {
        const productId = $(this).closest('tr').data('product-id');
        const barcode = $(this).closest('tr').find('.barcode-input').val();
        printBarcode(productId, barcode);
    });

    function getBarcode(productId) {
        $.ajax({
            url: pageUrl,
            type: 'POST',
            data: { action: 'get_barcode', product_id: productId },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    $(`tr[data-product-id="${productId}"] .barcode-input`).val(response.barcode);
                    showMessage('Штрихкод получен');
                } else {
                    showError(response.message);
                }
            },
            error: function(xhr, status, error) {
                showError('Ошибка получения штрихкода: ' + error);
            }
        });
    }

    function setBarcode(productId, barcode) {
        $.ajax({
            url: pageUrl,
            type: 'POST',
            data: { action: 'set_barcode', product_id: productId, barcode: barcode },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    showMessage('Штрихкод сохранен');
                    $(`tr[data-product-id="${productId}"]`).addClass('table-success');
                    setTimeout(() => {
                        $(`tr[data-product-id="${productId}"]`).removeClass('table-success');
                    }, 2000);
                } else {
                    showError(response.message);
                }
            },
            error: function(xhr, status, error) {
                showError('Ошибка сохранения штрихкода: ' + error);
            }
        });
    }

    function printBarcode(productId, barcode) {
        // Логика печати штрихкода
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
                <head>
                    <title>Печать штрихкода</title>
                </head>
                <body onload="window.print(); window.close();">
                    <div style="text-align: center; padding: 20px;">
                        <h3>Штрихкод: ${barcode}</h3>
                        <p>Товар ID: ${productId}</p>
                        <img src="https://barcode.tec-it.com/barcode.ashx?data=${barcode}&code=Code128&dpi=96" 
                             alt="Barcode" style="max-width: 300px;">
                    </div>
                </body>
            </html>
        `);
        printWindow.document.close();
    }

    // Обработка сканирования
    $('.scan-barcode').on('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const barcode = $(this).val().trim();
            if (barcode) {
                processScannedBarcode(barcode);
                $(this).val('');
            }
        }
    });

    function processScannedBarcode(barcode) {
        $.ajax({
            url: pageUrl,
            type: 'POST',
            data: { action: 'scan_barcode', barcode: barcode },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    $('#scan-barcode-history').prepend(
                        `<div class="scan-history-item">${new Date().toLocaleTimeString()}: ${response.message}</div>`
                    );
                } else {
                    showError(response.message);
                }
            },
            error: function(xhr, status, error) {
                showError('Ошибка обработки штрихкода: ' + error);
            }
        });
    }

    // Вспомогательные функции
    function showLoading() {
        $('.loading-overlay').show();
    }

    function hideLoading() {
        $('.loading-overlay').hide();
    }

    function showError(message) {
        $('#errors-container').text(message).show();
        setTimeout(() => $('#errors-container').hide(), 5000);
    }

    function showMessage(message) {
        // Временное сообщение в консоли
        console.log('Message:', message);
    }

    function clearErrors() {
        $('#errors-container').hide().empty();
    }

    function updateStats(stats) {
        if (stats) {
            $('.all').text('Строк: ' + (stats.total || 0));
            $('.print').text('Напечатано стикеров: ' + (stats.printed || 0));
        }
    }
});
</script>