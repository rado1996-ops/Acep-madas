/**
 * Modals JavaScript code.
 */

( function() {
	'use strict';

	/**
     * Onboarding modal.
     *
     * @since 3.1
     */
	WP_Smush.onboarding = {
		membership: 'free', // Assume free by default.
		onboardingModal: document.getElementById( 'smush-onboarding-dialog' ),
		scanFilesModal: document.getElementById( 'checking-files-dialog' ),
		settings: {
			first: true,
			last: false,
			slide: 'start',
			value: false,
		},
		selection: {
			auto: true,
			lossy: true,
			strip_exif: true,
			original: false,
			lazy_load: true,
			usage: true,
		},
		contentContainer: document.getElementById( 'smush-onboarding-content' ),
		onboardingSlides: [ 'start', 'auto', 'lossy', 'strip_exif', 'original', 'lazy_load', 'usage' ],
		touchX: null,
		touchY: null,

		/**
         * Init module.
         */
		init() {
			if ( ! this.onboardingModal ) {
				return;
			}

			this.membership = document.getElementById( 'smush-onboarding' ).dataset.type;

			if ( 'pro' !== this.membership ) {
				this.onboardingSlides = [ 'start', 'auto', 'strip_exif', 'lazy_load', 'usage' ];
				this.selection.lossy = false;
			}

			this.renderTemplate();

			// Skip setup.
			const skipButton = this.onboardingModal.querySelector( '.smush-onboarding-skip-link' );
			if ( skipButton ) {
				skipButton.addEventListener( 'click', this.skipSetup );
			}

			// Show the modal.
			const dialog = new A11yDialog( this.onboardingModal );
			dialog.show();
		},

		/**
         * Get swipe coordinates.
         *
         * @param e
         */
		handleTouchStart( e ) {
			const firstTouch = e.touches[ 0 ];
			this.touchX = firstTouch.clientX;
			this.touchY = firstTouch.clientY;
		},

		/**
         * Process swipe left/right.
         *
         * @param e
         */
		handleTouchMove( e ) {
			if ( ! this.touchX || ! this.touchY ) {
				return;
			}

			const xUp = e.touches[ 0 ].clientX,
				yUp = e.touches[ 0 ].clientY,
				xDiff = this.touchX - xUp,
				yDiff = this.touchY - yUp;

			if ( Math.abs( xDiff ) > Math.abs( yDiff ) ) {
				if ( xDiff > 0 ) {
					if ( false === WP_Smush.onboarding.settings.last ) {
						WP_Smush.onboarding.next( null, 'next' );
					}
				} else if ( false === WP_Smush.onboarding.settings.first ) {
					WP_Smush.onboarding.next( null, 'prev' );
				}
			}

			this.touchX = null;
			this.touchY = null;
		},

		/**
         * Update the template, register new listeners.
         *
         * @param {string} directionClass  Accepts: fadeInRight, fadeInLeft.
         */
		renderTemplate( directionClass ) {
			// Grab the selected value.
			const input = this.onboardingModal.querySelector( 'input[type="checkbox"]' );
			if ( input ) {
				this.selection[ input.id ] = input.checked;
			}

			const template = WP_Smush.onboarding.template( 'smush-onboarding' );
			const content = template( this.settings );

			if ( content ) {
				this.contentContainer.innerHTML = content;

				if ( 'undefined' === typeof directionClass ) {
					this.contentContainer.classList.add( 'loaded' );
				} else {
					this.contentContainer.classList.remove( 'loaded' );
					this.contentContainer.classList.add( directionClass );
					setTimeout( () => {
						this.contentContainer.classList.add( 'loaded' );
						this.contentContainer.classList.remove( directionClass );
					}, 600 );
				}
			}

			this.onboardingModal.addEventListener( 'touchstart', this.handleTouchStart, false );
			this.onboardingModal.addEventListener( 'touchmove', this.handleTouchMove, false );

			this.bindSubmit();
		},

		/**
         * Catch "Finish setup wizard" button click.
         */
		bindSubmit() {
			const submitButton = this.onboardingModal.querySelector( 'button[type="submit"]' );
			const self = this;

			if ( submitButton ) {
				submitButton.addEventListener( 'click', function( e ) {
					e.preventDefault();

					// Because we are not rendering the template, we need to update the last element value.
					const input = self.onboardingModal.querySelector( 'input[type="checkbox"]' );
					if ( input ) {
						self.selection[ input.id ] = input.checked;
					}

					const _nonce = document.getElementById( '_wpnonce' );

					const xhr = new XMLHttpRequest();
					xhr.open( 'POST', ajaxurl + '?action=smush_setup', true );
					xhr.setRequestHeader( 'Content-type', 'application/x-www-form-urlencoded' );
					xhr.onload = () => {
						if ( 200 === xhr.status ) {
							WP_Smush.onboarding.showScanDialog();
						} else {
							console.log( 'Request failed.  Returned status of ' + xhr.status );
						}
					};
					xhr.send( 'smush_settings=' + JSON.stringify( self.selection ) + '&_ajax_nonce=' + _nonce.value );
				} );
			}
		},

		/**
         * Handle navigation.
         *
         * @param e
         * @param whereTo
         */
		next( e, whereTo = null ) {
			const index = this.onboardingSlides.indexOf( this.settings.slide );
			let newIndex = 0;

			if ( ! whereTo ) {
				newIndex = null !== e && e.classList.contains( 'next' ) ? index + 1 : index - 1;
			} else {
				newIndex = 'next' === whereTo ? index + 1 : index - 1;
			}

			const directionClass = null !== e && e.classList.contains( 'next' ) ? 'fadeInRight' : 'fadeInLeft';

			this.settings = {
				first: 0 === newIndex,
				last: newIndex + 1 === this.onboardingSlides.length, // length !== index
				slide: this.onboardingSlides[ newIndex ],
				value: this.selection[ this.onboardingSlides[ newIndex ] ],
			};

			this.renderTemplate( directionClass );
		},

		/**
         * Handle circle navigation.
         *
         * @param target
         */
		goTo( target ) {
			const newIndex = this.onboardingSlides.indexOf( target );

			this.settings = {
				first: 0 === newIndex,
				last: newIndex + 1 === this.onboardingSlides.length, // length !== index
				slide: target,
				value: this.selection[ target ],
			};

			this.renderTemplate();
		},

		/**
         * Skip onboarding experience.
         */
		skipSetup: () => {
			const _nonce = document.getElementById( '_wpnonce' );

			const xhr = new XMLHttpRequest();
			xhr.open( 'POST', ajaxurl + '?action=skip_smush_setup&_ajax_nonce=' + _nonce.value );
			xhr.onload = () => {
				if ( 200 === xhr.status ) {
					WP_Smush.onboarding.showScanDialog();
				} else {
					console.log( 'Request failed.  Returned status of ' + xhr.status );
				}
			};
			xhr.send();
		},

		/**
         * Show checking files dialog.
         */
		showScanDialog() {
			const dialog = new A11yDialog( this.onboardingModal );
			dialog.hide();

			const scanDialog = new A11yDialog( this.scanFilesModal );
			scanDialog.show();

			const nonce = document.getElementById( 'wp_smush_options_nonce' );

			setTimeout( () => {
				const xhr = new XMLHttpRequest();
				xhr.open( 'POST', ajaxurl + '?action=scan_for_resmush', true );
				xhr.setRequestHeader( 'Content-type', 'application/x-www-form-urlencoded' );
				xhr.onload = () => {
					const elem = document.querySelector( '#smush-onboarding-dialog' );
					elem.parentNode.removeChild( elem );
					scanDialog.hide();

					if ( 200 === xhr.status ) {
						setTimeout( function() {
							location.reload();
						}, 1000
						);
					} else {
						console.log( 'Request failed.  Returned status of ' + xhr.status );
					}
				};
				xhr.send( 'type=media&get_ui=false&process_settings=false&wp_smush_options_nonce=' + nonce.value );
			}, 3000 );
		},
	};

	/**
     * Template function (underscores based).
     *
     * @type {Function}
     */
	WP_Smush.onboarding.template = _.memoize( ( id ) => {
		let compiled,
			options = {
				evaluate: /<#([\s\S]+?)#>/g,
				interpolate: /\{\{\{([\s\S]+?)\}\}\}/g,
				escape: /\{\{([^\}]+?)\}\}(?!\})/g,
				variable: 'data',
			};

		return ( data ) => {
			_.templateSettings = options;
			compiled = compiled || _.template( document.getElementById( id ).innerHTML );
			return compiled( data );
		};
	} );

	window.addEventListener( 'load', () => WP_Smush.onboarding.init() );
}() );
