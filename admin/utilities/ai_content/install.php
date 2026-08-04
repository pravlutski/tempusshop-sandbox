<?php require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php'; ?>
<?php
if (!CModule::IncludeModule('panel.manager')) {
	return;
}
$APPLICATION->SetTitle('AI content — установка');
if (!$USER->IsAdmin()) {
	ShowError('Только администратор');
	require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';
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

$reg = array('created' => false, 'id' => 0, 'skipped' => true, 'reason' => 'schema failed');
try {
	$reg = $repo->registerUtility();
} catch (Throwable $e) {
	$reg = array('created' => false, 'id' => 0, 'skipped' => true, 'reason' => $e->getMessage());
}

LocalRedirect('/admin/utilities/ai_content/settings.php');
?>
<?php require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php'; ?>
