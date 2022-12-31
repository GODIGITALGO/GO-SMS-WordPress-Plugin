<?php

if (!defined('ABSPATH')) {
    exit;
}

ignore_user_abort(true);

if (function_exists('fastcgi_finish_request') && version_compare(phpversion(), '7.0.16', '>=')) {
    if (!headers_sent()) {
        header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
        header('Cache-Control: no-cache, must-revalidate, max-age=0');
    }

    fastcgi_finish_request();
}

if (strpos(@ini_get('disable_functions'), 'set_time_limit') === false) {
    set_time_limit(0);
}

WPNotif_Background_Process::instance();

final class WPNotif_Background_Process
{
    protected static $_instance = null;

    public $last_time = 0;

    /** @var WPNotif_NewsLetter_Handler */
    public $handler = null;
    const LAST_EXECUTION_TIME = '_wpnotif_bg_last_execution_time';

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

    public function __construct()
    {
        $this->handler = WPNotif_NewsLetter_Handler::instance();
        $this->run();
    }

    public function check()
    {
        if ($this->handler->get_cron_type() != 'wpn_bg') {
            $this->stop();
        }
        $last_saved_time = get_option(self::LAST_EXECUTION_TIME, -1);
        $current_time = microtime(true);

        if ($last_saved_time != -1 && $this->last_time != $last_saved_time) {
            /*
             * if last execution time is less than 20 minutes then stop execution else continue
             * */
            if (($current_time - $last_saved_time) < 1200) {
                die();
            }
        }

        $this->last_time = $current_time;
        update_option(self::LAST_EXECUTION_TIME, $this->last_time);

    }

    public function run()
    {
        $start_time = time();

        $this->check();

        $this->handler->wpnotif_newsletter_bg_exec();


        $this->check();

        $wait_time = 60 - (time() - $start_time);

        $this->wait($wait_time);
    }

    public function wait($time)
    {

        $time = max($time, 5);

        if (empty($time)) {
            $this->stop();
        }

        sleep($time);
        $this->run();
    }


    public function stop()
    {
        delete_option(self::LAST_EXECUTION_TIME);
        die();
    }
}