( function( $, settings ) {

	'use strict';

	var JetThemeWizard = {
		css: {
			start: '[data-theme-wizard="start-install"]',
			getChild: '[data-theme-wizard="get-child"]',
			form: '.theme-wizard-form',
			input: '.wizard-input'
		},

		init: function() {

			$( document )
				.on( 'click.JetThemeWizard', JetThemeWizard.css.start, JetThemeWizard.startInstall )
				.on( 'click.JetThemeWizard', JetThemeWizard.css.getChild, JetThemeWizard.maybeGetChild )
				.on( 'focus.JetThemeWizard', JetThemeWizard.css.input, JetThemeWizard.clearErrorsOnFocus );
		},

		startInstall: function( event ) {

			var $this  = $( this ),
				$form  = $this.closest( JetThemeWizard.css.form ),
				$input = $( JetThemeWizard.css.input, $form ),
				errors = false,
				data   = {
					action: 'jet_theme_wizard_verify_data',
					theme: settings.theme
				};

			event.preventDefault();

			$input.each( function( index, el ) {
				var $this = $( this ),
					name  = $this.attr( 'name' ),
					val   = $this.val();

				if ( '' === val ) {
					JetThemeWizard.addError( $( this ), settings.errors.empty );
					errors = true;
				}

				data[ name ] = val;
			});

			if ( true === errors || $this.hasClass( 'in-progress' ) ) {
				return;
			}

			$this.addClass( 'in-progress' );

			JetThemeWizard.clearErrors( $input );
			JetThemeWizard.clearLog( $this );
			JetThemeWizard.doRecursiveAjax( $this, data );

		},

		maybeGetChild: function( event ) {

			var $this  = $( this ),
				$form  = $this.closest( JetThemeWizard.css.form ),
				$input = $( 'input[type="radio"]:checked', $form ),
				action = $input.val(),
				data   = {
					action: 'jet_theme_wizard_' + action
				};

			event.preventDefault();

			if ( $this.hasClass( 'in-progress' ) ) {
				return;
			}

			$this.addClass( 'in-progress' );

			JetThemeWizard.clearLog( $this );
			JetThemeWizard.doRecursiveAjax( $this, data );

		},

		doRecursiveAjax: function( $this, data ) {

			data.nonce = settings.nonce;

			$.ajax({
				url: ajaxurl,
				type: 'get',
				dataType: 'json',
				data: data
			}).done( function( response ) {

				if ( true === response.success ) {
					JetThemeWizard.addLog( $this, response.data.message, 'success' );

					if ( true === response.data.doNext ) {
						JetThemeWizard.doRecursiveAjax( $this, response.data.nextRequest );
					}

					if ( undefined !== response.data.redirect ) {
						window.location = response.data.redirect;
					}

					return;
				}

				JetThemeWizard.addLog( $this, response.data.message, 'error' );
				$this.removeClass( 'in-progress' );
			});

		},

		addLog: function ( $target, log, type ) {

			if ( ! $target.next( '.wizard-log' ).length ) {
				$target.after( '<div class="wizard-log"></div>' );
			}

			$target.next( '.wizard-log' ).append(
				'<div class="wizard-log__item type-' + type + '">' + log + '</div>'
			);
		},

		addError: function( $target, error ) {

			if ( $target.hasClass( 'wizard-error' ) ) {
				return;
			}

			$target.addClass( 'wizard-error' ).after('<div class="wizard-error-message">' + error + '</div>');
		},

		clearLog: function( $target ) {
			if ( $target.next( '.wizard-log' ).length > 0 ) {
				$target.next( '.wizard-log' ).remove();
			}
		},

		clearErrors: function( $target ) {

			if ( $target.hasClass( 'wizard-error' ) ) {
				$target.removeClass( 'wizard-error' ).next( '.wizard-error-message' ).remove();
			}

		},

		clearErrorsOnFocus: function( event ) {
			var $this = $( this );
			JetThemeWizard.clearErrors( $this );
		}
	};

	JetThemeWizard.init();

}( jQuery, window.JetThemeWizardSettings ) );