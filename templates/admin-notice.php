<?php
if(!defined('ABSPATH')) {
    exit('You must not access this file directly.');
}

?>
<div class="notice notice-error is-dismissible">
<p><?php _e('POR Payment Gateway requires WooCommerce to be installed and active.', 'por-payment-gateway'); ?></p>
</div>

<script>
    jQuery(document).ready(function($){
        $('.notice-error').on('click', '.notice-dismiss', function(e){
            e.preventDefault();
            var notice = $(this).parent();
            notice.slideUp(100, function(){
                notice.remove();
            });
        });
    });
</script>