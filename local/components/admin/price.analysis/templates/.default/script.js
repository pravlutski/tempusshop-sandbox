var xhr_1 = [];
var cntClick = 0;

var delay = (function() {
    var timer = 0;
    return function(callback, ms) {
        clearTimeout(timer);
        timer = setTimeout(callback, ms);
    };
})();

$(document).on("change", "#s-brand, #s-supplier, #s-website, #price-with-discount, #price-competitors, #price-competitors-act, #page_size, #only-minimal-price, #hide-rrc, #without-competitors, #only-active", function() {
    $.each(xhr_1, function(i, object) {
        object.abort();
        object = null;
    });
    
    var website = $("#s-website").val();
    $("#margin-platform").val(website == "s2" ? "0" : "5");
    getAnalysList("N");
});
/*
$(document).on("change", "#margin-platform", function() {
    $.each(xhr_1, function(i, object) {
        object.abort();
        object = null;
    });
    getAnalysList("N");
});
*/
$(document).on("keyup", "#tbl-analysis .td-price input", function() {
    var $tr = $(this).closest("tr");
    var b_price = $tr.attr("data-bprice");
    var price = $(this).val();
    
    if (parseFloat(b_price) != parseFloat(price)) {
        $tr.addClass("active");
    } else {
        $tr.removeClass("active");
    }
});

$(document).on("keyup", "#search-model, #search-price-from, #search-price-to", function(event) {
    if (event.keyCode == 32) return false;
    delay(function() {
        getAnalysList("N");
    }, 500);
});

$(document).on("change", "#tbl-change", function() {
    if ($(this).is(':checked')) {
        $("#tbl-analysis tbody tr").not(".active").hide();
    } else {
        $("#tbl-analysis tbody tr").show();
    }
});

$(document).on("click", "#tbl-analysis th.asd", function() {
    var sorting = [];
    var sortNumber = function(a, b) { return b - a; };
    
    $("#tbl-analysis").find('[data-name="sort"]').each(function() {
        sorting.push(Number($(this).data('value')));
    });
    
    sorting = sorting.sort(sortNumber);
    
    for (var i in sorting) {
        $("#tbl-analysis").find('#tbl-analysis [data-name="sort"][data-value="' + sorting[i] + '"]')
            .closest('tr')
            .appendTo('#tbl-analysis > tbody');
    }
});

$(document).on("click", "#tbl-analysis th.search", function() {
    $("#tbl-analysis th.search").not(this).removeClass("arrow-top arrow-bottom active");
    $(this).addClass("active");
    
    if ($(this).hasClass("arrow-top")) {
        $(this).addClass("arrow-bottom").removeClass("arrow-top");
        $(this).attr("data-order", "desc");
    } else {
        $(this).addClass("arrow-top").removeClass("arrow-bottom");
        $(this).attr("data-order", "asc");
    }
    
    getAnalysList("N");
});

$(document).on("click", "#download_xls", function() {
    getAnalysList("Y");
    var link = document.createElement('a');
    link.setAttribute('href', '/upload/price_analys.xlsx');
    link.setAttribute('download', 'price_analys.xlsx');
    link.click();
    return false;
});

$(document).on("click", ".filter-price", function() {
    if ($(this).hasClass("active")) {
        $(this).removeClass("active");
    } else {
        $(".filter-price").removeClass("active");
        $(this).addClass("active");
    }
    getAnalysList("N");
    return false;
});

