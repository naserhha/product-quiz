jQuery(document).ready(function($) {
    // Prevent conflicts with other scripts
    if (typeof perfumeQuizData === 'undefined') {
        console.error('perfumeQuizData is not defined. Quiz initialization aborted.');
        return;
    }

    // Ensure jQuery is loaded
    if (typeof $ !== 'function') {
        console.error('jQuery is not loaded. Quiz initialization aborted.');
        return;
    }

    // Debug mode - Add to console.log only if in debug mode
    const DEBUG = true;
    const debug = (message, data) => {
        if (DEBUG && console && console.log) {
            if (data) {
                console.log(`[Perfume Quiz] ${message}`, data);
            } else {
                console.log(`[Perfume Quiz] ${message}`);
            }
        }
    };

    // Create a namespace for our quiz
    const PerfumeQuiz = {
        // Configuration
        config: {
            taxonomyMap: {
                'gender': 'pa_gender',
                'suitable': 'pa_suitable',
                'type': 'pa_type',
                'nature': 'pa_nature',
                'product_cat': 'product_cat'
            },
            ajaxEndpoint: perfumeQuizData.ajaxurl,
            nonce: perfumeQuizData.nonce,
            i18n: perfumeQuizData.i18n || {},
            placeholderImage: perfumeQuizData.placeholder_image || ''
        },

        // State
        state: {
            currentQuestion: 0,
            answers: {},
            isLoading: false,
            hasError: false,
            errorMessage: ''
        },

        // DOM Elements
        elements: {
            form: $('.perfume-quiz-form'),
            questions: $('.question'),
            progressBar: $('.progress-bar-fill'),
            loading: $('.loading'),
            error: $('.error-message'),
            results: $('.quiz-results')
        },

        // Initialize the quiz
        init() {
            if (!this.validateInitialization()) {
                return;
            }

            this.updateProgress();
            this.showQuestion(this.state.currentQuestion);
            this.bindEvents();
        },

        // Validate initialization requirements
        validateInitialization() {
            if (!this.elements.form.length) {
                console.error('Quiz form not found');
                return false;
            }
            return true;
        },

        // Bind event handlers
        bindEvents() {
            this.bindOptionSelection();
            this.bindNavigation();
            this.bindFormSubmission();
        },

        // Handle option selection
        bindOptionSelection() {
            $('.option').on('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                const $option = $(e.currentTarget);
                const $input = $option.find('input');
                const isCheckbox = $input.attr('type') === 'checkbox';
                
                this.handleOptionClick($option, $input, isCheckbox);
            });
        },

        // Handle option click
        handleOptionClick($option, $input, isCheckbox) {
            if (isCheckbox) {
                $input.prop('checked', !$input.prop('checked'));
                $option.toggleClass('selected');
            } else {
                const name = $input.attr('name');
                $(`input[name="${name}"]`).prop('checked', false).closest('.option').removeClass('selected');
                $input.prop('checked', true);
                $option.addClass('selected');
            }
        },

        // Bind navigation buttons
        bindNavigation() {
            $('.btn-next').on('click', (e) => {
                e.preventDefault();
                this.nextQuestion();
            });
            
            $('.btn-prev').on('click', (e) => {
                e.preventDefault();
                this.prevQuestion();
            });
        },

        // Bind form submission
        bindFormSubmission() {
            this.elements.form.on('submit', (e) => {
                e.preventDefault();
                this.submitQuiz();
            });
        },

        // Show question by index
        showQuestion(index) {
            this.elements.questions.removeClass('active');
            $(this.elements.questions[index]).addClass('active');
            
            $('.btn-prev').toggle(index !== 0);
            $('.btn-next').toggle(index !== this.elements.questions.length - 1);
            $('.btn-submit').toggle(index === this.elements.questions.length - 1);
        },

        // Update progress bar
        updateProgress() {
            const progress = ((this.state.currentQuestion + 1) / this.elements.questions.length) * 100;
            this.elements.progressBar.css('width', progress + '%');
        },

        // Navigate to next question
        nextQuestion() {
            const $currentQuestion = $(this.elements.questions[this.state.currentQuestion]);
            const $selectedInputs = $currentQuestion.find('input:checked');
            const isRequired = $currentQuestion.find('input').first().prop('required');
            
            if (isRequired && !$selectedInputs.length) {
                this.showError(this.config.i18n.select_option);
                return;
            }
            
            if (this.state.currentQuestion < this.elements.questions.length - 1) {
                this.state.currentQuestion++;
                this.showQuestion(this.state.currentQuestion);
                this.updateProgress();
                this.hideError();
            }
        },

        // Navigate to previous question
        prevQuestion() {
            if (this.state.currentQuestion > 0) {
                this.state.currentQuestion--;
                this.showQuestion(this.state.currentQuestion);
                this.updateProgress();
                this.hideError();
            }
        },

        // Show error message
        showError(message) {
            this.state.hasError = true;
            this.state.errorMessage = message;
            this.elements.error.html(message).show();
        },

        // Hide error message
        hideError() {
            this.state.hasError = false;
            this.state.errorMessage = '';
            this.elements.error.hide();
        },

        // Submit quiz answers
        async submitQuiz() {
            if (this.state.isLoading) {
                return;
            }

            try {
                const answers = this.collectAnswers();
                if (!answers) {
                    return;
                }

                this.setState({ isLoading: true });
                this.updateUIForSubmission();

                const response = await this.sendQuizData(answers);
                this.handleQuizResponse(response);
            } catch (error) {
                console.error('Quiz submission error:', error);
                this.showError(this.config.i18n.error);
            } finally {
                this.setState({ isLoading: false });
            }
        },

        // Collect answers from form
        collectAnswers() {
            const answers = {};
            let hasErrors = false;

            this.elements.questions.each((_, question) => {
                const $question = $(question);
                const questionId = $question.data('question-id');
                const $selectedInputs = $question.find('input:checked');
                const isRequired = $question.find('input').first().prop('required');
                
                if ($selectedInputs.length > 0) {
                    answers[questionId] = $selectedInputs.map((_, input) => $(input).val()).get();
                } else if (isRequired) {
                    const questionTitle = $question.find('.question-title').text();
                    this.showError(`لطفاً گزینه‌های ${questionTitle} را انتخاب کنید.`);
                    hasErrors = true;
                    return false;
                }
            });

            return hasErrors ? null : answers;
        },

        // Update UI for submission
        updateUIForSubmission() {
            this.elements.loading.show();
            this.hideError();
            this.elements.results.hide();
        },

        // Send quiz data to server
        async sendQuizData(answers) {
            return $.ajax({
                url: this.config.ajaxEndpoint,
                type: 'POST',
                data: {
                    action: 'perfume_quiz_submit',
                    nonce: this.config.nonce,
                    answers: answers
                }
            });
        },

        // Handle quiz response
        handleQuizResponse(response) {
            this.elements.loading.hide();
            debug('Received response:', response);

            if (!response) {
                this.showError(this.config.i18n.error || 'An error occurred');
                debug('Error: Empty response');
                return;
            }

            if (!response.success) {
                const errorMessage = response.data && response.data.message 
                    ? response.data.message 
                    : this.config.i18n.error || 'An error occurred';
                this.showError(errorMessage);
                debug('Error: Response unsuccessful', response);
                return;
            }

            if (!response.data) {
                this.showError(this.config.i18n.error || 'An error occurred');
                debug('Error: No data in response');
                return;
            }

            const { products, message, available_attributes } = response.data;
            debug('Response data:', { products, message, available_attributes });

            if (available_attributes) {
                this.updateAvailableOptions(available_attributes);
            }

            if (!products || !Array.isArray(products) || products.length === 0) {
                this.displayNoResults(message);
                debug('No products found');
                return;
            }

            this.displayResults(products, message);
        },

        // Display no results message
        displayNoResults(message) {
            const html = `
                <div class="no-results">
                    <p>${message || this.config.i18n.no_results}</p>
                    <button type="button" class="btn btn-retry">شروع مجدد</button>
                </div>
            `;
            
            this.elements.results.html(html).show();
            $('.btn-retry').on('click', () => this.resetQuiz());
        },

        // Display results
        displayResults(products, message) {
            let resultsHtml = `
                <div class="results-message">${message}</div>
                <div class="products-grid">
            `;
            
            products.forEach(product => {
                if (!product) return;
                
                resultsHtml += this.createProductCard(product);
            });

            resultsHtml += '</div>';
            // Add restart button outside the grid for better layout
            resultsHtml += '<button type="button" class="btn btn-retry">شروع مجدد</button>';
            
            this.elements.results.html(resultsHtml).show();
            $('.btn-retry').on('click', () => this.resetQuiz());
            
            this.scrollToResults();
        },

        // Create product card HTML
        createProductCard(product) {
            if (!product) {
                debug('Error: Attempted to create card with empty product');
                return '';
            }

            try {
                const {
                    id = '',
                    image = this.config.placeholderImage || '',
                    title = '',
                    price = '',
                    url = '',
                    attributes = {},
                    stock_status = {},
                    sale_info = null
                } = product;

                const attributesHtml = this.createAttributesHtml(attributes);
                const stockHtml = this.createStockStatusHtml(stock_status);
                const saleHtml = this.createSaleInfoHtml(sale_info);

                // Ensure we have a valid placeholder image as fallback
                const placeholderImg = this.config.placeholderImage || 'data:image/svg+xml;charset=UTF-8,%3Csvg%20width%3D%22286%22%20height%3D%22180%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20286%20180%22%20preserveAspectRatio%3D%22none%22%3E%3Cdefs%3E%3Cstyle%20type%3D%22text%2Fcss%22%3E%23holder_17a3f068950%20text%20%7B%20fill%3A%23999%3Bfont-weight%3Anormal%3Bfont-family%3A%22Open%20Sans%22%2C%20sans-serif%3Bfont-size%3A14pt%20%7D%20%3C%2Fstyle%3E%3C%2Fdefs%3E%3Cg%20id%3D%22holder_17a3f068950%22%3E%3Crect%20width%3D%22286%22%20height%3D%22180%22%20fill%3D%22%23EEEEEE%22%3E%3C%2Frect%3E%3Cg%3E%3Ctext%20x%3D%22106.390625%22%20y%3D%2296.3%22%3ENo%20Image%3C%2Ftext%3E%3C%2Fg%3E%3C%2Fg%3E%3C%2Fsvg%3E';

                return `
                    <div class="product-card" data-product-id="${this.escapeHtml(id)}">
                        <div class="product-image-container">
                            ${saleHtml}
                            <img class="product-image" src="${this.escapeHtml(image)}" 
                                alt="${this.escapeHtml(title)}" 
                                onerror="this.onerror=null;this.src='${this.escapeHtml(placeholderImg)}';">
                        </div>
                        <div class="product-info">
                            <h3 class="product-title">${this.escapeHtml(title)}</h3>
                            <div class="product-price">${price}</div>
                            ${attributesHtml}
                            ${stockHtml}
                            <a href="${this.escapeHtml(url)}" class="product-link" target="_blank">
                                ${this.config.i18n.view_product || 'مشاهده محصول'}
                            </a>
                        </div>
                    </div>
                `;
            } catch (err) {
                debug('Error creating product card:', err);
                return '';
            }
        },

        // Create attributes HTML
        createAttributesHtml(attributes) {
            if (!attributes || typeof attributes !== 'object' || Object.keys(attributes).length === 0) {
                return '';
            }

            let html = '<div class="product-attributes">';
            for (const [key, value] of Object.entries(attributes)) {
                if (key && value) {
                    html += `<div class="product-attribute">
                        <span class="attribute-label">${this.escapeHtml(key)}:</span> 
                        ${this.escapeHtml(value)}
                    </div>`;
                }
            }
            html += '</div>';
            return html;
        },

        // Create stock status HTML
        createStockStatusHtml(stockStatus) {
            if (!stockStatus || typeof stockStatus !== 'object') {
                return '';
            }

            const { status = '', label = '' } = stockStatus;
            return `<div class="product-stock ${this.escapeHtml(status)}">${this.escapeHtml(label)}</div>`;
        },

        // Create sale info HTML
        createSaleInfoHtml(saleInfo) {
            if (!saleInfo || !saleInfo.is_on_sale) {
                return '';
            }

            return `<div class="sale-badge">${this.escapeHtml(saleInfo.badge)}</div>`;
        },

        // Scroll to results
        scrollToResults() {
            $('html, body').animate({
                scrollTop: this.elements.results.offset().top - 50
            }, 500);
        },

        // Reset quiz
        resetQuiz() {
            this.setState({
                currentQuestion: 0,
                answers: {},
                isLoading: false,
                hasError: false,
                errorMessage: ''
            });

            this.elements.form[0].reset();
            $('.option').removeClass('selected');
            this.elements.results.hide();
            this.showQuestion(0);
            this.updateProgress();
            this.hideError();
        },

        // Update available options
        updateAvailableOptions(availableAttributes) {
            debug('Updating available options with:', availableAttributes);
            const otherQuestions = this.elements.questions.not(
                this.elements.questions[this.state.currentQuestion]
            );
            
            otherQuestions.each((_, question) => {
                const $question = $(question);
                const questionId = $question.data('question-id');
                let taxonomy = this.config.taxonomyMap[questionId];
                
                // If not found directly, try with pa_ prefix
                if (!taxonomy && questionId) {
                    taxonomy = 'pa_' + questionId;
                    debug(`Using inferred taxonomy ${taxonomy} for question ID ${questionId}`);
                }
                
                if (!taxonomy || !availableAttributes[taxonomy]) {
                    debug(`No available attributes for taxonomy ${taxonomy}`);
                    return;
                }
                
                const availableTerms = availableAttributes[taxonomy];
                debug(`Available terms for ${taxonomy}:`, availableTerms);
                
                $question.find('.option').each((_, option) => {
                    const $option = $(option);
                    const value = $option.find('input').val();
                    const isSelected = $option.find('input').prop('checked');
                    
                    const shouldShow = availableTerms.includes(value) || isSelected;
                    debug(`Option ${value} should show: ${shouldShow}`);
                    $option.toggle(shouldShow);
                });
            });
        },

        // Update state
        setState(newState) {
            this.state = { ...this.state, ...newState };
        },

        // Escape HTML
        escapeHtml(unsafe) {
            if (typeof unsafe !== 'string') {
                unsafe = String(unsafe);
            }
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    };

    // Initialize the quiz
    PerfumeQuiz.init();
}); 