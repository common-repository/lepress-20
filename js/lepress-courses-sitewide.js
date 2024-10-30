/**
 * LePress Active Network Wide courses widget functions
 *
 * @author Raido Kuli
 */

/**
 * Show courses list for specific blog
 *
 * When user trigger mouseover event on blog link
 * this function expands available courses list. Last
 * opened courses list is stored in "prev_url" variable, so
 * when user moves to next blog link, previous one can be closed.
 *
 */
 
var prev_ul = false;
 
function showCoursesFullList(num) {
	var el = jQuery('#lepress-courses-sitewide-'+num);
	if(prev_ul && prev_ul.attr('id') != el.attr('id')) {
		prev_ul.slideUp('fast');
	}
	el.slideDown('fast');
	prev_ul = el;
}

/**
 * Open div popup layer with course description
 *
 * If course has a description field filled, this functions displays it
 *
 */
 
var timer;

function lepressSitewidePopup(el) {
	var el = jQuery(el);
	var popup = jQuery('#lepress-sitewide-courses-popup');
	if(popup.length > 0) {
		var title = el.attr('rel');
		if(title.length > 0) {
			if(typeof timer != "undefined") {
				clearTimeout(timer);
			}

			popup.html(title);
			popup.slideDown('fast');
			
			var offset = popup.position();
			if(offset.left < (popup.width()+50)) {
				popup.css({'margin-left': (popup.width()-35)});
			}			
			el.mouseleave(function() {
				if(typeof timer != "undefined") {
					clearTimeout(timer);
				}
				timer = setTimeout(function() { popup.slideUp('fast'); }, 500);
			});
		}
	}
}