function getAnalysList(xls) {
    $("#tbl-change").removeAttr("checked");
    
    var brand = $("#s-brand").val();
    var supplier = $("#s-supplier").val();
    var website = $("#s-website").val();
    var order = $("#tbl-analysis th.search.active").attr("data-order");
    var sort = $("#tbl-analysis th.search.active").attr("data-column");
    var search_text = $("#search-model").val();
    var page_size = $("#page_size").val();
    var price = $("#price-with-discount").is(':checked') ? "discount" : "";
    var only_minimal_price = $("#only-minimal-price").is(':checked') ? "Y" : "";
    
    var columnMap = {
        s1: "Мин. яндекса",
        s2: "Мин. онлайнера",
        s3: "Мин. ceneo",
        wb: "Мин. WB",
        sb: "Мин. SB",
        kz: "Мин. KZ",
        ozkz: "Мин. OZKZ"
    };
    
    if (columnMap[website]) {
        $("#tbl-analysis thead tr th:eq(3) span").html(columnMap[website]);
    }
    
    var price_competitors = $("#price-competitors").is(':checked') ? "Y" : "N";
    var price_competitors_act = $("#price-competitors-act").is(':checked') ? "Y" : "N";
    var without_competitors = $("#without-competitors").is(':checked') ? "Y" : "N";
    var only_active = $("#only-active").is(':checked') ? "Y" : "N";
    var search_price_from = $("#search-price-from").val();
    var search_price_to = $("#search-price-to").val();
    var hide_rrc = $("#hide-rrc").is(':checked') ? "Y" : "N";
    var filter_price = $(".filter-price.active").attr("data-type") || "";
    var margin_platform = $("#margin-platform").val();
    
    $("#analysis").block({ message: null });
    
    xhr_1.push($.ajax({
        type: "POST",
        data: "order=" + order + "&sort=" + sort + "&website=" + website + "&brand=" + brand + 
              "&supplier=" + supplier + "&price=" + price + "&search_text=" + search_text + 
              "&price_competitors=" + price_competitors + "&price_competitors_act=" + price_competitors_act + 
              "&search_price_from=" + search_price_from + "&search_price_to=" + search_price_to + 
              "&page_size=" + page_size + "&only_minimal_price=" + only_minimal_price + "&hide_rrc=" + hide_rrc + 
              "&download_xls=" + xls + "&without_competitors=" + without_competitors + 
              "&filter_price=" + filter_price + "&only_active=" + only_active + "&margin_platform=" + margin_platform,
        url: "/local/components/admin/price.analysis/ajax/get_list.php",
        async: false,
        success: function(data) {
            $("#tbl-analysis").html(data);
            if (price_competitors == "Y") {
                $("#tbl-analysis .c-competitors, #tbl-analysis #all-get-price-platform").show();
            } else {
                $("#tbl-analysis .c-competitors, #tbl-analysis #all-get-price-platform").hide();
            }
        },
        error: function() {
            alert("Не удалось получить список моделей2");
            $("#analysis").unblock();
        },
        complete: function() {
            $("#analysis").unblock();
        }
    }));
}

function getProfileList(active_id) {
    var website = $("#s-website").val();
    $.ajax({
        type: "POST",
        data: "website=" + website + "&active_id=" + active_id,
        url: "/admin/ajax/analysis/get_profile_list.php",
        success: function(data) {
            $("#settings-price").html(data);
        },
        error: function() {
            alert("Не удалось получить настройки getProfileList");
        }
    });
    
    if (parseInt(active_id, 10) > 0) {
        getProfile(active_id, $("#settings-price input[name=website]").val());
    }
}

$(document).on("click", "#btn_modal_settings_cost", function(event) {
    event.preventDefault();
    getProfileList();
    return false;
});

$(document).on("change", "#collection_id", function() {
    $("#selected_collection_id").val($(this).val());
});

$(document).on("submit", "#apply-profile", function(event) {
    event.preventDefault();
    $("#apply-profile").block({ message: null });
    var website = $("#s-website").val();
    var dataString = $(this).serialize() + "&website=" + website;
    
    $.ajax({
        type: "POST",
        data: dataString,
        dataType: "json",
        url: "/admin/ajax/analysis/set_profile.php",
        success: function(data) {
            $("#apply-profile .info-text").html("<p class='status-" + data.status + "'>" + data.text + "</p>").show();
            getProfileList();
            $("#apply-profile").unblock();
        },
        error: function() {
            alert("Не удалось получить профиль");
            $("#apply-profile").unblock();
        }
    });
    return false;
});

