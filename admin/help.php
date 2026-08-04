<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>

<div id="container" class="">
  <div class="tabs-block">
    <button class="tab-btn active" value="force-update-block">Принудительное обновление</button>
    <button class="tab-btn" value="settings-block">Настройки</button>
    <button class="tab-btn" value="onliner-block">Onliner</button>
  </div>

  <div id="force-update-block" class="tab">

    <div class="card update-stock panel panel-default">
      <h4>Обновление складов</h4>
      <p><b>Тип:</b> Выпадающий список</p>
      <p><b>Автоматический запуск (крон):</b> Каждые 5 минут</p>
      <p><b>Описание:</b>
        Запускает процесс синхронизации себестоимостей и остатков с МС для отдельных аккаунтов и складов.
      </p>
    </div>

    <div class="card update-stock panel panel-default">
      <h4>Обновление цен</h4>
      <p><b>Тип:</b> Выпадающий список</p>
      <p><b>Автоматический запуск (крон):</b> Каждый час в 0 минут</p>
      <p><b>Описание:</b>
        Запускает процесс формирования цен из установленных общих и индивидуальных наценок и загруженных прайслистов для каждого из типов цен.
      </p>
    </div>

    <div class="card update-top-wb panel panel-default">
      <h4>Обновить ТОП WB</h4>
      <p><b>Тип:</b> Действие</p>
      <p><b>Автоматический запуск (крон):</b> Каждый четверг в 21:00</p>
      <p><b>Описание:</b>
        Запускает процесс формирования топа WB исходя из указанных настроек и на основе отчета о продажах из МС. Фиксирует результат в таблице ci_wb_top.
      </p>
    </div>

    <div class="card update-top-sites panel panel-default">
      <h4>Обновить ТОП сайта</h4>
      <p><b>Тип:</b> Действие</p>
      <p><b>Автоматический запуск (крон):</b> Каждый день в 00:00</p>
      <p><b>Описание:</b>
        Запускает процесс формирования топов для сайтов s1 (RU) и s2 (BY) исходя из указанных настроек и на основе отчета о продажах из МС. Фиксирует результат в таблице ci_top_models.
      </p>
    </div>

    <div class="card update-catalog panel panel-default">
      <h4>Обновить каталог</h4>
      <p><b>Тип:</b> Действие</p>
      <p><b>Автоматический запуск (крон):</b> Каждый день в 23:00</p>
      <p><b>Описание:</b>
        Запускает процесс обновления информации о товарах в каталоге, включая доступность, сортировку и количество. Также обновляет данные о сроках доставки и устанавливает типы складов OZON (5D, 7D, Express и т.п.)
      </p>
    </div>

    <div class="card update-sale panel panel-default">
      <h4>Обновить раздел СУПЕРЦЕНА</h4>
      <p><b>Тип:</b> Действие</p>
      <p><b>Автоматический запуск (крон):</b> Каждые 10 минут</p>
      <p><b>Описание:</b>
        Запускает процесс обновления раздела "Cуперцена" для s1 (RU) и s2 (BY) на основе отчета  МС об остатках (количество дней на складе).
      </p>
    </div>

  </div>

  <div class="tab" id="settings-block" style="display: none">

    <div class="card brand panel panel-default">
      <h4>Настройки брендов</h4>
      <p><b>Тип:</b> Модальное окно</p>
      <p><b>Описание:</b>
        Модальное окно с настройками брендов. <i>Внимание: cписок не связан с инфоблоком "Бренды" Bitrix.</i>
      </p>
      <p><b>Функционал:</b></p>
      <ul>
        <li>Указание альтернативных названий брендов</li>
        <li>Установка регулярных выражений для артикулов</li>
        <li>Установка дополнительных наценок для брендов</li>
      </ul>
    </div>

    <div class="card suppliers panel panel-default">
      <h4>Поставщики</h4>
      <p><b>Тип:</b> Модальное окно</p>
      <p><b>Описание:</b>
        Модальное окно с настройками поставщиков.
      </p>
      <p><b>Функционал:</b></p>
      <ul>
        <li>Установка, для каких типов цен будут активны прайслисты поставщика</li>
        <li>Создание профилей для брендов (активность для типов цен, скидка)</li>
        <li>Настройки обработчика прайслистов</li>
        <li>Установка дней доставки для разных сайтов</li>
        <li>Установка типов складов для OZON</li>
        <li>Установка дополнительных наценок</li>
        <li>Сопоставление с контрагентом МС</li>
      </ul>
    </div>

    <div class="card suppliers panel panel-default">
      <h4>Настройки ТОП</h4>
      <p><b>Тип:</b> Модальное окно</p>
      <p><b>Описание:</b>
        Модальное окно с настройками формирования топов для сайтов и WB.
      </p>
    </div>

    <div class="card suppliers panel panel-default">
      <h4>Оптовики</h4>
      <p><b>Тип:</b> Модальное окно</p>
      <p><b>Описание:</b>
        Модальное окно с настройками оптовиков.
      </p>
      <p><b>Функционал:</b></p>
      <ul>
        <li>Установка дополнительных наценок</li>
        <li>Установка флага "Цены НДС"</li>
        <li>Возможность исключить определенные товары</li>
      </ul>
    </div>

    <div class="card suppliers panel panel-default">
      <h4>Лог</h4>
      <p><b>Тип:</b> Ссылка</p>
      <p><b>Описание:</b>
        Обозреватель логов внутренних процессов и процессов обмена.
      </p>
    </div>

  </div>

  <div class="tab" id="onliner-block" style="display: none">

    <div class="card upload-goods panel panel-default">
      <h4>Выгрузка товаров</h4>
      <p><b>Тип:</b> Действие</p>
      <p><b>Автоматический запуск (крон):</b> Каждый час в 10 минут</p>
      <p><b>Описание:</b>
        Принудительная выгрузка товаров, цен и информации о наличии.
      </p>
    </div>

    <div class="card parse-competitors panel panel-default">
      <h4>Парсинг конкурентов</h4>
      <p><b>Тип:</b> Действие</p>
      <p><b>Автоматический запуск (крон):</b> Каждые 40 минут</p>
      <p><b>Описание:</b>
        Принудительный запуск парсера цен конкурентов. Влияет на формирование цены BY. <i>При указании обязательного наличия цен конкурентов и пустой таблице цена BY обновляться не будет!</i>
      </p>
    </div>

    <div class="card parse-competitors panel panel-default">
      <h4>Парсинг структуры</h4>
      <p><b>Тип:</b> Действие</p>
      <p><b>Автоматический запуск (крон):</b> Каждый день в 02:10</p>
      <p><b>Описание:</b>
        Принудительный запуск парсера структуры. Получает артикулы онлайнера для привязки к ним наших карточек товаров</i>
      </p>
    </div>

  </div>

</div>

<style media="screen">
  .tabs-block{
    display: flex;
    flex-direction: row;
    margin-bottom: 20px;
  }
  .tab-btn{
    width: 320px;
    /* display: flex; */
    text-align: center;
    font-size: 17px;
    padding: 10px 20px;
    border: none;
    background-color: #286090;
    color: white;
  }
  .tab-btn:hover{
    font-weight: bolder;
  }
  .active{
    background-color: #f0ad4e;
    color: black;
    font-weight: bolder;
  }
  .card{
    padding: 8px 10px;
  }
</style>

<script type="text/javascript">
  $(document).on('click', '.tab-btn', function(){
    $('.tab-btn').removeClass('active');
    $('.tab').hide();
    $('#' + $(this).val() ).show();
    $(this).addClass('active');
  })
</script>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
