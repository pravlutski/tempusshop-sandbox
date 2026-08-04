 <div class="charts-container" style="display:block">
    <div class="tabs-charts-control">
      <button id="c-tab-WR" class="c-tab t-selected btn btn-warning">Кабинет WR</button>
      <button id="c-tab-TL" class="c-tab t-selected btn btn-light">Кабинет TL</button>
    </div>
    <hr>
   <div class="block-TL chart-block" style="display:none">
      <div class="row">
        <div class="col-md-6 col-sm-12">

          <div class="detail-block py-2 pl-4">
            <h3 class="mb-4 font-semibold text-xl">Динамика остатков FBO</h3>
            <hr>
            <h5 class="mb-4 font-semibold text-xl">Средние значения за период</h5>

            <form id="stat-period-form_TL" action="">
              <div class="d-1 d-d">
                <label for="input-date-log" class="font-bold mr-4"><b>От</b></label>
                <input type="date" name="min_date_stat" class="btn btn-light" style="margin-left:10px" id="input-date-min-TL" value="">
              </div>
              <div class="d-2 d-d">
                <label for="input-date-log" class="font-bold mr-4"><b>до</b></label>
                <input type="date" name="max_date_stat" class="btn btn-light" style="margin-left:10px" id="input-date-max-TL" value="">
              </div>
              <button id="show-stat-period-TL" class="show-period btn btn-light" style="margin-left:10px">Показать</button>
            </form>

            <div class="avg-stats pt-4" style="display:flex; flex-direction: column">
              <span style="display:flex; flex-direction:row" id="avg-stock_TL"></span>
              <span style="display:flex; flex-direction:row" id="avg-all_TL"></span>
              <!-- <p></p> -->
              <span style="display:flex; flex-direction:row" id="avg-stock-cost_TL"></span>
              <span style="display:flex; flex-direction:row" id="avg-all-cost_TL"></span>

            </div>

          </div>

          <hr>

          <div class="detail-block py-2 pl-4">
            <h5 class="mb-4 font-semibold text-xl">Детализация</h5>
            <form id="settings-log-form_TL" action="">
              <label for="input-date-log" class="font-bold mr-4"><b>Дата лога</b></label>
              <input type="date" name="date" class="btn btn-light" style="margin-left:10px" id="input-date-log-TL" value="">
              <button id="show-log-TL" class="show-log btn btn-light" style="margin-left:10px">Показать</button>
            </form>
          </div>

          <div class="list-ill list-ill-TL mt-2">

          </div>

        </div>
        <div class="col-md-6 col-sm-12">
          <i style="text-align: right;display:block; font-size: 14px; opacity:0.5"><?print_r( 'Последнее обновление было ' . date('Y.m.d G:i:s', filectime('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/logs/stat/TL/log.txt')) );?></i>
          <div id="stats-graph_TL" class="stats-graph">

          </div>
        </div>
      </div>

    </div>

   <div class="block-WR chart-block" style="">
      <div class="row" style="">
        <div class="col-md-6 col-sm-12">

          <div class="detail-block py-2 pl-4">
            <h3 class="mb-4 font-semibold text-xl">Динамика остатков FBO</h3>
            <hr>
            <h5 class="mb-4 font-semibold text-xl">Средние значения за период</h5>

            <form id="stat-period-form_WR" action="">
              <div class="d-1 d-d">
                <label for="input-date-log" class="font-bold mr-4"><b>От</b></label>
                <input type="date" name="min_date_stat" class="btn btn-light" style="margin-left:10px" id="input-date-min-WR" value="">
              </div>
              <div class="d-2 d-d">
                <label for="input-date-log" class="font-bold mr-4"><b>до</b></label>
                <input type="date" name="max_date_stat" class="btn btn-light" style="margin-left:10px" id="input-date-max-WR" value="">
              </div>
              <button id="show-stat-period-WR" class="show-period btn btn-light" style="margin-left:10px">Показать</button>
            </form>

            <div class="avg-stats pt-4" style="display:flex; flex-direction: column">
              <span style="display:flex; flex-direction:row" id="avg-stock_WR"></span>
              <span style="display:flex; flex-direction:row" id="avg-all_WR"></span>
              <!-- <p></p> -->
              <span style="display:flex; flex-direction:row" id="avg-stock-cost_WR"></span>
              <span style="display:flex; flex-direction:row" id="avg-all-cost_WR"></span>

            </div>

          </div>

          <hr>

          <div class="detail-block py-2 pl-4">
            <h5 class="mb-4 font-semibold text-xl">Детализация</h5>
            <form id="settings-log-form_WR" action="">
              <label for="input-date-log" class="font-bold mr-4"><b>Дата лога</b></label>
              <input type="date" name="date" class="btn btn-light" style="margin-left:10px" id="input-date-log-WR" value="">
              <button id="show-log-WR" class="show-log btn btn-light" style="margin-left:10px">Показать</button>
            </form>
          </div>

          <div class="list-ill list-ill-WR mt-2">

          </div>

        </div>
        <div class="col-md-6 col-sm-12">
          <i style="text-align: right;display:block; font-size: 14px; opacity:0.5"><?print_r( 'Последнее обновление было ' . date('Y.m.d G:i:s', filectime('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/logs/stat/WR/log.txt')) );?></i>
          <div id="stats-graph_WR" class="stats-graph">

          </div>
        </div>
      </div>
    </div>

 </div>

