<?php

namespace WPNotif_Compatibility\WPForms;


class FormSettings
{
    protected static $_instance = null;

    /**
     *  Constructor.
     */
    public function __construct()
    {
        add_action('wpforms_builder_settings_sections', [$this, 'add_settings_section'], 10, 2);
        add_action('wpforms_form_settings_panel_content', [$this, 'settings_panel_content'], 10, 1);
    }

    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function add_settings_section($sections, $form_data)
    {
        $sections['wpnotif'] = esc_html__('WPNotif', 'wpnotif');
        return $sections;
    }

    public function settings_panel_content($panel)
    {
        echo '<div class="wpforms-panel-content-section wpforms-panel-content-section-wpnotif" data-panel="wpnotif">';
        $this->form_settings_notifications($panel);
        echo '</div>';
    }

    public function form_settings_notifications($settings)
    {
        $groups = \WPNotif_NewsLetter::get_formated_usergroup_list(false);
        $formatted_groups = array();
        foreach ($groups as $group) {
            $formatted_groups[$group['value']] = $group['label'];
        }

        $id = 1;
        echo '<div class="wpforms-panel-content-section-title">';
        echo '<span id="wpforms-builder-settings-notifications-title">';
        esc_html_e('Phone Notifications', 'wpforms-lite');
        echo '</span>';
        echo '</div>';

        wpforms_panel_field(
            'toggle',
            'settings',
            'wpnotif_notification_enable',
            $settings->form_data,
            esc_html__('Enable Notifications', 'wpnotif')
        );
        ?>

        <div class="wpforms-notification wpforms-builder-settings-block">

            <div class="wpforms-builder-settings-block-header">
                <span><?php esc_html_e('Default Notification', 'wpnotif'); ?></span>
            </div>

            <div class="wpforms-builder-settings-block-content">
                <?php

                wpforms_panel_field(
                    'text',
                    'settings',
                    'wpnotif_user_phone',
                    $settings->form_data,
                    esc_html__('User Phone Field', 'wpnotif'),
                    [
                        'smarttags' => [
                            'type' => 'fields',
                            'fields' => 'wpnotif',
                        ],
                        'parent' => 'settings',
                        'subsection' => $id,
                        'tooltip' => esc_html__('Use Smart Tags', 'wpnotif'),

                    ]
                );
                wpforms_panel_field(
                    'text',
                    'settings',
                    'wpnotif_user_name',
                    $settings->form_data,
                    esc_html__('User Name Field', 'wpnotif'),
                    [
                        'smarttags' => [
                            'type' => 'fields',
                            'fields' => 'name',
                        ],
                        'parent' => 'settings',
                        'subsection' => $id,
                    ]
                );
                wpforms_panel_field(
                    'text',
                    'settings',
                    'wpnotif_user_email',
                    $settings->form_data,
                    esc_html__('User Email Field', 'wpnotif'),
                    [
                        'smarttags' => [
                            'type' => 'fields',
                            'fields' => 'email',
                        ],
                        'parent' => 'settings',
                        'subsection' => $id,
                    ]
                );

                wpforms_panel_field(
                    'text',
                    'settings',
                    'wpn_phone_admin_message',
                    $settings->form_data,
                    esc_html__('Admin Notification', 'wpnotif'),
                    [
                        'smarttags' => [
                            'type' => 'all',
                        ],
                        'parent' => 'settings',
                        'subsection' => $id,
                    ]
                );
                wpforms_panel_field(
                    'textarea',
                    'settings',
                    'wpn_phone_user_message',
                    $settings->form_data,
                    esc_html__('User Notification', 'wpnotif'),
                    [
                        'rows' => 6,
                        'default' => '',
                        'smarttags' => [
                            'type' => 'all',
                        ],
                        'parent' => 'settings',
                        'subsection' => $id,
                        'class' => 'phone-msg',
                        'after' => '<p class="note">' .
                            sprintf(
                            /* translators: %s - {all_fields} Smart Tag. */
                                esc_html__('To display all form fields, use the %s Smart Tag.', 'wpforms-lite'),
                                '<code>{all_fields}</code>'
                            ) .
                            '</p>',
                    ]
                );

                wpforms_panel_field(
                    'select',
                    'settings',
                    'wpnotif_route',
                    $settings->form_data,
                    esc_html__('Route', 'wpnotif'),
                    [
                        'default' => 'message',
                        'options' => [
                            1 => esc_html__('SMS', 'wpnotif'),
                            1001 => esc_html__('WhatsApp', 'wpnotif'),
                            -1 => esc_html__('Both', 'wpnotif'),
                        ],
                        'parent' => 'settings',
                        'subsection' => $id,
                    ]
                );


                wpforms_panel_field(
                    'select',
                    'settings',
                    'wpnotif_use_as_newsletter',
                    $settings->form_data,
                    esc_html__('Use as Newsletter Subscription form', 'wpnotif'),
                    [
                        'default' => 'message',
                        'options' => [
                            '0' => esc_html__('No', 'wpnotif'),
                            '1' => esc_html__('Yes', 'wpnotif'),
                        ],
                        'parent' => 'settings',
                        'subsection' => $id,
                    ]
                );

                wpforms_panel_field(
                    'select',
                    'settings',
                    'wpnotif_user_group',
                    $settings->form_data,
                    esc_html__('User Group', 'wpnotif'),
                    [
                        'default' => 'message',
                        'options' => $formatted_groups,
                        'parent' => 'settings',
                        'subsection' => $id,
                    ]
                );
                ?>
            </div>
        </div>

        <?php
        do_action('wpforms_builder_settings_wpnotif_after', 'wpnotif', $settings);
    }


}