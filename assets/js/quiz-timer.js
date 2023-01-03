( function( $ ) {
	
	// [name] is the name of the event "click", "mouseover", ..
	// same as you'd pass it to bind()
	// [fn] is the handler function
	$.fn.onFirst = function( name, fn ) {
		// bind as you normally would
		// don't want to miss out on any jQuery magic
		this.on( name, fn );

		// Thanks to a comment by @Martin, adding support for
		// namespaced events too.
		this.each( function() {
			var handlers = $._data( this, 'events' )[ name.split( '.' )[ 0 ] ];
			// take out the handler we just inserted from the end
			var handler = handlers.pop();
			// move it at the beginning
			handlers.splice( 0, 0, handler );
		} );
	};

	function showAlert( title, msg, btnText ) {
		var content = "<div id='wpr-dialog-wrap' class='dialog-ovelay'>" +
			"<div class='dialog'><header>" +
			" <h3> " + title + " </h3> " +
			"<i class='fa fa-close'></i>" +
			"</header>" +
			"<div class='dialog-msg'>" +
			" <p> " + msg + " </p> " +
			"</div>" +
			"<footer>" +
			"<div class='controls'>" +
			" <button class='button'>" + btnText + "</button> " +
			"</div>" +
			"</footer>" +
			"</div>" +
			"</div>";

		$( 'body' ).prepend( content );

		$( 'button, .fa-close' ).click( function() {
			$( this ).parents( '.dialog-ovelay' ).fadeOut( 500, function() {
				$( this ).remove();
			} );
		} );
	}

	function disableButtonClick( event ) {
		event.preventDefault();
		event.stopImmediatePropagation();

		var type = ( $( this ).is( '[name="check"]' ) ) ? 'question' : 'answer';

		showAlert( wpr_quiz_timer_js.strings.timer_title, wpr_quiz_timer_js.strings[ 'timer_' + type + '_message' ], 'OK' );

		return false;
	}

	function setButtonTimer( $button, time ) {
		$button.onFirst( 'click', disableButtonClick );

		$button.addClass( 'disabled wpr-forced-timer' );

		// Play related audio file.
		$button.parents( '.wpProQuiz_listItem' ).find( '.mejs-play' ).trigger( 'click' );

		// Start timer.
		setTimeout( function() {
			$button.off( 'click', disableButtonClick );

			$button.removeClass( 'disabled wpr-forced-timer' );
		}, time * 1000 );
	}

	function addQuestionTimers( $questionItem, type ) {
		var questionId = $( '.wpProQuiz_questionList', $questionItem ).data( 'question_id' );

		if (
			typeof questionId === 'undefined' ||
			typeof wpr_quiz_timer_js === 'undefined' ||
			typeof wpr_quiz_timer_js.timers === 'undefined' ||
			typeof wpr_quiz_timer_js.timers[ questionId ] === 'undefined' ||
			typeof wpr_quiz_timer_js.timers[ questionId ][ type ] === 'undefined'
		) {
			return;
		}

		// We have forced timers for this question.
		var timeInSeconds = wpr_quiz_timer_js.timers[ questionId ][ type ];

		if ( 0 < timeInSeconds ) {
			setButtonTimer( $( '.wpProQuiz_button[name="' + ( 'question' === type ? 'check' : 'next' ) + '"]', $questionItem ), timeInSeconds );
		}
	}

	$( '.wpProQuiz_content' ).on( 'changeQuestion', function( event ) {
		addQuestionTimers( $( '.wpProQuiz_listItem:visible' ), 'question' );
	});

	$( document ).ajaxComplete( function( event, xhr, settings ) {
		if ( settings.data.match( /action=ld_adv_quiz_pro_ajax&func=checkAnswers/ ) ) {
			var $question = $( '.wpProQuiz_listItem:visible' );
			addQuestionTimers( $question, 'answer' );
			if ( typeof $.fn.mediaelementplayer !== 'undefined' ) {
				$( '.wpProQuiz_AnswerMessage', $question ).find( 'audio' ).mediaelementplayer();
			}
		}
	});

	$( window ).load( function() {
		// Stop all autoplaying media.
		$( '.mejs-pause' ).trigger( 'click' );
	});
}( jQuery ) );