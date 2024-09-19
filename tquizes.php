<?php
/*
Plugin Name: General Culture Quiz
Description: A scalable quiz plugin for WordPress
Version: 1.0
Author: Your Name
*/

function gcq_register_quiz_post_type() {
    $args = array(
        'public' => true,
        'label'  => 'Quizzes',
        'supports' => array('title', 'editor'),
        'has_archive' => true,
        'rewrite' => array('slug' => 'quizzes'), // Add this line
    );
    register_post_type('gcq_quiz', $args);
}
add_action('init', 'gcq_register_quiz_post_type');

function gcq_add_quiz_meta_box() {
    add_meta_box('gcq_quiz_questions', 'Quiz Questions', 'gcq_quiz_questions_callback', 'gcq_quiz', 'normal', 'high');
}
add_action('add_meta_boxes', 'gcq_add_quiz_meta_box');

function gcq_quiz_questions_callback($post) {
    wp_nonce_field('gcq_save_quiz_meta', 'gcq_quiz_nonce');
    $questions = get_post_meta($post->ID, 'gcq_questions', true);
    if (!is_array($questions)) {
        $questions = array();
    }
    ?>
    <div id="gcq-questions-container">
        <?php foreach ($questions as $index => $question): ?>
            <div class="gcq-question-box">
                <h4>Question <?php echo $index + 1; ?></h4>
                <p>
                    <label>Question:</label>
                    <input type="text" name="gcq_questions[<?php echo $index; ?>][text]" value="<?php echo esc_attr($question['text']); ?>" style="width: 100%;">
                </p>
                <?php for ($i = 0; $i < 4; $i++): ?>
                    <p>
                        <label>Answer <?php echo $i + 1; ?>:</label>
                        <input type="text" name="gcq_questions[<?php echo $index; ?>][answers][]" value="<?php echo esc_attr($question['answers'][$i]); ?>" style="width: 80%;">
                        <input type="radio" name="gcq_questions[<?php echo $index; ?>][correct]" value="<?php echo $i; ?>" <?php checked($question['correct'], $i); ?>> Correct
                    </p>
                <?php endfor; ?>
                <button type="button" class="button gcq-remove-question">Remove Question</button>
            </div>
        <?php endforeach; ?>
    </div>
    <button type="button" id="gcq-add-question" class="button">Add Question</button>

    <script>
    jQuery(document).ready(function($) {
        var questionIndex = <?php echo count($questions); ?>;

        $('#gcq-add-question').on('click', function() {
            questionIndex++;
            var newQuestion = `
                <div class="gcq-question-box">
                    <h4>Question ${questionIndex}</h4>
                    <p>
                        <label>Question:</label>
                        <input type="text" name="gcq_questions[${questionIndex - 1}][text]" value="" style="width: 100%;">
                    </p>
                    <?php for ($i = 0; $i < 4; $i++): ?>
                        <p>
                            <label>Answer <?php echo $i + 1; ?>:</label>
                            <input type="text" name="gcq_questions[${questionIndex - 1}][answers][]" value="" style="width: 80%;">
                            <input type="radio" name="gcq_questions[${questionIndex - 1}][correct]" value="<?php echo $i; ?>"> Correct
                        </p>
                    <?php endfor; ?>
                    <button type="button" class="button gcq-remove-question">Remove Question</button>
                </div>
            `;
            $('#gcq-questions-container').append(newQuestion);
        });

        $(document).on('click', '.gcq-remove-question', function() {
            $(this).closest('.gcq-question-box').remove();
            $('.gcq-question-box').each(function(index) {
                $(this).find('h4').text('Question ' + (index + 1));
            });
            questionIndex = $('.gcq-question-box').length;
        });
    });
    </script>
    <?php
}