<style media="screen">
#stat-period-form_WR, #stat-period-form_TL{
  display: flex;
  flex-direction: row;
  gap: 10px;
}
#stat-period-form_WR , #stat-period-form_TL{
  display: flex;
  flex-direction: row;
  gap: 10px;
}
.d-d{
  flex-direction: row;
  width: fit-content;
}
canvas{
  /* height: 300px !important; */
}
@media (max-width: 867px){
  #stat-period-form_WR, #stat-period-form_TL{
    flex-direction: column;
  }
  .d-d{
    flex-direction: row;
    gap: 10px;
    margin-bottom: 10px;
  }
  #settings-log-form_WR, #settings-log-form_TL{
    display: flex;
    flex-direction: column;
    gap: 10px;
  }
  #settings-log-form_WR label, #settings-log-form_TL label{
    display: none;
  }
  #settings-log-form_WR input, #settings-log-form_TL input{
    width: 98%;
  }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script type="text/javascript">
  if ( screen.width > 768 ){
    var responsiveFlag = true;
  }else{
    var responsiveFlag = false;
  }

  const ajax_folder_path = '/admin/panel/wb/ajax/charts/';

  $(document).on('click', '.c-tab', function(e){
    e.preventDefault();
    var cabinet = $(this).attr('id').split('-')[2];
    var today = new Date().toISOString().slice(0, 10);
    $('.c-tab').removeClass('btn-warning');
    $('.c-tab').addClass('btn-light');
    $(this).removeClass('btn-light');
    $(this).addClass('btn-warning');
    $('.chart-block').hide();
    $('.block-' + cabinet).show();
    getChartData(cabinet, false, false);
    getStockDetail( cabinet, today );
  })

  $(document).on('click', '.show-period', function(e){
    e.preventDefault();
    var cabinet = $(this).attr('id').split('-')[3];
    var dateFrom = $('#input-date-min-' + cabinet).val();
    var dateTo = $('#input-date-max-' + cabinet).val();
    if ( dateFrom == '') dateFrom = false;
    if ( dateTo == '') dateTo = false;
    getChartData( cabinet, dateFrom, dateTo );
  })

  $(document).on('click', '.show-log', function(e){
    e.preventDefault();
    var cabinet = $(this).attr('id').split('-')[2];
    var date = $('#input-date-log-' + cabinet).val();
    getStockDetail( cabinet, date );
  })

  function getStockDetail( cabinet, date )
  {
    var form_data = new FormData();
    form_data.append('cabinet', cabinet);
    form_data.append('date', date);
    $.ajax({
      url: ajax_folder_path + 'getStockDetail.php',
      method: 'POST',
      data: form_data,
      processData: false,
      contentType: false,
      cache: false,
      success: function(response){
        $('.list-ill-' + cabinet).html(response);
      }
    })
  }

  function getChartData( cabinet, dateFrom = false, dateTo = false )
  {
    var form_data = new FormData();
    form_data.append( 'cabinet', cabinet );
    if ( dateFrom ){
      form_data.append( 'dateFrom', dateFrom );
    }
    if ( dateTo ){
      form_data.append( 'dateTo', dateTo );
    }
    $.ajax({
      url: ajax_folder_path + 'getStockData.php',
      method: 'POST',
      data: form_data,
      processData: false,
      contentType: false,
      cache: false,
      success: function(response){
        var result = $.parseJSON(response);
        var all_cost = result.avgData.all_cost;
        var stock_cost = result.avgData.stock_cost;
        var all_stock = result.avgData.all_stock;
        var stock_stock = result.avgData.stock_stock;
        $('#avg-stock-cost_' + cabinet).html( "<b>Средняя себестоимость, ₽:&nbsp</b>" + stock_cost.toLocaleString("ru") );
        // $('#avg-all-cost_' + cabinet).html( "<b>Среднее, ₽ (с учётом всего):&nbsp</b>" + all_cost.toLocaleString("ru") );
        $('#avg-stock_' + cabinet).html( "<b>Средний остаток, шт.:&nbsp</b>" + stock_stock.toLocaleString("ru") );
        // $('#avg-all_' + cabinet).html( "<b>Среднее, шт. (с учётом всего):&nbsp</b>" + all_stock.toLocaleString("ru") );
        $('#stats-graph_' + cabinet).html();
        $('#stats-graph_' + cabinet).html('<canvas class="my-4 w-100" id="stats_cost_fbo_'+cabinet+'" height="300"></canvas><canvas class="my-4 w-100" id="stats_stock_fbo_'+cabinet+'" height="300"></canvas><canvas class="my-4 w-100" id="stats_model_fbo_'+cabinet+'" height="300"></canvas>');
        drawCharts( result, cabinet );
      }
    })
  }

  function drawCharts( data, cabinet )
  {
    var costs_chart = document.getElementById('stats_cost_fbo_' + cabinet);
    var stocks_chart = document.getElementById('stats_stock_fbo_' + cabinet);
    var models_chart = document.getElementById('stats_model_fbo_' + cabinet);

    var labelsChart = Object.keys(data.chartData);
    var allCost = Object.values(data.chartData).map(item => item.all);
    var stockCost = Object.values(data.chartData).map(item => item.stock);

    var costs = new Chart(costs_chart, {
      type: 'bar',
     data: {
      labels: labelsChart,
      datasets: [
        // {
        //   label: 'С учётом всего',
        //   data: allCost,
        //   backgroundColor: 'rgba(0,123,255,0.5)',
        //   pointBackgroundColor: '#007bff'
        // },
        {
          label: 'FBO',
          data: stockCost,
          backgroundColor: 'rgba(0,255,123,0.5)',
          pointBackgroundColor: '#007bff'
        },
      ]
    },
     options: {
       responsive: true,
       plugins: {
         legend: {
           position: 'top',
         },
         title: {
           display: true,
           text: 'Cебестоимость по датам, ₽'
         }
       },
       responsive: responsiveFlag,
       // maintainAspectRatio: true,
     },
    })
    var labels = Object.keys(data.stockDynamic);
    var fromClientData = Object.values(data.stockDynamic).map(item => item.from_client);
    var toClientData = Object.values(data.stockDynamic).map(item => item.to_client);
    var stockData = Object.values(data.stockDynamic).map(item => item.stock);

    var stocks = new Chart(stocks_chart, {
      type: 'bar',
     data: {
      labels: labels,
      datasets: [
        // {
        //   label: 'В пути от клиента',
        //   data: fromClientData,
        //   backgroundColor: 'rgba(0,123,255,0.5)',
        //   pointBackgroundColor: '#007bff'
        // },
        // {
        //   label: 'В пути к клиенту',
        //   data: toClientData,
        //   backgroundColor: 'rgba(0,255,123,0.5)',
        //   pointBackgroundColor: '#007bff'
        // },
        {
          label: 'FBO',
          data: stockData,
          backgroundColor: 'rgba(255,123,0,0.5)',
          pointBackgroundColor: '#007bff'
        },
      ]
    },
     options: {
       responsive: true,
       plugins: {
         legend: {
           position: 'top',
         },
         title: {
           display: true,
           text: 'Динамика остатков, шт.'
         }
       },
       responsive: responsiveFlag,
       // maintainAspectRatio: false,
     },
    })

    var models = new Chart(models_chart, {
      type: 'bar',
     data: {
      labels: Object.keys(data.modelAssort),
      datasets: [
        {
          label: 'FBO',
          data: Object.values(data.modelAssort),
          backgroundColor: 'rgba(123,0,255,0.5)',
          pointBackgroundColor: '#007bff'
        },
      ]
    },
     options: {
       responsive: true,
       plugins: {
         legend: {
           position: 'top',
         },
         title: {
           display: true,
           text: 'Широта ассортимента, шт.'
         }
       },
       responsive: responsiveFlag,
       // maintainAspectRatio: false,
     },
    })

  }
  var today = new Date().toISOString().slice(0, 10);
  getStockDetail( 'WR', today )
  getChartData( 'WR', false, false );

</script>
