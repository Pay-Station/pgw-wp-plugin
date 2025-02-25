jQuery(document).ready(function ($) {
  $(document.body).on("click", "button#track-order-button", function () {
    let button = $("button#track-order-button");
    button.attr("disabled", true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');

    const cartTotal = document.getElementById("cartTotal").value;
    const payment_method = $('input[name="payment_method"]:checked').val();
    const baseurl = document.getElementById("baseurl").value;
    const payment_url = document.getElementById("payment_url").value;

    const billing_first_name = document.getElementById("billing_first_name")?.value || 'N/A';
    const billing_last_name = document.getElementById("billing_last_name")?.value || 'N/A';
    const billing_email = document.getElementById("billing_email").value || 'N/A';
    const billing_phone = document.getElementById("billing_phone").value || 'N/A';
    const billing_address_1 = document.getElementById("billing_address_1")?.value || 'N/A';
    const billing_city = document.getElementById("billing_city")?.value || 'N/A';
    const billing_state = document.getElementById("billing_state")?.value || 'N/A';
    const billing_country = document.getElementById("billing_country")?.value || 'N/A';

    // Check required fields
    if (billing_first_name === "N/A" || billing_last_name === "N/A" || billing_email === "" || billing_phone === "" || billing_address_1 === "N/A" || billing_city === "N/A" || billing_country === "N/A" || billing_state === "N/A") {
      Swal.fire({
        icon: 'error',
        title: 'Missing Information',
        text: 'Please fill up required information!',
      });
      button.attr("disabled", false).html("Place Order");
      return;
    }

    if (cartTotal <= 0) {
      Swal.fire({
        icon: 'warning',
        title: 'Invalid Order Amount',
        text: 'Order amount must be greater than 0!',
      });
      button.attr("disabled", false).html("Place Order");
      return;
    }

    const url = baseurl + "/wp-admin/admin-ajax.php";
    const demo = $(".checkout").serializeArray();

    $.post(url, { action: "complete_order", data: demo }, function (response) {
      if (response.order_id > 0) {
        if (payment_method === 'paystation_payment_gateway' && response.returnURL) {
          makePayment(response.order_id, response.returnURL);
        } else {
          window.open(response.returnURL, "_self");
        }
      } else {
        Swal.fire({
          icon: 'error',
          title: 'Payment Failed',
          text: 'There was an issue processing your order. Please try again!',
        });
        button.attr("disabled", false).html("Place Order");
      }
    });

    function makePayment(order_id, returnURL) {
      const ps_merchant_id = document.getElementById("ps_merchant_id").value;
      const ps_password = document.getElementById("ps_password").value;
      const billingChargeValue = document.getElementById("charge_for_customer").value;

      const body = {
        access: { merchantId: ps_merchant_id, password: ps_password },
        cartTotal: cartTotal,
        billing_first_name, billing_last_name, billing_email, billing_phone,
        billing_address_1, billing_city, billing_state, billing_country,
        charge_for_customer: billingChargeValue,
        invoice_number: order_id,
        baseurl, returnURL,
      };

      $.ajax({
        url: payment_url,
        data: body,
        method: "POST",
        dataType: "json",
        success: function (data) {
          if (data.status === "success") {
            window.open(data.payment_url, "_self");
          } else {
            Swal.fire({
              icon: 'error',
              title: 'Payment Error',
              text: data.message,
            });
            button.attr("disabled", false).html("Place Order");
          }
        },
      });
    }
  });

  // Handle order cancellation
  const wc_payment_status = document.getElementById("wc_payment_status")?.value;
  if (wc_payment_status && wc_payment_status === "cancelled") {
    $('h1.wp-block-woocommerce-legacy-template').text('Order Cancelled!');
    $('.woocommerce-order-received .woocommerce-thankyou-order-received').text('Your order has been cancelled.....');
    $('.woocommerce-order-details').hide();
    $('.woocommerce-customer-details').hide();
  }
});