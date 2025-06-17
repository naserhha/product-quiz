<?php
if (!defined('ABSPATH')) {
    exit;
}

// Get quiz questions
$quiz = new Perfume_Quiz();
$questions_data = $quiz->get_quiz_questions();

// Check for errors first
if (isset($questions_data['error']) && $questions_data['error']) {
    echo '<div class="perfume-quiz-error">' . esc_html($questions_data['message']) . '</div>';
    return;
}

// Get questions array
$questions = $questions_data['questions'] ?? array();

// Debug log
error_log('Quiz questions: ' . print_r($questions, true));

// If no questions, show message
if (empty($questions)) {
    echo '<div class="perfume-quiz-error">هیچ سوالی برای نمایش وجود ندارد.</div>';
    return;
}
?>

<div class="perfume-quiz-container" dir="rtl">
    <form class="perfume-quiz-form" id="perfume-quiz-form">
        <div class="quiz-progress">
            <div class="progress-bar">
                <div class="progress-bar-fill" style="width: 0%"></div>
            </div>
        </div>

        <?php foreach ($questions as $index => $question): ?>
            <div class="question <?php echo $index === 0 ? 'active' : ''; ?>" data-question-id="<?php echo esc_attr($question['id']); ?>">
                <h3><?php echo esc_html($question['question']); ?></h3>
                
                <?php if (isset($question['description']) && !empty($question['description'])): ?>
                    <div class="question-description">
                        <?php echo wp_kses_post($question['description']); ?>
                    </div>
                <?php endif; ?>

                <div class="options">
                    <?php foreach ($question['options'] as $option): ?>
                        <label class="option">
                            <input 
                                type="<?php echo esc_attr($question['type']); ?>" 
                                name="<?php echo esc_attr($question['id']); ?>[]"
                                value="<?php echo esc_attr($option['value']); ?>"
                                <?php echo $question['required'] ? 'required' : ''; ?>
                            >
                            <span class="option-label"><?php echo esc_html($option['label']); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="navigation">
            <button type="button" class="btn btn-prev" style="display: none;">قبلی</button>
            <button type="button" class="btn btn-next">بعدی</button>
            <button type="submit" class="btn btn-submit" style="display: none;">نمایش نتایج</button>
        </div>
    </form>

    <div class="loading" style="display: none;">در حال جستجوی محصولات...</div>
    <div class="error-message" style="display: none;"></div>
    <div class="quiz-results" style="display: none;"></div>
</div>

<template id="product-template">
    <div class="product-card">
        <img class="product-image" src="" alt="">
        <div class="product-info">
            <h3 class="product-title"></h3>
            <div class="product-price"></div>
            <div class="product-attributes"></div>
            <a class="product-link" href="" target="_blank">مشاهده محصول</a>
        </div>
    </div>
</template>

<template id="attribute-template">
    <div class="attribute-item">
        <div class="attribute-label"></div>
        <div class="attribute-value"></div>
    </div>
</template>

<style>
.perfume-quiz-error {
    background-color: #fff3cd;
    color: #856404;
    padding: 1rem;
    margin-bottom: 1rem;
    border: 1px solid #ffeeba;
    border-radius: 4px;
    text-align: center;
}

.quiz-results {
    padding: 2rem;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-top: 2rem;
}

.results-header {
    text-align: center;
    margin-bottom: 2rem;
}

.results-header h2 {
    color: #333;
    font-size: 1.8rem;
    margin-bottom: 1rem;
}

.results-description {
    color: #666;
    font-size: 1.1rem;
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 2rem;
    margin-top: 2rem;
}

.product-card {
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

.product-image {
    width: 100%;
    height: 280px;
    object-fit: cover;
}

.product-info {
    padding: 1.5rem;
}

.product-title {
    font-size: 1.2rem;
    color: #333;
    margin-bottom: 0.5rem;
    font-weight: 600;
    line-height: 1.4;
}

.product-price {
    color: #e44d26;
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 1rem;
}

.product-attributes {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #eee;
}

.attribute-item {
    display: flex;
    align-items: center;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
    color: #666;
}

.attribute-label {
    font-weight: 600;
    margin-left: 0.5rem;
}

.product-link {
    display: inline-block;
    padding: 0.8rem 1.5rem;
    background: #e44d26;
    color: #fff;
    text-decoration: none;
    border-radius: 5px;
    margin-top: 1rem;
    transition: background 0.3s ease;
    width: 100%;
    text-align: center;
}

.product-link:hover {
    background: #d04323;
    color: #fff;
}

.loading {
    text-align: center;
    padding: 2rem;
    font-size: 1.1rem;
    color: #666;
}

.error-message {
    background-color: #f8d7da;
    color: #721c24;
    padding: 1rem;
    margin: 1rem 0;
    border: 1px solid #f5c6cb;
    border-radius: 4px;
    text-align: center;
}
</style> 