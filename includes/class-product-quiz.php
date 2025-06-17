<?php
if (!defined('ABSPATH')) {
    exit;
}

class Perfume_Quiz {
    private $quiz_settings;
    private $settings_instance;

    public function __construct() {
        add_action('init', array($this, 'init'));
        add_shortcode('perfume_quiz', array($this, 'render_quiz'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public function init() {
        // Get settings instance
        $this->settings_instance = Perfume_Quiz_Settings::get_instance();
        // Refresh settings
        $this->quiz_settings = $this->settings_instance->get_settings();
        
        // Add logging for initialization
        error_log('Perfume Quiz initialized with settings: ' . print_r($this->quiz_settings, true));
    }

    public function enqueue_assets() {
        // Enqueue styles
        wp_enqueue_style(
            'perfume-quiz-style',
            PERFUME_QUIZ_PLUGIN_URL . 'assets/css/quiz.css',
            array(),
            PERFUME_QUIZ_VERSION
        );

        // Enqueue scripts
        wp_enqueue_script(
            'perfume-quiz-script',
            PERFUME_QUIZ_PLUGIN_URL . 'assets/js/quiz.js',
            array('jquery'),
            PERFUME_QUIZ_VERSION,
            true
        );

        // Get quiz questions
        $questions_data = $this->get_quiz_questions();

        // Add placeholder image to the quiz data
        $placeholder_image = wc_placeholder_img_src('medium');

        // Localize script with quiz data
        wp_localize_script('perfume-quiz-script', 'perfumeQuizData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('perfume_quiz_nonce'),
            'questions' => $questions_data['questions'] ?? array(),
            'error' => $questions_data['error'] ?? false,
            'message' => $questions_data['message'] ?? '',
            'placeholder_image' => $placeholder_image,
            'i18n' => array(
                'loading' => __('در حال بارگذاری...', 'perfume-quiz'),
                'error' => __('خطایی رخ داده است. لطفاً دوباره تلاش کنید.', 'perfume-quiz'),
                'no_results' => __('هیچ محصولی مطابق با ترجیحات شما یافت نشد.', 'perfume-quiz'),
                'view_product' => __('مشاهده محصول', 'perfume-quiz'),
                'select_option' => __('لطفاً یک گزینه را انتخاب کنید.', 'perfume-quiz'),
            )
        ));

        error_log('Quiz data localized with ' . 
                  (isset($questions_data['questions']) ? count($questions_data['questions']) : 0) . 
                  ' questions. Error status: ' . 
                  (isset($questions_data['error']) && $questions_data['error'] ? 'Yes' : 'No'));
    }

    public function render_quiz($atts) {
        // Debug log
        error_log('Rendering Perfume Quiz');

        // Start output buffering
        ob_start();

        // Include the quiz template
        include PERFUME_QUIZ_PLUGIN_DIR . 'templates/quiz-form.php';

        // Get the buffered content
        $content = ob_get_clean();

        // Debug log
        error_log('Quiz content: ' . $content);

        // Return the content
        return $content;
    }

    public function get_quiz_questions() {
        // Get enabled attributes from settings class
        $enabled_attributes = Perfume_Quiz_Settings::get_enabled_attributes();
        
        if (empty($enabled_attributes)) {
            error_log('No enabled attributes found in quiz settings.');
            return array(
                'error' => true,
                'message' => 'لطفاً ابتدا ویژگی‌های محصول را در تنظیمات کوئیز فعال کنید.'
            );
        }

        $questions = array();
        
        // Add product category question first
        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => true
        ));

        if (!is_wp_error($categories) && !empty($categories)) {
            $category_options = array();
            foreach ($categories as $category) {
                $category_options[] = array(
                    'value' => $category->slug,
                    'label' => $category->name
                );
            }

            $questions[] = array(
                'id' => 'product_cat',
                'type' => 'radio',
                'required' => true,
                'question' => 'دسته‌بندی محصول',
                'options' => $category_options
            );
        }

        // Add attribute questions
        foreach ($enabled_attributes as $attr_key => $attr) {
            $taxonomy = 'pa_' . $attr_key;
            
            // Verify taxonomy exists
            if (!taxonomy_exists($taxonomy)) {
                error_log("Taxonomy {$taxonomy} does not exist");
                continue;
            }

            // Get terms for this attribute
            $terms = get_terms(array(
                'taxonomy' => $taxonomy,
                'hide_empty' => false
            ));

            if (is_wp_error($terms) || empty($terms)) {
                error_log("No terms found for taxonomy {$taxonomy}");
                continue;
            }

            $options = array();
            foreach ($terms as $term) {
                $options[] = array(
                    'value' => $term->slug,
                    'label' => $term->name
                );
            }

            if (!empty($options)) {
                // Use the attribute key without the 'pa_' prefix to match JavaScript taxonomy map
                $questions[] = array(
                    'id' => $attr_key,
                    'type' => $attr['multiple'] ? 'checkbox' : 'radio',
                    'required' => $attr['required'],
                    'question' => $attr['label'],
                    'options' => $options
                );
            }
        }

        if (empty($questions)) {
            return array(
                'error' => true,
                'message' => 'هیچ ویژگی فعالی برای نمایش در کوئیز وجود ندارد.'
            );
        }

        return array(
            'error' => false,
            'questions' => $questions
        );
    }

