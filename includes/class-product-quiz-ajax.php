<?php
if (!defined('ABSPATH')) {
    exit;
}

class Perfume_Quiz_AJAX {
    private $taxonomy_map = array(
        'gender' => 'pa_gender',
        'suitable' => 'pa_suitable',
        'type' => 'pa_type',
        'nature' => 'pa_nature',
        'product_cat' => 'product_cat'
    );
    
    // Also map the keys with 'pa_' prefix to ensure we catch both formats
    private $reverse_map = array();

    private $default_messages = array(
        'no_products' => 'متأسفانه محصولی با این ویژگی‌ها پیدا نشد.',
        'error' => 'خطا در پردازش درخواست',
        'success' => 'تعداد %d محصول مطابق با سلیقه شخصی شما یافت شد:',
        'default_products' => 'با توجه به سلیقه شما، این محصولات انتخابی مخصوص شما هستند:'
    );

    private $stock_status_labels = array(
        'instock' => 'موجود در انبار',
        'outofstock' => 'ناموجود',
        'onbackorder' => 'قابل پیش‌سفارش'
    );

    public function __construct() {
        // Initialize reverse mapping for taxonomy lookups
        foreach ($this->taxonomy_map as $key => $taxonomy) {
            $this->reverse_map[$taxonomy] = $key;
            
            // If we have a 'pa_' prefix, also map it without the prefix
            if (strpos($taxonomy, 'pa_') === 0) {
                $this->reverse_map[substr($taxonomy, 3)] = $key;
            }
        }
        
        // Log the taxonomy mappings for debugging
        error_log('Perfume Quiz AJAX taxonomy mappings: ' . print_r($this->taxonomy_map, true));
        error_log('Perfume Quiz AJAX reverse mappings: ' . print_r($this->reverse_map, true));
        
        // Register AJAX actions for both logged-in and non-logged-in users
        add_action('wp_ajax_perfume_quiz_submit', array($this, 'handle_quiz_submission'));
        add_action('wp_ajax_nopriv_perfume_quiz_submit', array($this, 'handle_quiz_submission'));
        
        // Add debug logging for AJAX action registration
        add_action('init', array($this, 'debug_ajax_registration'));
    }

    public function debug_ajax_registration() {
        error_log('AJAX Actions Registered:');
        error_log('- wp_ajax_perfume_quiz_submit: ' . (has_action('wp_ajax_perfume_quiz_submit') ? 'Yes' : 'No'));
        error_log('- wp_ajax_nopriv_perfume_quiz_submit: ' . (has_action('wp_ajax_nopriv_perfume_quiz_submit') ? 'Yes' : 'No'));
    }

    public function handle_quiz_submission() {
        try {
            if (!$this->validate_submission()) {
                return;
            }

            $answers = $this->sanitize_answers($_POST['answers']);
            $tax_query = $this->build_tax_query($answers);
            $products = $this->get_matching_products($tax_query);
            $available_attributes = $this->get_available_attributes($products);

            if (empty($products)) {
                $default_products = $this->get_default_products($answers);
                $personalized_message = $this->generate_personalized_message($answers, true);
                
                wp_send_json_success(array(
                    'products' => $default_products,
                    'message' => $personalized_message,
                    'available_attributes' => $available_attributes
                ));
                return;
            }

            $personalized_message = $this->generate_personalized_message($answers, false, count($products));
            
            wp_send_json_success(array(
                'products' => $products,
                'message' => $personalized_message,
                'available_attributes' => $available_attributes
            ));

        } catch (Exception $e) {
            error_log('Error in handle_quiz_submission: ' . $e->getMessage());
            wp_send_json_error(array('message' => $this->default_messages['error']));
        }
    }

    private function validate_submission() {
        if (!isset($_POST['answers']) || !is_array($_POST['answers'])) {
            wp_send_json_error(array('message' => 'پاسخ‌های نامعتبر'));
            return false;
        }
        return true;
    }

    private function sanitize_answers($raw_answers) {
        $answers = array();
        foreach ($raw_answers as $key => $values) {
            if (!empty($values)) {
                $answers[sanitize_text_field($key)] = array_map('sanitize_text_field', (array)$values);
            }
        }
        return $answers;
    }

