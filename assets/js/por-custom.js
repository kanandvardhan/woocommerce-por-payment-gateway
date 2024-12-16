jQuery(function ($) {
  // Handle WooCommerce checkout process
  $("form.checkout").on("checkout_place_order", function () {
    return true; // Allow WooCommerce to proceed
  });

  // Intercept WooCommerce's AJAX response
  $(document).ajaxComplete(function (event, xhr, settings) {
    if (settings.url.includes("wc-ajax=checkout")) {
      $("#loading-overlay").remove(); // Remove loading overlay

      try {
        var response = JSON.parse(xhr.responseText);

        // Handle successful response with modal trigger
        if (response.result === "success" && response.modal) {
          // Prevent WooCommerce's default redirect behavior
          event.preventDefault();

          // Populate and display the modal
          var modalContent = "";

          if (response.data.qr_code) {
            modalContent +=
              "<p>Scan the QR code below to complete your payment</p>";
            modalContent +=
              '<p style="text-align:center;"><img src="' +
              response.data.qr_code +
              '" alt="QR Code" style="max-width:200px; margin: 20px auto;"></p>';
          }

          if (response.data.payment_link) {
            if (modalContent) modalContent += "<p>OR</p>";
            modalContent +=
              '<p><a href="' +
              response.data.payment_link +
              '" target="_blank">Click here to complete your payment</a></p>';
          }

          if (response.data.email) {
            modalContent +=
              "<p>A payment link has been sent to your email. Please complete it to finish your order.</p>";
          }

          if (response.data.phone && !response.data.phone.error) {
            modalContent +=
              "<p>A payment link has been sent to your phone number. Please complete it to finish your order.</p>";
          }

          modalContent +=
            '<button id="por-payment-confirm-btn" class="button">I have completed the payment</button>';

          $("#por-modal-body").html(modalContent);
          $("#por-payment-modal").fadeIn();

          // Prevent scrolling while modal is open
          $("body").addClass("modal-open");
        } else if (response.result === "failure") {
          console.error("Payment failed:", response);
        }
      } catch (error) {
        console.error("Error parsing response:", error);
      }
    }
  });

  // Handle confirm payment button click
  $(document).on("click", "#por-payment-confirm-btn", function () {
    $(this).attr("disabled", true).text("Processing...");

    $.ajax({
      url: por_gateway_params.ajax_url,
      method: "POST",
      data: {
        action: "por_update_order_status",
        order_id: por_gateway_params.order_id,
      },
      success: function (response) {
        if (response.success) {
          window.location.href = response.redirect_url; // Redirect to order confirmation page
        } else {
          alert("Failed to update order status. Please contact support.");
          $("#por-payment-confirm-btn")
            .removeAttr("disabled")
            .text("I have completed the payment");
        }
      },
      error: function () {
        alert("An error occurred. Please try again.");
        $("#por-payment-confirm-btn")
          .removeAttr("disabled")
          .text("I have completed the payment");
      },
    });
  });
});
