<?php


if (!defined('ABSPATH')) {
    exit;
}

WPNotif_NewsLetter_Handler::instance();

final class WPNotif_NewsLetter_Handler
{

    const CRON_INTERVAL = 10;
    const CRON_JOB = 'wpnotif_newsletter_exec';
    const TYPE = 'newsletter';
    protected static $_instance = null;
    const DATE_FORMAT = 'Y F j, g:i a';

    public function __construct()
    {
        $this->init_hooks();
    }

    private function init_hooks()
    {
        add_action('init', array($this, 'schedule'));
        add_action('wpnotif_activated', array($this, 'wpnotif_activated'));
        add_action('wpnotif_deactivated', array($this, 'wpnotif_deactivated'));
        add_filter('cron_schedules', array($this, 'add_interval'));

        add_action(self::CRON_JOB, array($this, 'wpnotif_newsletter_wp_exec'));

    }

    /**
     *  Constructor.
     */
    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function wpnotif_activated()
    {
        do_action('wpnotif_create_db');
        $this->schedule();
    }

    public function schedule()
    {
        if ($this->get_cron_type() == 'wp_cron') {
            if (!wp_next_scheduled(self::CRON_JOB)) {
                wp_schedule_event(time(), 'every_minute', self::CRON_JOB);
            }
        }
    }

    public function wpnotif_deactivated()
    {
        $timestamp = wp_next_scheduled(self::CRON_JOB);
        wp_unschedule_event($timestamp, self::CRON_JOB);
    }

    public static function wpn_background_process()
    {
        $handler = self::instance();
        if ($handler->get_cron_type() == 'wpn_bg') {
            require_once(plugin_dir_path(__FILE__) . 'background.php');
        }
    }

    public static function run_wpn_cron()
    {
        $handler = self::instance();
        if ($handler->get_cron_type() == 'wpn_cron') {
            $handler->wpnotif_newsletter_exec();
        } else {
            die();
        }
    }

    public function add_interval($schedules)
    {
        if ($this->get_cron_type() == 'wp_cron') {
            $schedules['every_minute'] = array(
                'interval' => self::CRON_INTERVAL,
                'display' => 'Every ' . self::CRON_INTERVAL . ' cron job for WPNotif Newsletter',
            );
        }
        return $schedules;
    }

    public function wpnotif_newsletter_wp_exec()
    {
        if ($this->get_cron_type() == 'wp_cron') {
            $this->wpnotif_newsletter_exec();
        }
    }

    public function wpnotif_newsletter_bg_exec()
    {
        if ($this->get_cron_type() == 'wpn_bg') {
            $this->wpnotif_newsletter_exec();
        }
    }


    public function get_cron_type()
    {
        $settings = $this->newsletter_settings();
        return $settings['cron_method'];

    }

    public function newsletter_settings()
    {
        return get_option('wpnotif_newsletter_settings', array('sending_frequency' => 5, 'cron_method' => 'wp_cron'));
    }


    public function check_newsletter_status()
    {
        $this->wpnotif_newsletter_exec(true);
    }

    public function check_newsletter_repeat($newsletter)
    {
        if (!empty($newsletter) && $newsletter->repeat_newsletter == 1) {
            $repeat_time = $newsletter->repeat_time;
            $repeat = explode("-", $repeat_time);
            $repeat_seconds = $repeat[0] * $this->convert_to_seconds($repeat[1]);

            if ($repeat_seconds > 0) {
                $data = array();
                $data['execution_time'] = $newsletter->execution_time + $repeat_seconds;
                $data['rid'] = 0;
                $data['status'] = WPNotif_NewsLetter::pending_status;
                WPNotif_NewsLetter::instance()->update_newsletter_by_id($newsletter->id, $data);
            }
        }
    }

    /*
     * day, week, month, year to seconds
     * */
    public function convert_to_seconds($type)
    {
        $day = 86400;
        switch ($type) {
            case 'week':
                return $day * 7;
            case 'month':
                return $day * 30;
            case 'year':
                return $day * 365;
            default:
                return $day;
        }
    }

