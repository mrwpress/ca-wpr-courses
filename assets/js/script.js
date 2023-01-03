jQuery( document ).ready(function($) {
    $('.wpProQuiz_content').on('learndash-quiz-finished-passed', function (status, item, results) {
        if( wprcourseData.quiz_id ===  wprcourseData.setting_quiz_id ){
            $('.wpProQuiz_content .wpProQuiz_certificate').remove();
            //console.log(wprcourseData.redirect_url);
            //$('#quiz_continue_link').attr('href', wprcourseData.redirect_url);
        }
    });
});