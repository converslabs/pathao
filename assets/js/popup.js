jQuery(document).ready(function ($) {

    if (typeof pathao_vars === "undefined") {
        console.error("pathao_vars is missing");
        return;
    }

    const AJAX_URL = pathao_vars.ajax_url;
    const NONCE    = pathao_vars.nonce;

    /* ---------------- MODAL ---------------- */
    function openPathaoModal(orderId) {
        $("#ptc-modal").fadeIn();
        $("#ptc-send-confirm").data("order-id", orderId);

        loadOrderData(orderId);
        loadCities(orderId);
    }

    function closePathaoModal() {
        $("#ptc-modal").fadeOut();
    }

        $(document).on("click", ".ptc-open-modal-button", function (e) {
        e.preventDefault();
        e.stopPropagation();
        openPathaoModal($(this).data("order-id"));
    });


    $(document).on("click", "#ptc-send-cancel", closePathaoModal);

    /* ---------------- ORDER INFO ---------------- */
    function loadOrderData(orderId) {
        $.post(AJAX_URL, {
            action: "get_wc_order_info",
            order_id: orderId
        }, function (res) {
            $("#ptc-name").val(res.name);
            $("#ptc-phone").val(res.phone);
            $("#ptc-address").val(res.address);
            $("#ptc-weight").val(res.weight);
            $("#ptc-quantity").val(res.quantity);
            $("#ptc-total-price").val(res.total);
            $("#ptc-payment-status").val(res.payment_status);
            $("#ptc-order-items").val(res.items);
            $("#ptc-order-number").val(orderId);
            $("#ptc-collectable").val(res.cod_amount);
            $("#ptc-store").val(res.store_name);
        });
    }

    /* ---------------- CITIES ---------------- */
    function loadCities(orderId) {
        $.post(AJAX_URL, {
            action: "get_cities",
            order_id: orderId,
            nonce: NONCE
        }, function (res) {
            const $city = $("#ptc-city").empty();
            res.cities.forEach(city => {
                $city.append(`<option value="${city.id}">${city.name}</option>`);
            });
            $city.trigger("change");
        });
    }

    /* ---------------- ZONES ---------------- */
    $("#ptc-city").on("change", function () {
        $.post(AJAX_URL, {
            action: "get_city_zones",
            city: $(this).val(),
            order_id: $("#ptc-send-confirm").data("order-id"),
            nonce: NONCE
        }, function (res) {
            const $zone = $("#ptc-zone").empty();
            res.zones.forEach(zone => {
                $zone.append(`<option value="${zone.id}">${zone.name}</option>`);
            });
            $zone.trigger("change");
        });
    });

    /* ---------------- AREAS ---------------- */
    $("#ptc-zone").on("change", function () {
        $.post(AJAX_URL, {
            action: "get_zone_areas",
            zone: $(this).val(),
            order_id: $("#ptc-send-confirm").data("order-id"),
            nonce: NONCE
        }, function (res) {
            const $area = $("#ptc-area").empty();
            res.areas.forEach(area => {
                $area.append(`<option value="${area.id}">${area.name}</option>`);
            });
        });
    });

    /* ---------------- SEND ORDER ---------------- */
        $("#ptc-send-confirm").on("click", function (e) {
        e.preventDefault();
        e.stopPropagation();

        const orderId = $(this).data("order-id");

        $.post(AJAX_URL, {
            action: "send_order_to_pathao",
            order_id: orderId,
            nonce: NONCE,
            form: $("#ptc-pathao-form").serialize()
        }, function (res) {

            if (res.success) {
                // closePathaoModal();
                location.reload();
            } else {
                alert("Failed: " + (res.data?.message || "Unknown error"));
                // keep modal open on failure
            }

        });
    });

});