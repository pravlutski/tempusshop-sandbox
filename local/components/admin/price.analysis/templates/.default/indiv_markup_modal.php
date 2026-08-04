<div class="modal fade" id="modal_individual_markup" tabindex="-1" role="dialog" aria-labelledby="modal_settings_cost" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
				<h4 class="modal-title" id="im-modal">Индивидуальные наценки</h4>
			</div>

				<div class="modal-body modal_big_padding">
					<form class="form-horizontal" action="#" method="POST" name="im-settings" id="im-settings">
            <textarea id="input-rules-im" name="input-rules" rows="8" cols="66" placeholder="Добавьте правила..."></textarea>
            <input id="rule-source" type="hidden" name="input-source" value="">
            <button id="add-rules-im" class="btn btn-primary">Применить</button>
						<a id="export-rules-im" href="" class="btn btn-primary">Экспорт</a>
					</form>
          <hr>
					<div style="width: 100%; display: flex; flex-direction: row">
						<h4 style="width: 50%; display: flex">Созданные правила</h4>
						<button type="button" id="delete-all-rules" class="btn btn-danger" style="margin-left: auto">Удалить всё</button>
					</div>
          <br>
          <form id="added-rules-im" class="" action="#" method="post">

          </form>
				</div>
				<div class="modal-footer">
          <button id="save-changes-im" class="btn btn-primary" type="button">Сохранить изменения</button>
					<button type="button" class="btn btn-default"  data-dismiss="modal"  aria-label="Close">Выйти</button>
				</div>
		</div>
	</div>
</div>

<style media="screen">
  #input-rules-im{
    resize: none;
    margin-top: 10px;
    padding: 6px;
  }
  #add-rules-im, #export-rules-im{
    margin-top: 10px;
    width: fit-content;
  }
  #im-settings{
    display: flex;
    flex-direction: column;
  }
  .rule-container{
    display: flex;
    flex-direction: row;
    padding: 10px;
    border-bottom: 1px solid rgba(0,0,0,0.1);
  }
  .model-label{
    margin-top: 6px;
		width: 324px;
  }
  .del-btn-im{
    margin-left: auto;
    margin-top: 2px;
  }
  .rule-input{
    margin-left: auto;
    width: 20%;
  }
  .notif-im{
    background-color: rgba(0,255,0,0.2);
    font-size: 16px;
    font-weight: bolder;
    text-align: center;
    border-radius: 6px;
  }
  .error-im{
    display: flex;
    flex-direction: column;
    background-color: rgba(255,0,0,0.5);
    border-radius: 6px;
  }
  .error_im span{
    display: flex;
  }

	.sticker-notify {
	position: absolute;
	top: -10px; /* Половина стикера выходит за верхнюю границу */
	right: -1px; /* Половина стикера выходит за правую границу */

	width: 20px;
	height: 20px;
	background: red;
	border-radius: 50%;

	display: flex;
	align-items: center;
	justify-content: center;

	color: white;
	font-size: 12px;
}
#btn_modal_ind_markup{
	overflow: visible;
  position: relative;
}
</style>
<script type="text/javascript">
var globalSource;
  $(document).on('click', '#btn_modal_ind_markup', function(e){
    var source = $('#s-website').val();
		if (globalSource != source){
			console.log( globalSource );
			console.log( source );
			console.log( globalSource == source );
			$('#input-rules-im').val('').change();
			globalSource = source;
		}
		$('#export-rules-im').attr('href', 'https://tempusshop.ru/bitrix/components/adm/price.analysis/ajax/exportTable.php?source=' + source);
    $('#rule-source').val(source).change();
    $('#im-modal').html('Индивидуальные наценки (' + source + ')');
    $.ajax({
      url: '/bitrix/components/adm/price.analysis/ajax/get_rules.php',
      method: 'post',
      data: {source:source},
      success: function(response){
        $('#added-rules-im').html(response);
      }
    })
  })
  $(document).on('click','#add-rules-im', function(e){
    e.preventDefault();
		$('#added-rules-im').html('Добавляю...');
    $.ajax({
      url: '/bitrix/components/adm/price.analysis/ajax/add_rules.php',
      method: 'post',
      data: $('#im-settings').serialize(),
      success: function(response){
        $('#added-rules-im').html(response);
      }
    })
  })
	$(document).on('click','#delete-all-rules', function(e){
		e.preventDefault();
		$('#added-rules-im').html('Удаляю...');
		$.ajax({
			url: '/bitrix/components/adm/price.analysis/ajax/delete_all_rules.php',
			method: 'post',
			data: {source: $('#s-website').val()},
			success: function(response){
				$('#added-rules-im').html(response);
			}
		})
	})
  $(document).on('click','#save-changes-im', function(e){
    e.preventDefault();
		// $('#added-rules-im').html('Сохраняю...');
    $.ajax({
      url: '/bitrix/components/adm/price.analysis/ajax/modify_rules.php',
      method: 'post',
      data: $('#added-rules-im').serialize(),
      success: function(response){
        $('#added-rules-im').html(response);
      }
    })
  })
  $(document).on('click','.del-btn-im', function(e){
    e.preventDefault();
    var rule_id = $(this).attr('id').split('_')[2];
    var source = $(this).attr('id').split('_')[3];
    $('#ruleim_container_' + rule_id).css('background-color', 'rgba(255,0,0,0.5)');
    $('#ruleim_container_' + rule_id).html('Удаляю...');
    console.log( rule_id );
    $.ajax({
      url: '/bitrix/components/adm/price.analysis/ajax/delete_rule.php',
      method: 'post',
      data: {id: rule_id, source: source},
      success: function(response){
        console.log('Deleted rule ' + rule_id);
        $('#ruleim_container_' + rule_id).remove();
      }
    })
  })
	$(document).on('change', '#s-website', function(e){
		e.preventDefault();
		get_active_markups();
	})
	get_active_markups();

	function get_active_markups()
	{
		var s = $('#s-website').val();
		$.ajax({
			url: '/bitrix/components/adm/price.analysis/ajax/get_active_markups.php',
			method: 'post',
			data: {source: s},
			success: function(response){
				var result = $.parseJSON(response);
				if ( result.count > 0 ){
					$('#btn_modal_ind_markup').html('Индивидуальные наценки <span class="sticker-notify">'+result.count+'</span>');
				}
			}
		})
	}
</script>
