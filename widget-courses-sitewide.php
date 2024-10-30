<?php

/**
 * LePress Active Courses sitewide
 *
 * @author Raido Kuli
 */

require_once('classes/teacher_include/class-course-meta.php');

class LePressCoursesWidget extends WP_Widget {

   /** constructor */
   function LePressCoursesWidget() {
        parent::WP_Widget(false, $name = __('LePress Courses Sitewide', lepress_textdomain), array('description' =>  __('Display LePress courses on the network, on your sidebar.', lepress_textdomain)));

		add_action('wp_enqueue_scripts', array(&$this, 'loadWidgetJavascript'));
		add_action('init', array(&$this, 'registerWidgetCSS'));
		add_action('wp_head', array(&$this, 'addIECSS'));
	}

	/**
	 * Add Internet Explorer specific styles 
	 */
	 
	function addIECSS() {
		echo '<!--[if lte IE 8]>
			<style type="text/css">
				#lepress-sitewide-courses-popup {
					border: 2px solid #ccc;
				}
			</style>
		<![endif]-->';
	}
	
	/**
	 * Register Widget CSS files on init call
	 */
	 
	function registerWidgetCSS() {
		wp_register_style('lepress_widget_courses', lepress_http_abspath.'css/lepress_widget_courses.css');
		wp_enqueue_style('lepress_widget_courses');
	}

	/**
	 * Add Javascript files via wp_enqueue_scripts hook
	 *
	 * This JavaScript files includes all the functions of LePress sitewide courses needed
	 */
	 
	function loadWidgetJavascript() {
	    wp_enqueue_script( 'jquery' );
		wp_register_script( 'lepress-courses-sitewide', lepress_http_abspath.'js/lepress-courses-sitewide.js');
    	wp_enqueue_script( 'lepress-courses-sitewide' );
	}
	
   /** @see WP_Widget::widget */
   function widget($args, $instance) {
		global $widget_instance, $blog_id;
		$current_blog_id = $blog_id;

       extract( $args );
       $title = apply_filters('widget_title', __('Active LePress Courses', lepress_textdomain));
       ?>
             <?php echo $before_widget; ?>
                 <?php if ( $title )
                       echo $before_title . $title . $after_title;
				?>
				<div id="lepress-sitewide-courses-popup" style="display:none;"><!-- Popped up HTML here --></div>
				<?php
					/* Build widget body */
					
					$found_courses = array();
					/* Check if we have something stored in cache */
					if(false !== ($cached = get_transient('lepress-courses-sitewide'))) {
						echo $cached;
					} else {
						//If not let's generate new updated HTML
						foreach($this->lepress_get_blog_list() as $blog) {
							/* Iterate through blogs and find one with active LePress plugin */
							$switched = switch_to_blog($blog->blog_id, true);
							if($switched) {
								global $LePress;
								$teacher_features_enabled = $LePress->isTeacherFeatures();
								if($teacher_features_enabled) {
									$blog_admin_user = get_user_by('email', get_bloginfo('admin_email'));
									foreach(get_categories('hide_empty=0') as $cat) {
										$course_meta = new CourseMeta($cat->cat_ID, false);
										//If category is course and advertise flag is set, add it to array used to build HTML
										if($course_meta->getIsCourse() && $course_meta->getAdvertise()) {
											// array "user_id", "blog"_id", "courses under blog_id"
											$cat->permalink = add_query_arg(array('cat' => $cat->cat_ID), home_url().'/');
											$cat->teacher = (object) array('firstname' => $blog_admin_user->first_name, 'lastname' => $blog_admin_user->last_name);
											$cat->blog_id = $blog->blog_id;
											$found_courses[get_blog_details($blog->blog_id)->blogname][$cat->cat_name] = $cat;
										}
									}
								}
							}
						}
						//Sort array by key, which is blog name
						ksort($found_courses);
						$count = 1;
						$html = '';
						//Build the actual HTML here now */
						foreach($found_courses as $blog_name => $cats) {
							$blog_details = get_blog_details(reset($cats)->blog_id);
							ksort($cats);
							$teacher = reset($cats)->teacher;
							$html .= '<a href="'.get_blogaddress_by_id(reset($cats)->blog_id).'" class="lepress-course-header" onmouseover="showCoursesFullList('.$count.')" title="'.$blog_details->blogname.'">'.$blog_details->blogname.'</a> ('.count($cats).')<br />';
							$html .= '<div id="lepress-courses-sitewide-'.$count.'" class="lepress-sitewide-hidden">';
							$html .= '<div class="lepress-course-teacher">'.$teacher->firstname.' '.$teacher->lastname.'</div>';
							foreach($cats as $cat) {
								$html .= '<a href="'.$cat->permalink.'" onmouseover="lepressSitewidePopup(this)" class="lepress-course" rel="'.trim($cat->category_description).'" title="'.$cat->cat_name.'">'.$cat->cat_name.'</a><br />';
							}
							$html .= '</div>';
							$count++;
						}
						
						//Yay, we have some HTML output, store it in 30 second cache
						if(!empty($html)) {
							echo $html;
							set_transient('lepress-courses-sitewide', $html, 30); 
						}
						//Revert back to current blog
						switch_to_blog($current_blog_id);
					}
					
					//Too bad, no courses found
					if(count($found_courses) == 0 && $cached === false) {
						echo '<p>'.__('No active courses found on the network', lepress_textdomain).'</p>';
					}
				?>
             <?php echo $after_widget; ?>
       <?php
   }

	/**
	 * Retrieve all the blogs on current site
	 *
	 * @return $wpdb->get_results(sql);
	 */
	
	function lepress_get_blog_list() {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( "SELECT blog_id, domain, path FROM $wpdb->blogs WHERE site_id = %d AND archived = '0' AND spam = '0' AND deleted = '0' ORDER BY registered DESC", $wpdb->siteid ));
	}
	
   /** @see WP_Widget::update */
   function update($new_instance, $old_instance) {
		return true;
   }

   /** @see WP_Widget::form */
   function form($instance) {
   		echo '<p>'.__('Simple widget to display LePress courses on the network', lepress_textdomain).'</p>';
   }

}

global $blog_id;

//Only visible on Wordpress network root site Widgets list
if(get_blog_details($blog_id)->path == "/") {
	add_action('widgets_init', create_function('', 'return register_widget("LePressCoursesWidget");'));
}

?>