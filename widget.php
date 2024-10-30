<?php

/**
 * LePress Widget
 *
 * @author Raido Kuli
 *
 */
 
require_once('wp-calendar-forked.php');
require_once('class-basic-widget.php');

$widget_instance = array(); //This will be populated before calendar method call

class LePressWidget extends WP_Widget {

   /** constructor */
   function LePressWidget() {
   		global $LePress;
   		$this->LePress = $LePress;
        parent::WP_Widget(false, $name = 'LePress', array('description' =>  __('LePress plugin widget', lepress_textdomain)));
        //If we have student role enabled
        if($this->LePress->isStudentFeatures()) {
       		require_once('classes/student_include/student-widget.php');
       		$this->studentWidget = new LePressStudentWidget();
        }
		//If we have teacher role enabled
        if($this->LePress->isTeacherFeatures()) {
       		require_once('classes/teacher_include/teacher-widget.php');
       		$this->teacherWidget = new LePressTeacherWidget();
        }
        //Load Basic widget class

		//Ajax load widget content hooks and triggers
        add_filter('query_vars', array(&$this, 'plugin_add_trigger'));
		add_action('template_redirect', array(&$this,'plugin_trigger_check'));
		add_action('wp_enqueue_scripts', array(&$this, 'loadWidgetJavascript'));
		add_action('init', array(&$this, 'registerWidgetCSS'));
		add_action('wp_head', array(&$this, 'addIECSS'));
	}

	/**
	 * Add IE styles, JS langvars
	 *
	 * This method adds Internet Explorer specific styles and
	 * JavaScript language vars need for widget AJAX interactions
	 */
	 
	function addIECSS() {
		if(isSet($_GET['if'])) { // If requesting post content via IFrame, do not append CSS/Scripts
			return false;
		}
		echo '<!--[if lte IE 8]>
			<style type="text/css">
				#lepress-assignments-list-of-day, .lepress-updated {
					border: 2px solid #ccc;
				}
			</style>
		<![endif]-->';
		
		$wp_ver = get_bloginfo('version');
		if($wp_ver <= '3.1.4' || get_current_theme() == "Twenty Ten") {
			echo '<style type="text/css">';
			echo '#lepress-ajax-loader { width:200px; height: 177px; }';
			echo '#lepress_widget_calendar h3 { line-height: 1.625em; }';
			echo '#lepress_widget_calendar h3 img { margin-top: 5px; }';
			echo '</style>';
		}
		