$(document).on("click", "#profile-add-item", function(event) {
    event.preventDefault();
    var next_id = 0;
    var settingsBlock = $(this).closest("form").find(".wrap-settings");
    var last = $(settingsBlock).find(".list").last();
    var last_id = parseInt($(last).attr("data-key"), 10);
    
    if (last_id >= 0) next_id = last_id + 1;
    
    var html = '<div class="list" data-key="' + next_id + '">' +
        '<div class="col-lg-5">' +
        '<input type="text" class="form-control rules_input" name="profile[' + next_id + '][price_from]" placeholder="От">' +
        '<input type="text" class="form-control rules_input fl-right" name="profile[' + next_id + '][price_to]" placeholder="До">' +
        '</div>' +
        '<div class="col-lg-5">' +
        '<input type="text" class="form-control rules_input fl-right" name="profile[' + next_id + '][markup]" placeholder="200%">' +
        '</div>' +
        '<div class="col-lg-2">' +
        '<button type="button" class="close"><span aria-hidden="true">×</span></button>' +
        '</div></div>';
    
    if (next_id == 0) {
        $(settingsBlock).html(html);
    } else {
        $(html).insertAfter($(last));
    }
    
    return false;
});

$(document).on("submit", "#form-control-rrc", function(event) {
    event.preventDefault();
    $("#form-control-rrc").block({ message: null });
    var dataString = $(this).serialize();
    
    $.ajax({
        type: "POST",
        data: dataString,
        dataType: "json",
        url: "/admin/ajax/analysis/set_control_rrc.php",
        success: function(data) {
            $("#modal_control_rrc .info-text").html("<p class='status-" + data.status + "'>" + data.text + "</p>").show();
            $("#form-control-rrc").unblock();
        },
        error: function() {
            alert("Не удалось сохранить настройки контроля РРЦ");
            $("#form-control-rrc").unblock();
        }
    });
    return false;
});

function updateCollections(brand_id, selectedCollectionId) {
    $.ajax({
        type: "POST",
        url: "/admin/ajax/analysis/get_collections.php",
        data: {
            brand_id: brand_id,
            collection_id: $("#selected_collection_id").val()
        },
        success: function(data) {
            $('#collection_id').html(data);
        }
    });
}

function getProfile(id, website) {
    $("#modal_edit_profile").block({ message: null });
    $.ajax({
        type: "POST",
        data: "id=" + id + "&website=" + website,
        url: "/admin/ajax/analysis/get_profile.php",
        success: function(data) {
            var selectedCollectionId = $("#selected_collection_id").val();
            $('#modal_edit_profile').modal('show');
            $("#modal_edit_profile .modal-content").html(data);
            updateCollections($("#brand_id option:selected").text(), selectedCollectionId);
            $("#modal_edit_profile").unblock();
            
            $("#brand_id").change(function() {
                updateCollections($("#brand_id option:selected").text(), 0);
            });
        },
        error: function() {
            alert("Не удалось получить профиль");
            $("#settings-price").unblock();
        }
    });
}

$(document).on("click", ".btn-profile-edit", function(e) {
    e.preventDefault();
    var id = $(this).attr("data-id");
    var website = $("#s-website").val();
    getProfile(id, website);
});

$(document).on("click", "#profile-new", function(event) {
    event.preventDefault();
    var website = $("#s-website").val();
    getProfile(0, website);
});

function confirmDeleteProfile(obj) {
    if (confirm('Вы действительно хотите удалить этот профиль?')) {
        var id = $(obj).attr("data-id");
        $.ajax({
            type: "POST",
            data: "id=" + id,
            dataType: "json",
            url: "/admin/ajax/analysis/delete_profile.php",
            success: function(data) {
                if (data.status == "error") {
                    alert("Не удалось удалить профиль");
                } else {
                    $('#modal_edit_profile').modal('hide');
                    getProfileList();
                }
            },
            error: function() {
                alert("Непредвиденная ошибка. Не удалось удалить профиль");
            }
        });
        return false;
    }
    return false;
}