    public function wpnotif_newsletter_exec($check = false)
    {

        $settings = $this->newsletter_settings();
        $frequency = max($settings['sending_frequency'], 0);

        if ($frequency <= 0 && !$check) return;


        if (!$check) {
            $is_newsletter_running = get_transient('wpnotif_newsletter_running');
            if (!empty($is_newsletter_running) && $is_newsletter_running == 1) {
                return;
            }
            set_transient('wpnotif_newsletter_running', 1, 60);
        }


        $handler = WPNotif_Handler::instance();


        $newsletter = WPNotif_NewsLetter::instance();
        $usergroup = WPNotif_UserGroups::instance();

        $active_newsletter = $newsletter->get_active_newsletter();

        if (empty($active_newsletter)) {

            $check_newsletter = $this->check_scheduled_newspaper();

            if (!empty($check_newsletter->status) && $check_newsletter->status == WPNotif_NewsLetter::running_status) {
                $this->wpnotif_newsletter_exec($check);
            } else {
                return;
            }
        }

        if (empty($active_newsletter)) {
            return;
        }


        $nid = $active_newsletter->id;
        $running_id = $active_newsletter->rid;
        $newsletter_msg = $this->parse_message($active_newsletter->message, $active_newsletter);
        $route = $active_newsletter->route;

        if ($route != 1) {
            $route = 1001;
        }

        $user_groups = explode(',', $active_newsletter->user_group_role);

        $running_instance = $newsletter->get_running_instance(array('id' => $running_id));


        if (empty($user_groups) || empty($newsletter_msg) || empty($running_instance)) {
            $newsletter->stop_all_newsletter();
            return;
        }


        $completed_group = $running_instance->completed_group;

        $send_no = max(0, $running_instance->sno);

        $completed_group = !empty($completed_group) ? explode(',', $completed_group) : array();


        $current_group = $running_instance->current_group;
        $offset = 0;
        if (!empty($current_group)) {
            $current_group = explode('-', $current_group, 2);
            $offset = $current_group[0];
            $current_group = $current_group[1];
        }

        $rem_group = array_diff($user_groups, $completed_group);

        if (empty($current_group) || !in_array($current_group, $user_groups)) {
            $current_group = reset($rem_group);
        }

        $users = array();

        if (!empty($current_group)) {


            $mobile_field = array('type' => $active_newsletter->mobile_field_type,
                'key' => $active_newsletter->mobile_field_key);

            $args = array();


            $args['order'] = array('column' => 'id', 'dir' => 'ASC');

            $args['id_greater_than'] = $offset;
            $args['only_limit'] = $frequency;
            $type = '';
            if ($this->starts_with('ug', $current_group)) {
                $gid = str_replace('ug_', '', $current_group);
                $users = $usergroup->get_group_users($gid, $args);
                $type = 'ug';
            } else {
                $user_role = str_replace('ur_', '', $current_group);
                $users = $this->get_users_by_userrole($frequency, $offset, $user_role);
                $type = 'ur';
            }

        }


        if (empty($users) || empty($current_group)) {

            if (!empty($current_group)) {
                $completed_group[] = $current_group;
                unset($rem_group[$current_group]);
            }
            $data = array();
            $data['current_group'] = '';
            $data['completed_group'] = implode(",", $completed_group);

            $completed = false;
            if (empty($rem_group)) {
                $data['end_time'] = date("Y-m-d H:i:s", time());

                $data['status'] = WPNotif_NewsLetter::completed_status;
                $completed = true;

                $newsletter->update_newsletter_status($nid, $data['status']);
            }

            $newsletter->update_running_instance_by_id($running_id, $data);

            if ($completed) {
                $this->check_newsletter_repeat($active_newsletter);
            }

            return;
        }
        if ($check) {
            return;
        }

        $running_data = array();
        $i = 0;


        $sms_details = WPNotif::data_type(self::TYPE, array('nid' => $nid));
        foreach ($users as $user) {

            $i++;
            $send_no++;

            $phone = $this->get_phone($mobile_field, $type, $user);


            $phone_obj = $this->parse_phone($phone);

            $countrycode = $phone_obj['countrycode'];
            $mobile = $phone_obj['mobile'];
            $phone = $countrycode . $mobile;

            $history = array();
            $history['rid'] = $running_id;

            $history['wp_id'] = $user->wp_id;

            $history['phone'] = $phone;

            $history['sno'] = $send_no;

            $history['route'] = $route;


            $running_data['sno'] = $send_no;
            $running_data['current_group'] = $user->id . '-' . $current_group;


            if (!$newsletter->get_running_instance_phone_history($history) && !empty($phone)) {
                if ($phone_obj) {
                    $history['type'] = $current_group;
                    $history['status'] = 'success';


                    $data = array();
                    $data['newsletter_user'] = $user;

                    $notification_data = WPNotif::data_type(self::TYPE, $data);

                    $user_id = 0;
                    $wp_user = false;
                    if (!empty($user->wp_id)) {
                        $user_id = $user->wp_id;
                        $wp_user = true;
                    } else {
                        $user_id = $user->ID;
                    }

                    $message = $this->update_user_placeholders($newsletter_msg, $user);

                    if ($wp_user) {
                        $message = $handler->parse_message($message, $notification_data, $user_id);
                    }
                    $message = trim($message);

                    $handler->send_notification($countrycode, $mobile, $message, $route, $sms_details);
                } else {
                    $history['status'] = 'invalid-phone';
                }

            } else if (empty($phone)) {
                $history['status'] = 'phone-not-found';
            } else {
                $history['status'] = 'duplicate';
            }

            $newsletter->add_running_history($history);

            $newsletter->update_running_instance_by_id($running_id, $running_data);

            $newsletter->update_newsletter_by_id($nid, array('progress' => $running_data['sno']));

        }

        delete_transient('wpnotif_newsletter_running');
    }

