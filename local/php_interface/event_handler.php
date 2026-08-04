<?php
use Bitrix\Main;
$eventManager = Main\EventManager::getInstance();

//$eventManager->addEventHandler('iblock', 'OnAfterIBlockSectionUpdate', Array('TsIblock', 'OnAfterIBlockSectionUpdate'));
//обмен с PW
$eventManager->addEventHandler('iblock', 'OnBeforeIBlockSectionAdd', Array('TsIblock', 'OnBeforeIBlockSectionAdd'));
$eventManager->addEventHandler('iblock', 'OnBeforeIBlockSectionUpdate', Array('TsIblock', 'OnBeforeIBlockSectionUpdate'));
//$eventManager->addEventHandler('iblock', 'OnBeforeIBlockSectionDelete', Array('TsIblock', 'OnBeforeIBlockSectionDelete'));

//обновляем значение доступности элемента
$eventManager->addEventHandler('iblock', 'OnAfterIBlockElementUpdate', Array('TsIblock', 'setPropAvailable'), 1000);
$eventManager->addEventHandler('iblock', 'OnAfterIBlockElementAdd', Array('TsIblock', 'setPropAvailable'));

$eventManager->addEventHandler('iblock', 'OnAfterIBlockPropertyAdd', Array('TsIblock', 'OnAfterIBlockPropertyAddHandler'));
$eventManager->addEventHandler('iblock', 'OnAfterIBlockPropertyDelete', Array('TsIblock', 'OnAfterIBlockPropertyDeleteHandler'));
$eventManager->addEventHandler('iblock', 'OnAfterIBlockPropertyUpdate', Array('TsIblock', 'OnAfterIBlockPropertyUpdateHandler'));

$eventManager->addEventHandler('iblock', 'OnAfterIBlockElementUpdate', Array('TsIblock', 'OnAfterIBlockElementUpdate'), 1100);
$eventManager->addEventHandler('iblock', 'OnAfterIBlockElementAdd', Array('TsIblock', 'OnAfterIBlockElementAdd'));

// tab на странице просмотра заказа
$eventManager->addEventHandler('main', 'OnAdminSaleOrderView', Array('TsAdminOrderPrintTabs', 'onInit'));