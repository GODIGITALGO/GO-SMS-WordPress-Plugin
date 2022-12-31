<?php

if (!defined('ABSPATH')) {
    exit;
}


function wpnotif_create_group_shortcode($gid)
{
    return '[wpn gid="' . esc_attr($gid) . '"]';
}

add_shortcode('wpn', 'wpnotif_edit_phone_shortcode');

function wpnotif_edit_phone_shortcode($atts)
{

    $user_id = get_current_user_id();
    $gid = false;

    if(isset($atts['gid'])){
        $gid = $atts['gid'];
    }
    if (!is_user_logged_in() && empty($atts['gid'])) return '';

    $edit_phone = '<form class="wpnotif_subscribe" method="post" autocomplete="off">';

    if (!empty($gid)) {
        $edit_phone .= '<input type="hidden" name="gid_code" value="' . esc_attr(wp_create_nonce('gid_' . $gid)) . '">';
        $edit_phone .= '<input type="hidden" name="gid" value="' . esc_attr($gid) . '">';
    }

    $nonce = 'wpnotif_mobile_update_nounce';

    $fname = '';
    $lname = '';
    if (is_user_logged_in()) {
        $user = wp_get_current_user();
        $fname = $user->first_name;
        $lname = $user->last_name;
        $email = $user->user_email;
    }
    $fields = array();
    if (isset($atts['fields'])) {
        $fields = explode(",", $atts['fields']);
        $fields = array_map('trim', $fields);
    }

    if (in_array('firstname', $fields)) {
        $nonce = $nonce . '_fname';
    }
    if (in_array('lastname', $fields)) {
        $nonce = $nonce . '_lname';
    }

    if (in_array('email', $fields)) {
        $nonce = $nonce . 'email';
    }

    foreach ($fields as $field) {
        $field = strtolower(trim($field));
        if ($field == 'firstname') {
            $edit_phone .= '<div class="wpnotif-field wpnotif-container-first_name">';
            $edit_phone .= '<label for="wpnotif_firstname">' . esc_attr__("First Name", "wpnotif") . '</label>';
            $edit_phone .= '<div class="wpnotif_field-wrapper"><input type="text" autocomplete="off"
                       name="wpnotif_firstname"
                       id="wpnotif_firstname"
                       value="' . esc_attr__($fname) . '"
                       class="wpnotif_firstname regular-text" required/>';
            $edit_phone .= '</div></div>';
        } else if ($field == 'lastname') {
            $edit_phone .= '<div class="wpnotif-field wpnotif-container-last_name">';
            $edit_phone .= '<label for="wpnotif_lastname">' . esc_attr__("Last Name", "wpnotif") . '</label>';
            $edit_phone .= '<div class="wpnotif_field-wrapper"><input type="text" autocomplete="off"
                       name="wpnotif_lastname"
                       id="wpnotif_lastname"
                       value="' . esc_attr__($lname) . '"
                       class="wpnotif_lastname regular-text" required/>';
            $edit_phone .= '</div></div>';
        } else if ($field == 'email') {
            $edit_phone .= '<div class="wpnotif-field wpnotif-container-email">';
            $edit_phone .= '<label for="wpnotif_email">' . esc_attr__("Email", "wpnotif") . '</label>';
            $edit_phone .= '<div class="wpnotif_field-wrapper"><input type="email" autocomplete="off"
                       name="wpnotif_email"
                       id="wpnotif_email"
                       value="' . esc_attr__($email) . '"
                       class="wpnotif_email regular-text" required/>';
            $edit_phone .= '</div></div>';
        } else if ($field == 'phone') {
            $edit_phone .= wpnotif_show_phone($user_id, true, true, $nonce);
        }
    }


    $phone_verify = get_option('wpnotif_phone_verification', 'off');

    if (!in_array('phone', $fields)) {
        $edit_phone .= wpnotif_show_phone($user_id, true, true, $nonce);
    }

    if ($phone_verify == 'on') {
        $edit_phone .= '<div class="wpnotif-field wpnotif-container-otp wpnotif_otp_field">';
        $edit_phone .= '<label for="wpnotif_otp">' . esc_attr__("OTP", "wpnotif") . '</label>';
        $edit_phone .= '<div class="wpnotif_field-wrapper"><input type="text" autocomplete="off"
                       name="wpnotif_otp"
                       id="wpnotif_otp"
                       value=""
                       class="wpnotif_otp regular-text"/>';
        $edit_phone .= '</div></div>';
    }


    $edit_phone .= '<div class="wpnotif-field"><button class="button button-primary wpnotif_update_mobile_submit" type="submit">' . esc_attr__('Subscribe', 'wpnotif') . '</button></div>';


    $edit_phone .= '</form>';

    return $edit_phone;
}

