/**
 * LePress Student Administration JavaScript methods
 *
 * @author Raido Kuli
 *
 */
 

/**
 * On document.ready init some actions
 */
 
jQuery(document).ready(function() {
	getAwaitingSubscriptionsStudent();
	getUngradedAssignmentsCountStudent();
	getAwaitingCommentsStudent();
	preFillSubmissionTitle();
});

/**
 * Prefill submission title on new post page
 */
 
function preFillSubmissionTitle() {
	var assignment_title = jQuery('#lepress-assignment-title');
	if(assignment_title.length > 0) {
		var wp_title_input = jQuery('#title');
		if(wp_title_input.length > 0) {
			wp_title_input.val(lepress_lang_vars_student['submission_for']+" "+assignment_title.text());
		}
	}
}

/**
 * Expand/collapse assignment content above new post form
 */

function expandAssignment(link_el) {
	var exp_div =  jQuery('div[id="lepress-assignment-before-titlediv"]');
    if(!exp_div.length) {
        var wp_title_div = jQuery('div[id="titlediv"]');
        var assignment_full_text = jQuery('div[id="lepress-assignment-content"]').html();
        wp_title_div.prepend('<div id="lepress-assignment-before-titlediv" class="postbox" style="display:none;"></div>');
        var title = jQuery('div[id="lepress-assignment-title"]').html();
        //Let's call container div seperately, for simpler HTML content adding
        var container = jQuery('div[id="lepress-assignment-before-titlediv"]');
        container.html('<h3>LePress assignment - '+title+'</h3><div style="padding: 3px 4px; background-color: #FFF;">'+assignment_full_text+'</div>');
        container.slideDown('slow');
        jQuery(link_el).html(lepress_lang_vars_student['hide']);
    } else {
        if(exp_div.is(':visible')) {
            jQuery(link_el).html(lepress_lang_vars_student['expand']);
            exp_div.slideUp('slow');
        } else {
            jQuery(link_el).html(lepress_lang_vars_student['hide']);
            exp_div.slideDown('slow');
        }
    }
    return false;
}

/**
 * Fetch student's awaiting subscriptions count
 */
 
function getAwaitingSubscriptionsStudent() {
	var wrapper = jQuery('#lepress-student-subs-count');
	jQuery.get(lepress_student_awaiting_url+"&w=subscriptions", function(data) {
  		wrapper.html(data).hide().fadeIn('fast');
	});
}

/**
 * Fetch student's awaiting comments count
 */

function getAwaitingCommentsStudent() {
	//No need to repeat comments count fetch,both teacher and student side return the same value
	//and teacher plugin makes the call first
	if(typeof lepress_teacher_awaiting_url == 'undefined') {
		var wrapper = jQuery(jQuery('a[href="edit-comments.php"]').find('span')[0]);
		var admin_bar = jQuery('#ab-awaiting-mod');
		wrapper.fadeOut('fast');
		admin_bar.fadeOut('fast');
		jQuery.get(lepress_student_awaiting_url+"&w=comments", function(data) {
			wrapper.replaceWith(data).stop().fadeIn('fast');
			var span = jQuery(jQuery(data).find('span')[0]);
  			admin_bar.html(span.html()).stop().fadeIn('fast');
		});
	}
}

/**
 * Fetch student's ungraded assignments count
 */
 
function getUngradedAssignmentsCountStudent() {
	var wrapper = jQuery('#lepress-student-assignments-count');
	jQuery.get(lepress_student_awaiting_url+"&w=assignments", function(data) {
  		wrapper.html(data).hide().fadeIn('fast');
	});
}

/**
 * Control post visibility private/public
 */

function setPostVisibility(checkbox, public_str, private_str) {
	var chk = checkbox.checked;
	var post_visibility_div = jQuery('#post-visibility-display');
	var private_radio = jQuery('#visibility-radio-private');
	jQuery('a.save-post-visibility').click(function() { setPostVisibility(checkbox, public_str, private_str); });
	jQuery('a.cancel-post-visibility').click(function() { setPostVisibility(checkbox, public_str, private_str); });
	
	if(chk) {
		post_visibility_div.html(private_str);
		private_radio.attr('checked', true);
	} else {
		post_visibility_div.html(public_str);
		private_radio.attr('checked', false);
	}
}