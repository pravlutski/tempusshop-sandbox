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
	if ($key !== '') {
		try {
			AiContentConfig::save($key, $model ?: 'gpt-4.1');
			$msg = 'OpenAI ключ сохранён в настройках Bitrix (переживает git deploy).';
			try {
				$repo->log(null, 'OpenAI key saved via install');
			} catch (Throwable $e) {
				// logging is optional during first install
			}
		} catch (Throwable $e) {
			$err = 'Не удалось сохранить ключ: ' . $e->getMessage();
		}
	} else {
		$err = 'Вставьте API key';
	}
}

$hasKey = AiContentConfig::hasKey();
$currentModel = 'gpt-4.1';
if (class_exists('COption')) {
	$currentModel = COption::GetOptionString('panel.manager', 'ai_content_openai_model', 'gpt-4.1') ?: 'gpt-4.1';
}
?>
<div style="max-width:720px;margin:20px 0;">
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
	</p>

	<form method="post" style="margin:18px 0;padding:14px;border:1px solid #ddd;border-radius:8px;">
		<?= bitrix_sessid_post() ?>
		<div style="margin-bottom:10px;">
			<label><strong>OpenAI API key</strong></label>
			<input type="password" name="api_key" class="form-control" style="width:100%" placeholder="sk-proj-..." autocomplete="off">
		</div>
		<div style="margin-bottom:10px;">
			<label><strong>Model</strong></label>
			<input type="text" name="model" class="form-control" style="width:100%" value="<?= htmlspecialchars($currentModel) ?>">
		</div>
		<button type="submit" class="btn btn-primary">Сохранить ключ</button>
	</form>

	<p><a class="btn btn-success" href="/admin/utilities/ai_content/">Открыть утилиту</a></p>
</div>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
