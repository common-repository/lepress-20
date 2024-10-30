/**
 * LePress Teacher administration JavaScript functions
 *
 * @author Raido Kuli
 */

/**
 * On document.ready try to find input field post_category[]
 * if found, we are on new post_page probably and let's override category selection
 * If our post is assignment, we can only allow one category selection only
 */
 
var last_checked = '';

jQuery(document).ready(function() {

	var cats_as_course = jQuery('input[id="cat_ids_course"]');
	var ids_arr = new Array();
	if(cats_as_course.length > 0) {
		ids_arr = cats_as_course.val().split(',');
	}

	//IF is-lepress-assignment checkbox is clicked
	jQuery('#is-lepress-assignment').click(function() {
		var checked = this.checked;
		jQuery('input[name="post_category[]"]').each(function() {
			if(checked) {
				this.checked = false;
			}
			//disable non course categories checkboxes, or enable if is-lepress-assignment dechecked
			if(jQuery.inArray(this.value, ids_arr) == -1) {
					if(checked) {
						this.disabled = true;
					} else {
						this.disabled = false;
					}
			}
		});
		if(last_checked != '' && jQuery.inArray(last_checked.value, ids_arr) > -1) {
			last_checked.checked = true;
		}
	});
	jQuery('input[name="post_category[]"]').each(function() {
		if(this.type != "hidden") {
			var ila = document.getElementById('is-lepress-assignment');
			//IF element was not found, halt code execution
			if(ila == null) {
				return false;
			}
			if(this.checked && last_checked == '') {
				last_checked = this;
			} else {
				if(ila.checked) {
					this.checked = false;
				}
			}
			//if, assignment, disable non course categories
			if(jQuery.inArray(this.value, ids_arr) == -1) {
				if(ila.checked) {
					this.disabled = true;
				}
			}
			jQuery(this).click(function() {
				onlyOneCourseSelect(this);
			});
		}
	});

	//Load awaiting counts via AJAX
	getAwaitingSubscriptions();
	getUngradedAssignmentsCount();
	getAwaitingCommentsTeacher();
	
	//If user in lepres-teacher role, run JS hack for displaying right num of categories and posts
	if(lepress_env['role'] == 'lepress-teacher') {
		var the_list = jQuery('#the-list');
		//Categories - courses count
		if(the_list.hasClass('list:tag')) {
			var list_count = the_list.find('tr[id^="tag"]').length;
			updateCountInSpan(list_count, false);
		}
		else if(the_list.hasClass('')) {
			//Posts list count
			var list_count = the_list.find('tr[id^="post"]').length;
			updateCountInSpan(list_count, false);
		} 
		
		var comment_list = jQuery('#the-comment-list');
		if(comment_list.length > 0) {
			var list_count = comment_list.find('tr[id^="comment"]').length;
			updateCountInSpan(list_count, true);
		}
	}
});

/**
 * Update count in span element
 */
 
function updateCountInSpan(list_count, comment) {
	if(lepress_lang_vars) {
		var word = list_count == 1 ? lepress_lang_vars['one_item'] : lepress_lang_vars['two_item'];
		var span = jQuery('span[class="displaying-num"]');
		var span_count = jQuery('span[class="count"]');
		if(span.length > 0) {
			span.html(list_count+" "+word).fadeIn('fast');
		}
		if(span_count.length > 0) {
			if(comment) {
				span_count.fadeIn('fast');
			} else {
				span_count.html("("+list_count+")").fadeIn('fast');
			}
		}
	}
}

/**
 * Fetch awaiting comments count on teacher side by AJAX
 */
 
function getAwaitingCommentsTeacher() {
	var wrapper = jQuery(jQuery('a[href="edit-comments.php"]').find('span')[0]);
	var admin_bar = jQuery('#ab-awaiting-mod');
	wrapper.fadeOut('fast');
	admin_bar.fadeOut('fast');
	jQuery.get(lepress_teacher_awaiting_url+"&w=comments", function(data) {
  		wrapper.replaceWith(data).stop().fadeIn('fast');
  		var span = jQuery(jQuery(data).find('span')[0]);
  		admin_bar.html(span.html()).stop().fadeIn('fast');
	});
}

/**
 * Fetch awaiting subscriptions count by AJAX
 */
 
function getAwaitingSubscriptions() {
	var wrapper = jQuery('#lepress-roster-count');
	jQuery.get(lepress_teacher_awaiting_url+"&w=subscriptions", function(data) {
  		wrapper.html(data).hide().fadeIn('fast');
	});
}

/**
 * Fetch ungraded assignments count by AJAX
 */
 
function getUngradedAssignmentsCount() {
	var wrapper = jQuery('#lepress-classbook-count');
	jQuery.get(lepress_teacher_awaiting_url+"&w=classbook", function(data) {
  		wrapper.html(data).hide().fadeIn('fast');
	});
}

/**
 * Override categories selectbox behaviour, making it behave like radio buttons
 * 
 * We can only allow one category selection, if assignment
 */
 
function onlyOneCourseSelect(checkbox) {
	//var ila = jQuery("#is-lepress-assignment"); // why can't jQuery find the element ??
	var ila = document.getElementById('is-lepress-assignment');
	//IF element was not found, halt
	if(ila == null) {
		return false;
	}
	if(ila.checked) {
		if(last_checked != '' && last_checked.id != checkbox.id) {
			last_checked.checked = false;
		}
	}
	last_checked = checkbox;
}