function gcq_save_quiz_meta($post_id) {
    if (!isset($_POST['gcq_quiz_nonce']) || !wp_verify_nonce($_POST['gcq_quiz_nonce'], 'gcq_save_quiz_meta')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (isset($_POST['gcq_questions']) && is_array($_POST['gcq_questions'])) {
        $questions = array();
        foreach ($_POST['gcq_questions'] as $question) {
            if (!empty($question['text']) && is_array($question['answers']) && isset($question['correct'])) {
                $sanitized_question = array(
                    'text' => sanitize_text_field($question['text']),
                    'answers' => array_map('sanitize_text_field', array_slice($question['answers'], 0, 4)),
                    'correct' => absint($question['correct']) % 4
                );
                $questions[] = $sanitized_question;
            }
        }
        update_post_meta($post_id, 'gcq_questions', $questions);
    } else {
        delete_post_meta($post_id, 'gcq_questions');
    }
}
add_action('save_post_gcq_quiz', 'gcq_save_quiz_meta');

function gcq_quiz_shortcode($atts) {
    $atts = shortcode_atts(array('id' => 0), $atts, 'gcq_quiz');
    $quiz_id = absint($atts['id']);
    
    if ($quiz_id <= 0) {
        return esc_html__('Invalid quiz ID', 'gcq');
    }
    
    $questions = get_post_meta($quiz_id, 'gcq_questions', true);
    if (!is_array($questions) || empty($questions)) {
        return esc_html__('No questions found for this quiz', 'gcq');
    }
    
    wp_enqueue_style('gcq-styles', plugins_url('assets/css/quiz-style.css', __FILE__));
    wp_enqueue_script('gcq-script', plugins_url('assets/js/quiz-script.js', __FILE__), array('jquery'), null, true);
    
    ob_start();
    ?>
    <div class="gcq-quiz" data-quiz-id="<?php echo esc_attr($quiz_id); ?>">
        <form id="gcq-quiz-form">
            <?php foreach ($questions as $index => $question): ?>
                <div class="gcq-question">
                    <h3><?php echo esc_html(($index + 1) . '. ' . $question['text']); ?></h3>
                    <?php foreach ($question['answers'] as $answer_index => $answer): ?>
                        <label>
                            <input type="radio" name="q<?php echo esc_attr($index); ?>" value="<?php echo esc_attr($answer_index); ?>">
                            <?php echo esc_html($answer); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            <button type="submit" class="gcq-submit"><?php esc_html_e('Submit Quiz', 'gcq'); ?></button>
        </form>
        <div class="gcq-results" style="display: none;"></div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('gcq_quiz', 'gcq_quiz_shortcode');

function gcq_admin_menu() {
    add_menu_page('General Culture Quiz', 'GC Quiz', 'manage_options', 'gcq-quiz-management', 'gcq_quiz_management_page');
}
add_action('admin_menu', 'gcq_admin_menu');

function gcq_quiz_management_page() {
    // Display quiz management interface
}

function gcq_submit_quiz() {
    check_ajax_referer('gcq_quiz_nonce', 'nonce');

    $quiz_id = absint($_POST['quiz_id']);
    $submitted_answers = isset($_POST['answers']) ? (array) $_POST['answers'] : array();
    
    $questions = get_post_meta($quiz_id, 'gcq_questions', true);
    if (!is_array($questions)) {
        wp_send_json_error(array('message' => __('Invalid quiz data', 'gcq')));
    }

    $correct_count = 0;
    
    foreach ($submitted_answers as $answer) {
        $question_index = absint(substr($answer['name'], 1));
        $answer_value = absint($answer['value']);
        if (isset($questions[$question_index]) && $questions[$question_index]['correct'] === $answer_value) {
            $correct_count++;
        }
    }
    
    wp_send_json_success(array(
        'correct' => $correct_count,
        'total' => count($questions)
    ));
}
add_action('wp_ajax_gcq_submit_quiz', 'gcq_submit_quiz');
add_action('wp_ajax_nopriv_gcq_submit_quiz', 'gcq_submit_quiz');

// Add this new function
function gcq_rewrite_flush() {
    gcq_register_quiz_post_type();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'gcq_rewrite_flush');

// Add this function to your plugin
function gcq_display_quiz_content($content) {
    if (is_singular('gcq_quiz') && in_the_loop() && is_main_query()) {
        $quiz_id = get_the_ID();
        $quiz_content = gcq_quiz_shortcode(array('id' => $quiz_id));
        return $content . $quiz_content;
    }
    return $content;
}
add_filter('the_content', 'gcq_display_quiz_content');

// Add theme support for custom post type
function gcq_theme_support() {
    add_theme_support('post-thumbnails', array('gcq_quiz'));
    add_theme_support('custom-fields', array('gcq_quiz'));
}
add_action('after_setup_theme', 'gcq_theme_support');

function gcq_enqueue_scripts() {
    wp_enqueue_script('gcq-script', plugins_url('assets/js/quiz-script.js', __FILE__), array('jquery'), null, true);
    wp_localize_script('gcq-script', 'gcq_ajax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('gcq_quiz_nonce'),
        'results_title' => __('Your Results:', 'gcq'),
        'results_text' => __('You got %1$s out of %2$s questions correct.', 'gcq'),
        'error_message' => __('There was an error submitting your quiz. Please try again.', 'gcq')
    ));
}
add_action('wp_enqueue_scripts', 'gcq_enqueue_scripts');
