<? if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die(); ?>
<div class="fbo-boxes">
    <? if ($arResult['STEP'] == 1): ?>
        <? include('step1.php'); ?>
    <? elseif ($arResult['STEP'] == 2): ?>
        <? include('step2.php'); ?>
    <? endif; ?>
</div>
<div class="fbo-loading" id="global-loading">
    <div class="loading-spinner"></div>
</div>
<button type="button" id="settingsFboBoxes" class="btn btn-settings">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="3"></circle>
        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
    </svg>
    Настройки
</button>
<? 
// Подключаем скрипты Bitrix
CJSCore::Init(['ajax', 'jquery']); 
?>
<div id="settingsFboBoxesModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Настройки FBO Короба</h2>
            <span class="close">×</span>
        </div>
        <div class="modal-body">
            <form id="settingsFboBoxesForm">
                <div class="settings-section">
                    <h3>Настройки</h3>
                    <div class="form-group">
						<table class="table">
							<tbody>
								<tr>
									<td>Cookie WR</td>
									<td><input type='text' class='form-control' name='cookie-wr' value='<?=$this->__component->settings['cookie-wr']?>'></td>
								</tr>
								<tr>
									<td>Authorizev3 WR</td>
									<td><input type='text' class='form-control' name='authorizev3-wr' value='<?=$this->__component->settings['authorizev3-wr']?>'></td>
								</tr>
								<tr>
									<td>Cookie IP</td>
									<td><input type='text' class='form-control' name='cookie-ip' value='<?=$this->__component->settings['cookie-ip']?>'></td>
								</tr>
								<tr>
									<td>Authorizev3 IP</td>
									<td><input type='text' class='form-control' name='authorizev3-ip' value='<?=$this->__component->settings['authorizev3-ip']?>'></td>
								</tr>
							</tbody>
						</table>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" id="saveSettingsBtn" class="btn btn-primary">Сохранить</button>
            <button type="button" id="cancelSettingsBtn" class="btn btn-default">Отмена</button>
        </div>
    </div>
</div>