    private function build_tax_query($answers) {
        $tax_query = array('relation' => 'AND');
        error_log('Building tax query from answers: ' . print_r($answers, true));

        foreach ($answers as $field => $values) {
            if (empty($values)) continue;
            
            // Try to map the field to a taxonomy
            $taxonomy = isset($this->taxonomy_map[$field]) ? $this->taxonomy_map[$field] : null;
            
            // If no mapping found, check if it's a WooCommerce attribute
            if (!$taxonomy && taxonomy_exists('pa_' . $field)) {
                $taxonomy = 'pa_' . $field;
                error_log("Found taxonomy {$taxonomy} for field {$field}");
            } else if (!$taxonomy) {
                error_log("Warning: No taxonomy mapping for field {$field}");
                continue;
            }
            
            // Validate that the taxonomy exists
            if (!taxonomy_exists($taxonomy)) {
                error_log("Warning: Taxonomy {$taxonomy} does not exist");
                continue;
            }
            
            $tax_query[] = array(
                'taxonomy' => $taxonomy,
                'field' => 'slug',
                'terms' => $values,
                'operator' => 'IN'
            );
            
            error_log("Added tax query for {$taxonomy}: " . print_r($values, true));
        }
        
        error_log('Final tax query: ' . print_r($tax_query, true));
        return $tax_query;
    }

    private function get_matching_products($tax_query) {
        $query = new WP_Query(array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'tax_query' => $tax_query
        ));

