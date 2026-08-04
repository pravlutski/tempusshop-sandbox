<?php

use Bitrix\Main;
use Yandex\Market;

$eventManager = Main\EventManager::getInstance();

$eventManager->addEventHandler('yandex.market', 'onExportEntityTypeBuildList', function(Main\Event $event) {
	require_once __DIR__ . '/source.php';
	require_once __DIR__ . '/event.php';

	return new Main\EventResult(Main\EventResult::SUCCESS, [
		'TYPE' => 'demo_product',
		'SOURCE_CLASS_NAME' => 'Site\PhpInterface\YandexMarket\Entity\Product\Source',
		'EVENT_CLASS_NAME' => 'Site\PhpInterface\YandexMarket\Entity\Product\Event',
	]);
});