function wpnotif_show_phone($user_id, $show_label, $is_req, $nonce = 'wpnotif_mobile_update_nounce')
{
    do_action('wpnotif_load_frontend_scripts');
    do_action('wpnotif_load_verification_scripts');


    $edit_phone = '';

    $phone = wpnotif_get_mobile($user_id);
    if (!empty($phone)) {
        $parse_phone = WPNotif_Handler::parseMobile($phone);
        $countrycode = $parse_phone['countrycode'];
        $phone = $parse_phone['mobile'];
    } else {
        $countrycode = WPNotif::getDefaultCountryCode();
    }

    $edit_phone .= '<input type="hidden" name="wpnotif_nounce" class="wpnotif_nounce" value="' . wp_create_nonce($nonce) . '" />';
    $edit_phone .= '<input type="hidden" name="wpnotif_update_mobile" class="wpnotif_update_mobile" value="1" />';

    $edit_phone .= '<div class="wpnotif-field wpnotif-edit-phone_container">';


    if ($show_label) {
        $edit_phone .= '<label for="wpnotif_phone">' . esc_attr__("Phone Number", "wpnotif") . '</label>';
    }

    $edit_phone .= '<div class="wpnotif_phone_field_container">';
    $edit_phone .= '<div class="wpnotif_phonefield">';

    $edit_phone .= '<div class="wpnotif_countrycodecontainer">';
    $edit_phone .= sprintf('<input type="text" name="wpnotif_countrycode"
                                   class="wpnotif_countrycode digits_countrycode"
                                   value="%s" maxlength="6" size="3"
                                   placeholder="%s" />', $countrycode, $countrycode);
    $edit_phone .= '</div>';

    $req = "";
    if ($is_req) {
        $req = "required";
    }

    $edit_phone .= '<input type="text" autocomplete="off"
                       name="wpnotif_phone"
                       id="wpnotif_phone"
                       value="' . esc_attr__($phone) . '"
                       class="wpnotif_phone mobile_field mobile_format regular-text" ' . $req . '/>';

    $edit_phone .= '</div>';
    $edit_phone .= '</div>';


    $edit_phone .= '</div>';

    return $edit_phone;
}

function wpnotif_form_update_number($user_id, $verified = false)
{

    if (isset($_POST['wpnotif_update_mobile'])) {
        $countrycode = sanitize_text_field($_POST['wpnotif_countrycode']);
        $mobile = sanitize_mobile_field_wpnotif($_POST['wpnotif_phone']);
        $phone = $countrycode . $mobile;

        if(empty($phone) || empty($mobile)){
            wpnotif_delete_mobile($user_id);
            return false;
        }

        $phone_obj = WPNotif_Handler::parseMobile($phone);

        if (!$phone_obj) {
            return new WP_Error('invalid_mobile', esc_attr__('Please enter a valid phone number!', 'wpnotif'));
        }

        $fname = !empty($_POST['wpnotif_firstname']) ? sanitize_text_field($_POST['wpnotif_firstname']) : null;
        $lname = !empty($_POST['wpnotif_lastname']) ? sanitize_text_field($_POST['wpnotif_lastname']) : null;
        $email = !empty($_POST['wpnotif_email']) ? sanitize_email($_POST['wpnotif_email']) : null;

        if (isset($_POST['wpnotif_email']) && (empty($email) || !isValidEmail($email))) {
            return new WP_Error('invlid_mobile', esc_attr__('Please enter a valid email!', 'wpnotif'));
        }

        if (is_user_logged_in()) {
            $user_data = array();
            $user = wp_get_current_user();

            if (!empty($fname)) {
                $user_data['first_name'] = $fname;
            }
            if (!empty($lname)) {
                $user_data['last_name'] = $lname;
            }

            if (!empty($user_data)) {
                $user_data['ID'] = $user->ID;
                wp_update_user($user_data);
            }

            wpnotif_update_user_number($user_id, $phone_obj);
        }
        if (isset($_POST['gid'])) {
            $gid = sanitize_text_field($_POST['gid']);

            if (wp_verify_nonce($_POST['gid_code'], 'gid_' . $gid)) {
                $details = array();
                $details['wp_id'] = get_current_user_id();


                if (!empty($fname)) {
                    $details['first_name'] = $fname;
                }
                if (!empty($lname)) {
                    $details['last_name'] = $lname;
                }
                if (!empty($email)) {
                    $details['email'] = $email;
                }

                $details['phone'] = $phone;
                $details['consent'] = 1;
                $details['consent_time'] = date("Y-m-d H:i:s", time());
                $user_details['activation'] = WPNotif_UserGroup_Import::generate_key();

                if ($verified) {
                    $details['verified'] = 1;
                }

                WPNotif_UserGroup_Import::instance()->check_update_user_import($gid, $details);
            }

            return true;
        }
    } else {
        return new WP_Error('error', esc_attr__('Error', 'wpnotif'));
    }
}


function wpnotif_update_user_number($user_id, $phone_obj)
{
    if (empty($phone_obj)) {
        wpnotif_delete_mobile($user_id);;
        return;
    }
    if (!$phone_obj) {
        return;
    } else {
        $countrycode = $phone_obj['countrycode'];
        $mobile = $phone_obj['mobile'];
        wpnotif_update_mobile($user_id, $countrycode, $mobile);
    }
}


function wpnotif_get_mobile($user_id = 0)
{
    if ($user_id == 0) {
        $user_id = get_current_user_id();
    }
    return get_user_meta($user_id, 'wpnotif_phone', true);
}

function wpnotif_delete_mobile($user_id)
{
    delete_user_meta($user_id, 'wpnotif_phone');
}


function wpnotif_update_mobile($user_id, $countrycode, $phone)
{
    $phone = $countrycode . $phone;

    update_user_meta($user_id, 'wpnotif_phone', $phone);

    WPNotif_UserGroup_Import::instance()->update_wp_user_id($user_id);
}


function wpnotif_admin_mobile_field($user)
{
    ?>
    <table class="form-table">
        <tr>
            <th>
                <label for="wpnotif_phone"><?php _e('Mobile Number', 'wpnotif'); ?> (WPNotif)</label>
            </th>
            <td>
                <?php echo wpnotif_show_phone($user->ID, false, false); ?>
            </td>
        </tr>
    </table>
    <?php
}

add_action('show_user_profile', 'wpnotif_admin_mobile_field', 110, 10);
add_action('edit_user_profile', 'wpnotif_admin_mobile_field');


add_action('personal_options_update', 'wpnotif_admin_update_phone_field');
add_action('edit_user_profile_update', 'wpnotif_admin_update_phone_field');

function wpnotif_admin_update_phone_field($user_id)
{
    if (current_user_can('edit_users')) {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }
        wpnotif_form_update_number($user_id);
    }
}

function sanitize_mobile_field_wpnotif($mobile)
{

    $mobile = preg_replace('/[\s+()-]+/', '', $mobile);

    return ltrim(sanitize_text_field($mobile), '0');
}