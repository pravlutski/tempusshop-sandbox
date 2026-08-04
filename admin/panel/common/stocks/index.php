<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<?$curdate = date('Y-m-d');?>
<?$APPLICATION->SetTitle('Товары на складах');?>
<script src="/admin/panel/ozon/lib/chart.js" type="text/javascript"></script>

<h1>Товары на складах</h1>
<hr>
<div class="">
  <button value="wb-tab" class="tab-btn active-tab">Wildberries</button>
  <button value="ozon-tab" class="tab-btn">Ozon</button>
  <button value="yandex-tab" class="tab-btn">Yandex</button>
</div>

<div class="wb-tab tab">
  <!-- <h2>Wildberries</h2> -->
  <hr>
  <label for="cabinet-wb">Кабинет:&nbsp;&nbsp;</label>
  <select id="cabinet-wb" style="width: 120px; background-color: transparent; border: 1px solid rgba(0,0,0,0.25); padding: 5px; border-radius: 4px;">
      <option value="WR">WR</option>
      <option value="TL">IP</option>
  </select>
  <hr>
  <span class="summary_sum"><b>Итого, руб.:</b> <span id="wb-susu-c">??</span></span><br>
  <span class="summary_sum"><b>Итого, шт.:</b> <span id="wb-susto-c">??</span></span>
  <hr>
  <div class="WR wb-block charts-block">
    <div style="display: flex; flex-direction: row">
      <div style="display: flex">
        <canvas id="wb_sum_wr" width="700" height="350"></canvas>
      </div>
      <div style="display: flex">
        <canvas id="wb_stock_wr" width="700" height="350"></canvas>
      </div>
    </div>
  </div>
  <div class="TL wb-block charts-block" style="display:none">
    <div style="display: flex; flex-direction: row">
      <div style="display: flex">
        <canvas id="wb_sum_tl" width="700" height="350"></canvas>
      </div>
      <div style="display: flex">
        <canvas id="wb_stock_tl" width="700" height="350"></canvas>
      </div>
    </div>
  </div>
</div>

<div class="yandex-tab tab" style="display: none">
  <!-- <h2>Wildberries</h2> -->
  <hr>
  <span class="summary_sum"><b>Итого, руб.:</b> <span id="ya-susu-c">??</span></span><br>
  <span class="summary_sum"><b>Итого, шт.:</b> <span id="ya-susto-c">??</span></span>
  <hr>
  <div class="yandex-block charts-block">
    <div style="display: flex; flex-direction: row">
      <div style="display: flex">
        <canvas id="yandex_sum" width="700" height="350"></canvas>
      </div>
      <div style="display: flex">
        <canvas id="yandex_stock" width="700" height="350"></canvas>
      </div>
    </div>
  </div>
</div>

<div class="ozon-tab tab" style="display: none">
  <hr>
  <span class="summary_sum"><b>Итого, руб.:</b> <span id="ozon-susu-c">??</span></span><br>
  <span class="summary_sum"><b>Итого, шт.:</b> <span id="ozon-susto-c">??</span></span>
  <hr>
  <div class="ozon-block charts-block" >
    <div style="display: flex; flex-direction: row">
      <div style="display: flex">
        <canvas id="ozon_sum_2" width="700" height="250"></canvas>
      </div>
      <div style="display: flex">
        <canvas id="ozon_stock_2" width="700" height="250"></canvas>
      </div>
    </div>
    <hr>
    <div style="display: flex; flex-direction: row">
      <div style="display: flex">
        <canvas id="move_sum_1" width="700" height="250"></canvas>
      </div>
      <div style="display: flex">
        <canvas id="move_stock_1" width="700" height="250"></canvas>
      </div>
    </div>
    <hr>
    <div style="display: flex; flex-direction: row">
      <div style="display: flex">
        <canvas id="move_sum_2" width="700" height="250"></canvas>
      </div>
      <div style="display: flex">
        <canvas id="move_stock_2" width="700" height="250"></canvas>
      </div>
    </div>
  </div>
</div>

<style media="screen">
  .tab-btn{
    width: 160px;
    padding: 10px;
    border: none;
    font-size: 17px;
    font-weight: 650;
    background-color: rgba(245,245,245,0.9);
  }
  .active-tab{
    background-color: rgba(255, 206, 27, 0.8);
  }
</style>

