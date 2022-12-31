<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once 'handler.php';

/**
 * Add a {wpn-phone} tag for use in either the purchase receipt email or admin notification emails
 */
add_action('edd_add_email_tags', 'wpn_edd_add_email_tag',100);

function wpn_edd_add_email_tag()
{

    edd_add_email_tag('wpnotif-phone', __('Buyer\'s phone number','wpnotif'), 'wpn_edd_email_tag_phone');
}

/**
 * The {phone} email tag
 */
function wpn_edd_email_tag_phone($payment_id)
{
    $phone = edd_get_order_meta($payment_id, 'wpnotif-phone', true);
    return $phone;
}
