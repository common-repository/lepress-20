/**
 * Simple iFrame expander script
 *
 * This script is included in iframe view of assignment, on send-feedback page
 *
 */
 
if(jQuery) {
	jQuery(document).ready(function() {
		parent.jQuery("iframe").each(function(iel, el) {
		  if(el.contentWindow == window) {
			var iframe = jQuery(el);
			var extra_height = 20;
			if(jQuery.browser.opera) {
				extra_height = extra_height+20;
			}
			setTimeout(function() {
				iframe.height(iframe.contents().height() + extra_height);
			}, 250);
		  }
		});
	});
} else {
	alert('jQuery not loaded inside the iFrame, something has gone terribly wrong...');
}
