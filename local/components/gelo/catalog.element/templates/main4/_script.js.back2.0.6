var ItemObj = {};

$(document).ready(function () {
  setTimeout(function () {
    setNewHeader();
  }, 10);

  //change fixed header
  if (arMaxOptions["THEME"]["SHOW_HEADER_GOODS"] == "Y") $("#headerfixed .logo-row").addClass("wproducts");

  //set fixed tabs
  if ($(".ordered-block.js-store-scroll .tabs > ul.nav.nav-tabs").length) {
    $(
      '<div class="product-item-detail-tabs-container-fixed">' +
        '<div class="wrapper_inner">' +
        '<div class="product-item-detail-tabs-wrapper arrow_scroll">' +
        '<ul class="product-item-detail-tabs-list nav nav-tabs">' +
        $(".ordered-block.js-store-scroll ul.nav.nav-tabs").html() +
        //'<li class="last"></li>'+
        "</ul>" +
        "</div>" +
        "</div>" +
        "</div>"
    ).insertAfter($("#headerfixed"));
  }

  SetActiveTab($(".product-container").find(".tabs > .nav-tabs > li"));

  var options = {
    arrows_css: { "background-color": "#fafafa" },
    linked_tabs: $(".ordered-block .tabs.arrow_scroll.arrow_scroll_init"),
  };
  $(".product-item-detail-tabs-wrapper").scrollTab(options);
  var options = {};
  $(".tabs.arrow_scroll").scrollTab(options);
  InitStickySideBar(".sticky-sidebar-custom", ".bottom-info-wrapper");

  $(".opener").click(function () {
    $(this).find(".opener_icon").toggleClass("opened");
    var showBlock = $(this).parents("tr").toggleClass("nb").next(".offer_stores").find(".stores_block_wrap");
    showBlock.slideToggle(200);
  });

  $("a.linked").on("shown.bs.tab", function (e) {
    $(this).closest(".ordered-block").find(".tab-pane").removeClass("cur");
    $("#" + $(this).attr("href").replace("#", "")).addClass("cur");
  });

  $('a[data-toggle="tab"]:not(.linked)').on("shown.bs.tab", function (e) {
    var _this = $(e.target),
      parent = _this.parent();

    if (_this.attr("href")) {
      history.pushState({}, "", _this.attr("href"));
    }

    //top nav
    if (_this.closest(".product-item-detail-tabs-list").length) {
      if ($(".ordered-block .tabs").length) {
        var content_offset = $(".ordered-block .tabs").offset(),
          tab_height = $(".product-item-detail-tabs-container-fixed").actual("outerHeight"),
          hfixed_height = $("#headerfixed").actual("outerHeight");
        // $('html, body').animate({scrollTop: content_offset.top-hfixed_height-tab_height}, 400);
        $("html, body").animate({ scrollTop: content_offset.top - 88 }, 400);

        if (typeof initReviewsGallery !== 'undefined') {
          initReviewsGallery(_this);
        }
      }
    }

    if (_this.attr("href") === "#stores" && $(".stores_tab").length) {
      if (typeof map !== "undefined") {
        map.container.fitToViewport();
        if (typeof clusterer !== "undefined" && !$(".stores_tab").find(".detail_items").is(":visible")) {
          map.setBounds(clusterer.getBounds(), {
            zoomMargin: 40,
            // checkZoomRange: true
          });
        }
      }
    }

    if (_this.attr("href") === "#reviews" && $(".tab-pane.reviews").length && typeof initReviewsGallery !== 'undefined') {
      initReviewsGallery(_this);
    }

    $(".nav.nav-tabs li").each(function () {
      var _this = $(this);
      if (!_this.find(" > a.linked").length) {
        _this.removeClass("active");
        if (_this.index() == parent.index()) _this.addClass("active");
      }
    });
    InitLazyLoad();
  });

  if ($(".title-tab-heading").length) {
    $(".title-tab-heading").on("click", function () {
      var _this = $(this),
        content_offset = _this.offset();
      $("html, body").animate({ scrollTop: content_offset.top - 100 }, 400);
    });
  }

  $("html, body").on("mousedown", function (e) {
    if (typeof e.target.className == "string" && e.target.className.indexOf("adm") < 0) {
      e.stopPropagation();
      var hint = $(e.target).closest(".hint");
      if (!$(e.target).closest(".hint").length) {
        $(".hint").removeClass("active").find(".tooltip").slideUp(100);
      } else {
        var pos_tmp = hint.offset().top + "" + hint.offset().left;
        $(".hint").each(function () {
          var pos_tmp2 = $(this).offset().top + "" + $(this).offset().left;
          if ($(this).text() + pos_tmp2 != hint.text() + pos_tmp) {
            $(this).removeClass("active").find(".tooltip").slideUp(100);
          }
        });
      }
    }
  });

  if ($(".list-sales-compact").length) {
    $(".list-sales-compact").appendTo($(".js-sales"));
    $(".js-sales").velocity(
      { height: $(".list-sales-compact").outerHeight() },
      {
        duration: 800,
        delay: 800,
        complete: function () {
          $(".js-sales").addClass("active").removeAttr("style");
        },
      }
    );
  }

  if ($(".js-services-hide").length) {
    $(".js-services-hide").appendTo($(".js-services"));
    $(".js-services").velocity(
      { height: $(".js-services-hide").outerHeight() },
      {
        duration: 800,
        delay: 800,
        complete: function () {
          $(".js-services").addClass("active").removeAttr("style");
        },
      }
    );
  }
});
$(".set_block").ready(function () {
  $(".set_block ").equalize({ children: '.item:not(".r") .cost', reset: true });
  $(".set_block").equalize({ children: ".item .item-title", reset: true });
  $(".set_block").equalize({ children: ".item .item_info", reset: false });
});

