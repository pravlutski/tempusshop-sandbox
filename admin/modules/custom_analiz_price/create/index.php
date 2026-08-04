<?php

/**
 * @global CMain $APPLICATION
 */

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");

if(!CModule::IncludeModule('panel.manager'))
    return;

opcache_reset();

CModule::IncludeModule("iblock");

$APPLICATION->SetTitle('Создание профиля | Конфигуратор');

?>
    <link href="/admin/modules/custom_analiz_price/style.css" rel="stylesheet">
    <h1 class="page-header">
        Создание профиля
    </h1>
    <div class="col-sm-12 panel panel-default create-block-tags">
        <div class="col-sm-12">
            <h3 class="page-header">
                Основные параметры
            </h3>
        </div>
        <form action="process_create.php" method="post">
            <div class="col-sm-12 mb-15">
                <label for="article">Артикул</label>
                <input type="text" name="article" id="article" class="form-control" required>
            </div>
            <div class="col-sm-12 mb-15">
                <label for="markup">Ставка</label>
                <input type="text" name="markup" id="markup" class="form-control"  pattern="^[0-9.]+$"
					   title="Пример: 2.10" placeholder="2.00" required>
            </div>
            <div class="col-sm-12 mb-15">
                <label for="price_id">Тип цены</label>
                <select name="price_id" class="form-control" id="price_id">
                    <option value="wb">Wildberries</option>
                    <option value="os">Ozon</option>
                    <option value="sb">SberMarket</option>
                    <option value="ya">Yandex</option>
                </select>
            </div>
            <hr>
            <div class="col-sm-12">
                <input type="submit" value="Создать" class="btn btn-primary set_period">
                <a href="/admin/modules/custom_analiz_price/" class="btn btn-primary">
                    Отменить
                </a>
            </div>
        </form>
    </div>
<?php

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
