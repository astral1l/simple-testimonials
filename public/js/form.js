document.addEventListener( 'DOMContentLoaded', function () {
	const forms = document.querySelectorAll( '.st-form' );
	const config = typeof window.stFormData !== 'undefined' ? window.stFormData : {};

	function showMessage( box, text, isSuccess ) {
		if ( ! box ) {
			return;
		}
		box.hidden = false;
		box.className =
			'st-form__message ' +
			( isSuccess ? 'st-form__message--success' : 'st-form__message--error' );
		box.setAttribute( 'role', isSuccess ? 'status' : 'alert' );
		box.textContent = text;
	}

	forms.forEach( function ( form ) {
		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			const submitBtn = form.querySelector( '.st-form__submit' );
			const messageBox = form.querySelector( '.st-form__message' );
			const nameInput = form.querySelector( '[name="st_author_name"]' );
			const emailInput = form.querySelector( '[name="st_author_email"]' );
			const textInput = form.querySelector( '[name="st_testimonial_text"]' );

			const nameVal = nameInput ? nameInput.value.trim() : '';
			const emailVal = emailInput ? emailInput.value.trim() : '';
			const textVal = textInput ? textInput.value.trim() : '';

			if ( ! nameVal || ! textVal ) {
				showMessage(
					messageBox,
					config.requiredError || 'Please fill in all required fields.',
					false
				);
				if ( ! nameVal && nameInput ) {
					nameInput.focus();
				} else if ( textInput ) {
					textInput.focus();
				}
				return;
			}

			if ( emailVal && ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( emailVal ) ) {
				showMessage(
					messageBox,
					config.emailError || 'Please enter a valid email address.',
					false
				);
				if ( emailInput ) {
					emailInput.focus();
				}
				return;
			}

			const originalBtnText = submitBtn ? submitBtn.textContent : '';
			if ( submitBtn ) {
				submitBtn.disabled = true;
				if ( config.submitting ) {
					submitBtn.textContent = config.submitting;
				}
			}

			if ( messageBox ) {
				messageBox.hidden = true;
				messageBox.textContent = '';
			}

			const formData = new FormData( form );
			const ajaxUrl = config.ajaxUrl || '/wp-admin/admin-ajax.php';

			fetch( ajaxUrl, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin',
			} )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( result ) {
					if ( result && result.success ) {
						showMessage(
							messageBox,
							result.data && result.data.message ? result.data.message : 'Success!',
							true
						);
						form.reset();
					} else {
						const errMsg =
							result && result.data && result.data.message
								? result.data.message
								: config.genericError || 'Submission failed.';
						showMessage( messageBox, errMsg, false );
					}
				} )
				.catch( function () {
					showMessage(
						messageBox,
						config.genericError || 'Submission failed.',
						false
					);
				} )
				.finally( function () {
					if ( submitBtn ) {
						submitBtn.disabled = false;
						submitBtn.textContent = originalBtnText;
					}
				} );
		} );
	} );
} );
