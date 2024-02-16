<?php
/**
 * WP Custom Jobvite Class.
 */
if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/* Check if Class Exists. */
if (! class_exists('Jobvite')) {
    class Jobvite
    {
        public $name;
        public $prefix;
        public $title;
        public $slug;
        public const JOBVITE_URL       = 'https://app.jobvite.com/api/v2/job';
        public const JOBVITE_STAGE_URL = 'https://app-stg.jobvite.com/api/v2/job';

        public function __construct()
        {
            $this->name   = 'jobvite';
            $this->prefix = 'jv_';
            $this->title  = ucwords(str_replace('_', ' ', $this->name));
            $this->slug   = str_replace('_', '-', $this->name);

            add_action('admin_init', [$this, $this->prefix . 'trigger_feed_update'], 1);
            add_action('admin_menu', [$this, $this->prefix . 'add_admin_page']);
            add_action('admin_init', [$this, $this->prefix . 'add_custom_settings']);
            add_filter('query_vars', [$this, $this->prefix . 'add_custom_query_vars']);
        }

        /**
         * Add admin page.
         */
        public function jv_add_admin_page()
        {
            add_menu_page(
                $this->title,
                $this->title,
                'manage_options',
                $this->slug,
                [$this, $this->prefix . 'render_settings_page'],
                'dashicons-rest-api',
                30
            );
        }

        /**
         * Register Settings.
         */
        public function jv_add_custom_settings()
        {
            add_settings_section(
                $this->prefix . 'jobvite_settings',
                'API Settings',
                [$this, $this->prefix . 'jobvite_settings_callback'],
                $this->slug . '-api-settings'
            );
            register_setting(
                $this->prefix . 'jobvite_settings',
                $this->prefix . 'api_keys'
            );
            add_settings_section(
                $this->prefix . 'jobvite_feed_settings',
                'Jobvite Feed',
                [$this, $this->prefix . 'jobvite_feed_callback'],
                $this->slug . '-feed-settings'
            );
            register_setting(
                $this->prefix . 'jobvite_feed_settings',
                $this->prefix . 'job_feed'
            );
        }

        public function get_jobs()
        {
            return json_decode(get_option($this->prefix . 'job_feed'));
        }

        public function get_departments()
        {
            return json_decode(get_option($this->prefix . 'job_department'));
        }

        public function get_locations()
        {
            return json_decode(get_option($this->prefix . 'job_locations'));
        }

        private function fetch_job_prep($jobs)
        {
            $feeds = $locations = $categories = [];
            if (! empty($jobs)) {
                foreach ($jobs as $job) {
                    $feed     = $job;
                    $category = $job->category;
                    $location = $job->locationState;
                    if (! in_array($feed, $feeds)) {
                        $feeds[$feed->eId] = $feed;
                    }
                    if (! in_array($location, $locations)) {
                        $locations[] = $location;
                    }
                    if (! in_array($category, $categories)) {
                        $categories[] = $category;
                    }
                }
                update_option(
                    $this->prefix . 'job_feed',
                    json_encode($feeds)
                );
                update_option(
                    $this->prefix . 'job_department',
                    json_encode($categories)
                );
                update_option(
                    $this->prefix . 'job_locations',
                    json_encode($locations)
                );
            }
        }

        public function job_post($job_id = 0)
        {
            $jobs = json_decode(get_option($this->prefix . 'job_feed'));

            return (isset($jobs->{$job_id})) ? $jobs->{$job_id} : null;
        }

        /**
         * Cache Jobs Feed.
         */
        private function jv_cache_jobvite_feed()
        {
            file_put_contents(dirname(__FILE__) . '/complex_processing.txt', 1);
            $data = $this->get_api_response();
            $jobs = json_decode($data);
            if ($jobs->total > 0) {
                $this->fetch_job_prep($jobs->requisitions);
                $this->jv_redirect_with('success');
            } else {
                $this->jv_redirect_with('error');
            }
        }

        private function build_api_url()
        {
            $api_options = get_option($this->prefix . 'api_keys');

            return $url  =  (('live' == $api_options['api_type']) ? self::JOBVITE_URL : self::JOBVITE_STAGE_URL) .
                '?api=' . $api_options['api'] .
                '&sc=' . $api_options['secret'] .
                '&jobStatus=Open';
        }

        private function get_api_response()
        {
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_URL, $this->build_api_url());
            $result = curl_exec($curl);
            curl_close($curl);

            return $result;
        }

        public function jv_add_custom_query_vars($query_vars)
        {
            $query_vars[] = 'career_id';
            $query_vars[] = 'career_apply';

            return $query_vars;
        }

        public function jv_settings_callback()
        {
            settings_fields($this->prefix . 'jobvite_settings');
            $this->jv_api_settings_callback();
        }

        public function jv_api_settings_callback()
        {
            $api_options = get_option($this->prefix . 'api_keys');
            $api_type    = ! empty($api_options['api_type']) ? $api_options['api_type'] : 'staging';
            ?>

<label for="<?php echo $this->prefix; ?>api_keys[api]">API Key</label>
<input name="<?php echo $this->prefix; ?>api_keys[api]" type="text" value="<?php echo $api_options['api']; ?>" />
<br>
<label for="<?php echo $this->prefix; ?>api_keys[secret]">Secret Key</label>
<input name="<?php echo $this->prefix; ?>api_keys[secret]" type="password" value="<?php echo $api_options['secret']; ?>" />
<br>
<br>
<label>Live API:
    <input type="radio" name="<?php echo $this->prefix; ?>api_keys[api_type]" value="live" <?php checked($api_type, 'live'); ?>/>
</label>
<label>Test API:
    <input type="radio" name="<?php echo $this->prefix; ?>api_keys[api_type]" value="staging" <?php checked($api_type, 'staging'); ?>/>
</label>
<?php
        }

        private function jv_admin_notice_error()
        {
            ?>

<div class="settings-error notice notice-error">
    <p><strong>Error:</strong> There was an issue with the Jobvite API and saving your Jobvite data. Please
        try again.</p>
</div>

<?php
        }

        private function jv_admin_notice_success()
        {
            ?>

<div class="settings-error notice notice-success">
    <p><strong>Success:</strong> Jobvite feed has been updated successfully.</p>
</div>

<?php
        }

        private function jv_redirect_with($type)
        {
            wp_redirect(admin_url('/options-general.php?page=' . $this->slug . '&message-type=' . $type));
            exit;
        }

        public function jv_trigger_feed_update()
        {
            if (! empty($_REQUEST['cache-jobvite-feed'])) {
                $this->jv_cache_jobvite_feed();
            }
        }

        public function jv_cron_feed_update()
        {
            $this->jv_cache_jobvite_feed();
        }

        public function jv_render_settings_page()
        {
            ?>

<div class="wrap">

    <?php
                $message_type = (isset($_GET['message-type'])) ? $_GET['message-type'] : '';

            if (! empty($message_type)) {
                $message_method = $this->prefix . 'admin_notice_' . $message_type;
                $this->$message_method();
            }
            ?>

    <h2><?php echo esc_html(get_admin_page_title()); ?></h2>
    <form method="post" action="options.php">

        <?php
                do_settings_sections($this->slug . '-api-settings');
            submit_button('Save Jobvite Settings', 'primary', 'submit');
            ?>

    </form>
    <form method="post" action="options-general.php?page=<?php echo $this->slug; ?>">
        <?php
            do_settings_sections($this->slug . '-feed-settings');
            submit_button(
                'Update Jobvite Cache',
                'secondary',
                'cache-jobvite-feed',
                false
            );
            ?>
    </form>
</div>

<?php
        }
    }
}
?>