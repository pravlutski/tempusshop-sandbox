<?php

/**
 * @global $APPLICATION
 */

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");

if(!CModule::IncludeModule('panel.manager'))
    return;

opcache_reset();

CModule::IncludeModule("iblock");

global $DB; // Подключаем глобальный объект базы данных

$profile_id = $_GET["id"]; // Пример ID тега, для которого хотим получить информацию
$profile = [];

$strSql = "SELECT * FROM `ci_custom_analiz_price` WHERE `id` = " . intval($profile_id);
$res = $DB->Query($strSql);
if ($row = $res->Fetch()) {
    $profile = $row;
}

$APPLICATION->SetTitle('Редактирование профиля | Ручной анализ цен');

?>
    <link href="/admin/modules/custom_analiz_price/style.css" rel="stylesheet">
    <h1 class="page-header">Редактирование профиля: <?= $profile['article'] ?></h1>
    <div class="col-sm-12 panel panel-default create-block-tags">
        <div class="col-sm-12">
            <h3 class="page-header">Основные параметры</h3>
        </div>
        <form action="process_edit.php" method="post">
            <input type="hidden" name="id" value="<?= $profile_id ?>"> <!-- Передаем ID тега для обработки -->
            <div class="col-sm-12 mb-15">
                <label>Дата и время создания:</label>
                <span><?=$profile["created_at"]?></span>
            </div>
            <div class="col-sm-12 mb-15">
                <label>Дата и время последнего обновления:</label>
                <span><?=$profile["updated_at"]?></span>
            </div>
			<div class="col-sm-12 mb-15">
                <label for="price_id">Тип цены:</label>
                <span><?=$profile['price_id'];?></span>
            </div>
            <div class="col-sm-12 mb-15">
                <label for="article">Артикул</label>
                <input type="text" name="article" id="article" class="form-control" value="<?= $profile['article'] ?>" required>
            </div>
			<div class="col-sm-12 mb-15">
				<label for="markup">Ставка</label>
				<input type="text" name="markup" id="markup" class="form-control" value="<?= $profile['markup'] ?>"
					   pattern="^[0-9.]+$" title="Пример: 2.10" placeholder="2.00" required>
			</div>
            <div class="col-sm-12 mt-30">
                <input type="submit" value="Сохранить" class="btn btn-primary set_period">
                <a href="/admin/modules/custom_analiz_price/" class="btn btn-primary">
                    Отменить
                </a>
            </div>
        </form>
    </div>
<?php

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");