    public function get_recommended_products($answers) {
        // Log incoming answers
        error_log('Getting recommended products for answers: ' . print_r($answers, true));

        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        );

        $tax_query = array('relation' => 'AND');

        // Add category filter if selected
        if (!empty($answers['product_cat'])) {
            $tax_query[] = array(
                'taxonomy' => 'product_cat',
                'field' => 'slug',
                'terms' => $answers['product_cat'],
                'operator' => 'IN'
            );
        }

        // Add attribute filters
        foreach ($answers as $key => $values) {
            if ($key === 'product_cat') continue;

            if (!empty($values)) {
                $taxonomy = 'pa_' . $key;
                if (taxonomy_exists($taxonomy)) {
                    $tax_query[] = array(
                        'taxonomy' => $taxonomy,
                        'field' => 'slug',
                        'terms' => (array)$values,
                        'operator' => 'IN'
                    );
                    error_log("Added tax query for {$taxonomy}: " . print_r($values, true));
                } else {
                    error_log("Warning: Taxonomy {$taxonomy} does not exist");
                }
            }
        }

        if (count($tax_query) > 1) {
            $args['tax_query'] = $tax_query;
        }

        // Log query arguments
        error_log('Product query args: ' . print_r($args, true));

        // Get products
        $query = new WP_Query($args);
        $products = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $product = wc_get_product(get_the_ID());
                if ($product) {
                    $products[] = array(
                        'id' => $product->get_id(),
                        'title' => $product->get_name(),
                        'price' => $product->get_price_html(),
                        'url' => get_permalink($product->get_id()),
                        'image' => get_the_post_thumbnail_url($product->get_id(), 'woocommerce_thumbnail') ?: wc_placeholder_img_src('medium'),
                        'attributes' => $this->get_product_attributes($product)
                    );
                }
            }
            wp_reset_postdata();
        }

        // Log results
        error_log('Found ' . count($products) . ' products');

        return $products;
    }

    private function get_product_attributes($product) {
        $attributes = array();
        foreach ($product->get_attributes() as $attribute) {
            if ($attribute->get_visible()) {
                $name = wc_attribute_label($attribute->get_name());
                $values = array();
                
                if ($attribute->is_taxonomy()) {
                    $terms = wp_get_post_terms(
                        $product->get_id(),
                        $attribute->get_name(),
                        array('fields' => 'names')
                    );
                    if (!is_wp_error($terms)) {
                        $values = $terms;
                    }
                } else {
                    $values = $attribute->get_options();
                }
                
                if (!empty($values)) {
                    $attributes[$name] = is_array($values) ? implode(', ', $values) : $values;
                }
            }
        }
        return $attributes;
    }
} 