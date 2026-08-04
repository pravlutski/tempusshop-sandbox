<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?
if (!CModule::IncludeModule('panel.manager')) {
	return;
}
$APPLICATION->SetTitle('AI content — установка');
if (!$USER->IsAdmin()) {
	ShowError('Только администратор');
	require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
	return;
}

require_once __DIR__ . '/classes/AiContentBootstrap.php';
AiContentBootstrap::init();

$repo = new AiContentRepository();
$schemaOk = true;
$schemaError = '';
try {
	$repo->ensureSchema();
} catch (Throwable $e) {
	$schemaOk = false;
	$schemaError = $e->getMessage();
}
$reg = ['created' => false, 'id' => 0, 'skipped' => true, 'reason' => 'schema failed'];
try {
	$reg = $repo->registerUtility();
} catch (Throwable $e) {
	$reg = ['created' => false, 'id' => 0, 'skipped' => true, 'reason' => $e->getMessage()];
}

$msg = '';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
	$key = trim((string)($_POST['api_key'] ?? ''));
	$model = trim((string)($_POST['model'] ?? 'gpt-4.1'));
	$proxy = trim((string)($_POST['proxy'] ?? ''));
	$proxyType = trim((string)($_POST['proxy_type'] ?? 'http'));
	try {
		// Keep existing key if field left blank
		if ($key === '' && AiContentConfig::hasKey()) {
			$key = ' '; // sentinel: save() skips empty after trim... use explicit path
			AiContentConfig::save(
				(string)COption::GetOptionString('panel.manager', 'ai_content_openai_key', ''),
				$model ?: 'gpt-4.1',
				$proxy,
				$proxyType
			);
		} else {
			if ($key === '') {
				throw new RuntimeException('Вставьте API key');
			}
			AiContentConfig::save($key, $model ?: 'gpt-4.1', $proxy, $proxyType);
		}
		$msg = 'Настройки сохранены (ключ + прокси в Bitrix options).';
		try {
			$repo->log(null, 'OpenAI settings saved via install', [
				'proxy' => $proxy !== '',
				'proxy_type' => $proxyType,
			]);
		} catch (Throwable $e) {
		}
	} catch (Throwable $e) {
		$err = 'Не удалось сохранить: ' . $e->getMessage();
	}
}

$hasKey = AiContentConfig::hasKey();
$currentModel = 'gpt-4.1';
$currentProxy = AiContentConfig::getProxyRaw();
$currentProxyType = AiContentConfig::getProxyTypeRaw();
if (class_exists('COption')) {
	$currentModel = COption::GetOptionString('panel.manager', 'ai_content_openai_model', 'gpt-4.1') ?: 'gpt-4.1';
}
?>
<div style="max-width:760px;margin:20px 0;">
	<h2>Установка AI наполнения контента</h2>
	<? if ($msg): ?><p style="color:green"><?= htmlspecialchars($msg) ?></p><? endif; ?>
	<? if ($err): ?><p style="color:red"><?= htmlspecialchars($err) ?></p><? endif; ?>
	<? if (!$schemaOk): ?><p style="color:red">Schema: <?= htmlspecialchars($schemaError) ?></p><? endif; ?>

	<p>Таблицы AI-утилиты и (при необходимости) <code>admin_utilities_*</code> созданы/проверены.</p>
	<p>
		Утилита в списке:
		<? if (!empty($reg['skipped'])): ?>
			<strong>пропущена</strong> (<?= htmlspecialchars((string)($reg['reason'] ?? 'n/a')) ?>) — можно пользоваться по прямой ссылке
		<? elseif ($reg['created']): ?>
			<strong>добавлена</strong> (id <?= (int)$reg['id'] ?>)
		<? else: ?>
			<strong>уже была</strong> (id <?= (int)$reg['id'] ?>)
		<? endif; ?>
	</p>
	<p>
		OpenAI ключ:
		<? if ($hasKey): ?>
			<span style="color:green">задан</span>
		<? else: ?>
			<span style="color:red">не задан</span>
		<? endif; ?>
		|
		Прокси:
		<? if ($currentProxy !== ''): ?>
			<span style="color:green">задан (<?= htmlspecialchars($currentProxyType) ?>)</span>
		<? else: ?>
			<span style="color:red">не задан — нужен для РФ/BY</span>
		<? endif; ?>
	</p>

	<form method="post" id="ai-install-form" style="margin:18px 0;padding:14px;border:1px solid #ddd;border-radius:8px;">
		<?= bitrix_sessid_post() ?>
		<div style="margin-bottom:10px;">
			<label><strong>OpenAI API key</strong></label>
			<input type="password" name="api_key" id="api_key" class="form-control" style="width:100%" placeholder="<?= $hasKey ? 'оставьте пустым, чтобы не менять' : 'sk-proj-...' ?>" autocomplete="off">
		</div>
		<div style="margin-bottom:10px;">
			<label><strong>Model</strong></label>
			<input type="text" name="model" id="model" class="form-control" style="width:100%" value="<?= htmlspecialchars($currentModel) ?>">
		</div>
		<div style="margin-bottom:10px;">
			<label><strong>Proxy</strong></label>
			<input type="text" name="proxy" id="proxy" class="form-control" style="width:100%" value="<?= htmlspecialchars($currentProxy) ?>" placeholder="host:port:user:pass или socks5://user:pass@host:port">
			<small class="text-muted">Форматы: <code>ip:port</code>, <code>ip:port:user:pass</code>, <code>http://user:pass@ip:port</code>, <code>socks5://user:pass@ip:port</code></small>
		</div>
		<div style="margin-bottom:10px;">
			<label><strong>Proxy type</strong> (если без схемы в URL)</label>
			<select name="proxy_type" id="proxy_type" class="form-control" style="width:100%">
				<? foreach (['http','https','socks5','socks5h'] as $t): ?>
					<option value="<?= $t ?>" <?= $currentProxyType === $t ? 'selected' : '' ?>><?= $t ?></option>
				<? endforeach; ?>
			</select>
		</div>
		<button type="submit" class="btn btn-primary">Сохранить</button>
		<button type="button" id="btn-test-proxy" class="btn btn-warning">Проверить прокси → OpenAI</button>
		<span id="proxy-test-result" style="margin-left:10px;"></span>
	</form>

	<p><a class="btn btn-success" href="/admin/utilities/ai_content/">Открыть утилиту</a></p>
</div>
<script>
$('#btn-test-proxy').on('click', function(){
	const $r = $('#proxy-test-result').text('проверка…').css('color','#666');
	$.post('/admin/utilities/ai_content/ajax/testProxy.php', {
		api_key: $('#api_key').val(),
		model: $('#model').val(),
		proxy: $('#proxy').val(),
		proxy_type: $('#proxy_type').val()
	}).done(function(res){
		if (typeof res === 'string') try { res = JSON.parse(res); } catch(e) {}
		if (res.ok) {
			$r.css('color','green').text('OK, HTTP ' + res.http_code + (res.proxy ? ' через прокси' : ''));
		} else {
			$r.css('color','red').text(res.error || 'fail');
		}
	}).fail(function(xhr){
		let msg = 'fail';
		try { const j = JSON.parse(xhr.responseText); if (j.error) msg = j.error; } catch(e) {}
		$r.css('color','red').text(msg);
	});
});
</script>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
