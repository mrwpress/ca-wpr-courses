( function( $ ) {

	var passed = false;

	function wpr_inquisitor_check_answer( data, retries = 0 ) {
		if ( retries >= wpr_inquisitor_js.retry_max ) {
			// Maximum retries reached, log user out.
			location.assign( wpr_inquisitor_js.logout_url );
			return;
		}

		var question = jQuery( '<div></div>' ).html( data.question ).text();
		var message  = wpr_inquisitor_js.intro_text + "\n\n" + question + "\n\n" + wpr_inquisitor_js.retry_warn;
		var answer   = prompt( message );

		if ( typeof answer !== 'string' || answer.length < 2 ) {
			alert( wpr_inquisitor_js.no_answer );
			return wpr_inquisitor_check_answer( data, retries );
		} else {
			$.ajax( {
				url: wpr_inquisitor_js.ajaxurl,
				type: 'POST',
				data: {
					action: 'wpr_check_inquisitor_answer',
					answer: answer,
					nonce: data.nonce,
				},

				success: function( response ) {
					if ( true === response.data.passed ) {
						passed = true;
						$( 'body' ).removeClass( 'inquisitor-loading' );
						$( 'form#sfwd-mark-complete' ).submit();
					} else {
						alert( wpr_inquisitor_js.incorrect );
						return wpr_inquisitor_check_answer( data, retries + 1 );
					}
				},

				error: function( jqXHR ) {
					alert( wpr_inquisitor_js.error );
					passed = false;
				}
			} );
		}
	}

	function wpr_inquisitor_summon() {
		$( 'body' ).addClass( 'inquisitor-loading' );

		$.ajax( {
			url: wpr_inquisitor_js.ajaxurl,
			type: 'POST',
			data: {
				action: 'wpr_summon_inquisitor',
			},

			success: function( response ) {
				if ( response.error ) {
					alert( wpr_inquisitor_js.error );
					passed = false;
				} else if ( 0 === response.data ) {
					passed = true;
					$( 'body' ).removeClass( 'inquisitor-loading' );
					$( 'form#sfwd-mark-complete' ).submit();
				} else {
					wpr_inquisitor_check_answer( response.data );
				}
			},

			error: function() {
				alert( wpr_inquisitor_js.error );
				passed = false;
			}
		} );
	}

	$( document ).on( 'ready', function() {
		$( 'form#sfwd-mark-complete' ).on( 'submit', function( event ) {
			if ( ! passed ) {
				event.preventDefault();
				wpr_inquisitor_summon();
			}
		});
	});
}( jQuery ));