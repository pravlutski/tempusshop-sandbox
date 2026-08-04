<?php

use Bitrix\Main;

$eventManager = Main\EventManager::getInstance();

$eventManager->addEventHandler('yandex.market', 'onExportPromoProviderBuildList', function(Main\Event $event) {
	require_once __DIR__ . '/provider.php';

	$providerType = 'my_promo';
	$providerClassName = 'Site\PhpInterface\YandexMarket\Promo\MyProvider';

	return new Main\EventResult(
		Main\EventResult::SUCCESS,
		[
			'TYPE' => $providerType,
			'CLASS_NAME' => $providerClassName
		]
	);
});