		//Add some Widget language variables too
		$lang_vars = array('try_again' => __('Already subscribed ?', lepress_textdomain),
							'success_simple' => __('Success', lepress_textdomain),
							'blog_url_empty' => __('Blog URL not entered', lepress_textdomain),
							'url_not_valid' => __('Blog URL not valid', lepress_textdomain),
							'simple_profile_not_filled' => __('Subscribing failed', lepress_textdomain).'; '.__('Your profile is not filled (firstname/lastname)', lepress_textdomain),
							'course_locked' => __('This course does not accept any new subscriptions', lepress_textdomain));
		echo '<script type="text/javascript">';
		echo 'var lepress_lang_vars_widget = '.json_encode($lang_vars).';'."\n";
		echo '</script>';
	}
	
	/**
	 * Register Widget CSS files on init call
	 */
	 
	function registerWidgetCSS() {
		if(isSet($_GET['if'])) {
			return false;
		}
		wp_register_style('lepress_widget', lepress_http_abspath.'css/lepress_widget.css');
		wp_enqueue_style('lepress_widget');
	}

	/**
	 * AJAX calendar prev/next month handler
	 */
	 
	function plugin_trigger_check() {
		//If we have a query var lepress-calendar with value == 1
		if(intval(get_query_var('lepress-calendar')) == 1) {
			//WP default header is 404, have to override it
			header("HTTP/1.0 200 OK");
			if(isSet($_GET['c'])) {
				$parts = explode('-', strip_tags(trim($_GET['c'])));
				$selected_cat_ID = intval($parts[1]);
				$this->storeLastCourse($_GET['c']);
			}
			global $widget_instance;
			$widget_instance = $this->getSettingsByQuery(array('collapse_participants', 'collapse_assignments'));
			get_calendar_forked(true, true, $selected_cat_ID, LePressBasicWidget::getCourseOwner(), $this->teacherWidget, $this->studentWidget, true, $instance_settings);
			exit;
		}
   	}

	/**
	 * Add additional query var to query_variables
	 * @return query_vars array
	 */
	 
	function plugin_add_trigger($vars) {
		$vars[] = 'lepress-calendar';
    	return $vars;
	}

	/**
	 * Store last selected course as option in database
	 */

	function storeLastCourse($course_id) { //teacher-X or student-X
		update_option('lepress-last-widget-course', $course_id);
	}

	/**
	 * Retrieve last selected course option from database 
	 * 
	 * @return string or boolean false
	 */

	function getLastStoredCourse() {
		return get_option('lepress-last-widget-course', false);
	}

	/**
	 * Add Javascript files via wp_enqueue_scripts hook 
	 */

	function loadWidgetJavascript() {
	    wp_enqueue_script( 'jquery' );
	    if(isSet($_GET['if'])) {
			return false;
		}
		wp_register_script( 'lepress-wp-calendar', lepress_http_abspath.'js/lepress-calendar.js');
    	wp_enqueue_script( 'lepress-wp-calendar' );
	}
	
	/**
	 * Get settings by query
	 *
	 * @param array $array_what What setting to filter out of query
	 *
	 * @return array
	 */
	 
	function getSettingsByQuery($array_what = array()) {
		$out = array();
		foreach($this->get_settings() as $ar) {
			foreach($ar as $key => $val) {
				if(in_array($key, $array_what)) {
					$out[$key] = $val; 
				}
			}
		}
		return $out;
	}
	
   /** @see WP_Widget::widget */
   function widget($args, $instance) {
		global $LePress, $widget_instance;

       extract( $args );
       $title = apply_filters('widget_title', 'LePress');
       $widget_instance = $this->getSettingsByQuery(array('collapse_participants', 'collapse_assignments'));
       ?>
             <?php echo $before_widget; ?>
                 <?php if ( $title )
                       echo $before_title . $title . $after_title;
                       if($LePress->isStudentFeatures() || $LePress->isTeacherFeatures()) {
						   echo '<div id="lepress-ajax-loader"><img src="'.lepress_http_abspath.'img/ajax_3d.gif" alt="Loading" /></div>';
							?>                    
						   <form action="<?php echo get_bloginfo('siteurl'); ?>" method="get">
						   <select name="c" id="lepress-course-dropdown" onchange="return lepress_course_change(this.id)">
						   <?php
						   $_GET['c'] = $this->getLastStoredCourse();
						   if($LePress->isTeacherFeatures()) {
								$selected_cat_ID = $this->teacherWidget->getCourseSelectOptions();
							 }
							if($LePress->isStudentFeatures()) {
								if(!$selected_cat_ID) {
									$selected_cat_ID = $this->studentWidget->getCourseSelectOptions();
								} else {
									$this->studentWidget->getCourseSelectOptions();
								}
							}
					 ?>
					 </select>
					 </form>
					 <script type="text/javascript">
						function removeEmailMask(link) {
							link.href = link.href.replace("[at]", "@");
						}
	
						function addEmailMask(link) {
							link.href = link.href.replace("@", "[at]");
						}
					 </script>
					 <?php
						get_calendar_forked(true, true, $selected_cat_ID, LePressBasicWidget::getCourseOwner(), $this->teacherWidget, $this->studentWidget, false, $instance_settings);
                  } else {
                  	echo '<p>'.__('You have not configured your plugin. Please login to administrative dashboard, go to Settings -> LePress and finish LePress configuration', lepress_textdomain).'</p>';
                  }
                 ?>
             <?php echo $after_widget; ?>
       <?php
   }

   /** @see WP_Widget::update */
   function update($new_instance, $old_instance) {
		$instance = $old_instance;
		$instance['collapse_participants'] = strip_tags($new_instance['collapse_participants']);
		$instance['collapse_assignments'] = strip_tags($new_instance['collapse_assignments']);
		return $instance;
   }

   /** @see WP_Widget::form */
   function form($instance) {
		global $LePress;
		$collapse_participants = esc_attr($instance['collapse_participants']);
       	$collapse_assignments = esc_attr($instance['collapse_assignments']);
       ?>
       	<p>
       		<b><?php _e('By default', lepress_textdomain); ?>:</b>
       	</p>
           <p><div><input class="checkbox" id="<?php echo $this->get_field_id('collapse_participants'); ?>" <?php echo $collapse_participants ? 'checked="checked"': ''; ?> name="<?php echo $this->get_field_name('collapse_participants'); ?>" type="checkbox" value="1" /> <label for="<?php echo $this->get_field_id('collapse_participants'); ?>"><?php _e('Collapse participants list', lepress_textdomain); ?></label></div>
           <?php if($LePress->isTeacherFeatures()) { ?>
           	<input class="checkbox" id="<?php echo $this->get_field_id('collapse_assignments'); ?>" <?php echo $collapse_assignments ? 'checked="checked"': ''; ?> name="<?php echo $this->get_field_name('collapse_assignments'); ?>" type="checkbox" value="1" /> <label for="<?php echo $this->get_field_id('collapse_assignments'); ?>"><?php _e('Collapse assignments list (teacher)', lepress_textdomain); ?></label></p>
           <?php } ?>
       <?php
   }

}

//Add LePress widget to widgets_init hook
add_action('widgets_init', create_function('', 'return register_widget("LePressWidget");'));


?>