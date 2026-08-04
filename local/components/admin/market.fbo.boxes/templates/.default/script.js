document.addEventListener('DOMContentLoaded', function() {
    console.log('FBO Boxes component initialized');
    
    let isLoading = false;

    initComponent();

    function initComponent() {
        bindEvents();
        focusFirstInput();
    }

    function bindEvents() {
        // Выбор маркетплейса
        const marketplaceSelect = document.getElementById('marketplace-select');
        if (marketplaceSelect) {
            marketplaceSelect.addEventListener('change', handleMarketplaceSelect);
        }

        // Выбор поставки
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('select-supply')) {
                handleSupplySelect(e);
            }
        });

        // Создание коробов
        const createForm = document.getElementById('create-boxes-form');
        if (createForm) {
            createForm.addEventListener('submit', function(e) {
                e.preventDefault(); // ВАЖНО!
                handleCreateBoxes(e);
            });
        }

        // Сканирование штрихкодов
        document.addEventListener('keypress', function(e) {
            if (e.target.classList.contains('barcode-input') && e.key === 'Enter') {
                e.preventDefault();
                handleBarcodeScan(e);
            }
        });
		
		// Удаление товара
		document.addEventListener('click', function(e) {
			if (e.target.classList.contains('btn-remove')) {
				handleRemoveProduct(e);
			}
		});

		// Удаление короба
		document.addEventListener('click', function(e) {
			if (e.target.classList.contains('btn-box-remove')) {
				handleRemoveBox(e);
			}
		});
		
        // Отправка данных
        const sendButton = document.getElementById('send-boxes');
        if (sendButton) {
            sendButton.addEventListener('click', handleSendBoxes);
        }

        // печать
        const printButton = document.getElementById('print-barcodes');
        if (printButton) {
            //printButton.addEventListener('click', handlePrintBarcode);
			printButton.addEventListener('click', function(e) {
                e.preventDefault();
                handlePrintBarcode(e);
            });
        }
		
		const backButton = document.getElementById('back-to-step1');
		if (backButton) {
			backButton.addEventListener('click', handleBackToStep1);
		}
		

		const settingsButton = document.getElementById('settingsFboBoxes');
		const settingsFboBoxesModal = document.getElementById('settingsFboBoxesModal');
		const closeModal = document.querySelector('.close');
		const cancelSettingsBtn = document.getElementById('cancelSettingsBtn');
		const saveSettingsBtn = document.getElementById('saveSettingsBtn');
		const settingsFboBoxesForm = document.getElementById('settingsFboBoxesForm');

		settingsButton.addEventListener('click', function() {
			settingsFboBoxesModal.style.display = 'block';
		});

		// закрываем настройки
		function closeSettingsModal() {
			settingsFboBoxesModal.style.display = 'none';
		}
		closeModal.addEventListener('click', closeSettingsModal);
		cancelSettingsBtn.addEventListener('click', closeSettingsModal);

		// Закрытие при клике вне модального окна
		window.addEventListener('click', function(event) {
			if (event.target === settingsFboBoxesModal) {
				closeSettingsModal();
			}
		});

		// Сохранение настроек
		saveSettingsBtn.addEventListener('click', function() {
			const formData = new FormData(settingsFboBoxesForm);
			const settings = {};

			for (let [key, value] of formData.entries()) {
				settings[key] = value;
			}
			console.log(settings);

			$.ajax({
				url: '/local/components/admin/market.fbo.boxes/actions.php',
				method: 'POST',
				data: {
					action: 'set_settings',
					settings: settings
				},
				dataType: 'json',
				success: function(response) {
					if (response && response.status == "ok") {
						alert('Настройки сохранены');
						setTimeout(() => window.location.reload(), 500);
					} else {
						alert('Ошибка при сохранении настроек');
					}
				},
				error: function(xhr, status, error) {
					alert('Ошибка при сохранении настроек', 'error');
				}
			});
		});
    }
	
	function handleBackToStep1() {
		if (!confirm('Вернуться к выбору поставки? Все данные в коробах будут потеряны.')) {
			return;
		}

		setLoading(true);

		$.ajax({
			url: '',
			type: 'POST',
			data: {
				action: 'reset_to_step1',
				sessid: BX.bitrix_sessid()
			},
			success: function(response) {
				setLoading(false);
				if (response.success) {
					showNotification('Возврат к выбору поставки', 'success');
					// Перезагружаем страницу
					setTimeout(() => window.location.reload(), 500);
				} else {
					showNotification('Ошибка возврата', 'error');
				}
			},
			error: function(xhr, status, error) {
				setLoading(false);
				showNotification('Ошибка соединения', 'error');
			}
		});
	}
	
    function handleMarketplaceSelect(e) {
        const marketplace = e.target.value;
        if (!marketplace) return;

        setLoading(true);

        $.ajax({
            url: '',
            type: 'POST',
            data: {
                action: 'select_marketplace',
                marketplace: marketplace,
                sessid: BX.bitrix_sessid()
            },
            success: function(response) {
                setLoading(false);
                if (response.success) {
                    showNotification('Маркетплейс выбран', 'success');
                    setTimeout(() => window.location.reload(), 500);
                } else {
                    showNotification(response.error || 'Ошибка выбора маркетплейса', 'error');
                }
            },
            error: function(xhr, status, error) {
                setLoading(false);
                showNotification('Ошибка соединения', 'error');
            }
        });
    }

    function handleSupplySelect(e) {
        const supplyId = e.target.dataset.supplyId;
        document.getElementById('selected-supply').value = supplyId;
        document.getElementById('box-count-form').style.display = 'block';
        showNotification('Поставка выбрана', 'success');
    }

    function handleCreateBoxes(e) {
        console.log('Creating boxes...');
        
        const form = e.target;
        const boxCount = form.box_count.value;
        const supplyId = form.supply_id.value;
        
        if (!boxCount || boxCount < 1 || boxCount > 20) {
            showNotification('Введите количество коробов от 1 до 20', 'error');
            return;
        }

        setLoading(true);

        $.ajax({
            url: '',
            type: 'POST',
            data: {
                action: 'create_boxes',
                box_count: boxCount,
                supply_id: supplyId,
                sessid: BX.bitrix_sessid()
            },
            success: function(response) {
                setLoading(false);
                console.log('Response:', response);
                if (response.success) {
                    showNotification('Короба созданы успешно!', 'success');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showNotification(response.error || 'Ошибка создания коробов', 'error');
                }
            },
            error: function(xhr, status, error) {
                setLoading(false);
                showNotification('Ошибка соединения', 'error');
                console.error('AJAX error:', error);
            }
        });
    }

	function handleBarcodeScan(e) {
		const input = e.target;
		const barcode = input.value.trim();
		const boxNumber = input.dataset.boxNumber;

		if (!barcode) {
			showNotification('Введите штрихкод', 'error');
			return;
		}

		// Валидация штрихкода
		if (barcode.length < 8) {
			showNotification('Неверный штрихкод', 'error');
			input.value = '';
			return;
		}

		setLoading(true);
		input.disabled = true;
		updateScanStatus('Сканирование...', 'scanning');

		$.ajax({
			url: '',
			type: 'POST',
			data: {
				action: 'scan_product',
				barcode: barcode,
				box_number: boxNumber,
				sessid: BX.bitrix_sessid()
			},
			success: function(response) {
				setLoading(false);
				input.disabled = false;
				input.focus();
				
				if (response.success) {
					// Воспроизводим звуковой сигнал
					playScanSound(response.action);
					updateBoxContent(boxNumber, response.product, response.action);
					
					updateTotalItemsCount();
					
					const actionText = response.action === 'added' ? 'добавлен' : 'увеличено количество';
					showNotification(`Товар "${response.product.article}" ${actionText} в короб #${boxNumber}`, 'success');
					
					input.value = '';
					
				} else {
					// Звук ошибки
					playErrorSound();
					updateScanStatus('Ошибка', 'error');
					showNotification(response.error || 'Ошибка сканирования', 'error');
				}
			},
			error: function(xhr, status, error) {
				setLoading(false);
				input.disabled = false;
				input.focus();
				playErrorSound();
				updateScanStatus('Ошибка сети', 'error');
				showNotification('Ошибка соединения', 'error');
			}
		});
	}

	function updateBoxContent(boxNumber, product, action) {
		const box = document.querySelector('.box[data-box-number="' + boxNumber + '"]');
		const tbody = box.querySelector('.box-items tbody');
		const emptyBox = box.querySelector('.empty-box');
		//const itemsCount = box.querySelector('.box-items-count');
		//const itemsCountSum = box.querySelector('.box-items-count-sum');
		
		if (emptyBox) {
			emptyBox.remove();
		}
		
		if (!tbody) {
			const table = document.createElement('table');
			table.innerHTML = `
				<thead>
					<tr>
						<th>Артикул</th>
						<th>Кол-во</th>
						<th>Штрихкод</th>
						<th>Действие</th>
					</tr>
				</thead>
				<tbody></tbody>
			`;
			box.querySelector('.box-items').appendChild(table);
		}
		
		const currentTbody = box.querySelector('.box-items tbody');
		const existingRow = currentTbody.querySelector('tr[data-barcode="' + product.barcode + '"]');
		
		if (existingRow) {
			const quantityCell = existingRow.querySelector('.quantity');
			quantityCell.textContent = product.quantity;
			
			highlightElement(quantityCell);
		} else {
			// Добавляем новый товар
			const row = document.createElement('tr');
			row.dataset.barcode = product.barcode;
			row.innerHTML = `
				<td class="article">${product.article || 'N/A'}</td>
				<td class="quantity">${product.quantity}</td>
				<td class="barcode">${product.barcode}</td>
				<td class="actions">
					<button class="btn-remove" 
							data-box-number="${boxNumber}"
							data-barcode="${product.barcode}"
							title="Удалить товар">
						✕
					</button>
				</td>
			`;
			currentTbody.appendChild(row);
			
			highlightElement(row);
		}
		
		/*if (itemsCount) {
			const itemCount = currentTbody.querySelectorAll('tr').length;
			itemsCount.textContent = 'Артикулов: ' + itemCount;
		}
		
		if (itemsCountSum) {
			const quantityCells = currentTbody.querySelectorAll('.quantity');
			let totalSum = 0;
			
			quantityCells.forEach(cell => {
				const quantity = parseInt(cell.textContent) || 0;
				totalSum += quantity;
			});
			
			itemsCountSum.textContent = `Товаров: ${totalSum}`;
		}*/
		updateBoxCounters(box);
		
		updateScanStatus('Успешно', 'success');
	}
	
	function updateBoxCounters(box) {
		const itemsCount = box.querySelector('.box-items-count');
		const itemsCountSum = box.querySelector('.box-items-count-sum');
		const currentTbody = box.querySelector('.box-items tbody');
		
		if (!currentTbody) return;
		
		// Подсчет количества артикулов (уникальных строк)
		const rows = currentTbody.querySelectorAll('tr');
		const itemCount = rows.length;
		
		if (itemsCount) {
			itemsCount.textContent = 'Артикулов: ' + itemCount;
		}
		
		// Подсчет суммы quantity
		if (itemsCountSum) {
			let totalSum = 0;
			const quantityCells = currentTbody.querySelectorAll('.quantity');
			
			quantityCells.forEach(cell => {
				const quantity = parseInt(cell.textContent) || 0;
				totalSum += quantity;
			});
			
			itemsCountSum.textContent = `Товаров: ${totalSum}`;
		}
	}
	function updateTotalItemsCount() {
		let uniqueArticles = new Set();
		let totalQuantity = 0;
		
		document.querySelectorAll('.box').forEach(box => {
			box.querySelectorAll('.article').forEach(td => {
				const article = td.textContent.trim();
				if (article && article !== 'N/A') {
					uniqueArticles.add(article);
				}
			});
			
			box.querySelectorAll('.quantity').forEach(td => {
				const quantity = parseInt(td.textContent) || 0;
				totalQuantity += quantity;
			});
		});
		
		document.querySelector('.total-items-count').textContent = `Всего артикулов: ${uniqueArticles.size}`;
		document.querySelector('.total-items-count-sum').textContent = `Всего товаров: ${totalQuantity}`;
	}

	function updateScanStatus(text, status) {
		const statusElement = document.querySelector('.status-text');
		if (statusElement) {
			statusElement.textContent = text;
			statusElement.className = 'status-text ' + status;
		}
	}

	function handleRemoveProduct(e) {
		const button = e.target;
		const boxNumber = button.dataset.boxNumber;
		const barcode = button.dataset.barcode;
		const article = button.closest('tr').querySelector('.article').textContent;
		
		if (!confirm(`Удалить товар "${article}" из короба #${boxNumber}?`)) {
			return;
		}
		
		setLoading(true);
		updateScanStatus('Удаление...', 'scanning');

		$.ajax({
			url: '',
			type: 'POST',
			data: {
				action: 'remove_product',
				box_number: boxNumber,
				barcode: barcode,
				sessid: BX.bitrix_sessid()
			},
			success: function(response) {
				setLoading(false);
				
				if (response.success) {
					playRemoveSound();
					
					removeProductFromTable(boxNumber, barcode);
					
					
					showNotification(`Товар "${response.product.article}" удален из короба #${boxNumber}`, 'success');
					
				} else {
					playErrorSound();
					updateScanStatus('Ошибка', 'error');
					showNotification(response.error || 'Ошибка удаления', 'error');
				}
			},
			error: function(xhr, status, error) {
				setLoading(false);
				playErrorSound();
				updateScanStatus('Ошибка сети', 'error');
				showNotification('Ошибка соединения', 'error');
			}
		});
	}
	
	function handleRemoveBox(e) {
		const button = e.target;
		const boxNumber = button.dataset.boxNumber;

		if (!confirm(`Удалить короб #${boxNumber}?`)) {
			return;
		}
		
		setLoading(true);
		updateScanStatus('Удаление...', 'scanning');

		$.ajax({
			url: '',
			type: 'POST',
			data: {
				action: 'remove_box',
				box_number: boxNumber,
				sessid: BX.bitrix_sessid()
			},
			success: function(response) {
				setLoading(false);
				
				if (response.success) {
					playRemoveSound();
					
					//removeProductFromTable(boxNumber, barcode);
					updateTotalItemsCount();
					setTimeout(() => window.location.reload(), 1000);
					showNotification(`Короб #${boxNumber} удален`, 'success');
					
				} else {
					playErrorSound();
					updateScanStatus('Ошибка', 'error');
					showNotification(response.error || 'Ошибка удаления', 'error');
				}
			},
			error: function(xhr, status, error) {
				setLoading(false);
				playErrorSound();
				updateScanStatus('Ошибка сети', 'error');
				showNotification('Ошибка соединения', 'error');
			}
		});
	}

	function removeProductFromTable(boxNumber, barcode) {
		const box = document.querySelector('.box[data-box-number="' + boxNumber + '"]');
		const row = box.querySelector('tr[data-barcode="' + barcode + '"]');
		
		if (row) {
			row.style.opacity = '0.5';
			row.style.transition = 'opacity 0.3s ease';
			
			setTimeout(() => {
				row.remove();
				
				const tbody = box.querySelector('.box-items tbody');
				const remainingRows = tbody.querySelectorAll('tr');
				//const itemsCount = box.querySelector('.box-items-count');
				//const itemsCountSum = box.querySelector('.box-items-count-sum');
				
				//if (itemsCount) {
				//	itemsCount.textContent = 'Артикулов: ' + remainingRows.length;
				//}
				updateBoxCounters(box);
				updateTotalItemsCount();
				
				if (remainingRows.length === 0) {
					showEmptyBoxMessage(box);
				}
				
			}, 300);
		}
	}

	function showEmptyBoxMessage(box) {
		const emptyBox = document.createElement('div');
		emptyBox.className = 'empty-box';
		emptyBox.innerHTML = `
			<p>Короб пустой</p>
			<p>Отсканируйте штрихкоды товаров</p>
		`;
		
		box.querySelector('.box-items').appendChild(emptyBox);
	}

	function playRemoveSound() {
		try {
			const context = new (window.AudioContext || window.webkitAudioContext)();
			const oscillator = context.createOscillator();
			const gainNode = context.createGain();
			
			oscillator.connect(gainNode);
			gainNode.connect(context.destination);
			
			oscillator.frequency.value = 400;
			gainNode.gain.value = 0.1;
			oscillator.start(0);
			
			setTimeout(() => {
				oscillator.stop();
			}, 150);
		} catch (e) {
			console.log('Audio not supported');
		}
	}

	function playScanSound(action) {
		try {
			const context = new (window.AudioContext || window.webkitAudioContext)();
			const oscillator = context.createOscillator();
			const gainNode = context.createGain();
			
			oscillator.connect(gainNode);
			gainNode.connect(context.destination);
			
			if (action === 'added') {
				oscillator.frequency.value = 800;
			} else {
				oscillator.frequency.value = 600;
			}
			
			gainNode.gain.value = 0.1;
			oscillator.start(0);
			
			setTimeout(() => {
				oscillator.stop();
			}, 100);
		} catch (e) {
			console.log('Audio not supported');
		}
	}

	function playErrorSound() {
		/*try {
			const context = new (window.AudioContext || window.webkitAudioContext)();
			const oscillator = context.createOscillator();
			const gainNode = context.createGain();
			
			oscillator.connect(gainNode);
			gainNode.connect(context.destination);
			
			oscillator.frequency.value = 300;
			gainNode.gain.value = 0.1;
			oscillator.start(0);
			
			setTimeout(() => {
				oscillator.stop();
			}, 200);
		} catch (e) {
			console.log('Audio not supported');
		}*/
		try {
			// Проверяем поддержку SpeechSynthesis
			if ('speechSynthesis' in window) {
				const utterance = new SpeechSynthesisUtterance('Ошибка');
				utterance.lang = 'ru-RU'; // Указываем русский язык
				utterance.volume = 0.8; // Громкость от 0 до 1
				
				speechSynthesis.speak(utterance);
			} else {
				// Fallback на старый звук если синтез речи не поддерживается
				playFallbackSound();
			}
		} catch (e) {
			console.log('Speech synthesis not supported');
			playFallbackSound();
		}
	}
	
	function playFallbackSound() {
		try {
			const context = new (window.AudioContext || window.webkitAudioContext)();
			const oscillator = context.createOscillator();
			const gainNode = context.createGain();
			
			oscillator.connect(gainNode);
			gainNode.connect(context.destination);
			
			oscillator.frequency.value = 300;
			gainNode.gain.value = 0.1;
			oscillator.start(0);
			
			setTimeout(() => {
				oscillator.stop();
			}, 200);
		} catch (e) {
			console.log('Audio not supported');
		}
	}

	function highlightElement(element) {
		element.classList.add('highlight');
		setTimeout(() => {
			element.classList.remove('highlight');
		}, 1000);
	}

    function handleSendBoxes() {
        if (!confirm('Вы уверены, что хотите отправить данные? Номера коробов будут перезаписаны.')) return;

        setLoading(true);

        $.ajax({
            url: '',
            type: 'POST',
            data: {
                action: 'send_boxes',
                sessid: BX.bitrix_sessid()
            },
            success: function(response) {
                setLoading(false);
                if (response.success) {
                    showNotification(response.message, 'success');
                    setTimeout(() => window.location.reload(), 2000);
                } else {
                    showNotification(response.error || 'Ошибка отправки', 'error');
					if (response.errors_detail) {
						showErrorsModal(response.errors_detail);
					}
                }
            },
            error: function(xhr, status, error) {
                setLoading(false);
                showNotification('Ошибка соединения', 'error');
            }
        });
    }
	
	function showErrorsModal(errorsDetail) {
		const modal = document.createElement('div');
		modal.style.cssText = `
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background: rgba(0,0,0,0.5);
			display: flex;
			justify-content: center;
			align-items: center;
			z-index: 9999;
		`;
		
		const modalContent = document.createElement('div');
		modalContent.style.cssText = `
			background: white;
			border-radius: 8px;
			width: 90%;
			max-width: 800px;
			max-height: 80vh;
			overflow: auto;
			padding: 20px;
			box-shadow: 0 5px 15px rgba(0,0,0,0.3);
		`;
		
		const title = document.createElement('h3');
		title.textContent = 'Детализация ошибок';
		title.style.cssText = `
			margin-top: 0;
			color: #e74c3c;
			margin-bottom: 20px;
			border-bottom: 1px solid #eee;
			padding-bottom: 10px;
		`;
		
		const jsonContainer = document.createElement('pre');
		jsonContainer.style.cssText = `
			background: #f5f5f5;
			padding: 15px;
			border-radius: 5px;
			overflow: auto;
			font-family: 'Courier New', monospace;
			font-size: 14px;
			line-height: 1.4;
			margin: 0;
			max-height: 60vh;
		`;
		
		jsonContainer.textContent = JSON.stringify(errorsDetail, null, 2);
		
		const closeButton = document.createElement('button');
		closeButton.textContent = 'Закрыть';
		closeButton.style.cssText = `
			background: #3498db;
			color: white;
			border: none;
			padding: 10px 20px;
			border-radius: 4px;
			cursor: pointer;
			margin-top: 20px;
			float: right;
		`;
		closeButton.onclick = function() {
			document.body.removeChild(modal);
		};
		
		modalContent.appendChild(title);
		modalContent.appendChild(jsonContainer);
		modalContent.appendChild(closeButton);
		modal.appendChild(modalContent);
		
		document.body.appendChild(modal);
		
		modal.onclick = function(e) {
			if (e.target === modal) {
				document.body.removeChild(modal);
			}
		};
		
		document.addEventListener('keydown', function closeOnEsc(e) {
			if (e.key === 'Escape') {
				document.body.removeChild(modal);
				document.removeEventListener('keydown', closeOnEsc);
			}
		});
	}

    function handlePrintBarcode(e) {
		const button = e.currentTarget; // или e.target
		const dataHref = button.getAttribute('data-href');
		
		if (dataHref) {
			window.open(dataHref, '_blank');
			return;
		}
		setLoading(true);

        $.ajax({
            url: '',
            type: 'POST',
            data: {
                action: 'print_barcodes',
                sessid: BX.bitrix_sessid()
            },
            success: function(response) {
                setLoading(false);
                if (response.success) {
                    showNotification(response.message, 'success');
                    //setTimeout(() => window.location.reload(), 2000);
					const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
					const filename = `shrikhkody_${timestamp}.pdf`;
					
					// Скачиваем файл
					const link = document.createElement('a');
					link.href = response.data.file_url;
					link.download = filename;
					link.target = '_blank';
					document.body.appendChild(link);
					link.click();
					document.body.removeChild(link);
					/*const printWindow = window.open(response.data.file_url, '_blank');
					
					if (printWindow) {
						printWindow.onload = function() {
							try {
								setTimeout(() => {
									printWindow.print();
									showNotification('Файл готов к печати', 'success');
								}, 1000);
							} catch (e) {
								console.log('Print error:', e);
								showNotification('Откройте PDF для печати', 'info');
							}
						};
					} else {
						showNotification('Файл сгенерирован: ' + response.data.file_url, 'success');
					}*/
                } else {
                    showNotification(response.error || 'Ошибка отправки', 'error');
                }
            },
            error: function(xhr, status, error) {
                setLoading(false);
                showNotification('Ошибка соединения', 'error');
            }
        });
    }
	
    function setLoading(loading) {
        isLoading = loading;
        const loadingElement = document.getElementById('global-loading');
        if (loading) {
            loadingElement.classList.add('active');
        } else {
            loadingElement.classList.remove('active');
        }
    }

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

    function focusFirstInput() {
        setTimeout(() => {
            const firstInput = document.querySelector('.barcode-input');
            if (firstInput) firstInput.focus();
        }, 100);
    }
	updateTotalItemsCount();
});