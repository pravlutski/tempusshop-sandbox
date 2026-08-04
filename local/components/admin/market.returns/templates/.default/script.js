document.addEventListener('DOMContentLoaded', function() {
    const orderNumberInput = document.getElementById('orderNumber');
    const barcodeInput = document.getElementById('barcode');
    const articleInput = document.getElementById('article');
    const productIdInput = document.getElementById('product_id');
    const salesChannelSelect = document.getElementById('salesChannel');
    const findButton = document.getElementById('findButton');
    const processButton = document.getElementById('processButton');
    const resultsSection = document.querySelector('.returns-mp-results-section');
    const validationStatus = document.querySelector('.validation-status');
    const orderDetails = document.getElementById('orderDetails');
    const saveBarcodeBtn = document.getElementById('saveBarcodeBtn');
	
    const tabButtons = document.querySelectorAll('.tab-btn');
    const warehouseSelect = document.getElementById('warehouse');
	
    let currentOrderData = null;
    let currentShipmentData = null;
    let isBarcodeRequestInProgress = false;
	let currentTab = 's1';
	
    function loadTabData(tab) {
        currentTab = tab;
        
        tabButtons.forEach(btn => {
            if (btn.dataset.tab === tab) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });
        
        loadSalesChannels(tab);
        loadWarehouses(tab);
        resetForm();
    }
	
    function loadSalesChannels(tab) {
        makeAjaxRequest('get_sales_channels', {})
            .then(response => {
                if (response.channels && Array.isArray(response.channels)) {
                    salesChannelSelect.innerHTML = '<option value="">Выберите канал</option>';
                    
                    response.channels.forEach(channel => {
                        const option = document.createElement('option');
                        option.value = channel.MS_ID;
                        option.textContent = `${channel.NAME} (${channel.SITE_ID})`;
                        salesChannelSelect.appendChild(option);
                    });
                    
                    if (tab === 's2') {
                        const wbOption = Array.from(salesChannelSelect.options)
                            .find(opt => opt.textContent.includes('WB') || opt.textContent.includes('s2'));
                        if (wbOption) {
                            salesChannelSelect.value = wbOption.value;
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Ошибка загрузки каналов продаж:', error);
            });
    }
    
    function loadWarehouses(tab) {
        makeAjaxRequest('get_warehouses', {})
            .then(response => {
                if (response.warehouses) {
                    warehouseSelect.innerHTML = '';
                    
                    for (const [id, name] of Object.entries(response.warehouses)) {
                        const option = document.createElement('option');
                        option.value = id;
                        option.textContent = name;
                        warehouseSelect.appendChild(option);
                    }
                }
            })
            .catch(error => {
                console.error('Ошибка загрузки складов:', error);
            });
    }
    
    tabButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const tab = this.dataset.tab;
            if (tab !== currentTab) {
                loadTabData(tab);
            }
        });
    });
	
    function showNotification(message, type = 'info') {
        const existingNotifications = document.querySelectorAll('.custom-notification');
        existingNotifications.forEach(notification => notification.remove());
        
        const notification = document.createElement('div');
        notification.className = `custom-notification ${type}`;
        notification.textContent = message;
        
        notification.style.position = 'fixed';
        notification.style.top = '20px';
        notification.style.right = '20px';
        notification.style.padding = '12px 20px';
        notification.style.borderRadius = '4px';
        notification.style.color = 'white';
        notification.style.zIndex = '10000';
        notification.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
        notification.style.maxWidth = '400px';
        
        if (type === 'error') {
            notification.style.background = '#f44336';
        } else if (type === 'success') {
            notification.style.background = '#4caf50';
        } else {
            notification.style.background = '#2196f3';
        }
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 5000);
    }
    
    // Функция для выполнения AJAX-запросов
    function makeAjaxRequest(action, data = {}) {
        findButton.disabled = true;
        findButton.textContent = 'Загрузка...';
        
        const formData = new FormData();
        formData.append('action', action);
        formData.append('cabinet', currentTab);
		
        for (const key in data) {
            formData.append(key, data[key]);
        }
		
        return fetch('/local/components/admin/market.returns/actions.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Ошибка сети');
            }
            return response.json();
        })
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Произошла ошибка');
            }
            return data;
        })
        .catch(error => {
            showNotification(error.message, 'error');
            throw error;
        })
        .finally(() => {
            // Восстанавливаем кнопку
            findButton.disabled = false;
            findButton.textContent = 'Найти';
        });
    }
    
    // Обработка изменения ШК - поиск артикула
	function processBarcode(barcode) {
		if (isBarcodeRequestInProgress) return;
		
		if (barcode !== '' && orderNumberInput.value.trim() === '') {
			isBarcodeRequestInProgress = true;
			
			// Показываем индикатор загрузки
			validationStatus.className = 'validation-status loading';
			barcodeInput.disabled = true;
			
			makeAjaxRequest('get_article', {
				barcode: barcode
			})
				.then(response => {
					if (response.article) {
						// Артикул найден
						articleInput.value = response.article;
						productIdInput.value = response.productId || '';
						
						validationStatus.className = 'validation-status valid';
						showNotification('Артикул найден по ШК', 'success');
						
						saveBarcodeBtn.style.display = 'none';
						
						/*if (salesChannelSelect.value === '') {
							salesChannelSelect.focus();
						} else {
							findButton.focus();
						}*/
						if (salesChannelSelect.value !== '') {
							setTimeout(() => {
								findButton.click();
							}, 300);
						} else {
							salesChannelSelect.focus();
						}
					} else {
						// Артикул не найден
						validationStatus.className = 'validation-status invalid';
						showNotification('ШК не найден. Заполните артикул вручную', 'error');
						articleInput.focus();
						
						if (articleInput.value.trim() !== '') {
							saveBarcodeBtn.style.display = 'block';
						}
					}
				})
				.catch(error => {
					validationStatus.className = 'validation-status invalid';
					console.error('Ошибка при поиске по ШК:', error);
					
					if (articleInput.value.trim() !== '') {
						saveBarcodeBtn.style.display = 'block';
					}
				})
				.finally(() => {
					isBarcodeRequestInProgress = false;
					barcodeInput.disabled = false;
				});
		}
	}

	// вставки из буфера обмена
	barcodeInput.addEventListener('paste', function(e) {
		clearTimeout(barcodeTimeout);
		
		setTimeout(() => {
			const barcode = this.value.trim();
			if (barcode.length > 0) {
				processBarcode(barcode);
			}
		}, 100);
	});

	// Enter
	barcodeInput.addEventListener('keydown', function(e) {
		if (e.key === 'Enter') {
			e.preventDefault();
			clearTimeout(barcodeTimeout); // Очищаем таймаут
			const barcode = this.value.trim();
			if (barcode.length > 0) {
				processBarcode(barcode);
			}
		}
	});

	let barcodeTimeout;
	barcodeInput.addEventListener('input', function() {
		const barcode = this.value.trim();
		const article = articleInput.value.trim();
		
		clearTimeout(barcodeTimeout);
		
		if (barcode === '') {
			validationStatus.className = 'validation-status';
			articleInput.value = '';
			productIdInput.value = '';
			return;
		}
		
		if (barcode !== '' && article !== '' && validationStatus.className.includes('valid')) {
			saveBarcodeBtn.style.display = 'block';
		} else {
			saveBarcodeBtn.style.display = 'none';
		}
	
		if (barcode.length >= 8 && !isBarcodeRequestInProgress) {
			validationStatus.className = 'validation-status loading';
			
			barcodeTimeout = setTimeout(() => {
				if (!isBarcodeRequestInProgress) {
					processBarcode(barcode);
				}
			}, 1000);
		}
	});

	// потеря фокуса
	barcodeInput.addEventListener('blur', function() {
		clearTimeout(barcodeTimeout); // Очищаем таймаут
		const barcode = this.value.trim();
		if (barcode !== '' && !isBarcodeRequestInProgress) {
			processBarcode(barcode);
		}
	});
	
	/* end input barcode */
	
	// сохранение баркода
	saveBarcodeBtn.addEventListener('click', function() {
		const barcode = barcodeInput.value.trim();
		const article = articleInput.value.trim();
		
		if (barcode === '' || article === '') {
			showNotification('Заполните ШК и артикул', 'error');
			return;
		}
		
		saveBarcodeBtn.disabled = true;
		saveBarcodeBtn.textContent = 'Сохранение...';
		
		makeAjaxRequest('save_barcode', {
			barcode: barcode,
			article: article,
			productId: productIdInput.value
		})
		.then(response => {
			if (response.success) {
				showNotification('ШК успешно сохранен для товара', 'success');
				saveBarcodeBtn.style.display = 'none';
				
				// Автоматически запускаем поиск, если канал продаж выбран
				if (salesChannelSelect.value !== '') {
					setTimeout(() => {
						findButton.click();
					}, 300);
				}
			} else {
				showNotification('Ошибка при сохранении ШК', 'error');
			}
		})
		.catch(error => {
			showNotification('Ошибка при сохранении ШК', 'error');
			console.error('Ошибка:', error);
		})
		.finally(() => {
			saveBarcodeBtn.disabled = false;
			saveBarcodeBtn.textContent = 'Сохранить ШК';
		});
	});

    /*barcodeInput.addEventListener('blur', function() {
        const barcode = this.value.trim();
        
        if (barcode !== '' && orderNumberInput.value.trim() === '') {
            // Показываем индикатор загрузки
            validationStatus.className = 'validation-status loading';
            
            makeAjaxRequest('get_article', { barcode: barcode })
                .then(response => {
                    if (response.article) {
                        // Артикул найден
                        articleInput.value = response.article;
                        productIdInput.value = response.productId; 
						
                        validationStatus.className = 'validation-status valid';
                        showNotification('Артикул найден по ШК', 'success');
                    } else {
                        // Артикул не найден
                        validationStatus.className = 'validation-status invalid';
                        showNotification('ШК не найден. Заполните артикул вручную', 'error');
                        articleInput.focus();
                    }
                })
                .catch(error => {
                    validationStatus.className = 'validation-status invalid';
                    console.error('Ошибка при поиске по ШК:', error);
                });
        }
    });*/
    
    // Обработка изменения артикула - проверка существования
    articleInput.addEventListener('blur', function() {
        const article = this.value.trim();
        
        if (article !== '' && orderNumberInput.value.trim() === '') {
            // Показываем индикатор загрузки
            validationStatus.className = 'validation-status loading';
            
            makeAjaxRequest('check_article', {
				article: article
			})
                .then(response => {
                    if (response.exists) {
                        validationStatus.className = 'validation-status valid';
						productIdInput.value = response.productId; 
						
						if (barcodeInput.value.trim() !== '') {
							saveBarcodeBtn.style.display = 'block';
						}
                    } else {
                        validationStatus.className = 'validation-status invalid';
                        showNotification('Артикул не найден в системе', 'error');
						saveBarcodeBtn.style.display = 'none';
                    }
                })
                .catch(error => {
                    validationStatus.className = 'validation-status invalid';
                    console.error('Ошибка при проверке артикула:', error);
					saveBarcodeBtn.style.display = 'none';
                });
        }
    });
    
    // Обработка изменения номера заказа
    orderNumberInput.addEventListener('input', function() {
        const orderNumber = this.value.trim();
        
        if (orderNumber !== '') {
            salesChannelSelect.removeAttribute('required');
            salesChannelSelect.closest('.form-group').classList.remove('required');
        } else {
            salesChannelSelect.setAttribute('required', 'required');
            salesChannelSelect.closest('.form-group').classList.add('required');
        }
    });
    
    findButton.addEventListener('click', function() {
        const orderNumber = orderNumberInput.value.trim();
        const barcode = barcodeInput.value.trim();
        const article = articleInput.value.trim();
        const productId = productIdInput.value.trim();
        const salesChannel = salesChannelSelect.value;
        
        // Валидация формы
        if (orderNumber === '' && barcode === '' && article === '') {
            showNotification('Заполните номер заказа, ШК товара или артикул', 'error');
            return;
        }
        
        if (orderNumber === '' && salesChannel === '') {
            showNotification('При отсутствии номера заказа необходимо указать канал продаж', 'error');
            salesChannelSelect.focus();
            return;
        }
        
        if (orderNumber === '' && article === '') {
            showNotification('При отсутствии номера заказа необходимо указать артикул', 'error');
            articleInput.focus();
            return;
        }
        
		if (orderNumber === '') {
			$('.pre-submit-text').html('');
		} else {
			$('.pre-submit-text').html('<span>Заказ будет переведен в статус</span> <select id="orderStatusSelect" class="form-control" style="width: auto; display: inline-block; margin-left: 5px;"><option value="NZ">Отказ на этапе доставки</option><option value="F">Выполнен</option></select>');
		}
		
		
        const searchData = {
            orderNumber: orderNumber,
            barcode: barcode,
            article: article,
            productId: productId,
            salesChannel: salesChannel
        };
        
        makeAjaxRequest('find_order', searchData)
            .then(response => {
                if (response.order) {
                    // Заказ найден
                    currentOrderData = response.order;
                    currentShipmentData = response.shipment;
                    
                    displayOrderInfo(response.order, response.shipment);
                    
                    resultsSection.style.display = 'block';
                    
                    resultsSection.scrollIntoView({ behavior: 'smooth' });
                    
                    showNotification('Заказ найден', 'success');
                } else {
                    // Заказ не найден
                    showNotification('Заказ не найден', 'error');
                    resultsSection.style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Ошибка при поиске заказа:', error);
                resultsSection.style.display = 'none';
            });
    });
    
	function displayOrderInfo(order, shipment) {
		if (!orderDetails) return;
		
		let html = `
			<div class="order-info-card">
				<h4>Информация о заказе</h4>
				<p><strong>Заказ ${order.number}</strong> от ${order.date}</p>
		`;
		
		if (shipment) {
			html += `<p><strong>Отгрузка ${shipment.number}</strong> от ${shipment.date}</p>`;
		}
		
		if (order.products && order.products.length > 0) {
			html += `
				<div class="products-info">
					<h5>Товары в заказе:</h5>
					<div class="products-list">
			`;
			
			order.products.forEach((product, index) => {
				// Проверяем, есть ли информация о возвратах
				const hasReturns = product.returns && Object.keys(product.returns).length > 0;
				//const isDisabled = hasReturns && product.max_quantity == 0 ? 'disabled' : '';
				//const isChecked = hasReturns ? '' : 'checked';
				const isDisabled = product.max_quantity == 0 ? 'disabled' : '';
				const isChecked = product.checked ? 'checked' : '';
				// ${hasReturns ? 'has-returns' : ''}
				html += `
					<div class="product-item ${index > 0 ? 'product-divider' : ''}">
						<div class="wrap-checked">
							<input type="checkbox" class="form-control" 
								   data-id="${product.assortment_id}" 
								   ${isChecked} ${isDisabled}>
							${hasReturns ? '<div class="return-badge">Есть возврат</div>' : ''}
						</div>
						<div class="wrap-photo">
							<img src="${product.picture}" class="photo" onerror="this.src='/local/templates/default/images/no-photo.jpg'">
						</div>
						<div class="product-info">
							<p><strong>Товар:</strong> ${product.name || 'Не указано'}</p>
							<p><strong>Артикул:</strong> ${product.article || 'Не указано'}</p>
							<p class="quantity"><strong>Количество:</strong> <input type="number" class="form-control" 
								   data-id="${product.assortment_id}" 
								   value="${product?.quantity_input || 1}"> из ${product.max_quantity || 1}</p>
							${product.price ? `<p><strong>Цена:</strong> ${product.price} руб.</p>` : ''}
							${hasReturns ? `
								<div class="return-info">
									<p><strong>Возвращено:</strong> ${product.returns.quantity} шт.</p>
									<p><strong>На сумму:</strong> ${product.returns.price} руб.</p>
								</div>
							` : ''}
						</div>
					</div>
				`;
			});
			
			html += `
					</div>
				</div>
			`;
		} else {
			html += `<p class="no-products">Нет информации о товарах</p>`;
		}
		
		if (order.sum) {
			html += `<p class="order-total"><strong>Сумма заказа:</strong> ${order.sum} руб.</p>`;
		}

		if (order.payedSum) {
			html += `<p class="order-total"><strong>Оплачено:</strong> ${order.payedSum} руб.</p>`;
		}
		
		if (order.shippedSum) {
			html += `<p class="order-total"><strong>Отгружено:</strong> ${order.shippedSum} руб.</p>`;
		}
		
		if (order.status) {
			html += `<p class="order-status"><strong>Статус:</strong> ${order.status}</p>`;
		}
		
		html += `</div>`;
		
		orderDetails.innerHTML = html;
	}
    
    // "Провести"
    processButton.addEventListener('click', function() {
        if (!currentOrderData || !currentShipmentData) {
            showNotification('Сначала найдите заказ', 'error');
            return;
        }
        
        const warehouseId = document.getElementById('warehouse').value;
        const commentReturn = document.getElementById('commentReturn').value;
		const selectedStatus = document.getElementById('orderStatusSelect')?.value || 'NZ';
		
        if (!warehouseId) {
            showNotification('Выберите склад', 'error');
            return;
        }
        
		const orderNumber = orderNumberInput.value.trim();

		const checkedProducts = Array.from(
		  document.querySelectorAll('.wrap-checked input[type="checkbox"]:checked')
		).map(checkbox => checkbox.getAttribute('data-id'));
		
		/*if (checkedProducts.length == 0) {
            showNotification('Выберите товар', 'error');
            return;
		}*/
		const productsData = Array.from(
			document.querySelectorAll('.wrap-checked input[type="checkbox"]:checked')
		).map(checkbox => {
			const productId = checkbox.getAttribute('data-id');
			const quantityInput = document.querySelector(`.product-info input[data-id="${productId}"]`);
			const quantity = quantityInput ? parseInt(quantityInput.value) : 1;
			
			return {
				id: productId,
				quantity: quantity
			};
		});
		console.log(productsData);

        processButton.disabled = true;
        processButton.textContent = 'Создание возврата...';
        
        // Создаем возврат
        makeAjaxRequest('create_return', {
            orderId: currentOrderData.id,
            shipmentId: currentShipmentData.id,
            warehouseId: warehouseId,
            comment: commentReturn,
            orderNumber: orderNumber,
            //productIds: checkedProducts
            products: JSON.stringify(productsData),
			orderStatus: selectedStatus
        })
        .then(response => {
            if (response.returnNumber) {
                showNotification(`Возврат ${response.returnNumber} успешно создан`, 'success');
                orderDetails.innerHTML = '';
                //addLogEntry(response.returnNumber); 
                
                resetForm();
				
            } else {
                throw new Error('Не удалось создать возврат');
            }
        })
        .catch(error => {
            console.error('Ошибка при создании возврата:', error);
        })
        .finally(() => {
			viewHistory();
            processButton.disabled = false;
            processButton.textContent = 'Провести';
        });
    });
    
    /*function addLogEntry(returnNumber) {
        const logEntries = document.querySelector('.log-entries');
        const now = new Date();
        const dateStr = now.toLocaleDateString('ru-RU') + ' ' + 
                        now.toLocaleTimeString('ru-RU', {hour: '2-digit', minute: '2-digit'});
        
        const newLogEntry = document.createElement('div');
        newLogEntry.className = 'log-entry';
        newLogEntry.innerHTML = `
            <span class="log-date">${dateStr}</span>
            <span class="log-action">Возврат ${returnNumber} создан.</span>
        `;
        
        logEntries.prepend(newLogEntry);
    }*/
    
    function resetForm() {
        orderNumberInput.value = '';
        barcodeInput.value = '';
        articleInput.value = '';
        productIdInput.value = '';
        //salesChannelSelect.value = '';
        validationStatus.className = 'validation-status';
        resultsSection.style.display = 'none';
        currentOrderData = null;
        currentShipmentData = null;
        
        //salesChannelSelect.removeAttribute('required');
        //salesChannelSelect.closest('.form-group').classList.remove('required');
		barcodeInput.focus();
    }
    
    salesChannelSelect.closest('.form-group').classList.add('required');

    const settingsButton = document.getElementById('settingsButton');
    const settingsModal = document.getElementById('settingsModal');
    const closeModal = document.querySelector('.close');
    const cancelSettingsBtn = document.getElementById('cancelSettingsBtn');
    const saveSettingsBtn = document.getElementById('saveSettingsBtn');
    const settingsForm = document.getElementById('settingsForm');
    
    // настройки
    settingsButton.addEventListener('click', function() {
        loadSettings();
        settingsModal.style.display = 'block';
    });
    
    // закрываем настройки
    function closeSettingsModal() {
        settingsModal.style.display = 'none';
    }
    
    closeModal.addEventListener('click', closeSettingsModal);
    cancelSettingsBtn.addEventListener('click', closeSettingsModal);
    
    window.addEventListener('click', function(event) {
        if (event.target === settingsModal) {
            closeSettingsModal();
        }
    });
    
    function loadSettings() {
        makeAjaxRequest('get_settings')
            .then(response => {
                if (response.settings) {
                    const settings = response.settings;
                    //document.getElementById('defaultWarehouse').value = settings.defaultWarehouse || '123';
                    //document.getElementById('defaultSalesChannel').value = settings.defaultSalesChannel || 'OZON';
                    //document.getElementById('logRetentionDays').value = settings.logRetentionDays || 30;
					let html = '';
					if (settings.salesChannels && settings.salesChannels.length > 0) {
						settings.salesChannels.forEach((sales_channel, index) => {
							html += `
								<tr>
									<td>${sales_channel.NAME} (${sales_channel.SITE_ID})</td>
									<td><input type='text' class='form-control' name='sales_channel[${sales_channel.MS_ID}]' value='${sales_channel.AGENT_ID}'></td>
								</tr>
							`;
						}); 

						document.getElementById('settingsSalesChannels').innerHTML = html;
					}
                    showNotification('Настройки загружены', 'success');
                }
            })
            .catch(error => {
                console.error('Ошибка при загрузке настроек:', error);
            });
    }
    
    // Сохранение настроек
    saveSettingsBtn.addEventListener('click', function() {
        const formData = new FormData(settingsForm);
        const settings = {};
        
        for (let [key, value] of formData.entries()) {
            /*if (key === 'autoCreateReturn' || key === 'enableNotifications') {
                settings[key] = value === 'on';
            } else if (key === 'logRetentionDays') {
                settings[key] = parseInt(value);
            } else {
                settings[key] = value;
            }*/
			settings[key] = value;
        }
        
        makeAjaxRequest('set_settings', settings)
            .then(response => {
                showNotification('Настройки сохранены', 'success');
                closeSettingsModal();
            })
            .catch(error => {
                console.error('Ошибка при сохранении настроек:', error);
            });
    });
    
    //loadSettings();
	
	function viewHistory(limit = 10, filter = {}) {
		makeAjaxRequest('get_history', { limit: limit, filter: filter })
			.then(response => {
				if (response.history) {
					//const $historyBlock = $("#scan-barcode-history");
					//const $logEntries = document.querySelector('.log-entries');
					const $logEntries = $(".log-entries");
					$logEntries.empty();
					
					response.history.forEach(item => {
						//const html = `<p class="${item.type || 'info'}">${item.message}</p>`;
						const html = `<div class="log-entry ${item.type || 'info'}">
							<span class="log-date">${item.date}</span>
							<span class="log-action">${item.message}</span>
						</div>`;
						$logEntries.append(html);
					});
				} else {
					console.error('Ошибка получения истории:', response);
					validationStatus.className = 'validation-status invalid';
					showNotification('Ошибка получения истории', 'error');
				}
			})
			.catch(error => {
				validationStatus.className = 'validation-status invalid';
				console.error('Ошибка получения истории', error);
			});
		/*$.ajax({
			url: '/admin/ajax/barcode/actions.php',
			method: 'POST',
			data: {
				action: 'get_history',
				limit: limit,
				filter: filter
			},
			dataType: 'json',
			success: function(response) {
				if (response.status === "ok" && response.history) {
					const $historyBlock = $("#scan-barcode-history");
					$historyBlock.empty();
					
					response.history.forEach(item => {
						const html = `<p class="${item.type || 'info'}">${item.message}</p>`;
						$historyBlock.append(html);
					});
				} else {
					console.error('Ошибка получения истории:', response.message);
				}
			},
			error: function(xhr, status, error) {
				console.error('Ошибка при получении истории:', error);
			}
		});*/
	}

    function init() {
        loadTabData('s1');
        viewHistory();
    }
    
    init();
});