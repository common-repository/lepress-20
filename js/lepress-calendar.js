/**
 * LePress Widget functions
 *
 * This file holds all the Javascript methods used by widget
 *
 * @author Raido Kuli
 *
 */


/**
 * Show/hide AJAX loader gif
 */
 
function toggleAjaxLoader() {
	var loader = jQuery("#lepress-ajax-loader");
	if(loader.is(':visible')) {
		loader.cross_IE_FadeOut('fast');
	} else {
		loader.cross_IE_FadeIn('fast');
	}
}

/**
 * Load next month
 *
 * @param link href next link from the widget
 * @return boolean false
 */

function lepress_next_month(link) {
	toggleAjaxLoader();
	jQuery.get(getListVisibility(link.href), function(data) {
  		jQuery('div[id="lepress_widget_calendar"]').replaceWith(data);
  		bindListActions();
  		toggleAjaxLoader();
	});
	return false;
}

/**
 * Load previous month
 *
 * @param link href previous link from the widget
 * @return boolean false
 */
 
function lepress_previous_month(link) {
	toggleAjaxLoader();
	jQuery.get(getListVisibility(link.href), function(data) {
  		jQuery('div[id="lepress_widget_calendar"]').replaceWith(data);
  		bindListActions();
  		toggleAjaxLoader();
	});
	return false;
}

/**
 * Load selected course data
 *
 * @param select_id <select> tag ID
 * @return boolean false
 */

function lepress_course_change(select_id) {
	toggleAjaxLoader();
	var sel = jQuery('#'+select_id);
	var form = sel.parent();
	var url = form.attr('action')+"?c="+sel.val()+"&lepress-calendar=1";
	jQuery.get(getListVisibility(url), function(data) {
  		jQuery('div[id="lepress_widget_calendar"]').replaceWith(data);
  		bindListActions();
  		toggleAjaxLoader();
	});
	return false;
}

/**
 * Add lists visibility parameter to the URL
 * This way we can determine, what current user lists states were, thus don't have 
 * return to default visible or hidden both
 * 
 * @return string url
 */
 
function getListVisibility(url) {
	var participants_list = +jQuery('#lepress-participants').is(':visible');
	var assignments_list = +jQuery('#lepress-assignments').is(':visible');
	return url+'&p='+participants_list+'&a='+assignments_list;
}

/**
 * Run some actions on when document has finished loading
 */
 
jQuery(document).ready(function() {
	bindListActions();
	jQuery(document.body).click(function() {
   		jQuery('#lepress-assignments-list-of-day').slideUp('fast');
	});
	jQuery(document).keydown(function(e) {
    	// ESCAPE key pressed
		if (e.keyCode == 27) {
			jQuery('#lepress-assignments-list-of-day').slideUp('fast');
		}
	});
});

/**
 * Bind events actions to the elements
 *
 * This method is called after every AJAX load and on document.ready
 *
 */
 
