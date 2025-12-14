jQuery(document).ready(function ($) {
    console.log("popup.js ready");

    // Open modal
    function openPathaoModal(orderId) {
        console.log("Opening modal for Order:", orderId);

        $("#ptc-modal").fadeIn();
        $("#ptc-send-confirm").data("order-id", orderId);

        loadOrderData(orderId);
        loadCities(orderId);
    }

    function closePathaoModal() {
        $("#ptc-modal").fadeOut();
    }

    // Button click → open modal
    $(document).on("click", ".ptc-open-modal-button", function () {
        const orderId = $(this).data("order-id");
        openPathaoModal(orderId);
    });

    // Cancel button
    $(document).on("click", "#ptc-send-cancel", function () {
        closePathaoModal();
    });

    /* -----------------------------------------------------------
     * LOAD ORDER DETAILS INTO FORM
     * ----------------------------------------------------------- */
    function loadOrderData(orderId) {
        $.ajax({
            url: ajaxurl,
            method: "POST",
            data: {
                action: "get_wc_order_info",
                order_id: orderId,
            },
            success: function (res) {
                console.log("Order info loaded:", res);

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
            }
        });
    }

    /* -----------------------------------------------------------
     * LOAD CITIES
     * ----------------------------------------------------------- */
    function loadCities(orderId) {
        $.ajax({
            url: ajaxurl,
            method: "POST",
            data: {
                action: "get_cities",
                order_id: orderId,
            },
            success: function (res) {
                const $city = $("#ptc-city");
                $city.empty();

                console.log("Cities:", res);

                res.cities.forEach(function (city) {
                    $city.append(`<option value="${city.id}">${city.name}</option>`);
                });

                // Auto-load zones when city changes
                $city.trigger("change");
            }
        });
    }

    /* -----------------------------------------------------------
     * WHEN CITY SELECTED → LOAD ZONES
     * ----------------------------------------------------------- */
    $("#ptc-city").on("change", function () {
        const cityId = $(this).val();
        const orderId = $("#ptc-send-confirm").data("order-id");

        $.ajax({
            url: ajaxurl,
            method: "POST",
            data: {
                action: "get_city_zones",
                city: cityId,
                order_id: orderId,
                nonce: pathao_vars.nonce,
            },
            success: function (res) {
                const $zone = $("#ptc-zone");
                $zone.empty();

                console.log("Zones:", res);

                res.zones.forEach(function (zone) {
                    $zone.append(`<option value="${zone.id}">${zone.name}</option>`);
                });

                $zone.trigger("change");
            }
        });
    });

    /* -----------------------------------------------------------
     * WHEN ZONE SELECTED → LOAD AREAS
     * ----------------------------------------------------------- */
    $("#ptc-zone").on("change", function () {
        const zoneId = $(this).val();
        const orderId = $("#ptc-send-confirm").data("order-id");

        $.ajax({
            url: ajaxurl,
            method: "POST",
            data: {
                action: "get_zone_areas",
                zone: zoneId,
                order_id: orderId,
                nonce: pathao_vars.nonce,
            },
            success: function (res) {
                const $area = $("#ptc-area");
                $area.empty();

                console.log("Areas:", res);

                res.areas.forEach(function (area) {
                    $area.append(`<option value="${area.id}">${area.name}</option>`);
                });
            }
        });
    });

    /* -----------------------------------------------------------
     * SEND FORM TO PATHAO
     * ----------------------------------------------------------- */
    $(document).on("click", "#ptc-send-confirm", function () {
        const orderId = $(this).data("order-id");

        // collect form fields
        const formData = $("#ptc-pathao-form").serialize();

        console.log("Submitting to Pathao with form:", formData);

        $.ajax({
            url: ajaxurl,
            method: "POST",
            data: {
                action: "send_order_to_pathao",
                order_id: orderId,
                nonce: pathao_vars.nonce,
                form: formData,
            },
            success: function (res) {
                if (res.success) {
                    alert("Order successfully sent to Pathao!");
                } else {
                    alert("Failed: " + res.data.message);
                }

                closePathaoModal();
            },
            error: function (err) {
                console.log(err);
                alert("Server error sending to Pathao.");
            }
        });
    });
});