        $products = array();
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $product = wc_get_product(get_the_ID());
                if ($product && $product->is_visible()) {
                    $products[] = $this->prepare_product_data($product);
                }
            }
            wp_reset_postdata();
        }

        return $products;
    }

    private function get_default_products($answers = array()) {
        // Start with basic query
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => 10,
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        // Try to use some of the user's preferences for more relevant suggestions
        if (!empty($answers)) {
            $tax_query = array('relation' => 'OR');
            $has_tax_query = false;
            
            // Try to match at least the category
            if (!empty($answers['product_cat'])) {
                $tax_query[] = array(
                    'taxonomy' => 'product_cat',
                    'field' => 'slug',
                    'terms' => $answers['product_cat'],
                    'operator' => 'IN'
                );
                $has_tax_query = true;
            }
            
            // Try to match at least one attribute
            foreach ($answers as $field => $values) {
                if ($field === 'product_cat') {
                    continue;
                }
                
                if (!empty($values)) {
                    $taxonomy = 'pa_' . $field;
                    if (taxonomy_exists($taxonomy)) {
                        $tax_query[] = array(
                            'taxonomy' => $taxonomy,
                            'field' => 'slug',
                            'terms' => $values,
                            'operator' => 'IN'
                        );
                        $has_tax_query = true;
                    }
                }
            }
            
            if ($has_tax_query) {
                $args['tax_query'] = $tax_query;
            }
        }
        
        $query = new WP_Query($args);
        
        $products = array();
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $product = wc_get_product(get_the_ID());
                if ($product && $product->is_visible()) {
                    $products[] = $this->prepare_product_data($product);
                }
            }
            wp_reset_postdata();
        }
        
        // If we still don't have products, get completely default ones
        if (empty($products)) {
            $query = new WP_Query(array(
                'post_type' => 'product',
                'post_status' => 'publish',
                'posts_per_page' => 10,
                'orderby' => 'date',
                'order' => 'DESC'
            ));
            
            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $product = wc_get_product(get_the_ID());
                    if ($product && $product->is_visible()) {
                        $products[] = $this->prepare_product_data($product);
                    }
                }
                wp_reset_postdata();
            }
        }

        return $products;
    }

    private function get_available_attributes($products) {
        $available_attributes = array();
        foreach ($products as $product) {
            $product_obj = wc_get_product($product['id']);
            if (!$product_obj) continue;

            $attributes = $product_obj->get_attributes();
            foreach ($attributes as $attribute) {
                if ($attribute->is_taxonomy()) {
                    $taxonomy = $attribute->get_name();
                    $terms = wc_get_product_terms($product['id'], $taxonomy, array('fields' => 'slugs'));
                    if (!isset($available_attributes[$taxonomy])) {
                        $available_attributes[$taxonomy] = array();
                    }
                    $available_attributes[$taxonomy] = array_merge(
                        $available_attributes[$taxonomy],
                        $terms
                    );
                }
            }
        }

        // Remove duplicates
        foreach ($available_attributes as &$terms) {
            $terms = array_unique($terms);
        }

        return $available_attributes;
    }

    private function prepare_product_data($product) {
        try {
            if (!$product || !($product instanceof WC_Product)) {
                error_log('Error: Invalid product object provided to prepare_product_data');
                return null;
            }

            $product_id = $product->get_id();
            if (empty($product_id)) {
                error_log('Error: Product ID is empty');
                return null;
            }

            $product_url = get_permalink($product_id);
            
            error_log("Preparing product data for ID: {$product_id}");

            if (empty($product_url)) {
                error_log("Warning: Empty product URL for ID: {$product_id}");
                // Create a fallback URL
                $product_url = home_url("?post_type=product&p={$product_id}");
                error_log("Generated fallback URL: {$product_url}");
            }

            $image_id = $product->get_image_id();
            $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : null;
            
            if (empty($image_url)) {
                error_log("Warning: No image found for product ID: {$product_id}");
                $image_url = wc_placeholder_img_src('medium');
            }

            $data = array(
                'id' => $product_id,
                'title' => $product->get_name(),
                'price' => $product->get_price_html(),
                'url' => $product_url,
                'image' => $image_url,
                'attributes' => $this->get_product_attributes($product),
                'stock_status' => $this->get_stock_status($product),
                'sale_info' => $this->get_sale_info($product)
            );

            return $data;
        } catch (Exception $e) {
            error_log("Error preparing product data: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return null;
        }
    }

    private function get_product_attributes($product) {
        $attributes = array();
        $product_attributes = $product->get_attributes();

        foreach ($product_attributes as $attribute) {
            if ($attribute->is_taxonomy()) {
                $attribute_taxonomy = $attribute->get_taxonomy_object();
                $attribute_values = wc_get_product_terms(
                    $product->get_id(), 
                    $attribute->get_name(), 
                    array('fields' => 'names')
                );
                if (!empty($attribute_values)) {
                    $attributes[$attribute_taxonomy->attribute_label] = implode(', ', $attribute_values);
                }
            }
        }

        return $attributes;
    }

    private function get_stock_status($product) {
        $status = $product->get_stock_status();
        return array(
            'status' => $status,
            'label' => isset($this->stock_status_labels[$status]) ? 
                      $this->stock_status_labels[$status] : ''
        );
    }

    private function get_sale_info($product) {
        if (!$product->is_on_sale()) {
            return null;
        }

        $sale_info = array(
            'is_on_sale' => true,
            'badge' => 'پیشنهاد ویژه!'
        );

        $regular_price = (float) $product->get_regular_price();
        $sale_price = (float) $product->get_sale_price();
        
        if ($regular_price > 0) {
            $percentage = round((($regular_price - $sale_price) / $regular_price) * 100);
            $sale_info['badge'] = sprintf('%d٪ تخفیف!', $percentage);
            $sale_info['percentage'] = $percentage;
        }

        return $sale_info;
    }

    /**
     * Generate a personalized message based on user selections
     */
    private function generate_personalized_message($answers, $is_default = false, $product_count = 0) {
        // Get user selections as readable text
        $selections = $this->get_selected_attribute_values($answers);
        
        if (empty($selections)) {
            if ($is_default) {
                return $this->default_messages['default_products'];
            } else {
                return sprintf($this->default_messages['success'], $product_count);
            }
        }
        
        $category = '';
        if (!empty($answers['product_cat'])) {
            $term = get_term_by('slug', $answers['product_cat'][0], 'product_cat');
            if ($term) {
                $category = $term->name;
            }
        }
        
        // Create personalized message
        if ($is_default) {
            if (!empty($category)) {
                $message = sprintf('با توجه به علاقه شما به %s، این محصولات منتخب را به شما پیشنهاد می‌کنیم:', $category);
            } else if (count($selections) > 0) {
                $selections_text = implode(' و ', $selections);
                $message = sprintf('با توجه به علاقه شما به %s، این محصولات منتخب را به شما پیشنهاد می‌کنیم:', $selections_text);
            } else {
                $message = 'این محصولات منتخب مخصوص شما هستند:';
            }
        } else {
            if (!empty($category) && count($selections) > 0) {
                $selections_text = implode(' و ', $selections);
                $message = sprintf('محصولات منتخب %s با ویژگی‌های %s برای شما:', $category, $selections_text);
            } else if (!empty($category)) {
                $message = sprintf('محصولات منتخب %s برای شما:', $category);
            } else if (count($selections) > 0) {
                $selections_text = implode(' و ', $selections);
                $message = sprintf('محصولات منتخب با ویژگی‌های %s برای شما:', $selections_text);
            } else {
                $message = 'محصولات منتخب مخصوص شما:';
            }
        }
        
        return $message;
    }
    
    /**
     * Get readable names of selected attributes
     */
    private function get_selected_attribute_values($answers) {
        $selections = array();
        
        foreach ($answers as $field => $values) {
            if ($field === 'product_cat' || empty($values)) {
                continue;
            }
            
            $taxonomy = 'pa_' . $field;
            if (!taxonomy_exists($taxonomy)) {
                continue;
            }
            
            foreach ($values as $value) {
                $term = get_term_by('slug', $value, $taxonomy);
                if ($term) {
                    $selections[] = $term->name;
                }
            }
        }
        
        return $selections;
    }
} 