<?php
class Perfume_Quiz_Admin {
    private $plugin_name;
    private $version;
    private $settings_page;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        add_action('admin_menu', array($this, 'add_plugin_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    public function enqueue_admin_styles($hook) {
        if ($hook !== $this->settings_page) {
            return;
        }

        wp_enqueue_style(
            $this->plugin_name . '-admin',
            plugin_dir_url(__FILE__) . 'css/perfume-quiz-admin.css',
            array(),
            $this->version
        );
    }

    public function enqueue_admin_scripts($hook) {
        if ($hook !== $this->settings_page) {
            return;
        }

        wp_enqueue_script(
            $this->plugin_name . '-admin',
            plugin_dir_url(__FILE__) . 'js/perfume-quiz-admin.js',
            array('jquery'),
            $this->version,
            true
        );

        wp_localize_script(
            $this->plugin_name . '-admin',
            'perfumeQuizAdmin',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('perfume_quiz_admin'),
                'i18n' => array(
                    'saved' => 'تنظیمات با موفقیت ذخیره شد.',
                    'error' => 'خطا در ذخیره تنظیمات.'
                )
            )
        );
    }

    public function add_plugin_admin_menu() {
        $this->settings_page = add_menu_page(
            'تنظیمات کوئیز عطر',
            'کوئیز عطر',
            'manage_options',
            'perfume-quiz-settings',
            array($this, 'display_plugin_admin_page'),
            'dashicons-list-view',
            30
        );
    }

    public function register_settings() {
        register_setting('perfume_quiz_settings', 'perfume_quiz_options', array(
            'sanitize_callback' => array($this, 'sanitize_settings')
        ));

        // General Settings
        add_settings_section(
            'perfume_quiz_general',
            'تنظیمات عمومی',
            array($this, 'render_section_general'),
            'perfume_quiz_settings'
        );

        // Appearance Settings
        add_settings_section(
            'perfume_quiz_appearance',
            'تنظیمات ظاهری',
            array($this, 'render_section_appearance'),
            'perfume_quiz_settings'
        );

        // Questions Settings
        add_settings_section(
            'perfume_quiz_questions',
            'تنظیمات سوالات',
            array($this, 'render_section_questions'),
            'perfume_quiz_settings'
        );

        // Register fields
        $this->register_general_fields();
        $this->register_appearance_fields();
        $this->register_questions_fields();
    }

    private function register_general_fields() {
        add_settings_field(
            'quiz_title',
            'عنوان کوئیز',
            array($this, 'render_text_field'),
            'perfume_quiz_settings',
            'perfume_quiz_general',
            array('field' => 'quiz_title')
        );

        add_settings_field(
            'quiz_description',
            'توضیحات کوئیز',
            array($this, 'render_textarea_field'),
            'perfume_quiz_settings',
            'perfume_quiz_general',
            array('field' => 'quiz_description')
        );

        add_settings_field(
            'results_count',
            'تعداد نتایج',
            array($this, 'render_number_field'),
            'perfume_quiz_settings',
            'perfume_quiz_general',
            array('field' => 'results_count', 'min' => 1, 'max' => 50)
        );
    }

    private function register_appearance_fields() {
        add_settings_field(
            'primary_color',
            'رنگ اصلی',
            array($this, 'render_color_field'),
            'perfume_quiz_settings',
            'perfume_quiz_appearance',
            array('field' => 'primary_color')
        );

        add_settings_field(
            'secondary_color',
            'رنگ ثانویه',
            array($this, 'render_color_field'),
            'perfume_quiz_settings',
            'perfume_quiz_appearance',
            array('field' => 'secondary_color')
        );

        add_settings_field(
            'font_family',
            'فونت',
            array($this, 'render_select_field'),
            'perfume_quiz_settings',
            'perfume_quiz_appearance',
            array(
                'field' => 'font_family',
                'options' => array(
                    'IRANSans' => 'ایران سنس',
                    'Vazir' => 'وزیر',
                    'Yekan' => 'یکان'
                )
            )
        );
    }

    private function register_questions_fields() {
        add_settings_field(
            'questions_order',
            'ترتیب سوالات',
            array($this, 'render_questions_order'),
            'perfume_quiz_settings',
            'perfume_quiz_questions'
        );

        add_settings_field(
            'required_questions',
            'سوالات اجباری',
            array($this, 'render_required_questions'),
            'perfume_quiz_settings',
            'perfume_quiz_questions'
        );
    }

    public function sanitize_settings($input) {
        $sanitized = array();

        if (isset($input['quiz_title'])) {
            $sanitized['quiz_title'] = sanitize_text_field($input['quiz_title']);
        }

        if (isset($input['quiz_description'])) {
            $sanitized['quiz_description'] = wp_kses_post($input['quiz_description']);
        }

        if (isset($input['results_count'])) {
            $sanitized['results_count'] = absint($input['results_count']);
        }

        if (isset($input['primary_color'])) {
            $sanitized['primary_color'] = sanitize_hex_color($input['primary_color']);
        }

        if (isset($input['secondary_color'])) {
            $sanitized['secondary_color'] = sanitize_hex_color($input['secondary_color']);
        }

        if (isset($input['font_family'])) {
            $sanitized['font_family'] = sanitize_text_field($input['font_family']);
        }

        if (isset($input['questions_order'])) {
            $sanitized['questions_order'] = array_map('sanitize_text_field', $input['questions_order']);
        }

        if (isset($input['required_questions'])) {
            $sanitized['required_questions'] = array_map('sanitize_text_field', $input['required_questions']);
        }

        return $sanitized;
    }

