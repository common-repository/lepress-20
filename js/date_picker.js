/**
 * jQuery datepicker functions
 *
 * This file is included in wp_admin, if start_date input field is not found script returns false
 *
 * @author Raido Kuli
 *
 */
 
jQuery(document).ready(function() {
	var start_date_picker = jQuery( "#lepress-assignment-start-date");
	if(start_date_picker.length == 0) {
		return false;
	} else {
		start_date_picker.datepicker();
	}
	if(start_date_picker.datepicker('getDate') != null) {
		start_date_picker.datepicker('setDate', dateFormatToLocale(start_date_picker.val()));
	}
	var end_date_picker = jQuery( "#lepress-assignment-end-date" ).datepicker();
	if(end_date_picker.datepicker('getDate') != null) {
		end_date_picker.datepicker('setDate', dateFormatToLocale(end_date_picker.val()));
	}

	//Bind post form
	var post_form = jQuery('form[id="post"]');
	post_form.submit(function() {
		var is_assignment_flag = jQuery('#is-lepress-assignment').is(':checked');
		if(start_date_picker.length > 0 && end_date_picker.length > 0 && is_assignment_flag) {
			var start_date = start_date_picker.datepicker('getDate');
			var end_date = end_date_picker.datepicker('getDate');
			if(start_date >= end_date) {
				//Override default WP post save behaviour, hide ajax-gif and remove disabled class from submit button.
				var wp_ajax_gif = jQuery('img[id="ajax-loading"]');
				wp_ajax_gif.hide();
				var submit = jQuery(post_form.find('input[id="publish"]')[0]);
				submit.removeClass('button-primary-disabled');
				var lepress_meta_error = jQuery('#lepress-meta-error');
	
				//Display error message
				var end_error_str = lepress_lang_vars['date_greater'];
				lepress_meta_error.html(end_error_str);
				lepress_meta_error.fadeIn('fast');
				alert(end_error_str);
				//Return false, to cancel form submit
				return false;
			}
  		}
  	});
});

/**
 * Simple function to convert timestamp value into date
 */
 
function dateFormatToLocale(tstamp) {
	var newDate = new Date( );
	newDate.setTime(tstamp*1000);
	return newDate;
}