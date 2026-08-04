(function() {
    let isProcessing = false;
    let currentOffset = 0;
    let userIds = '';
    let totalCount = 0;
    let deletedTotal = 0;
    let errorsTotal = 0;
    let allErrors = [];
    
    const deleteButton = document.getElementById('deleteButton');
    const clearButton = document.getElementById('clearButton');
    const userIdsList = document.getElementById('userIdsList');
    const progressPanel = document.getElementById('progressPanel');
    const processedCountSpan = document.getElementById('processedCount');
    const deletedCountSpan = document.getElementById('deletedCount');
    const errorsCountSpan = document.getElementById('errorsCount');
    const totalCountSpan = document.getElementById('totalCount');
    const progressBar = document.getElementById('progressBar');
    const errorsList = document.getElementById('errorsList');
    const errorsContent = document.getElementById('errorsContent');
    const successMessage = document.getElementById('successMessage');
    
    const chunkSize = parseInt(BX.message('CHUNK_SIZE')) || 100;
    
    function showLoader() {
        const btnText = deleteButton.querySelector('.btn-text');
        const btnLoader = deleteButton.querySelector('.btn-loader');
        btnText.style.display = 'none';
        btnLoader.style.display = 'inline-flex';
        deleteButton.disabled = true;
        clearButton.disabled = true;
    }
    
    function hideLoader() {
        const btnText = deleteButton.querySelector('.btn-text');
        const btnLoader = deleteButton.querySelector('.btn-loader');
        btnText.style.display = 'inline';
        btnLoader.style.display = 'none';
        deleteButton.disabled = false;
        clearButton.disabled = false;
    }
    
    function updateProgress() {
        const processed = currentOffset;
        const percent = (processed / totalCount) * 100;
        progressBar.style.width = percent + '%';
        progressBar.textContent = Math.round(percent) + '%';
        
        processedCountSpan.textContent = processed;
        deletedCountSpan.textContent = deletedTotal;
        errorsCountSpan.textContent = errorsTotal;
        
        if (allErrors.length > 0) {
            errorsList.style.display = 'block';
            errorsContent.innerHTML = allErrors.map(err => `<div>${escapeHtml(err)}</div>`).join('');
        }
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function deleteChunk() {
        if (!isProcessing) return;
        
        if (currentOffset >= totalCount) {
            finishDeletion();
            return;
        }
        
        updateProgress();
        
        BX.ajax({
            url: '/local/components/admin/users.remove/ajax.php',
            method: 'POST',
            data: {
                user_ids: userIds,
                offset: currentOffset,
                chunk_size: chunkSize
            },
            dataType: 'json',
            onsuccess: function(response) {
                if (response.error) {
                    showError(response.error);
                    finishDeletion(true);
                    return;
                }
                
                deletedTotal += response.deleted;
                errorsTotal += response.errors.length;
                allErrors = allErrors.concat(response.errors);
                currentOffset = response.offset;
                
                if (response.complete) {
                    finishDeletion();
                } else {
                    deleteChunk();
                }
            },
            onfailure: function() {
                showError('Ошибка соединения с сервером');
                finishDeletion(true);
            }
        });
    }
    
    function finishDeletion(error) {
        isProcessing = false;
        hideLoader();
        updateProgress();
        
        if (!error && errorsTotal === 0) {
            successMessage.style.display = 'block';
            userIdsList.value = '';
        } else if (!error && errorsTotal > 0) {
            successMessage.style.display = 'none';
        }
        
        if (error) {
            deleteButton.disabled = false;
        }
    }
    
    function showError(message) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv.style.cssText = 'background: #e74c3c; color: #fff; padding: 10px; border-radius: 4px; margin-top: 10px;';
        errorDiv.textContent = message;
        progressPanel.insertBefore(errorDiv, progressPanel.firstChild);
        setTimeout(() => errorDiv.remove(), 5000);
    }
    
    function startDeletion() {
        userIds = userIdsList.value.trim();
        
        if (!userIds) {
            alert('Введите ID пользователей для удаления');
            return;
        }
        
        const ids = userIds.split('\n')
            .map(id => parseInt(id.trim()))
            .filter(id => !isNaN(id) && id > 0);
        
        if (ids.length === 0) {
            alert('Введите корректные ID пользователей (только числа)');
            return;
        }
        
        if (confirm(`Вы уверены, что хотите удалить ${ids.length} пользователей? Это действие нельзя отменить!`)) {
            totalCount = ids.length;
            currentOffset = 0;
            deletedTotal = 0;
            errorsTotal = 0;
            allErrors = [];
            isProcessing = true;
            
            progressPanel.style.display = 'block';
            errorsList.style.display = 'none';
            successMessage.style.display = 'none';
            
            totalCountSpan.textContent = totalCount;
            updateProgress();
            showLoader();
            
            deleteChunk();
        }
    }
    
    function clearForm() {
        if (!isProcessing) {
            userIdsList.value = '';
            progressPanel.style.display = 'none';
        }
    }
    
    deleteButton.addEventListener('click', startDeletion);
    clearButton.addEventListener('click', clearForm);
})();