<script type="text/javascript">
const CHART_DICT = {
  wb_stock: 'Остатки',
  wb_sum: 'Суммы',
  ozon_stock: 'Остатки',
  ozon_sum: 'Суммы',
  ozon_sum_2: "Доступный остаток, P",
  ozon_stock_2: "Доступный остаток, шт.",
  move_sum_2: "Возвраты/Проверка/Брак, P",
  move_stock_2: "Возвраты/Проверка/Брак, шт.",
  move_sum_1: "Движения, P",
  move_stock_1: "Движения, шт.",
}
const CHARTS_COLORS = {
  stock: 'rgba(255,0,123,0.4)',
  from: 'rgba(0,255,123,0.4)',
  to: 'rgba(123,0,255,0.4)',
  defect: 'rgba(0,255,255,0.4)'
}
function getWBChartData(cabinet = 'WR')
{
  $.ajax({
    url: 'ajax/getWBChartData.php',
    method: "POST",
    data: {cabinet: cabinet},
    success: function(response){
      var result = $.parseJSON(response);

      var last = Object.values(result).at(-1);
      $('#wb-susu-c').html(last.summary_sum);
      $('#wb-susto-c').html(last.summary_stock);

      var datasets = [
        {
          label: 'К клиенту',
          data: Object.values(result).map((el) => el.to_sum ),
          backgroundColor: CHARTS_COLORS['to'],
          pointBackgroundColor: '#007bff'
        },
        {
          label: 'От клиента',
          data: Object.values(result).map((el) => el.from_sum ),
          backgroundColor: CHARTS_COLORS['from'],
          pointBackgroundColor: '#007bff'
        },
        {
          label: 'Доступный остаток',
          data: Object.values(result).map((el) => el.stock_sum ),
          backgroundColor: CHARTS_COLORS['stock'],
          pointBackgroundColor: '#007bff'
        },
      ]

      drawChart( Object.keys(result), datasets, 'wb_sum', "_" + cabinet.toLowerCase() );

      datasets = [
        {
          label: 'К клиенту',
          data: Object.values(result).map((el) => el.to_client ),
          backgroundColor: CHARTS_COLORS['to'],
          pointBackgroundColor: '#007bff'
        },
        {
          label: 'От клиента',
          data: Object.values(result).map((el) => el.from_client ),
          backgroundColor: CHARTS_COLORS['from'],
          pointBackgroundColor: '#007bff'
        },
        {
          label: 'Доступный остаток',
          data: Object.values(result).map((el) => el.stock ),
          backgroundColor: CHARTS_COLORS['stock'],
          pointBackgroundColor: '#007bff'
        },
      ]

      drawChart( Object.keys(result), datasets, 'wb_stock', "_" + cabinet.toLowerCase() );
    }
  })
}

function getYandexChartData()
{
  $.ajax({
    url: 'ajax/getYandexChartData.php',
    method: "POST",
    success: function(response){
      var result = $.parseJSON(response);

      var last = Object.values(result).at(-1);
      $('#ya-susu-c').html(last.summary_sum);
      $('#ya-susto-c').html(last.summary_stock);

      var datasets = [
        {
          label: 'К клиенту',
          data: Object.values(result).map((el) => el.to_sum ),
          backgroundColor: CHARTS_COLORS['to'],
          pointBackgroundColor: '#007bff'
        },
        {
          label: 'От клиента',
          data: Object.values(result).map((el) => el.from_sum ),
          backgroundColor: CHARTS_COLORS['from'],
          pointBackgroundColor: '#007bff'
        },
        {
          label: 'На реализации/утилизации',
          data: Object.values(result).map((el) => el.utilization_sum ),
          backgroundColor: CHARTS_COLORS['stock'],
          pointBackgroundColor: '#007bff'
        },
      ]

      drawChart( Object.keys(result), datasets, 'yandex_sum',);

      datasets = [
        {
          label: 'К клиенту',
          data: Object.values(result).map((el) => el.to_client ),
          backgroundColor: CHARTS_COLORS['to'],
          pointBackgroundColor: '#007bff'
        },
        {
          label: 'От клиента',
          data: Object.values(result).map((el) => el.from_client ),
          backgroundColor: CHARTS_COLORS['from'],
          pointBackgroundColor: '#007bff'
        },
        {
          label: 'На реализации/утилизации',
          data: Object.values(result).map((el) => el.utilization ),
          backgroundColor: CHARTS_COLORS['stock'],
          pointBackgroundColor: '#007bff'
        },
      ]

      drawChart( Object.keys(result), datasets, 'yandex_stock' );
    }
  })
}

