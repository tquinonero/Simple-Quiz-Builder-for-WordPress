jQuery(document).ready(function($) {
    $('.gcq-quiz').on('submit', '#gcq-quiz-form', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $quiz = $form.closest('.gcq-quiz');
        var quizId = $quiz.data('quiz-id');
        var answers = $form.serializeArray();
        
        $.ajax({
            url: gcq_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'gcq_submit_quiz',
                quiz_id: quizId,
                answers: answers,
                nonce: gcq_ajax.nonce // Add this line
            },
            success: function(response) {
                if (response.success) {
                    var resultsHtml = '<h3>' + gcq_ajax.results_title + '</h3>';
                    resultsHtml += '<p>' + gcq_ajax.results_text.replace('%1$s', response.data.correct).replace('%2$s', response.data.total) + '</p>';
                    
                    $quiz.find('.gcq-results').html(resultsHtml).show();
                    $form.hide();
                } else {
                    alert(gcq_ajax.error_message);
                }
            },
            error: function() {
                alert(gcq_ajax.error_message);
            }
        });
    });
});