function getOptimalPrice(obj) {
    var $tr = $(obj).closest("tr");
    var website = $("#s-website").val();
    var id = $(obj).attr("data-id");
    
    $.ajax({
        type: "POST",
        data: "id=" + id + "&website=" + website,
        url: "/admin/ajax/analysis/get_optimal_price.php",
        dataType: "json",
        async: false,
        success: function(data) {
            $($tr).find("td.td-price input").val(data.price);
            var b_price = $tr.attr("data-bprice");
            
            if (parseFloat(b_price) != parseFloat(data.price)) {
                $tr.addClass("active");
                if (!$tr.find("td.td-price span").length > 0) {
                    $tr.find("td.td-price input").after("<span>" + b_price + "</span>");
                }
            }
        },
        error: function() {
            alert("Не удалось получить настройки getOptimalPrice");
        }
    });
}

function getOptimalPricePlatform(obj) {
    var $tr = $(obj).closest("tr");
    var website = $("#s-website").val();
    var percent = $("#margin-platform").val();
    var id = $(obj).attr("data-id");
    var id_price = $(obj).attr("data-priceid");
    
    xhr_1.push($.ajax({
        type: "POST",
        data: "id=" + id + "&website=" + website + "&percent=" + percent + "&id_price=" + id_price,
        url: "/admin/ajax/analysis/get_optimal_price_platform.php",
        dataType: "json",
        async: false,
        success: function(data) {
            cntClick += 1;
            if (data.status == "ok") {
                $($tr).find("td.td-price input").val(data.price);
                var b_price = $tr.attr("data-bprice");
                
                if (parseFloat(b_price) != parseFloat(data.price)) {
                    $tr.addClass("active");
                    if (!$tr.find("td.td-price span").length > 0) {
                        $tr.find("td.td-price input").after("<span>" + b_price + "</span>");
                    }
                }
            }
        },
        error: function() {}
    }));
}

$('#modal_edit_profile').on('hidden.bs.modal', function() {
    $("body").addClass("modal-open");
});

$('#modal_settings_cost').on('hidden.bs.modal', function() {
    $("body").removeClass("modal-open");
});

/*
$(document).on("click", "#all-set-price_rrc", function(event) {
    event.preventDefault();
    $.each(xhr_1, function(i, object) {
        object.abort();
        object = null;
    });
    
    $("#tbl-analysis").find('.btn-set-price-price_rrc').each(function(key, value) {
        getOptimalPrice(value);
    });
    
    return false;
});

*/
$(document).on("click", "#tbl-analysis .btn-get-price", function(event) {
    event.preventDefault();
    getOptimalPrice(this);
    return false;
});

$(document).on("click", "#all-get-price-platform", function(event) {
    event.preventDefault();
    
    $.each(xhr_1, function(i, object) {
        object.abort();
        object = null;
    });
    cntClick = 0;
    
    $("#tbl-analysis").find('.btn-get-price-platform').each(function(key, obj) {
        getOptimalPricePlatform(obj);
    });
    
    return false;
});

$(document).on("click", "#tbl-analysis .btn-get-price-platform", function(event) {
    event.preventDefault();
    getOptimalPricePlatform(this);
    return false;
});

$(document).on("click", ".btn-all-get-price", function(event) {
    event.preventDefault();
    var obj = $("#tbl-analysis .no-price .btn-get-price");
    
    $.each(obj, function(key, value) {
        getOptimalPrice(value);
    });
    
    return false;
});