(function (window) {
  if (!window.JCCatalogOnlyElement) {
    window.JCCatalogOnlyElement = function (arParams) {
      if (typeof arParams === "object") {
        this.params = arParams;

        this.obProduct = null;
        this.set_quantity = 1;

        this.currentPriceMode = "";
        this.currentPrices = [];
        this.currentPriceSelected = 0;
        this.currentQuantityRanges = [];
        this.currentQuantityRangeSelected = 0;

        if (this.params.MESS) {
          this.mess = this.params.MESS;
        }

        this.init();
      }
    };
    window.JCCatalogOnlyElement.prototype = {
      init: function () {
        var i = 0,
          j = 0,
          treeItems = null;

        this.obProduct = BX(this.params.ID);

        if (!!this.obProduct) {
          $(this.obProduct)
            .find(".counter_wrapp .counter_block input")
            .data("product", "ob" + this.obProduct.id + "el");
          this.currentPriceMode = this.params.ITEM_PRICE_MODE;
          this.currentPrices = this.params.ITEM_PRICES;
          this.currentQuantityRanges = this.params.ITEM_QUANTITY_RANGES;
        }
      },

      setPriceAction: function () {
        this.set_quantity = this.params.MIN_QUANTITY_BUY;
        if ($(this.obProduct).find("input[name=quantity]").length)
          this.set_quantity = $(this.obProduct).find("input[name=quantity]").val();

        this.checkPriceRange(this.set_quantity);

        $(this.obProduct).find(".not_matrix").hide();

        $(this.obProduct)
          .find(".with_matrix .price_value_block")
          .html(
            getCurrentPrice(
              this.currentPrices[this.currentPriceSelected].PRICE,
              this.currentPrices[this.currentPriceSelected].CURRENCY,
              this.currentPrices[this.currentPriceSelected].PRINT_PRICE
            )
          );

        if ($(this.obProduct).find(".with_matrix .discount")) {
          $(this.obProduct)
            .find(".with_matrix .discount")
            .html(
              getCurrentPrice(
                this.currentPrices[this.currentPriceSelected].BASE_PRICE,
                this.currentPrices[this.currentPriceSelected].CURRENCY,
                this.currentPrices[this.currentPriceSelected].PRINT_BASE_PRICE
              )
            );
        }

        if (this.params.SHOW_DISCOUNT_PERCENT_NUMBER == "Y") {
          if (
            this.currentPrices[this.currentPriceSelected].PERCENT > 0 &&
            this.currentPrices[this.currentPriceSelected].PERCENT < 100
          ) {
            if (!$(this.obProduct).find(".with_matrix .sale_block .sale_wrapper .value").length)
              $('<div class="value"></div>').insertBefore(
                $(this.obProduct).find(".with_matrix .sale_block .sale_wrapper .text")
              );

            $(this.obProduct)
              .find(".with_matrix .sale_block .sale_wrapper .value")
              .html("-<span>" + this.currentPrices[this.currentPriceSelected].PERCENT + "</span>%");
          } else {
            if ($(this.obProduct).find(".with_matrix .sale_block .sale_wrapper .value").length)
              $(this.obProduct).find(".with_matrix .sale_block .sale_wrapper .value").remove();
          }
        }

        $(this.obProduct)
          .find(".with_matrix .sale_block .text .values_wrapper")
          .html(
            getCurrentPrice(
              this.currentPrices[this.currentPriceSelected].DISCOUNT,
              this.currentPrices[this.currentPriceSelected].CURRENCY,
              this.currentPrices[this.currentPriceSelected].PRINT_DISCOUNT
            )
          );

        if ("NOT_SHOW" in this.params && this.params.NOT_SHOW != "Y") $(this.obProduct).find(".with_matrix").show();

        if (arMaxOptions["THEME"]["SHOW_TOTAL_SUMM"] == "Y") {
          if (typeof this.currentPrices[this.currentPriceSelected] !== "undefined")
            setPriceItem($(this.obProduct), this.set_quantity, this.currentPrices[this.currentPriceSelected].PRICE);
        }
      },

      checkPriceRange: function (quantity) {
        if (typeof quantity === "undefined" || this.currentPriceMode != "Q") return;

        var range,
          found = false;

        for (var i in this.currentQuantityRanges) {
          if (this.currentQuantityRanges.hasOwnProperty(i)) {
            range = this.currentQuantityRanges[i];

            if (
              parseInt(quantity) >= parseInt(range.SORT_FROM) &&
              (range.SORT_TO == "INF" || parseInt(quantity) <= parseInt(range.SORT_TO))
            ) {
              found = true;
              this.currentQuantityRangeSelected = range.HASH;
              break;
            }
          }
        }

        if (!found && (range = this.getMinPriceRange())) {
          this.currentQuantityRangeSelected = range.HASH;
        }

        for (var k in this.currentPrices) {
          if (this.currentPrices.hasOwnProperty(k)) {
            if (this.currentPrices[k].QUANTITY_HASH == this.currentQuantityRangeSelected) {
              this.currentPriceSelected = k;
              break;
            }
          }
        }
      },

      getMinPriceRange: function () {
        var range;

        for (var i in this.currentQuantityRanges) {
          if (this.currentQuantityRanges.hasOwnProperty(i)) {
            if (!range || parseInt(this.currentQuantityRanges[i].SORT_FROM) < parseInt(range.SORT_FROM)) {
              range = this.currentQuantityRanges[i];
            }
          }
        }

        return range;
      },
    };
  }

  if (!!window.JCCatalogElement) {
    return;
  }

  var BasketButton = function (params) {
    BasketButton.superclass.constructor.apply(this, arguments);
    this.nameNode = BX.create("span", {
      props: { className: "bx_medium bx_bt_button", id: this.id },
      style: typeof params.style === "object" ? params.style : {},
      text: params.text,
    });
    this.buttonNode = BX.create("span", {
      attrs: { className: params.ownerClass },
      children: [this.nameNode],
      events: this.contextEvents,
    });
    if (BX.browser.IsIE()) {
      this.buttonNode.setAttribute("hideFocus", "hidefocus");
    }
  };
  BX.extend(BasketButton, BX.PopupWindowButton);
})(window);
