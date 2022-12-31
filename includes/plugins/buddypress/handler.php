<?php

namespace WPNotif_Compatibility\BuddyPress;

use WPNotif;
use WPNotif_Handler;

if (!defined('ABSPATH')) {
    exit;
}


function wpn_load_buddypress()
{
    BuddyPress::instance();
}

add_action('bp_include', function () {
    wpn_load_buddypress();
});

final class BuddyPress
{
    const SLUG = 'wpnotif_bp_field';
    const SMS_SLUG = 'wpnotif_bp_field_sms';
    const WHATSAPP_SLUG = 'wpnotif_bp_field_whatsapp';
    const ENABLE_KEY = 'wpnotif_enable_sms_notification';
    const ROUTE = 'wpnotif_bp_route';
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
        add_action('add_meta_boxes_' . bp_get_email_post_type(), array($this, 'wpnotif_sms_metaboxes'));
        add_action('save_post', array($this, 'update_field'));
        add_action('bp_send_email', array($this, 'bp_send_email'), 10, 4);
        add_filter('wpnotif_notification_options_' . self::SLUG, array(&$this, 'notification_options'), 10);
    }

    public function notification_options($values)
    {
        $values['identifier'] = self::SLUG;
        $values['different_gateway_content'] = 'off';
        return $values;
    }

    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function wpnotif_sms_metaboxes()
    {
        add_meta_box('bp_wpnotifcust_plaintext_metabox', __('WPNotif Notification', 'buddypress'), array($this, 'bp_wpnotif_plaintext_metabox'), null, 'normal', 'default');
    }

    public function bp_wpnotif_plaintext_metabox($post)
    {
        wp_nonce_field(self::SLUG, 'wp_nonce_' . self::SLUG);

        $sms_value = get_post_meta($post->ID, self::SMS_SLUG, true);
        $whatsapp_value = get_post_meta($post->ID, self::WHATSAPP_SLUG, true);
        $enable = get_post_meta($post->ID, self::ENABLE_KEY, true);
        $route = get_post_meta($post->ID, self::ROUTE, true);
        ?>
        <table>

            <tr>
                <td>
                    <label>
                        <?php
                        _e('Enable Notification', 'wpnotif');
                        ?>
                    </label>
                </td>
                <td>
                    <select name="<?php echo self::ENABLE_KEY; ?>"
                    >
                        <option value="1" <?php if ($enable == 1) {
                            echo 'selected';
                        } ?>>
                            <?php
                            _e('Yes', 'buddypress');
                            ?>
                        </option>
                        <option value="0" <?php if ($enable == 0) {
                            echo 'selected';
                        } ?>>
                            <?php
                            _e('No', 'buddypress');
                            ?>
                        </option>
                    </select>
                </td>
            </tr>
            <tr>
                <td>
                    <label for="<?php echo self::ROUTE; ?>">
                        <?php echo esc_html__('Route', 'wpnotif'); ?>
                    </label>
                </td>
                <td>
                    <select id="<?php echo self::ROUTE; ?>"
                            name="<?php echo self::ROUTE; ?>"
                    >
                        <?php
                        $selected = (isset($route)) ? $route : '1';

                        $routes = array(
                            array(
                                'label' => esc_html__('Both', 'wpnotif'),
                                'value' => -1,
                                'show' => 'both',
                            ),
                            array(
                                'label' => esc_html__('SMS', 'wpnotif'),
                                'value' => 1,
                                'show' => 'sms',
                            ),
                            array(
                                'label' => esc_html__('Whatsapp', 'wpnotif'),
                                'value' => 1001,
                                'show' => 'whatsapp',
                            ),
                        );
                        foreach ($routes as $option) {
                            $sel = '';
                            if ($option['value'] == $selected) {
                                $sel = 'selected="selected"';
                            }
                            $attr = "data-show='{$option['show']}'";
                            echo '<option ' . $attr . ' value="' . esc_attr($option['value']) . '" ' . $sel . '>' . esc_html($option['label']) . '</option>';
                        }
                        ?>
                    </select>

                </td>
            </tr>
            <tr class="wpn_bp_sms wpn_bp_both wpn_bp_gateway">
                <td>
                    <label for="cust_sms_content">
                        <?php
                        _e('SMS Notification', 'buddypress');
                        ?>
                    </label>
                </td>
                <td>
                    <textarea rows="3"
                              cols="40" name="<?php echo self::SMS_SLUG; ?>"
                              id="cust_sms_content"><?php echo $sms_value;
                        ?></textarea>
                </td>
            </tr>
            <tr class="wpn_bp_whatsapp wpn_bp_both wpn_bp_gateway">
                <td>
                    <label for="cust_whatsapp_content">
                        <?php
                        _e('WhatsApp Notification', 'buddypress');
                        ?>
                    </label>
                </td>
                <td>
                    <textarea rows="3"
                              cols="40" name="<?php echo self::WHATSAPP_SLUG; ?>"
                              id="cust_whatsapp_content"><?php echo $whatsapp_value;
                        ?></textarea>
                </td>
            </tr>
        </table>
        <input type="hidden" name="<?php echo self::SLUG; ?>" value="1"/>
        <?php
    }

    public function update_field($post_id)
    {

        if (!isset($_POST[self::SLUG])) {
            return;
        }

        if (!wp_verify_nonce($_POST['wp_nonce_' . self::SLUG], self::SLUG)) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        update_post_meta($post_id, self::ENABLE_KEY, $_POST[self::ENABLE_KEY]);
        update_post_meta($post_id, self::SMS_SLUG, $_POST[self::SMS_SLUG]);
        update_post_meta($post_id, self::WHATSAPP_SLUG, $_POST[self::WHATSAPP_SLUG]);
        update_post_meta($post_id, self::ROUTE, $_POST[self::ROUTE]);
    }

    public function bp_send_email(&$email, $email_type, $to, $args)
    {

        if (!$to instanceof \WP_User) {
            $get_type = is_email($to) ? 'email' : 'ID';
            $to = get_user_by($get_type, $to);
        }

        $post = $email->get_post_object();

        if (empty($post) || !$post instanceof \WP_Post) {
            return;
        }

        $route = get_post_meta($post->ID, self::ROUTE, true);
        $sms_message = get_post_meta($post->ID, self::SMS_SLUG, true);
        $whatsapp_message = get_post_meta($post->ID, self::WHATSAPP_SLUG, true);
        $enable = get_post_meta($post->ID, self::ENABLE_KEY, true);
        if ($enable != 1) {
            return;
        }
        if (empty($sms_message) && empty($whatsapp_message)) {
            return;
        }

        $enable_sms = false;
        $enable_whatsapp = false;

        if (!empty($sms_message) && ($route == -1 || $route == 1)) {
            $enable_sms = true;
        }
        if (!empty($whatsapp_message) && ($route == -1 || $route == 1001)) {
            $enable_whatsapp = true;
        }


        $phone = $this->get_phone($to);
        $phone_obj = WPNotif_Handler::parseMobile($phone);
        if (empty($phone_obj)) {
            return;
        }

        $tokens = $args['tokens'];
        $formatted_tokens = array();
        foreach ($tokens as $name => $value) {
            $formatted_tokens['{{' . $name . '}}'] = strip_tags($value);
        }

        $sms_message = strtr($sms_message, $formatted_tokens);
        $whatsapp_message = strtr($whatsapp_message, $formatted_tokens);

        $data['user_phone'] = $phone;

        $data['user_message'] = $sms_message;
        $data['whatapp_message'] = $whatsapp_message;

        $data['email'] = $email;
        $data = WPNotif::data_type(self::SLUG, $data);

        WPNotif::notify(get_current_user_id(), self::SLUG, $data, $enable_sms, $enable_whatsapp);

    }

    public function get_phone($user)
    {

        $phone = WPNotif_Handler::get_user_phone($user->ID, false);

        if (!$this->starts_with('+', $phone)) {
            $phone = '+' . $phone;
        }

        return $phone;
    }

    public static function starts_with($query, $str)
    {
        return strpos($str, $query) === 0;
    }

}
