var fileDef = undefined;
var fileIll = undefined;

$(document).on('click','#def-tab', function(){
  $('.spin-wrapper').remove();
  $('#ill-disc-block').hide();
  $('#settings-block').hide();
  // $('#logs-block').hide();
  $('#def-disc-block').show();
  $('.tab-btn').css('background-color','#083344');
  $('.tab-btn').css('color','white');
  $('#def-tab').css('background-color','#f0ad4e');
  $('#def-tab').css('color','#083344');
  // $('#discounts-ill-input').val('').change();
  // fileIll = undefined;
})
$(document).on('click','#ill-tab', function(){
  $('.spin-wrapper').remove();
  $('#def-disc-block').hide();
  $('#settings-block').hide();
  // $('#logs-block').hide();
  $('#ill-disc-block').show();
  $('.tab-btn').css('background-color','#083344');
  $('.tab-btn').css('color','white');
  $('#ill-tab').css('background-color','#f0ad4e');
  $('#ill-tab').css('color','#083344');
  // $('#discounts-def-input').val('').change();
  // fileDef = undefined;
})
$(document).on('click','#settings-tab', function(){
  $('.spin-wrapper').remove();
  $('#ill-disc-block').hide();
  $('#def-disc-block').hide();
  // $('#logs-block').hide();
  $('#settings-block').show();
  $('.tab-btn').css('background-color','#083344');
  $('.tab-btn').css('color','white');
  $('#settings-tab').css('background-color','#f0ad4e');
  $('#settings-tab').css('color','#083344');
})

// $(document).on('click','#logs-tab', function(){
//   $('.spin-wrapper').remove();
//   $('#ill-disc-block').hide();
//   $('#def-disc-block').hide();
//   $('#settings-block').hide();
//   $('#logs-block').show();
//   $('.tab-btn').css('background-color','#083344');
//   $('.tab-btn').css('color','white');
//   $('#logs-tab').css('background-color','#f0ad4e');
//   $('#logs-tab').css('color','#083344');
// })

$(document).on('change','#discounts-def-input', function(){
  fileDef = this.files[0];

  $('.filename-def').html(fileDef != undefined ? fileDef.name : 'Загрузить файл');
  $('#label-def').css('background-color', '#f0ad4e');
  $('#label-def').css('color', '#083344');
})
$(document).on('change','#discounts-ill-input', function(){
  fileIll = this.files[0];
  $('.filename-ill').html(fileIll != undefined ? fileIll.name : '');
  $('#label-ill').css('background-color', '#f0ad4e');
  $('#label-ill').css('color', '#083344');
})

loadSettings();
$(document).on('change','#cab-selector',function(){
  loadSettings();
})

$(document).on('click','#send-def-data-btn', function(){
  $('.ill-table').html('\
    <div class="spin-wrapper">\
      <div class="spinner">\
      </div>\
    </div>\
    ');
    if (fileDef != undefined){
      sendDataDefault(fileDef);
    }else{
      alert('Файл не выбран');
    }
})
$(document).on('keyup', '#discount-max', function(){
  calculateMargin()
})
$(document).on('keyup', '#discount-min', function(){
  calculateMargin()
})
$(document).on('keyup', '#markup', function(){
  calculateMargin()
})
$(document).on('keyup', '#comission', function(){
  calculateMargin()
})
$(document).on('click','#send-ill-data-btn', function(){
    if (fileIll != undefined){
      sendDataIlliquid(fileIll);
    }else{
      alert('Файл не выбран');
    }
})
$(document).on('click','#upload-ill-disc-btn', function(){
  uploadDisc('illiquid');
})
$(document).on('click','#upload-def-disc-btn', function(){
  uploadDisc('default');
})
$(document).on('click','#save-settings-def-btn',function(){
  saveSettingsDef();
})
$(document).on('click','#save-settings-ill-btn',function(){
  saveSettingsIll();
})

function uploadDisc(type){
  if (type == 'illiquid'){
    var selector = '.ill-table';
  }else if(type == 'default'){
    var selector = '.def-chart';
  }
  $(selector).html('\
    <div class="spin-wrapper">\
      <div class="spinner">\
      </div>\
    </div>\
    ');
    var cabinet = $('#cab-selector').val();
  $.ajax({
    url: '/admin/modules/DiscountsWB/ajax/applyDiscounts.php',
    method: 'POST',
    // dataType: 'json',
    data: {flag: type, cabinet: cabinet},
    success: function(response){
      var result = $.parseJSON(response);
      if (type == 'illiquid'){
        var selector = '.ill-table';
      }else if(type == 'default'){
        var selector = '.def-chart';
      }
      if (result.error != undefined && result.error.length > 0){
        for (let i = 0; i < result.error.length; i++){
          var chunk = result.error[i];
          $(selector).html('<p>Ошибка в '+chunk.data.id+': '+chunk.errorText+'</p>');
        }
      }else{
        $(selector).html('<p class="inside-goods">Выгрузка успешна</p>');
      }

    },
    error: function(response){
      $(selector).html('<pre><code>' + response + '</code></pre>');
    }
  })
}

