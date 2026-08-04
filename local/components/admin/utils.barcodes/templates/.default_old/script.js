var xhr_1 = [];



/* кнопка Наш ШК */
$(document).on("click", "#barcode .get_barcode", function (event) {
	event.preventDefault();
	getBarcode(this);
	return false;
});

/* кнопка установить все Наш ШК */
$(document).on("click", "#get_all_barcode", function (event) {
	event.preventDefault();

	$.each(xhr_1, function(i, object) {
		object.abort();
		object = null;
	});

	$("#barcode").find('.get_barcode').each(function(key, value){
		getBarcode(value);
	});

	return false;
});

function getBarcode(obj){
	var id = $(obj).attr("data-id");
	$.ajax({
		type: "POST",
		data: "id=" + id,
		url: "/admin/ajax/barcode/get_barcode.php",
		dataType: "json",
		async: false,
		success: function(data) {
			console.log(data);
			$("input#barcode_" + id).val(data.barcode);

		},
		error:function(data) {
			alert("Не удалось получить Barcode");
		},
		complete: function(xhr, textStatus) {}
	});
}

/* кнопка Сохранить barcode */
$(document).on("click", ".set_barcode", function (event) {
	event.preventDefault();

	var obj = this;
	var id = $(obj).attr("data-id");
	//var barcode = $(obj).prev().val();
	var barcode = $(obj).closest('td').find("#barcode_" + id).val();

	//var barcode_original = $(obj).prev().prev().val();
	var barcode_original = $(obj).closest('td').find("#barcode_original_" + id).val();    

	$(obj).closest("tr").removeClass("success danger");

	$.ajax({
		type: "POST",
		data: "id=" + id + "&barcode=" + barcode + "&barcode_original=" + barcode_original,
		url: "/admin/ajax/barcode/set_barcode.php",
		dataType: "json",
		async: false,
		success: function(data) {
			if(data.status == "ok")
				$(obj).closest("tr").addClass("success");
			else{
				$(obj).closest("tr").addClass("danger");
				alert(data.error);
			}
		},
		error:function(data) {
			console.log(data);
			$(obj).closest("tr").addClass("danger");
		},
		complete: function(xhr, textStatus) {}
	});
	return false;
});
$('.barcode').on('keydown', function(e) {
	// Блокируем Enter
	if (e.key === 'Enter') {
		e.preventDefault();
		$(this).closest('td').find(".set_barcode").click();
		return false;
	}

});
/* кнопка Печать */
$(document).on("click", "#barcode .print_barcode", function (event) {
	event.preventDefault();

	var id = $(this).attr("data-id");
	var barcode = $("#barcode_" + id).val();
	var article = $(this).attr("data-article");

	$.ajax({
		type: "POST",
		data: "barcode=" + barcode,
		url: "/admin/ajax/barcode/print_barcode.php",
		dataType: "json",
		async: false,
		success: function(data) {console.log(data);
			if(data.status == "ok") printBarcode(data.barcodeURL, barcode, article); else alert(data.error);
		},
		error:function(data) {
			alert("Не удалось получить Barcode");
		},
		complete: function(xhr, textStatus) {}
	});
	return false;
});

function printBarcode(barcodeURL, barcode, article) {
    var win = window.open('about:blank', "_new");
    win.document.open();
    win.document.write([
        '<html>',
        '   <head>',
        '   </head>',
        '   <body onload="window.print()" onafterprint="window.close()">',
        '       <img src="' + barcodeURL + '"/>',
		'       <p style="width:285px;font-size:30px;letter-spacing: 4px;margin:0 0 0 2px;padding:0 0 0 0;font-weight: bold;font-family: "Helvetica Neue",Helvetica,Arial,sans-serif;">' + barcode + '</p>',
		'       <p style=\'width:285px;font-size:20px;\'>' + article + '</p>',
        '   </body>',
        '</html>'
    ].join(''));
    win.document.close();
}

$(document).on("click", "#group-result", function (event) {
	console.log("asdsadsad");
	if ($(this).is(":checked")){
		$(".group-result").val("Y");
	}else{
		$(".group-result").val("N");
	}
	return true;
});

$(document).on("click", "#is-yandex", function (event) {
	console.log("asdsadsad");
	if ($(this).is(":checked")){
		$(".is-yandex").val("Y");
	}else{
		$(".is-yandex").val("N");
	}
	return true;
});

$(document).on("click", "#use-id", function (event) {
	console.log("asdsadsad");
	if ($(this).is(":checked")){
		$(".use-id").val("Y");
	}else{
		$(".use-id").val("N");
	}
	return true;
});