function bindListActions() {
	var expand_span = jQuery('.expand-collapse-lepress').each(function() {
		var span = jQuery(this);
		var h3 = span.parent('h3');
		span.addClass('lepress-opacity');
		h3.click(function() {
			expandCollapse(span.attr('rel'), span);
		});
		h3.mouseenter(function() {
			span.removeClass('lepress-opacity');
		});
		
		h3.mouseleave(function() {
			span.addClass('lepress-opacity');
		});
	});
	
	jQuery('#lepress-assignments-list-of-day').click(function(e) { e.stopPropagation() });
	jQuery('a[class="lepress-assign-day"]').each(function() {
		var el = jQuery(this);
		el.click(function(e) {
			showForDay(el.attr('rel'), el.attr('title'));
			e.stopPropagation();
			return false;
		});
	});
	
	var simple_form = jQuery('form[id="lepress-simple-subscribe"]');
	if(simple_form.length > 0) {
		simple_form.submit(function() {
			var data = jQuery(this).serialize();
			var simple_message = jQuery('#lepress-simple-message');
			var blog_url = jQuery(this).find('input[name="simple-subscriber-blog"]:first');
			if(blog_url.length > 0) {
				blog_url = blog_url.val().trim();
			} else {
				blog_url = "";
			}
			var user_blog_id = jQuery(this).find('input[name="lepress_user_blog_id"]:first').val();
			var ajax_gif = jQuery('#small-lepress-ajax-loader');
			simple_message.html('');
			if(blog_url.length > 0 || user_blog_id > 0) {
				ajax_gif.css({'visibility' : 'visible'});
				if(blog_url.length > 0) {
					return true;
				}
				jQuery.post(simple_form.attr('action'), data, function(data) {
					switch(parseInt(data)) {
						case 0:
							simple_message.html(lepress_lang_vars_widget['try_again']);
							break;
						case 1:
							simple_message.html(lepress_lang_vars_widget['success_simple']);
							setTimeout(function() { lepress_course_change('lepress-course-dropdown'); }, 1500);
							break;
						case 2:
							simple_message.html(lepress_lang_vars_widget['simple_profile_not_filled']);
							break;
						case 3:
							simple_message.html(lepress_lang_vars_widget['url_not_valid']);
							break;
						case 4:
							simple_message.html(lepress_lang_vars_widget['course_locked']);
							break;
						}
					ajax_gif.css({'visibility' : 'hidden'});
				});
			} else {
				simple_message.html(lepress_lang_vars_widget['blog_url_empty']);
			}
			return false;
		});
	}
}

/**
 * Expand/Collapse lists
 *
 * This methods collapses/expands assignments, participants, teacher lists
 *
 */

function expandCollapse(el_id, span) {
	var wrp = jQuery('#'+el_id);
	if(wrp.length > 0) {
		var expand_img = jQuery(span.find('img[rel="lepress-expand"]')[0]);
		var collapse_img = jQuery(span.find('img[rel="lepress-collapse"]')[0]);
		wrp.is(':visible') ? wrp.addClass('lepress-hidden') : wrp.removeClass('lepress-hidden');
		expand_img.is(':visible') ? expand_img.addClass('lepress-hidden') : expand_img.removeClass('lepress-hidden');
		collapse_img.is(':visible') ? collapse_img.addClass('lepress-hidden') : collapse_img.removeClass('lepress-hidden');
	}
}

/**
 * Show assignments for specific day
 *
 * When clicked on day in calendar, this methods gets called
 * @param day Day number
 * @param date Date string to display in popup header
 */
 
function showForDay(day, date) {
	var content = jQuery('#lepress-assignments-on-day-'+day).html();
	if(content.length > 0) {
		jQuery('#lepress-date-header').html(date);
		var a = jQuery('#lepress-list-content');
		a.html(content);
		var a = jQuery('#lepress-assignments-list-of-day');
		a.slideDown('fast');
		var offset = a.position();
		if(offset.left < (a.width()+50)) {
			a.css({'margin-left': (a.width()-35)});
		}	
	}
}

/**
 * Teacher list handler
 */
 
var prev_ul = false;

function showTeacherListHandler(post_id, left) {
	if(left) {
		var ul_el = jQuery('#lepress-assign-ul-left-'+post_id);
	} else {
		var ul_el = jQuery('#lepress-assign-ul-'+post_id);
	}
	if(prev_ul && prev_ul.attr('id') != ul_el.attr('id')) {
		prev_ul.slideUp('fast');
	}
	ul_el.slideDown('fast');
	prev_ul = ul_el;
}

/**
 * Cross-browser fadein effect with opacity FIX IE 
 */

(function($) {
	$.fn.cross_IE_FadeIn = function(speed, callback) {
		$(this).fadeIn(speed, function() {
			if(jQuery.browser.msie)
				$(this).get(0).style.removeAttribute('filter');
			if(callback != undefined)
				callback();
		});
	};
	$.fn.cross_IE_FadeOut = function(speed, callback) {
		$(this).fadeOut(speed, function() {
			if(jQuery.browser.msie)
				$(this).get(0).style.removeAttribute('filter');
			if(callback != undefined)
				callback();
		});
	};
})(jQuery);