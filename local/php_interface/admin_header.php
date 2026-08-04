<?if($_SERVER['SCRIPT_NAME'] == '/bitrix/admin/sale_order.php'): ?>
<? CJSCore::Init( 'jquery' ); ?>
<script>
function send_order_tempus(id){
	$.ajax({
		type : 'POST',
		url : '/local/ajax/rabbitmq_actions.php',
		data : ({action: "send_order", id: id}),
		success : function(data) {
			
		},
		error: function () {
			alert("Не удалось отправить");
		}
	});
}
function send_order_list_tempus(){
	var that = this;
	var orders = [];
	var table = $('#tbl_sale_order');
	table.find('tbody tr.adm-list-table-row').each(function () {
		var order = $(this).find('input[name="ID[]"]:checked');
		if (order.val())
			orders.push(parseInt(order.val()));
	});
	$.ajax({
		type : 'POST',
		url : '/local/ajax/rabbitmq_actions.php',
		data : ({action: "send_order_list", ids: orders}),
		success : function(data) {
			
		},
		error: function () {
			alert("Не удалось отправить");
		}
	});
}
</script>
<?endif;?>
<?if($_SERVER['SCRIPT_NAME'] == '/bitrix/admin/iblock_list_admin.php' && $_REQUEST["IBLOCK_ID"] == 16): ?>
<? CJSCore::Init( 'jquery' ); ?>
<script>
function send_product_tempus(id){
	$.ajax({
		type : 'POST',
		url : '/local/ajax/rabbitmq_actions.php',
		data : ({action: "send_product", id: id}),
		success : function(data) {
			
		},
		error: function () {
			alert("Не удалось отправить");
		}
	});
}
function send_product_list_tempus(){
	var that = this;
	var products = [];
	var table = $('#tbl_iblock_list_57d2ce27dff4931ed5c50a3ed253ca84_table');
	table.find('tbody tr.main-grid-row').each(function () {
		var product = $(this).find('input[name="ID[]"]:checked');
		if (product.val()){
			const product_id = product.val();

			if (product_id.startsWith("E")) {
				products.push(parseInt(product_id.slice(1)));
			}
			
		}
	});

	$.ajax({
		type : 'POST',
		url : '/local/ajax/rabbitmq_actions.php',
		data : ({action: "send_product_list", ids: products}),
		success : function(data) {
			
		},
		error: function () {
			alert("Не удалось отправить");
		}
	});
}
</script>
<?endif;?>