    public function display_plugin_admin_page() {
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
        ?>
        <div class="wrap perfume-quiz-admin">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="perfume-quiz-admin-header">
                <nav class="nav-tab-wrapper">
                    <a href="?page=perfume-quiz-settings&tab=general" 
                       class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                        تنظیمات عمومی
                    </a>
                    <a href="?page=perfume-quiz-settings&tab=appearance" 
                       class="nav-tab <?php echo $active_tab === 'appearance' ? 'nav-tab-active' : ''; ?>">
                        تنظیمات ظاهری
                    </a>
                    <a href="?page=perfume-quiz-settings&tab=questions" 
                       class="nav-tab <?php echo $active_tab === 'questions' ? 'nav-tab-active' : ''; ?>">
                        تنظیمات سوالات
                    </a>
                </nav>
            </div>

            <div class="perfume-quiz-admin-content">
                <form method="post" action="options.php" class="perfume-quiz-form">
                    <?php
                    settings_fields('perfume_quiz_settings');
                    do_settings_sections('perfume_quiz_settings');
                    submit_button('ذخیره تنظیمات');
                    ?>
                </form>
            </div>
        </div>
        <?php
    }

    public function render_section_general() {
        echo '<p>تنظیمات عمومی کوئیز عطر را در این بخش انجام دهید.</p>';
    }

    public function render_section_appearance() {
        echo '<p>تنظیمات ظاهری و استایل کوئیز را در این بخش تنظیم کنید.</p>';
    }

    public function render_section_questions() {
        echo '<p>ترتیب و تنظیمات سوالات را در این بخش مدیریت کنید.</p>';
    }

    public function render_text_field($args) {
        $options = get_option('perfume_quiz_options');
        $value = isset($options[$args['field']]) ? $options[$args['field']] : '';
        ?>
        <input type="text" 
               class="regular-text" 
               name="perfume_quiz_options[<?php echo esc_attr($args['field']); ?>]" 
               value="<?php echo esc_attr($value); ?>"
        />
        <?php
    }

    public function render_textarea_field($args) {
        $options = get_option('perfume_quiz_options');
        $value = isset($options[$args['field']]) ? $options[$args['field']] : '';
        ?>
        <textarea class="large-text" 
                  rows="5" 
                  name="perfume_quiz_options[<?php echo esc_attr($args['field']); ?>]"
        ><?php echo esc_textarea($value); ?></textarea>
        <?php
    }

    public function render_number_field($args) {
        $options = get_option('perfume_quiz_options');
        $value = isset($options[$args['field']]) ? $options[$args['field']] : '';
        ?>
        <input type="number" 
               class="small-text" 
               name="perfume_quiz_options[<?php echo esc_attr($args['field']); ?>]" 
               value="<?php echo esc_attr($value); ?>"
               min="<?php echo esc_attr($args['min']); ?>" 
               max="<?php echo esc_attr($args['max']); ?>"
        />
        <?php
    }

    public function render_color_field($args) {
        $options = get_option('perfume_quiz_options');
        $value = isset($options[$args['field']]) ? $options[$args['field']] : '';
        ?>
        <input type="color" 
               class="color-picker" 
               name="perfume_quiz_options[<?php echo esc_attr($args['field']); ?>]" 
               value="<?php echo esc_attr($value); ?>"
        />
        <?php
    }

    public function render_select_field($args) {
        $options = get_option('perfume_quiz_options');
        $value = isset($options[$args['field']]) ? $options[$args['field']] : '';
        ?>
        <select name="perfume_quiz_options[<?php echo esc_attr($args['field']); ?>]">
            <?php foreach ($args['options'] as $key => $label) : ?>
                <option value="<?php echo esc_attr($key); ?>" 
                    <?php selected($value, $key); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    public function render_questions_order() {
        $options = get_option('perfume_quiz_options');
        $questions = array(
            'jensiyat' => 'جنسیت',
            'monasebe' => 'مناسب برای',
            'noe' => 'نوع رایحه',
            'tabe' => 'طبع',
            'product_cat' => 'دسته‌بندی محصول'
        );
        ?>
        <div class="questions-order-container">
            <ul id="sortable-questions" class="sortable-list">
                <?php 
                $order = isset($options['questions_order']) ? $options['questions_order'] : array_keys($questions);
                foreach ($order as $key) : 
                    if (isset($questions[$key])) :
                ?>
                    <li class="sortable-item" data-question="<?php echo esc_attr($key); ?>">
                        <span class="dashicons dashicons-menu"></span>
                        <span class="question-title"><?php echo esc_html($questions[$key]); ?></span>
                        <input type="hidden" 
                               name="perfume_quiz_options[questions_order][]" 
                               value="<?php echo esc_attr($key); ?>"
                        />
                    </li>
                <?php 
                    endif;
                endforeach; 
                ?>
            </ul>
        </div>
        <?php
    }

    public function render_required_questions() {
        $options = get_option('perfume_quiz_options');
        $required = isset($options['required_questions']) ? $options['required_questions'] : array();
        $questions = array(
            'jensiyat' => 'جنسیت',
            'monasebe' => 'مناسب برای',
            'noe' => 'نوع رایحه',
            'tabe' => 'طبع',
            'product_cat' => 'دسته‌بندی محصول'
        );
        ?>
        <div class="required-questions-container">
            <?php foreach ($questions as $key => $label) : ?>
                <label class="checkbox-label">
                    <input type="checkbox" 
                           name="perfume_quiz_options[required_questions][]" 
                           value="<?php echo esc_attr($key); ?>"
                           <?php checked(in_array($key, $required)); ?>
                    />
                    <?php echo esc_html($label); ?>
                </label>
            <?php endforeach; ?>
        </div>
        <?php
    }
} 