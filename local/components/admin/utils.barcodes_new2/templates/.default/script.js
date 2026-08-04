class BarcodeManager {
    constructor() {
        // Исправлено: data('page-url') вместо data('data-page-url')
        this.componentPath = $('.barcode-manager-component').data('page-url');
        this.init();
    }

    init() {
        this.bindEvents();
        this.initScanner();
        this.initDatepicker();
        
        // Для отладки
        console.log('Component path:', this.componentPath);
    }

    initDatepicker() {
        if ($.fn.datetimepicker) {
            $('.datepicker').datetimepicker({
                format: "d.m.Y H:i",
                autoclose: true,
                startDate: new Date()
            });
        }
    }

    bindEvents() {
        // Обработчики форм
        $('.ajax-form').on('submit', (e) => this.handleFormSubmit(e));
        
        // Обработчики кнопок в таблице
        $(document).on('click', '.get-barcode', (e) => this.handleGetBarcode(e));
        $(document).on('click', '.save-barcode', (e) => this.handleSaveBarcode(e));
        $(document).on('click', '.print-barcode', (e) => this.handlePrintBarcode(e));
    }

    initScanner() {
        $('.scan-barcode').on('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.processScannedBarcode($(e.target).val());
                $(e.target).val('');
            }
        });
    }

    async handleFormSubmit(e) {
        e.preventDefault();
        const form = $(e.target);
        const action = form.data('action');
        const formData = form.serialize();
        
        this.showLoading();
        
        try {
            console.log('Sending request to:', this.componentPath);
            console.log('Action:', action);
            console.log('Data:', formData);
            
            const response = await this.ajaxRequest(action, formData);
            this.hideLoading();
            
            if (response.status === 'success') {
                this.displayData(response.data);
                this.updateStats(response.data.stats);
                this.clearErrors();
            } else {
                this.showError(response.message);
            }
        } catch (error) {
            this.hideLoading();
            this.showError('Ошибка загрузки: ' + error);
            console.error('Request error:', error);
        }
    }

    async handleGetBarcode(e) {
        const productId = $(e.target).closest('tr').data('product-id');
        try {
            const response = await this.ajaxRequest('get_barcode', { product_id: productId });
            if (response.status === 'success') {
                $(e.target).closest('tr').find('.barcode-input').val(response.barcode);
                this.showMessage('Штрихкод получен');
            }
        } catch (error) {
            this.showError('Ошибка получения штрихкода');
        }
    }

    async handleSaveBarcode(e) {
        const productId = $(e.target).data('product-id');
        const barcode = $(e.target).closest('td').find('.barcode-input').val();
        
        if (!barcode) {
            this.showError('Введите штрихкод');
            return;
        }
        
        try {
            const response = await this.ajaxRequest('set_barcode', { 
                product_id: productId, 
                barcode: barcode 
            });
            
            if (response.status === 'success') {
                this.showMessage('Штрихкод сохранен');
                $(e.target).closest('tr').addClass('table-success');
                setTimeout(() => {
                    $(e.target).closest('tr').removeClass('table-success');
                }, 2000);
            }
        } catch (error) {
            this.showError('Ошибка сохранения штрихкода');
        }
    }

    async processScannedBarcode(barcode) {
        if (!barcode) return;
        
        try {
            const response = await this.ajaxRequest('scan_barcode', { barcode: barcode });
            this.updateScanHistory(response);
        } catch (error) {
            this.showError('Ошибка обработки штрихкода');
        }
    }

	async ajaxRequest(action, data = {}) {
		const formData = new URLSearchParams();
		formData.append('action', action);
		formData.append('ajax', 'Y'); // Важно для идентификации AJAX
		
		if (typeof data === 'string') {
			const params = new URLSearchParams(data);
			for (let [key, value] of params) {
				formData.append(key, value);
			}
		} else {
			for (const key in data) {
				if (data.hasOwnProperty(key)) {
					formData.append(key, data[key]);
				}
			}
		}

		try {
			const response = await fetch(this.componentPath, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: formData,
				credentials: 'same-origin'
			});

			if (!response.ok) {
				throw new Error(`HTTP error! status: ${response.status}`);
			}

			const text = await response.text();
			
			// Пытаемся распарсить JSON
			try {
				return JSON.parse(text);
			} catch (e) {
				console.error('Invalid JSON response:', text);
				throw new Error('Invalid JSON response from server');
			}
			
		} catch (error) {
			console.error('Request failed:', error);
			throw error;
		}
	}

    displayData(data) {
        const tbody = $('#barcode-table tbody');
        tbody.empty();

        if (data.orders && data.orders.length > 0) {
            data.orders.forEach(order => {
                const row = `
                    <tr data-product-id="${order.PRODUCT_ID}" data-article="${order.ARTICLE}">
                        <td>${order.PHOTO_HTML || ''}</td>
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

        this.initDataTable();
    }

    initDataTable() {
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

    updateStats(stats) {
        if (stats) {
            $('.all').text('Строк: ' + (stats.total || 0));
            $('.print').text('Напечатано стикеров: ' + (stats.printed || 0));
        }
    }

    updateScanHistory(response) {
        if (response.status === 'success') {
            $('#scan-barcode-history').prepend(
                `<div class="scan-history-item">${new Date().toLocaleTimeString()}: ${response.message}</div>`
            );
        }
    }

    showLoading() {
        $('.loading-overlay').show();
    }

    hideLoading() {
        $('.loading-overlay').hide();
    }

    showError(message) {
        $('#errors-container').text(message).show();
        setTimeout(() => $('#errors-container').hide(), 5000);
    }

    showMessage(message) {
        // Можно реализовать toast-уведомления
        console.log('Message:', message);
    }

    clearErrors() {
        $('#errors-container').hide().empty();
    }
}

// Инициализация при загрузке документа
$(document).ready(() => {
    // Проверяем, что элемент существует
    if ($('.barcode-manager-component').length) {
        new BarcodeManager();
    } else {
        console.error('Barcode manager component not found');
    }
});