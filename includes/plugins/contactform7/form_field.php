<?php

namespace WPNotif_Compatibility\ContactForm7;


use WPNotif;

if (!defined('ABSPATH')) {
    exit;
}


final class Field_Phone
{
    public static $field_type = 'wpnotif_phone';
    protected static $_instance = null;
    public $type = 'contactform7';
    public $countrycode_prefix = 'countrycode_';

    /**
     *  Constructor.
     */
    public function __construct()
    {
        $this->init_hooks();
    }

    private function init_hooks()
    {
        add_action('wpcf7_admin_init', array($this, 'add_tag_generator'), 50);
        add_action('wpcf7_init', array($this, 'add_field'), 10, 0);

        add_filter('wpcf7_validate_' . self::$field_type, array($this, 'validate'), 10, 2);
        add_filter('wpcf7_validate_' . self::$field_type . '*', array($this, 'validate'), 10, 2);

        add_filter('wpcf7_posted_data_' . self::$field_type, array($this, 'posted_value'), 10, 3);

    }

    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function posted_value($value, $value_orig, $tag)
    {
        $field_name = $tag['name'];

        $countrycode = sanitize_text_field($_POST[$this->countrycode_prefix . $field_name]);
        $phone = $countrycode . $value;

        return $phone;
    }

    public function validate($result, $tag)
    {
        $name = $tag->name;
        $is_required = $tag->is_required();
        $nationalNumber = isset($_POST[$name]) ? $_POST[$name] : '';

        $countrycode = isset($_POST[$this->countrycode_prefix . $name]) ? $_POST[$this->countrycode_prefix . $name] : '';

        $phone = $countrycode . $nationalNumber;

        $parse = \WPNotif_Handler::parseMobile($phone);
        if (!$parse && !empty($phone)) {
            $result->invalidate($tag, esc_attr__("Please enter a valid number", 'wpnotif'));
        } else if ($is_required && empty($phone) && empty($parse)) {
            $result->invalidate($tag, wpcf7_get_message('invalid_required'));
        }

        return $result;
    }

    public function add_field()
    {
        wpcf7_add_form_tag(array(self::$field_type, self::$field_type . '*'),
            array($this, 'form_tag_handler'), array('name-attr' => true));
    }


    public function add_tag_generator()
    {
        $tag_generator = \WPCF7_TagGenerator::get_instance();
        $tag_generator->add(self::$field_type, __('Phone Number (WPNotif)', 'wpnotif'),
            array($this, 'tag_generator'));
    }


    public function form_tag_handler($tag)
    {

        if (empty($tag->name)) {
            return '';
        }

        $field = '';
        $validation_error = wpcf7_get_validation_error($tag->name);

        $class = wpcf7_form_controls_class($tag->type);

        $class .= ' wpcf7-validates-as-' . self::$field_type;

        if ($validation_error) {
            $class .= ' wpcf7-not-valid';
        }

        $atts = array();

        $atts['class'] = $tag->get_class_option($class);
        $atts['id'] = $tag->get_id_option();
        $atts['tabindex'] = $tag->get_option('tabindex', 'signed_int', true);

        if ($tag->has_option('readonly')) {
            $atts['readonly'] = 'readonly';
        }

        if ($tag->is_required()) {
            $atts['aria-required'] = 'true';
        }

        $atts['aria-invalid'] = $validation_error ? 'true' : 'false';

        $countrycode = WPNotif::getDefaultCountryCode();


        $atts['value'] = '';

        $atts['type'] = 'text';

        $atts['name'] = $tag->name;

        $atts = wpcf7_format_atts($atts);

        $countrycode_atts = array();
        $countrycode_atts['name'] = $this->countrycode_prefix . $tag->name;
        $countrycode_atts = wpcf7_format_atts($countrycode_atts);


        $field .= sprintf(
            '<span class="wpcf7-form-control-wrap %1$s">', sanitize_html_class($tag->name));


        $field .= "<span class='contactform7_container_mobile wpnotif_phone_field_container'>";
        $field .= '<span class="wpnotif_phonefield">';
        $field .= '<span class="wpnotif_countrycodecontainer">';
        $field .= sprintf('<input type="text" %s
                                   class="wpnotif_countrycode"
                                   value="%s" maxlength="6" size="3"
                                   placeholder="%s" />', $countrycode_atts, $countrycode, $countrycode);
        $field .= '</span>';

        $field .= sprintf('<input %1$s />', $atts);

        $field .= '</span>';

        $field .= '</span>' . $validation_error;

        $field .= '</span>';
        do_action('wpnotif_load_frontend_scripts');

        return $field;
    }

    public function tag_generator($contact_form, $args = '')
    {
        $args = wp_parse_args($args, array());
        $type = self::$field_type;

        $description = __("For placeholders, see %s", 'wpnotif');

        $desc_link = wpcf7_link('https://help.unitedover.com/wpnotif/kb/how-to-send-sms-whatsapp-notifications-using-contact-form-7/', __('here', 'wpnotif'));

        ?>
        <div class="control-box">
            <fieldset>
                <legend><?php echo sprintf(esc_html($description), $desc_link); ?></legend>

                <table class="form-table">
                    <tbody>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo esc_attr($args['content'] . '-required'); ?>"><?php echo esc_html(__('Required Field')); ?></label>
                        </th>
                        <td>
                            <fieldset>
                                <input id="<?php echo esc_attr($args['content'] . '-required'); ?>" type="checkbox"
                                       name="required"/>
                            </fieldset>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label
                                    for="<?php echo esc_attr($args['content'] . '-name'); ?>"><?php echo esc_html(__('Name', 'contact-form-7')); ?></label>
                        </th>
                        <td><input type="text" name="name" class="tg-name oneline"
                                   id="<?php echo esc_attr($args['content'] . '-name'); ?>"/></td>
                    </tr>

                    <tr>
                        <th scope="row"><label
                                    for="<?php echo esc_attr($args['content'] . '-id'); ?>"><?php echo esc_html(__('Id attribute', 'contact-form-7')); ?></label>
                        </th>
                        <td><input type="text" name="id" class="idvalue oneline option"
                                   id="<?php echo esc_attr($args['content'] . '-id'); ?>"/></td>
                    </tr>

                    <tr>
                        <th scope="row"><label
                                    for="<?php echo esc_attr($args['content'] . '-class'); ?>"><?php echo esc_html(__('Class attribute', 'contact-form-7')); ?></label>
                        </th>
                        <td><input type="text" name="class" class="classvalue oneline option"
                                   id="<?php echo esc_attr($args['content'] . '-class'); ?>"/></td>
                    </tr>
                    </tbody>
                </table>
            </fieldset>
        </div>

        <div class="insert-box">
            <input type="text" name="<?php echo $type; ?>" class="tag code" readonly="readonly"
                   onfocus="this.select()"/>

            <div class="submitbox">
                <input type="button" class="button button-primary insert-tag"
                       value="<?php echo esc_attr(__('Insert Tag', 'contact-form-7')); ?>"/>
            </div>

            <br class="clear"/>

        </div>
        <?php
    }

}