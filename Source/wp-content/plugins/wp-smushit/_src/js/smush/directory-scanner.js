/* global ajaxurl */
/* global wp_smush_msgs */

/**
 * Directory scanner module that will Smush images in the Directory Smush modal.
 *
 * @since 2.8.1
 *
 * @param {string|number} totalSteps
 * @param {string|number} currentStep
 * @return {Object}  Scan object.
 * @constructor
 */
const DirectoryScanner = ( totalSteps, currentStep ) => {
	totalSteps = parseInt( totalSteps );
	currentStep = parseInt( currentStep );

	let cancelling = false,
		failedItems = 0;

	const obj = {
		scan() {
			const remainingSteps = totalSteps - currentStep;
			if ( currentStep !== 0 ) {
				// Scan started on a previous page load.
				step( remainingSteps );
			} else {
				jQuery.post( ajaxurl, { action: 'directory_smush_start' },
					() => step( remainingSteps ) );
			}
		},

		cancel() {
			cancelling = true;
			return jQuery.post( ajaxurl, { action: 'directory_smush_cancel' } );
		},

		getProgress() {
			if ( cancelling ) {
				return 0;
			}
			// O M G ... Logic at it's finest!
			const remainingSteps = totalSteps - currentStep;
			return Math.min( Math.round( ( parseInt( ( totalSteps - remainingSteps ) ) * 100 ) / totalSteps ), 99 );
		},

		onFinishStep( progress ) {
			jQuery( '.wp-smush-progress-dialog .sui-progress-state-text' ).html( ( currentStep - failedItems ) + '/' + totalSteps + ' ' + wp_smush_msgs.progress_smushed );
			WP_Smush.directory.updateProgressBar( progress );
		},

		onFinish() {
			WP_Smush.directory.updateProgressBar( 100 );
			window.location.href = wp_smush_msgs.directory_url + '&scan=done';
		},

		limitReached() {
			const dialog = jQuery( '#wp-smush-progress-dialog' );

			dialog.addClass( 'wp-smush-exceed-limit' );
			dialog.find( '#cancel-directory-smush' ).attr( 'data-tooltip', wp_smush_msgs.bulk_resume );
			dialog.find( '.sui-icon-close' ).removeClass( 'sui-icon-close' ).addClass( 'sui-icon-play' );
			dialog.find( '#cancel-directory-smush' ).attr( 'id', 'cancel-directory-smush-disabled' );
		},

		resume() {
			const dialog = jQuery( '#wp-smush-progress-dialog' );
			const resume = dialog.find( '#cancel-directory-smush-disabled' );

			dialog.removeClass( 'wp-smush-exceed-limit' );
			dialog.find( '.sui-icon-play' ).removeClass( 'sui-icon-play' ).addClass( 'sui-icon-close' );
			resume.attr( 'data-tooltip', 'Cancel' );
			resume.attr( 'id', 'cancel-directory-smush' );

			obj.scan();
		},
	};

	/**
	 * Execute a scan step recursively
	 *
	 * Private to avoid overriding
	 *
	 * @param {number} remainingSteps
	 */
	const step = function( remainingSteps ) {
		if ( remainingSteps >= 0 ) {
			currentStep = totalSteps - remainingSteps;
			jQuery.post( ajaxurl, {
				action: 'directory_smush_check_step',
				step: currentStep,
			}, ( response ) => {
				// We're good - continue on.
				if ( 'undefined' !== typeof response.success && response.success ) {
					currentStep++;
					remainingSteps = remainingSteps - 1;
					obj.onFinishStep( obj.getProgress() );
					step( remainingSteps );
				} else if ( 'undefined' !== typeof response.data.error && 'dir_smush_limit_exceeded' === response.data.error ) {
					// Limit reached. Stop.
					obj.limitReached();
				} else {
					// Error? never mind, continue, but count them.
					failedItems++;
					currentStep++;
					remainingSteps = remainingSteps - 1;
					obj.onFinishStep( obj.getProgress() );
					step( remainingSteps );
				}
			} );
		} else {
			jQuery.post( ajaxurl, {
				action: 'directory_smush_finish',
				items: ( totalSteps - failedItems ),
				failed: failedItems,
			}, ( response ) => obj.onFinish( response ) );
		}
	};

	return obj;
};

export default DirectoryScanner;
