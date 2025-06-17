(function($) {
    'use strict';

    // Quiz Admin Object
    const PerfumeQuizAdmin = {
        init: function() {
            this.initSortable();
            this.initColorPickers();
            this.initFormValidation();
            this.initTabNavigation();
            this.initAjaxSave();
        },

        // Initialize Sortable Questions
        initSortable: function() {
            if ($.fn.sortable && $('#sortable-questions').length) {
                $('#sortable-questions').sortable({
                    handle: '.dashicons-menu',
                    axis: 'y',
                    update: function() {
                        PerfumeQuizAdmin.showChangesSavedNotice();
                    }
                });
            }
        },

        // Initialize Color Pickers
        initColorPickers: function() {
            if ($.fn.wpColorPicker) {
                $('.color-picker').wpColorPicker({
                    change: function() {
                        PerfumeQuizAdmin.showChangesSavedNotice();
                    }
                });
            }
        },

        // Initialize Form Validation
        initFormValidation: function() {
            $('.perfume-quiz-form').on('submit', function(e) {
                const $form = $(this);
                const $requiredFields = $form.find('[required]');
                let isValid = true;

                $requiredFields.each(function() {
                    const $field = $(this);
                    if (!$field.val()) {
                        isValid = false;
                        PerfumeQuizAdmin.showError($field);
                    } else {
                        PerfumeQuizAdmin.hideError($field);
                    }
                });

                if (!isValid) {
                    e.preventDefault();
                    PerfumeQuizAdmin.showNotice('لطفاً همه فیلدهای الزامی را پر کنید.', 'error');
                }
            });
        },

        // Initialize Tab Navigation
        initTabNavigation: function() {
            const $tabs = $('.nav-tab');
            const $sections = $('.perfume-quiz-section');

            // Handle tab clicks
            $tabs.on('click', function(e) {
                e.preventDefault();
                const $tab = $(this);
                const target = $tab.attr('href').split('tab=')[1];

                // Update URL without reload
                window.history.pushState({}, '', $tab.attr('href'));

                // Update active states
                $tabs.removeClass('nav-tab-active');
                $tab.addClass('nav-tab-active');

                // Show/hide sections
                $sections.hide();
                $(`.perfume-quiz-section[data-tab="${target}"]`).show();
            });

            // Handle initial tab on page load
            const currentTab = window.location.search.match(/tab=([^&]*)/);
            if (currentTab) {
                $(`.nav-tab[href*="tab=${currentTab[1]}"]`).trigger('click');
            } else {
                $tabs.first().trigger('click');
            }
        },

        // Initialize AJAX Save
        initAjaxSave: function() {
            $('.perfume-quiz-form').on('submit', function(e) {
                e.preventDefault();

                const $form = $(this);
                const $submitButton = $form.find('.button-primary');

                // Disable submit button
                $submitButton.prop('disabled', true);

                // Show loading state
                PerfumeQuizAdmin.showLoadingState();

                // Collect form data
                const formData = new FormData($form[0]);
                formData.append('action', 'perfume_quiz_save_settings');
                formData.append('nonce', perfumeQuizAdmin.nonce);

                // Send AJAX request
                $.ajax({
                    url: perfumeQuizAdmin.ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            PerfumeQuizAdmin.showNotice(perfumeQuizAdmin.i18n.saved, 'success');
                        } else {
                            PerfumeQuizAdmin.showNotice(response.data.message || perfumeQuizAdmin.i18n.error, 'error');
                        }
                    },
                    error: function() {
                        PerfumeQuizAdmin.showNotice(perfumeQuizAdmin.i18n.error, 'error');
                    },
                    complete: function() {
                        // Re-enable submit button and hide loading state
                        $submitButton.prop('disabled', false);
                        PerfumeQuizAdmin.hideLoadingState();
                    }
                });
            });
        },

        // Show Loading State
        showLoadingState: function() {
            const $loading = $('<div class="perfume-quiz-loading"><span class="spinner is-active"></span></div>');
            $('.perfume-quiz-admin-content').prepend($loading);
        },

        // Hide Loading State
        hideLoadingState: function() {
            $('.perfume-quiz-loading').remove();
        },

        // Show Field Error
        showError: function($field) {
            $field.addClass('error');
            if (!$field.next('.error-message').length) {
                $field.after('<span class="error-message">این فیلد الزامی است.</span>');
            }
        },

        // Hide Field Error
        hideError: function($field) {
            $field.removeClass('error');
            $field.next('.error-message').remove();
        },

        // Show Admin Notice
        showNotice: function(message, type = 'success') {
            const $notice = $(`
                <div class="notice notice-${type} is-dismissible">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">بستن این اعلان.</span>
                    </button>
                </div>
            `);

            // Remove existing notices
            $('.notice').remove();

            // Add new notice
            $('.perfume-quiz-admin-header').after($notice);

            // Handle dismiss button
            $notice.find('.notice-dismiss').on('click', function() {
                $notice.fadeOut(200, function() {
                    $(this).remove();
                });
            });

            // Auto-hide after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(200, function() {
                    $(this).remove();
                });
            }, 5000);
        },

        // Show Changes Saved Notice
        showChangesSavedNotice: function() {
            const $saveButton = $('.perfume-quiz-form .button-primary');
            $saveButton.addClass('button-primary-highlight');

            setTimeout(function() {
                $saveButton.removeClass('button-primary-highlight');
            }, 1000);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        PerfumeQuizAdmin.init();
    });

})(jQuery); 