function getOzonChartData2()
{
  $.ajax({
    url: 'ajax/getOzonChartData2.php',
    method: "POST",
    success: function(response){
      var result = $.parseJSON(response);

      var last = Object.values(result).at(-1);
      $('#ozon-susu-c').html(last.summary_sum);
      $('#ozon-susto-c').html(last.summary_stock);

      var datasets = [
        {
          label: 'Доступный остаток',
          data: Object.values(result).map((el) => el.valid_stock_count_sum ),
          backgroundColor: CHARTS_COLORS['stock'],
          pointBackgroundColor: '#007bff'
        },
      ]
      // stock
      drawChart( Object.keys(result), datasets, 'ozon_sum_2' );

      datasets = [
        {
          label: 'Доступный остаток',
          data: Object.values(result).map((el) => el.valid_stock_count ),
          backgroundColor: CHARTS_COLORS['stock'],
          pointBackgroundColor: '#007bff'
        },
      ]

      drawChart( Object.keys(result), datasets, 'ozon_stock_2' );
      // move
      datasets = [
        {
          label: 'Возвраты покупателей',
          data: Object.values(result).map((el) => el.from_client_sum ),
          backgroundColor: CHARTS_COLORS['to'],
          pointBackgroundColor: '#007bff'
        },
        {
          label: 'В пути к клиенту',
          data: Object.values(result).map((el) => el.to_client_sum ),
          backgroundColor: CHARTS_COLORS['stock'],
          pointBackgroundColor: '#007bff'
        },
      ]

      drawChart( Object.keys(result), datasets, 'move_sum_1' );

      datasets = [
        {
          label: 'Возвраты покупателей',
          data: Object.values(result).map((el) => el.from_client ),
          backgroundColor: CHARTS_COLORS['to'],
          pointBackgroundColor: '#007bff'
        },
        {
          label: 'В пути к клиенту',
          data: Object.values(result).map((el) => el.to_client ),
          backgroundColor: CHARTS_COLORS['stock'],
          pointBackgroundColor: '#007bff'
        },
      ]

      drawChart( Object.keys(result), datasets, 'move_stock_1' );
      // static
      datasets = [
        {
          label: 'На проверке',
          data: Object.values(result).map((el) => el.other_stock_count_sum ),
          backgroundColor: CHARTS_COLORS['from'],
          pointBackgroundColor: '#007bff'
        },
        {
          label: 'Брак',
          data: Object.values(result).map((el) => el.stock_defect_stock_count_sum ),
          backgroundColor: CHARTS_COLORS['defect'],
          pointBackgroundColor: '#007bff'
        },
      ];

      drawChart( Object.keys(result), datasets, 'move_sum_2' );

      datasets = [
        {
          label: 'На проверке',
          data: Object.values(result).map((el) => el.other_stock_count ),
          backgroundColor: CHARTS_COLORS['from'],
          pointBackgroundColor: '#007bff'
        },
        {
          label: 'Брак',
          data: Object.values(result).map((el) => el.stock_defect_stock_count ),
          backgroundColor: CHARTS_COLORS['defect'],
          pointBackgroundColor: '#007bff'
        },
      ]

      drawChart( Object.keys(result), datasets, 'move_stock_2' );
    }
  })
}

function drawChart( keys, datasets, canvas, postfix = '' )
{
  let chart = document.getElementById(canvas + postfix);

  var costs = new Chart(chart, {
    type: 'bar',
   data: {
    labels: keys,
    datasets: datasets
  },
   options: {
     responsive: true,
     plugins: {
       legend: {
         position: 'top',
       },
       title: {
         display: true,
         text: CHART_DICT[canvas]
       }
     },
     responsive: false,
   },
  })
}

$(document).on('change', '#cabinet-wb', function(e){
  e.preventDefault();
  var cab = $(this).val();
  $('.wb-block').hide();
  $('.' + cab).show();
  getWBChartData( cab );
})

$(document).on('click', '.tab-btn', function(e){
  e.preventDefault();
  var tab = $(this).val();

  $('.tab').hide();
  $('.' + tab).show();

  $('.tab-btn').removeClass('active-tab');
  $(this).addClass('active-tab');
})

getWBChartData('WR');
getYandexChartData();
getOzonChartData2();
</script>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
