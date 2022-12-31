<?php

namespace WPNotif_Compatibility\ContactForm7;


if (!defined('ABSPATH')) {
    exit;
}


final class NotificationSettings
{
    public static $field_type = 'wpnotif_phone';
    protected static $_instance = null;
    public $type = 'contactform7';
    public $settings_slug = 'wpnotif_notifications';
    public $field_id_prefix = 'wpnotif_field';

    /**
     *  Constructor.
     */
    public function __construct()
    {
        $this->init_hooks();
    }

    private function init_hooks()
    {

        add_action('wpcf7_editor_panels', array($this, 'add_settings_panel'), 50);
        add_action('wpcf7_save_contact_form', array($this, 'save_settings'), 10, 1);

    }

    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function add_settings_panel($panels)
    {
        $panels['wpnotif_phone_notifications'] = array(
            'title' => __('WPNotif', 'wpnotif'),
            'callback' => array($this, 'settings'),
        );

        return $panels;
    }

    public function get_field_type()
    {
        return self::$field_type;
    }

    public function settings($post)
    {
        $id = $this->field_id_prefix;

        $form_id = intval($_GET['post']);

        $settings = $this->get_settings($form_id);

        ?>
        <h2><?php echo esc_html(__('Phone Notifications', 'wpnotif')); ?></h2>
        <input type="hidden" name="wpnotif_contactform_settings" value="1">
        <fieldset>
            <table class="form-table">
                <tbody>
                <?php
                foreach ($this->notification_fields() as $field_key => $notification_field) {
                    $value = isset($settings[$field_key]) ? $settings[$field_key] : '';
                    ?>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo $id; ?>-<?php echo $field_key; ?>>"><?php echo $notification_field['label'] ?></label>
                        </th>
                        <td>
                        <textarea type="text" id="<?php echo $id . '-' . $field_key; ?>"
                                  name="<?php echo $id . '[' . $field_key . ']'; ?>"
                                  placeholder="<?php echo $notification_field['placeholder']; ?>"
                                  class="large-text code" rows="4"><?php esc_html_e($value); ?></textarea>
                        </td>
                    </tr>
                    <?php
                }
                ?>

                <tr>
                    <th scope="row">
                        <label for="<?php echo $id; ?>-phonefield_name"><?php echo esc_html(__('Phone Field', 'wpnotif')) . ' (' . esc_html(__('Name', 'wpnotif')) . ')'; ?></label>
                    </th>
                    <td>
                        <input type="text" id="<?php echo $id; ?>-phonefield_name"
                               name="<?php echo $id . '[' . self::$field_type . ']'; ?>"
                               class="large-text code" size="70"
                               value="<?php if (isset($settings[self::$field_type])) echo $settings[self::$field_type]; ?>"/>
                    </td>
                </tr>

                <?php
                $options = array(
                    array(
                        'label' => esc_html__('No', 'wpnotif'),
                        'value' => 0
                    ),
                    array(
                        'label' => esc_html__('Yes', 'wpnotif'),
                        'value' => 1
                    )
                );

                ?>
                <tr>
                    <th scope="row">
                        <label for="<?php echo $id; ?>-route"><?php echo esc_html__('Route', 'wpnotif'); ?></label>
                    </th>
                    <td>
                        <select id="<?php echo $id; ?>-route"
                                name="<?php echo $id . '[route]'; ?>"
                                class="large wpnotif_max_width">
                            <?php
                            $selected = (isset($settings['route'])) ? $settings['route'] : '1';

                            $routes = array(
                                array(
                                    'label' => esc_html__('SMS', 'wpnotif'),
                                    'value' => 1,
                                ),
                                array(
                                    'label' => esc_html__('Whatsapp', 'wpnotif'),
                                    'value' => 1001,
                                ),
                                array(
                                    'label' => esc_html__('Both', 'wpnotif'),
                                    'value' => -1,
                                )
                            );
                            foreach ($routes as $option) {
                                $sel = '';
                                if ($option['value'] == $selected) {
                                    $sel = 'selected="selected"';
                                }
                                echo '<option value="' . esc_attr($option['value']) . '" ' . $sel . '>' . esc_html($option['label']) . '</option>';
                            }
                            ?>
                        </select>

                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="<?php echo $id; ?>-use_as_newsletter"><?php echo esc_html__('Use as Newsletter Subscription form', 'wpnotif'); ?></label>
                    </th>
                    <td>

                        <select id="<?php echo $id; ?>-use_as_newsletter"
                                name="<?php echo $id . '[use_as_newsletter]'; ?>"
                                class="wpnotif_use_as_newsletter_inp wpnotif_max_width large">
                            <?php
                            $selected = (isset($settings['use_as_newsletter'])) ? $settings['use_as_newsletter'] : '0';

                            foreach ($options as $option) {
                                $sel = '';
                                if ($option['value'] == $selected) {
                                    $sel = 'selected="selected"';
                                }
                                echo '<option value="' . esc_attr($option['value']) . '" ' . $sel . '>' . esc_html($option['label']) . '</option>';
                            }
                            ?>
                        </select>

                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="<?php echo $id; ?>-user_group"><?php echo esc_html__('User Group', 'wpnotif'); ?></label>
                    </th>
                    <td>
                        <select id="<?php echo $id; ?>-user_group"
                                name="<?php echo $id . '[user_group]'; ?>"
                                class="large wpnotif_max_width wpnotif_use_newsletter">
                            <?php
                            $selected = (isset($settings['user_group'])) ? $settings['user_group'] : '0';
                            $groups = \WPNotif_NewsLetter::get_formated_usergroup_list(false);
                            foreach ($groups as $group) {
                                $sel = '';
                                if ($group['value'] == $selected) {
                                    $sel = 'selected="selected"';
                                }
                                echo '<option value="' . esc_attr($group['value']) . '" ' . $sel . '>' . esc_html($group['label']) . '</option>';
                            }
                            ?>
                        </select>

                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="<?php echo $id; ?>-first_name"><?php echo esc_html(__('First Name Field', 'wpnotif')) . ' (' . esc_html(__('Name', 'wpnotif')) . ')'; ?></label>
                    </th>
                    <td>
                        <input type="text" id="<?php echo $id; ?>-first_name"
                               name="<?php echo $id . '[first_name]'; ?>"
                               class="large-text code wpnotif_use_newsletter" size="70"
                               value="<?php if (isset($settings['first_name'])) echo $settings['first_name']; ?>"/>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="<?php echo $id; ?>-last_name"><?php echo esc_html(__('Last Name Field', 'wpnotif')) . ' (' . esc_html(__('Name', 'wpnotif')) . ')'; ?></label>
                    </th>
                    <td>
                        <input type="text" id="<?php echo $id; ?>-last_name"
                               name="<?php echo $id . '[last_name]'; ?>"
                               class="large-text code wpnotif_use_newsletter" size="70"
                               value="<?php if (isset($settings['last_name'])) echo $settings['last_name']; ?>"/>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="<?php echo $id; ?>-email"><?php echo esc_html(__('Email Field', 'wpnotif')) . ' (' . esc_html(__('Name', 'wpnotif')) . ')'; ?></label>
                    </th>
                    <td>
                        <input type="text" id="<?php echo $id; ?>-email"
                               name="<?php echo $id . '[email]'; ?>"
                               class="large-text code wpnotif_use_newsletter" size="70"
                               value="<?php if (isset($settings['email'])) echo $settings['email']; ?>"/>
                    </td>
                </tr>


                </tbody>
            </table>
        </fieldset>
        <?php
    }