    public function update_user_placeholders($message, $user)
    {
        $placeholder_values = array(
            '{{first_name}}' => $user->first_name,
            '{{last_name}}' => $user->last_name,
            '{{wp-first_name}}' => $user->first_name,
            '{{wp-last_name}}' => $user->last_name
        );
        return strtr($message, $placeholder_values);
    }

    public static function starts_with($query, $str)
    {
        return strpos($str, $query) === 0;
    }

    public function get_phone($mobile_field, $type, $obj)
    {
        if ($type == 'ug') {
            $phone = $obj->phone;
        } else if ($type == 'ur') {
            if ($mobile_field['type'] == '1') {
                $phone = WPNotif_Handler::get_user_phone($obj, true);
            } else {
                $phone = get_user_meta($obj, $mobile_field['key'], true);
            }
        }

        if ((empty($phone) || $phone == '+') && !empty($obj)) {
            $phone = WPNotif_Handler::get_user_phone($obj, true);
        }
        if (!$this->starts_with('+', $phone)) {
            $phone = '+' . $phone;
        }

        return $phone;
    }

    public function parse_phone($phone)
    {
        return WPNotif_Handler::parseMobile($phone);
    }

    public function check_scheduled_newspaper()
    {
        $newsletter_instance = WPNotif_NewsLetter::instance();
        $newsletter = $newsletter_instance->get_scheduled_newspaper();

        if (!empty($newsletter)) {
            $run_newsletter = $newsletter_instance->run_newsletter($newsletter->id, true, false);
            if (is_wp_error($run_newsletter) || !$run_newsletter) {
                return false;
            }
            $newsletter->status = WPNotif_NewsLetter::running_status;
        }
        return $newsletter;
    }

    public function get_users_by_userrole($current_id, $number, $role)
    {
        $user_ids = array();
        $users = get_users(
            array(
                'fields' => array('ID'),
                'role__in' => $role,
                'orderby' => 'ID',
                'order' => 'ASC',
                'offset' => $current_id,
                'number' => $number
            )
        );
        foreach ($users as $user) {
            $user_ids[] = $user->ID;
        }
        return $user_ids;
    }

    private function parse_message($message, $active_newsletter)
    {
        if (!empty($active_newsletter->post_id)) {
            $post = get_post($active_newsletter->post_id);
            if (!empty($post)) {

                preg_match_all('/{{([^#]+?)}}/', $message, $placeholders);
                if (is_array($placeholders) && isset($placeholders[1])) {

                    foreach ($placeholders[1] as $placeholder) {
                        $value = '';
                        if (strpos($placeholder, 'post-') === 0) {
                            $meta_key = WPNotif_Handler::str_replace_once('post-', '', $placeholder);

                            if ($meta_key == 'title') {
                                $value = $post->post_title;
                            } else if ($meta_key == 'content') {
                                $value = $this->trim_if_long($post->post_content);
                            } else if ($meta_key == 'link') {
                                $value = get_permalink($post);
                            } else if ($meta_key == 'excerpt') {
                                $value = $this->trim_if_long(get_the_excerpt($post));
                            } else if ($meta_key == 'author') {
                                $post_author = $post->post_author;
                                if (!empty($post_author)) {
                                    $author = get_user_by('ID', $post_author);
                                    $value = $author->display_name;
                                }

                            } else if ($meta_key == 'date') {
                                $value = get_the_date(self::DATE_FORMAT, $post->post_date);
                            } else if ($meta_key == 'category') {
                                $value = strip_tags(get_the_category_list(',', '', $post->ID));
                            } else if ($meta_key == 'tags') {
                                $value = strip_tags(get_the_tag_list(',', '', $post->ID));
                            } else {
                                $value = get_post_meta($post->ID, $meta_key, true);
                            }
                            if (!$value) {
                                $value = '';
                            }

                            $message = str_replace('{{' . $placeholder . '}}', $value, $message);
                        }
                    }

                }
            }
        }
        return $message;
    }

    public function trim_if_long($string)
    {
        return mb_strimwidth($string, 0, 100, "...");
    }

}