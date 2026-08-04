var fileDef = undefined;
var fileIll = undefined;

$(document).on('click','#def-tab', function(){
  $('.spin-wrapper').remove();
  $('#ill-disc-block').hide();
  $('#settings-block').hide();
  $('#def-disc-block').show();
  $('.tab-btn').css('background-color','#083344');
  $('.tab-btn').css('color','white');
  $('#def-tab').css('background-color','#f0ad4e');
  $('#def-tab').css('color','#083344');
})

$(document).on('click','#settings-tab', function(){
  $('.spin-wrapper').remove();
  $('#ill-disc-block').hide();
  $('#def-disc-block').hide();
  $('#settings-block').show();
  $('.tab-btn').css('background-color','#083344');
  $('.tab-btn').css('color','white');
  $('#settings-tab').css('background-color','#f0ad4e');
  $('#settings-tab').css('color','#083344');
})

$(document).on('change','#discounts-def-input', function(){
  fileDef = this.files[0];

  $('.filename-def').html(fileDef != undefined ? fileDef.name : 'Загрузить файл');
  $('#label-def').css('background-color', '#f0ad4e');
  $('#label-def').css('color', '#083344');
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
// $(document).on('keyup', '#discount-max', function(){
//   calculateMargin()
// })
// $(document).on('keyup', '#discount-min', function(){
//   calculateMargin()
// })
// $(document).on('keyup', '#markup', function(){
//   calculateMargin()
// })
// $(document).on('keyup', '#comission', function(){
//   calculateMargin()
// })

$(document).on('click','#save-settings',function(){
  saveSettingsDef();
})

function sendDataDefault(file){
  var form_data = new FormData;
  form_data.append('file', file);
  form_data.append( 'margin', $('#margin').val() );
  form_data.append( 'comission', $('#comission').val() );
  form_data.append( 'markup', $('#markup').val() );
  $('#def-table').html('\
    <div class="spin-wrapper">\
      <div class="spinner">\
      </div>\
    </div>\
    ');
  $.ajax({
    url: '/admin/modules/DiscountsYA/ajax/file_processor.php',
    method: 'POST',
    data: form_data,
    // dataType: 'json',
    cache: false,
    processData: false,
    contentType: false,
    success: function(response){
      var result = response;
      $('#def-table').html(response);
    },
    error: function(response){
      $('#def-table').html('<div class="error-alert">Системная ошибка!<br>' + JOSN.stringify(response) + '</div>');
    }
  })
}

// function calculateMargin(){
//   var basePrice = 5000;
//   var markup = $('#markup').val();
//   var comission = $('#comission').val() / 100;
//   var discMax = $('#discount-max').val() / 100;
//   var discMin = $('#discount-min').val() / 100;
//
//   var priceWB = basePrice * markup * 1.25;
//   var discFullPrice = (priceWB * (1 - discMax)) * (1 - comission);
//   var flatMargin = discFullPrice - basePrice;
//   var margin = (flatMargin / basePrice) * 100;
//
//   $('.margin-info').html(margin.toPrecision(2) + '%');
// }

function saveSettingsDef(){
  var form_data = new FormData();
  // form_data.append('markup',$('#markup-set').val());
  form_data.append('comission',$('#comission-set').val());
  form_data.append('margin',$('#margin-set').val());
  form_data.append('sku_col',$('#sku-set').val());
  form_data.append('price_col',$('#max-price-set').val());
  // form_data.append('uplDisc_col',$('#discount-upload-set').val());

  $.ajax({
    url: '/admin/modules/DiscountsYA/ajax/set_settings.php',
    method: 'POST',
    data: form_data,
    cache: false,
    processData: false,
    contentType: false,
    success: function(response){
      var result = response;
      if (result.error != undefined){
        alert(result.error);
      }else{
        $('#set-info-block').html('\
        <p class="def-notif inside-goods">Настройки сохранены. Перезагрузите страницу</p>\
        ');
      }
    },
    error: function(response){
      $('.set-table').html(response);
    }
  })
}