$(document).on("click", "#save-changes", function(event) {
    event.preventDefault();
    var website = $("#s-website").val();
    var $rows = $("#tbl-analysis tr.active");
    var ar = [];
    
    $rows.each(function(key, value) {
        var tmp = {};
        tmp.product_id = parseInt($(value).attr("data-productid"), 10);
        tmp.skuid = parseInt($(value).attr("data-skuid"), 10);
        tmp.price = parseFloat($(value).find("td.td-price input").val());
        ar.push(tmp);
    });
    
    if (objectSize(ar) > 0) {
        $("#analysis").block({ message: null });
        $.ajax({
            type: "POST",
            data: "ar_items=" + JSON.stringify(ar) + "&website=" + website,
            url: "/admin/ajax/analysis/set_prices.php",
            success: function(data) {
                $('#modal_changes_log .modal-body').html(data);
                $('#modal_changes_log').modal('show');
                $("#analysis").unblock();
            },
            error: function() {
                alert("Не удалось Сохранить изменения в каталоге");
                $("#analysis").unblock();
            }
        });
    }
    
    return false;
});

$(document).ready(function() {
    $('#modal_changes_log').on('hidden.bs.modal', function() {
        getAnalysList("N");
    });
});

$(document).on("click", "#profile-save-default", function(event) {
    event.preventDefault();
    var dataString = $(this).closest("form").serialize();
    
    $.ajax({
        type: "POST",
        data: dataString,
        url: "/admin/ajax/analysis/set_default_rrc.php",
        success: function(data) {
            getProfileList();
        },
        error: function() {
            alert("Не удалось сохранить настройки РРЦ");
        }
    });
    return false;
});

$(document).on("click", "#profile-save-price_type", function(event) {
    event.preventDefault();
    var dataString = $(this).closest("form").serialize();
    
    $.ajax({
        type: "POST",
        data: dataString,
        url: "/admin/ajax/analysis/set_price_type.php",
        success: function(data) {
            getProfileList();
        },
        error: function() {
            alert("Не удалось изменить тип цены");
        }
    });
    return false;
});



function setRrcPrice(button) {
    const row = button.closest('tr');
    if (!row) return;
    
    const newPrice = button.getAttribute('data-price_rrc');
    if (!newPrice) return;
    
    const priceInput = row.querySelector('.td-price input');
    if (!priceInput) return;
    
    const oldPrice = priceInput.value;
    
    if (oldPrice !== newPrice) {
        row.classList.add('active');
		
		const oldPriceSpan = document.createElement('span');
		oldPriceSpan.className = 'old-price-rrc';
		oldPriceSpan.style.cssText = 'display: block; font-size: 12px; color: #999; margin-top: 5px; text-decoration: line-through;';
		oldPriceSpan.textContent = `${oldPrice}`;
		
		const existingSpan = priceInput.parentNode.querySelector('.old-price-rrc');
		if (existingSpan) {
			existingSpan.remove();
		}
		
		priceInput.insertAdjacentElement('afterend', oldPriceSpan);
		
		priceInput.value = newPrice;
		
		priceInput.style.backgroundColor = '#ffffcc';
		setTimeout(() => {
			priceInput.style.backgroundColor = '';
    }, 500);
    } else {
        //row.classList.remove('active');
    }
    

}

function setAllRrcPrices() {
    const allButtons = document.querySelectorAll('.table .btn-set-price_rrc');
    
    if (allButtons.length === 0) {
        return;
    }
    
    allButtons.forEach(button => {
        setRrcPrice(button);
    });
	
	showNotification(`Проставили РРЦ на: ${allButtons.length}`);
}

$(document).on("click", ".btn-set-price_rrc", function(event) {
    event.preventDefault();
    setRrcPrice(this);
    
    return false;
});

$(document).on("click", "#all-set-price_rrc", function(event) {
    event.preventDefault();
    setAllRrcPrices();
    
    return false;
});

function showNotification(message, rowElement) {
    const notification = document.createElement('div');
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: #4caf50;
        color: white;
        padding: 10px 20px;
        border-radius: 5px;
        z-index: 9999;
        animation: fadeInOut 2s ease-in-out;
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 2000);
}

const style = document.createElement('style');
style.textContent = `
    @keyframes fadeInOut {
        0% { opacity: 0; transform: translateY(20px); }
        15% { opacity: 1; transform: translateY(0); }
        85% { opacity: 1; transform: translateY(0); }
        100% { opacity: 0; transform: translateY(-20px); }
    }
`;
document.head.appendChild(style);