    public function get_settings($form_id)
    {
        $settings = get_post_meta($form_id, $this->settings_slug, true);
        return empty($settings) ? array() : $settings;
    }

    public function notification_fields()
    {
        return array(
            'admin_message' => array('label' => esc_attr__('Admin Notification', 'wpnotif'), 'placeholder' => esc_attr__('Leave empty to disable', 'wpnotif')),
            'user_message' => array('label' => esc_attr__('User Notification', 'wpnotif'), 'placeholder' => esc_attr__('Leave empty to disable', 'wpnotif')),
        );
    }

    public function save_settings($contact_form)
    {
        if (empty($_POST) || !isset($_POST['wpnotif_contactform_settings'])) {
            return;
        }

        $id = $this->field_id_prefix;

        $post_id = $contact_form->id();
        if (!$post_id)
            return;

        $data = array();
        $wpnotif_fields = $_POST[$id];

        foreach ($this->notification_fields() as $field_key => $notification_field) {
            $data[$field_key] = sanitize_textarea_field($wpnotif_fields[$field_key]);
        }
        $data[self::$field_type] = sanitize_text_field($wpnotif_fields[self::$field_type]);

        $fields = array('route', 'use_as_newsletter', 'first_name', 'last_name', 'email', 'user_group');
        foreach ($fields as $field) {
            $value = isset($wpnotif_fields[$field]) ? $wpnotif_fields[$field] : 0;
            $data[$field] = sanitize_text_field($value);
        }

        update_post_meta($post_id, $this->settings_slug, $data);
    }

}