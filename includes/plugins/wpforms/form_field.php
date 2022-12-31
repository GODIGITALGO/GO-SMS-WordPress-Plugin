<?php

namespace WPNotif_Compatibility\WPForms;

use WPForms_Field;

/**
 * Phone text field.
 *
 * @since 1.0.0
 */
class WPForms_Field_Phone extends WPForms_Field
{

    /**
     * Encoding.
     *
     * @since 1.6.9
     */
    const ENCODING = 'UTF-8';

    /**
     * Primary class constructor.
     *
     * @since 1.0.0
     */
    public function init()
    {

        // Define field type information.
        $this->name = esc_html__('Phone (WPNotif)', 'wpnotif');
        $this->type = 'wpnotif';
        $this->icon = 'fa-phone';
        $this->order = 170;

        // Set field to default to required.
        add_filter('wpforms_field_new_required', array($this, 'default_required'), 10, 2);

        add_filter('wpforms_save_form_args', [$this, 'save_form_args'], 11, 3);
    }


    /**
     * Field should default to being required.
     *
     * @param bool $required
     * @param array $field
     * @return bool
     * @since 1.0.9
     */
    public function default_required($required, $field)
    {

        if ('wpnotif' === $field['type']) {
            return true;
        }
        return $required;
    }

    /**
     * Field options panel inside the builder.
     *
     * @param array $field
     * @since 1.0.0
     *
     */
    public function field_options($field)
    {
        /*
         * Basic field options.
         */

        // Options open markup.
        $args = array(
            'markup' => 'open',
        );
        $this->field_option('basic-options', $field, $args);

        // Label.
        $this->field_option('label', $field);

        // Description.
        $this->field_option('description', $field);

        // Required toggle.
        $this->field_option('required', $field);

        // Options close markup.
        $args = array(
            'markup' => 'close',
        );
        $this->field_option('basic-options', $field, $args);

        /*
         * Advanced field options.
         */

        // Options open markup.
        $args = array(
            'markup' => 'open',
        );
        $this->field_option('advanced-options', $field, $args);

        // Size.
        $this->field_option('size', $field);

        // Placeholder.
        $this->field_option('placeholder', $field);

        // Default value.
        $this->field_option('default_value', $field);


        // Custom CSS classes.
        $this->field_option('css', $field);

        // Hide Label.
        $this->field_option('label_hide', $field);

        // Hide sublabels.
        $this->field_option('sublabel_hide', $field);

        // Options close markup.
        $args = [
            'markup' => 'close',
        ];

        $this->field_option('advanced-options', $field, $args);
    }

    /**
     * Field preview inside the builder.
     *
     * @param array $field
     * @since 1.0.0
     */
    public function field_preview($field)
    {

        // Define data.
        $placeholder = !empty($field['placeholder']) ? esc_attr($field['placeholder']) : '';
        $confirm_placeholder = !empty($field['confirmation_placeholder']) ? esc_attr($field['confirmation_placeholder']) : '';
        $confirm = !empty($field['confirmation']) ? 'enabled' : 'disabled';

        // Label.
        $this->field_preview_option('label', $field);
        ?>

        <div class="wpforms-confirm wpforms-confirm-<?php echo sanitize_html_class($confirm); ?>">

            <div class="wpforms-confirm-primary">
                <input type="email" placeholder="<?php echo esc_attr($placeholder); ?>" class="primary-input" readonly>
                <label class="wpforms-sub-label"><?php esc_html_e('Email', 'wpforms-lite'); ?></label>
            </div>

            <div class="wpforms-confirm-confirmation">
                <input type="email" placeholder="<?php echo esc_attr($confirm_placeholder); ?>" class="secondary-input"
                       readonly>
                <label class="wpforms-sub-label"><?php esc_html_e('Confirm Email', 'wpforms-lite'); ?></label>
            </div>

        </div>

        <?php
        // Description.
        $this->field_preview_option('description', $field);
    }

    /**
     * Field display on the form front-end.
     *
     * @param array $field
     * @param array $deprecated
     * @param array $form_data
     * @since 1.0.0
     */
    public function field_display($field, $deprecated, $form_data)
    {

        // Define data.
        $form_id = absint($form_data['id']);
        $primary = $field['properties']['inputs']['primary'];


        // Primary field.
        printf(
            '<input type="email" %s %s>',
            wpforms_html_attributes($primary['id'], $primary['class'], $primary['data'], $primary['attr']),
            esc_attr($primary['required'])
        );
        $this->field_display_error('primary', $field);

    }