function sendDataIlliquid(file){
  $('.ill-table').html('\
    <div class="spin-wrapper">\
      <div class="spinner">\
      </div>\
    </div>\
    ');
  var form_data = new FormData;
  form_data.append('file', file);
  form_data.append('cabinet', $('#cab-selector').val());
  $.ajax({
    url: '/admin/modules/DiscountsWB/ajax/illProcessor.php',
    method: 'POST',
    data: form_data,
    dataType: 'json',
    cache: false,
    processData: false,
    contentType: false,
    success: function(response){
      var result = response;
      console.log(result);
      if (result.error != undefined){
        $('.ill-table').html();
        alert(result.error);
        return;
      }
      $('#upload-ill-disc-btn').show();
      $('.ill-table').html('\
      <br>\
      <p class="def-notif inside-goods">Новая скидка в '+result.discMax+'% будет установлена для '+result.onSale.length+' товаров</p>\
      <p class="def-notif inside-goods">Новая скидка в '+result.discMin+'% будет установлена для '+result.notOnSale.length+' товаров</p>\
      ');
    }
  })
}

function sendDataDefault(file){
  var form_data = new FormData;
  form_data.append('file', file);
  form_data.append( 'discMax', $('#discount-max').val() );
  form_data.append('cabinet', $('#cab-selector').val());
  $.ajax({
    url: '/admin/modules/DiscountsWB/ajax/defProcessor.php',
    method: 'POST',
    data: form_data,
    dataType: 'json',
    cache: false,
    processData: false,
    contentType: false,
    success: function(response){
      var result = response;
      if (result.error != undefined){
        alert(result.error);
        return;
      }
      if (result.flag == 'chart'){
        $('#def-table').html('<canvas class="" id="stats-def" width="1450" height="350"></canvas>');
        var ctx = document.getElementById('stats-def');
        var myChart = new Chart(ctx, {
          type: 'line',
          data: {
            labels: result.xaxis,
            datasets: [{
              label: 'Количество товаров ('+result.countGoods+')',
              data: result.yaxis,
              lineTension: 0,
              backgroundColor: 'transparent',
              borderColor: '#007bff',
              borderWidth: 1,
              pointBackgroundColor: '#007bff'
            }
          ]
        },
        options: {
          scales: {
            y: {
              ticks: {
                // beginAtZero: false,
                stepSize: 50,
                min: 0,
                max: 1000,
                suggestedMax: 20
              }
            },
            x:{
              ticks: {
                // beginAtZero: false,
                autoSkip: false,
                stepSize: 1,
                min: 20,
                max: 100,
              }
            }
          },
          legend: {
            display: false
          },



        }
      })
    }else{
      $('#upload-def-disc-btn').show();
      $('#def-chart').html('<p class="inside-goods">Товаров участвует в акции: '+result.discGoods+'</p>')
    }

    }
  })
}

function calculateMargin(){
  var basePrice = 5000;
  var markup = $('#markup').val();
  var comission = $('#comission').val() / 100;
  var discMax = $('#discount-max').val() / 100;
  var discMin = $('#discount-min').val() / 100;

  var priceWB = basePrice * markup * 1.25;
  var discFullPrice = (priceWB * (1 - discMax)) * (1 - comission);
  var flatMargin = discFullPrice - basePrice;
  var margin = (flatMargin / basePrice) * 100;

  $('.margin-info').html(margin.toPrecision(2) + '%');
}

function loadSettings(){
  var form_data = new FormData();
  form_data.append('cabinet', $('#cab-selector').val());
  $.ajax({
    url: '/admin/modules/DiscountsWB/ajax/loadSettings.php',
    method: 'POST',
    data: form_data,
    // dataType: 'json',
    cache: false,
    processData: false,
    contentType: false,
    success: function(response){
      var result = $.parseJSON(response);
      $('#discMin-ill').val(result.discMin).change();
      $('#discMax-ill').val(result.discMax).change();
      $('#turnover-set-ill').val(result.turnover_col).change();
      $('#nmid-set-ill').val(result.nmid_col).change();
    },
    error: function(response){
      alert('Ошибка загрузки настроек кабинета');
    }
  })
}

function saveSettingsDef(){
  var form_data = new FormData();
  form_data.append('markup',$('#markup-set').val());
  form_data.append('comission',$('#comission-set').val());
  form_data.append('discMin',$('#discount-min-set').val());
  form_data.append('nmid_col',$('#nmid-set').val());
  form_data.append('curDisc_col',$('#discount-current-set').val());
  form_data.append('uplDisc_col',$('#discount-upload-set').val());
  $.ajax({
    url: '/admin/modules/DiscountsWB/ajax/setSettingsDef.php',
    method: 'POST',
    data: form_data,
    // dataType: 'json',
    cache: false,
    processData: false,
    contentType: false,
    success: function(response){
      var result = response;
      if (result.error != undefined){
        alert(result.error);
      }else{
        $('#set-info-block').html('\
        <br>\
        <br>\
        <p class="def-notif inside-goods">Настройки сохранены. Перезагрузите страницу</p>\
        ');
      }
    },
    error: function(response){
      $('.set-table').html(response);
    }
  })
}
function saveSettingsIll(){
  var form_data = new FormData();
  form_data.append('nmid_col',$('#nmid-set-ill').val());
  form_data.append('turnover_col',$('#turnover-set-ill').val());
  form_data.append('discMin',$('#discMin-ill').val());
  form_data.append('discMax',$('#discMax-ill').val());
  form_data.append('cabinet',$('#cab-selector').val());
  $.ajax({
    url: '/admin/modules/DiscountsWB/ajax/setSettingsIll.php',
    method: 'POST',
    data: form_data,
    // dataType: 'json',
    cache: false,
    processData: false,
    contentType: false,
    success: function(response){
      var result = response;
      if (result.error != undefined){
        alert(result.error);
      }else{
        $('.set-table').html('\
        <br>\
        <br>\
        <p class="def-notif inside-goods">Настройки сохранены. Перезагрузите страницу</p>\
        ');
      }
    },
    error: function(response){
      $('.set-table').html(response);
    }
  })
}
