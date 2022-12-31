<?php

namespace WPNotif_Compatibility\EDD;

use WPNotif;

if (!defined('ABSPATH')) {
    exit;
}


final class EDDCheckout
{
    const SLUG = 'wpnotif_edd_field';
    protected static $_instance = null;

    /**
     *  Constructor.
     */
    public function __construct()
    {
        $this->init_hooks();
    }

    private function init_hooks()
    {

        add_action('edd_purchase_form_user_info_fields', [$this, 'edd_display_checkout_fields'], 10);
        add_filter('edd_purchase_form_required_fields', [$this, 'edd_required_checkout_fields']);

        add_action('edd_checkout_error_checks', [$this, 'edd_validate_checkout_fields'], 10, 2);

        add_action('edd_built_order', [$this, 'edd_store_custom_fields'], 10, 2);

        add_action('edd_payment_view_details', [$this, 'edd_view_order_details'], 10, 1);

    }

    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function edd_display_checkout_fields()
    {
        $countrycode = WPNotif::getDefaultCountryCode();

        $country_field = sprintf('<input type="text" name="wpnotif_countrycode"
                                   class="wpnotif_countrycode digits_countrycode"
                                   value="%s" maxlength="6" size="3"
                                   placeholder="%s" />', $countrycode, $countrycode);
        ?>
        <div id="edd-phone-wrap">
            <label class="edd-label" for="edd-phone">
                <?php esc_attr_e('Phone Number', 'wpnotif'); ?>
                <span class="edd-required-indicator">*</span>
            </label>
            <span class="edd-description" id="edd-phone-description">
                <?php esc_attr_e('We will use this to personalize your account experience.', 'wpnotif'); ?>
            </span>
            <div class="wpnotif_phone_field_container">
                <div class="wpnotif_phonefield">
                    <div class="wpnotif_countrycodecontainer">
                        <?php echo $country_field; ?>
                    </div>
                    <input type="text" autocomplete="off"
                           class="edd-input required wpnotif_phone mobile_field mobile_format"
                           name="edd_phone" id="edd-phone"/>
                </div>
            </div>

        </div>

        <?php

        do_action('wpnotif_load_frontend_scripts');
    }

    public function edd_store_custom_fields($order_id, $order_data)
    {
        if (0 !== did_action('edd_pre_process_purchase')) {
            $mobile = isset($_POST['edd_phone']) ? sanitize_text_field($_POST['edd_phone']) : '';
            $mobile = sanitize_mobile_field_wpnotif($mobile);

            $countrycode = isset($_POST['wpnotif_countrycode']) ? sanitize_text_field($_POST['wpnotif_countrycode']) : '';
            $phone = $countrycode . $mobile;
            edd_add_order_meta($order_id, 'wpnotif-phone', $phone);
        }
    }

    public function edd_validate_checkout_fields($valid_data, $data)
    {
        if (empty($data['edd_phone']) || empty($data['wpnotif_countrycode'])) {
            edd_set_error('invalid_phone', __('Please enter your phone number', 'wpnotif'));
        }
        $mobile = sanitize_mobile_field_wpnotif($data['edd_phone']);
        $phone = $data['wpnotif_countrycode'] . $mobile;
        if (!\WPNotif_Handler::parseMobile($phone)) {
            edd_set_error('invalid_phone', __('Please enter a valid phone number', 'wpnotif'));
        }
    }

    public function edd_view_order_details($order_id)
    {
        $phone = edd_get_order_meta($order_id, 'wpnotif-phone', true);
        ?>

        <div class="column-container">
            <div class="column">
                <strong><?php echo esc_attr__('Phone Number', 'wpnotif'); ?>: </strong>
                <?php echo $phone; ?>
            </div>
        </div>
        <?php
    }

    public function edd_required_checkout_fields($required_fields)
    {
        $required_fields['edd_phone'] = array(
            'error_id' => 'invalid_phone',
            'error_message' => __('Please enter a valid Phone number', 'wpnotif')
        );

        return $required_fields;
    }

}