    /**
     * Format and sanitize field.
     *
     * @param int $field_id Field ID.
     * @param mixed $field_submit Field value that was submitted.
     * @param array $form_data Form data and settings.
     * @since 1.3.0
     */
    public function format($field_id, $field_submit, $form_data)
    {

        // Define data.
        if (is_array($field_submit)) {
            $value = !empty($field_submit['primary']) ? $field_submit['primary'] : '';
        } else {
            $value = !empty($field_submit) ? $field_submit : '';
        }

        $name = !empty($form_data['fields'][$field_id] ['label']) ? $form_data['fields'][$field_id]['label'] : '';

        // Set final field details.
        wpforms()->process->fields[$field_id] = array(
            'name' => sanitize_text_field($name),
            'value' => $value,
            'id' => absint($field_id),
            'type' => $this->type,
        );
    }

    /**
     * Validate field on form submit.
     *
     * @param int $field_id Field ID.
     * @param mixed $field_submit Field value that was submitted.
     * @param array $form_data Form data and settings.
     * @since 1.0.0
     *
     */
    public function validate($field_id, $field_submit, $form_data)
    { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

        $form_id = (int)$form_data['id'];

        parent::validate($field_id, $field_submit, $form_data);

        if (!is_array($field_submit) && !empty($field_submit)) {
            $field_submit = [
                'primary' => $field_submit,
            ];
        }

        if (!empty($field_submit['primary'])) {
            $field_submit['primary'] = $this->encode_punycode($field_submit['primary']);
        }

        // Validate email field with confirmation.
        if (isset($form_data['fields'][$field_id]['confirmation']) && !empty($field_submit['primary']) && !empty($field_submit['secondary'])) {

            if (!is_email($field_submit['primary'])) {
                wpforms()->process->errors[$form_id][$field_id] = esc_html__('The provided email is not valid.', 'wpforms-lite');

            } elseif ($field_submit['primary'] !== $this->encode_punycode($field_submit['secondary'])) {
                wpforms()->process->errors[$form_id][$field_id] = esc_html__('The provided emails do not match.', 'wpforms-lite');

            } elseif (!$this->is_restricted_email($field_submit['primary'], $form_data['fields'][$field_id])) {
                wpforms()->process->errors[$form_id][$field_id] = wpforms_setting('validation-email-restricted', esc_html__('This email address is not allowed.', 'wpforms-lite'));
            }
        }

        // Validate regular email field, without confirmation.
        if (!isset($form_data['fields'][$field_id]['confirmation']) && !empty($field_submit['primary'])) {

            if (!is_email($field_submit['primary'])) {
                wpforms()->process->errors[$form_id][$field_id] = esc_html__('The provided email is not valid.', 'wpforms-lite');

            } elseif (!$this->is_restricted_email($field_submit['primary'], $form_data['fields'][$field_id])) {
                wpforms()->process->errors[$form_id][$field_id] = wpforms_setting('validation-email-restricted', esc_html__('This email address is not allowed.', 'wpforms-lite'));
            }
        }
    }

    /**
     * Sanitize allow/deny list before saving.
     *
     * @param array $form Form array which is usable with `wp_update_post()`.
     * @param array $data Data retrieved from $_POST and processed.
     * @param array $args Empty by default, may contain custom data not intended to be saved, but used for processing.
     *
     * @return array
     * @since 1.6.8
     *
     */
    public function save_form_args($form, $data, $args)
    {

        // Get a filtered form content.
        $form_data = json_decode(stripslashes($form['post_content']), true);

        if (!empty($form_data['fields'])) {
            foreach ((array)$form_data['fields'] as $key => $field) {
                if (empty($field['type']) || $field['type'] !== 'email') {
                    continue;
                }

                $form_data['fields'][$key]['allowlist'] = !empty($field['allowlist']) ? implode(PHP_EOL, $this->sanitize_restricted_rules($field['allowlist'])) : '';
                $form_data['fields'][$key]['denylist'] = !empty($field['denylist']) ? implode(PHP_EOL, $this->sanitize_restricted_rules($field['denylist'])) : '';
            }
        }

        $form['post_content'] = wpforms_encode($form_data);

        return $form;
    }
}
