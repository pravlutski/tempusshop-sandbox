<?php
require __DIR__ . '/_init.php';
AiContentBootstrap::requirePost();

$brandId = (int)($_POST['brand_id'] ?? 0);
$raw = (string)($_POST['articles'] ?? '');
$articles = preg_split('/[\r\n,;]+/', $raw) ?: [];

if ($brandId <= 0) {
	AiContentBootstrap::jsonResponse(['ok' => false, 'error' => 'Выберите бренд'], 400);
}

$content = new CPanelContent();
$brand = $content->getSection($brandId);
if (!$brand || !$content->isBrand($brandId)) {
	AiContentBootstrap::jsonResponse(['ok' => false, 'error' => 'Некорректный бренд'], 400);
}

global $USER;
$repo = new AiContentRepository();
$result = $repo->addTasks($brandId, (string)$brand['name'], $articles, (int)$USER->GetID());

AiContentBootstrap::jsonResponse(['ok' => true] + $result);
