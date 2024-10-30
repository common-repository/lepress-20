<?php

/**
 * Display calendar with days that have posts as links.
 *
 * The calendar is cached, which will be retrieved, if it exists. If there are
 * no posts for the month, then it will not be displayed.
 *
 * LePress - This is heavily forked version on Wordpress calendar, to display assignments and other course data instead.
 *
 * @since 1.0.0
 *
 * @param bool $initial Optional, default is true. Use initial calendar names.
 * @param bool $echo Optional, default is true. Set to false for return.
 */

function get_calendar_forked($initial = true, $echo = true, $cat_ID = false, $role = false, $teacherWidget = false, $studentWidget = false, $ajax = false) {
	global $wpdb, $m, $monthnum, $year, $wp_locale, $posts, $LePress;
	
	//If cached role is teacher but now teacher features are not enabled anymore, clear role
	if($role == 'teacher' && !$teacherWidget) {
		$role = "";
	}
	
	//If cached role is student but now student features are not enabled anymore, clear role
	if($role == 'student' && !$studentWidget) {
		$role = "";
	}
	
	if($ajax) {
		$year = $_GET['y'];
		$monthnum = $_GET['m'];
	}

	$cache = array();
	$key = md5( $m . $monthnum . $year . '_lepress' );
	if ( !$cache = wp_cache_get( 'get_calendar', 'lepress-calendar' ) ) {
		if ( is_array($cache) && isset( $cache[ $key ] ) ) {
			if ( $echo ) {
				echo apply_filters( 'get_calendar',  $cache[$key] );
				return;
			} else {
				return apply_filters( 'get_calendar',  $cache[$key] );
			}
		}
	}

	if ( !is_array($cache) )
		$cache = array();

	// Quick check. If we have no posts at all, abort!
	if ( !$posts && !$ajax ) {
		if($role == 'teacher') {
			$args = array('meta_key' => '_is-lepress-assignment', 'meta_value' => 1);
			$gotsome = get_posts($args);
		} else {
			$gotsome = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM ".$studentWidget->getAssignmentsTable()));
		}
		if ( $gotsome ) {
			$cache[ $key ] = '';
			wp_cache_set( 'get_calendar', $cache, 'lepress-calendar' );
			return;
		}
	}

	if ( isset($_GET['w']) )
		$w = ''.intval($_GET['w']);

	// week_begins = 0 stands for Sunday
	$week_begins = intval(get_option('start_of_week'));

	// Let's figure out when we are
	if ( !empty($monthnum) && !empty($year) ) {
		$thismonth = ''.zeroise(intval($monthnum), 2);
		$thisyear = ''.intval($year);
	} elseif ( !empty($w) ) {
		// We need to get the month from MySQL
		$thisyear = ''.intval(substr($m, 0, 4));
		$d = (($w - 1) * 7) + 6; //it seems MySQL's weeks disagree with PHP's
		$thismonth = $wpdb->get_var("SELECT DATE_FORMAT((DATE_ADD('{$thisyear}0101', INTERVAL $d DAY) ), '%m')");
	} elseif ( !empty($m) ) {
		$thisyear = ''.intval(substr($m, 0, 4));
		if ( strlen($m) < 6 )
				$thismonth = '01';
		else
				$thismonth = ''.zeroise(intval(substr($m, 4, 2)), 2);
	} else {
		$thisyear = gmdate('Y', current_time('timestamp'));
		$thismonth = gmdate('m', current_time('timestamp'));
	}

	$unixmonth = mktime(0, 0 , 0, $thismonth, 1, $thisyear);
	$last_day = date('t', $unixmonth);

	$previous = false;
	$next = false;
	$gotsome_assignments = array();
	$sorted = array();
	//Raw assignments data
	$student_raw_assignments = array();

	if($role == 'teacher') {
		if(is_int($cat_ID) && $cat_ID > 0) {
			$args = array('meta_key' => '_is-lepress-assignment', 'meta_value' => 1, 'category' => $cat_ID, 'numberposts' => -1);
			$gotsome_assignments = get_posts($args);
		}

		//Add assignments to array with key = assignment_end_date => $post
		foreach($gotsome_assignments as $post) {
			$assignment_end = get_post_meta($post->ID, '_lepress-assignment-end-date', true);
			$sorted[strtotime($assignment_end)][] = $post;
		}
		//Get prev link
		krsort($sorted);
		foreach($sorted as $end_time => $post) {
			if($end_time < strtotime($thisyear.'-'.$thismonth.'-01')) {
				$previous = (object) 'lepress_prev';
				$previous->year = gmdate('Y', $end_time);
				$previous->month = gmdate('n', $end_time);
				break;
			}
		}
		//Get next link
		ksort($sorted);
		foreach($sorted as $end_time => $post) {
			if($end_time > strtotime($thisyear.'-'.$thismonth.'-'.$last_day.' 23:59:59')) {
				$next = (object) 'lepress_next';
				$next->year = gmdate('Y', $end_time);
				$next->month = gmdate('n', $end_time);
				break;
			}
		}
	} elseif ($role == 'student') {
		//Student previous month
		$previous = $wpdb->get_row("SELECT MONTH(end_date) AS month, YEAR(end_date) AS year "
			."FROM ".$studentWidget->getAssignmentsTable()." "
			."WHERE end_date < '{$thisyear}-{$thismonth}-01 00:00:00' "
			."AND course_id = '".esc_sql($cat_ID)."' ORDER BY end_date DESC LIMIT 1");

		$next = $wpdb->get_row("SELECT MONTH(end_date) AS month, YEAR(end_date) AS year "
			."FROM ".$studentWidget->getAssignmentsTable()." "
			."WHERE end_date > '{$thisyear}-{$thismonth}-{$last_day} 23:59:59' "
			."AND course_id = '".esc_sql($cat_ID)."' ORDER BY end_date ASC LIMIT 1");

		$gotsome_assignments = $wpdb->get_results("SELECT ID, post_id, title, url, start_date, end_date, DAYOFMONTH(end_date) as dom "
			."FROM ".$studentWidget->getAssignmentsTable()." "
			."WHERE end_date <= '{$thisyear}-{$thismonth}-{$last_day} 23:59:59' "
			."AND end_date >= '{$thisyear}-{$thismonth}-01 00:00:00' "
			."AND course_id = '".esc_sql($cat_ID)."'");
			
		//Get all assgiments, for assigmnetn list on the sidebar widget
		$student_raw_assignments = $wpdb->get_results("SELECT ID, post_id, title, url, start_date, end_date, DAYOFMONTH(end_date) as dom "
			."FROM ".$studentWidget->getAssignmentsTable()." "
			."WHERE course_id = '".esc_sql($cat_ID)."'");
	}

	//THERES a bug in PHP < 5.3, that causes error in +1 month if day of month is 31
	// http://bugs.php.net/bug.php?id=44073
	//prev and next link
	if(!$previous && $thismonth > gmdate('n')) {
		$previous = (object) 'lepress_prev';
		$current_month = gmdate('Y-n-d', strtotime($thisyear.'-'.$thismonth.'-01 +1 month'));
		$previous->year = gmdate('Y', strtotime($current_month));
		$previous->month = gmdate('n', strtotime($current_month));
	} 
	
	if(!$next && $thismonth < gmdate('n')) {
		$next = (object) 'lepress_prev';
		$current_month = gmdate('Y-n-d', strtotime($thisyear.'-'.$thismonth.'-01 +1 month'));
		$next->year = gmdate('Y', strtotime($current_month));
		$next->month = gmdate('n', strtotime($current_month));
	}

	/* translators: Calendar caption: 1: month name, 2: 4-digit year */
	$calendar_caption = _x('%1$s %2$s', 'calendar caption');
	$calendar_output = '<div class="widget_calendar" id="lepress_widget_calendar">';
	$calendar_output .= '<div id="lepress-assignments-list-of-day"><span id="lepress-date-header"><!-- This is date header conteiner --></span><span id="lepress-list-content"><!-- this is for content --></span></div>';
	$calendar_output .= '<table id="wp-calendar" summary="' . esc_attr__('Calendar') . '">
	<caption>' . sprintf($calendar_caption, $wp_locale->get_month($thismonth), date('Y', $unixmonth)) . '</caption>
	<thead>
	<tr>';

	$myweek = array();

	for ( $wdcount=0; $wdcount<=6; $wdcount++ ) {
		$myweek[] = $wp_locale->get_weekday(($wdcount+$week_begins)%7);
	}

	foreach ( $myweek as $wd ) {
		$day_name = (true == $initial) ? $wp_locale->get_weekday_initial($wd) : $wp_locale->get_weekday_abbrev($wd);
		$wd = esc_attr($wd);
		$calendar_output .= "\n\t\t<th scope=\"col\" title=\"$wd\">$day_name</th>";
	}

	$calendar_output .= '
	</tr>
	</thead>

	<tfoot>
	<tr>';

	if ( $previous ) {
		$calendar_output .= "\n\t\t".'<td colspan="3" id="prev"><a href="' .add_query_arg(array('c'=> $role.'-'.$cat_ID, 'lepress-calendar' => 1, 'y' => $previous->year , 'm' => $previous->month), get_bloginfo('siteurl')) . '" onclick="return lepress_previous_month(this);" title="' . esc_attr( sprintf(__('View posts for %1$s %2$s'), $wp_locale->get_month($previous->month), date('Y', mktime(0, 0 , 0, $previous->month, 1, $previous->year)))) . '">&laquo; ' . $wp_locale->get_month_abbrev($wp_locale->get_month($previous->month)) . '</a></td>';
	} else {
		$calendar_output .= "\n\t\t".'<td colspan="3" id="prev" class="pad">&nbsp;</td>';
	}

	$calendar_output .= "\n\t\t".'<td class="pad">&nbsp;</td>';

	if ( $next ) {
		$calendar_output .= "\n\t\t".'<td colspan="3" id="next"><a href="' .add_query_arg(array('c'=> $role.'-'.$cat_ID, 'lepress-calendar' => 1, 'y' => $next->year , 'm' => $next->month), get_bloginfo('siteurl')) . '" onclick="return lepress_next_month(this);" title="' . esc_attr( sprintf(__('View posts for %1$s %2$s'), $wp_locale->get_month($next->month), date('Y', mktime(0, 0 , 0, $next->month, 1, $next->year))) ) . '">' . $wp_locale->get_month_abbrev($wp_locale->get_month($next->month)) . ' &raquo;</a></td>';
	} else {
		$calendar_output .= "\n\t\t".'<td colspan="3" id="next" class="pad">&nbsp;</td>';
	}

	$calendar_output .= '
	</tr>
	</tfoot>

	<tbody>
	<tr>';

	$dayswithposts = array();

	// Get days with posts
	//Get the real end date of our assignment
	foreach($gotsome_assignments as $post) {
		if($role == 'teacher') {
			$day_of_post_query = get_post_meta($post->ID, '_lepress-assignment-end-date', true);
			$tstamp = strtotime($day_of_post_query);
			//Check if post/assignment is on current month
			if($thismonth == gmdate('n', $tstamp)) {
				$day_of_post = gmdate('j', $tstamp);
				$dayswithposts[] = $day_of_post;
			}
		} elseif ($role == 'student')  {
			$dayswithposts[] = $post->dom;
		}

	}

	$daywithpost = array();

	if ( $dayswithposts ) {
		foreach ( (array) $dayswithposts as $daywith ) {
			$daywithpost[] = $daywith;
		}
	} else {
		$daywithpost = array();
	}

	if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false || stripos($_SERVER['HTTP_USER_AGENT'], 'camino') !== false || stripos($_SERVER['HTTP_USER_AGENT'], 'safari') !== false)
		$ak_title_separator = "\n";
	else
		$ak_title_separator = ', ';

	$ak_titles_for_day = array();

	if ( $gotsome_assignments ) {
		foreach ( (array) $gotsome_assignments as $ak_post_title ) {
				if($role == "teacher") {
					$post_title = esc_attr( apply_filters( 'the_title', $ak_post_title->post_title, $ak_post_title->ID ) );
					//Get assignment end date as dom (day of month), so it will be displayed on end date not on start date
					$day_of_post_query = get_post_meta($ak_post_title->ID, '_lepress-assignment-end-date', true);
					$dom = gmdate('j', strtotime($day_of_post_query));
					$url = get_permalink($ak_post_title->ID);
				} else {
					$post_title = esc_attr( apply_filters( 'the_title', $ak_post_title->title, $ak_post_title->ID ) );
					$dom = $ak_post_title->dom;
					$url = $ak_post_title->url;
				}

				if ( empty($ak_titles_for_day["$dom"]) )
					$ak_titles_for_day["$dom"] = '';
				if ( empty($ak_titles_for_day["$dom"]) ) // first one
					$ak_titles_for_day["$dom"][] = array('post_id' => ($role == 'teacher' ? $ak_post_title->ID : $ak_post_title->post_id), 'post_title' => $post_title, 'url' => $url);
				else
					$ak_titles_for_day["$dom"][] = array('post_id' => ($role == 'teacher' ? $ak_post_title->ID : $ak_post_title->post_id), 'post_title' => $post_title, 'url' => $url);

		}
	}

	// See how much we should pad in the beginning
	$pad = calendar_week_mod(date('w', $unixmonth)-$week_begins);
	if ( 0 != $pad )
		$calendar_output .= "\n\t\t".'<td colspan="'. esc_attr($pad) .'" class="pad">&nbsp;</td>';

	$daysinmonth = intval(date('t', $unixmonth));
	for ( $day = 1; $day <= $daysinmonth; ++$day ) {
		if ( isset($newrow) && $newrow )
			$calendar_output .= "\n\t</tr>\n\t<tr>\n\t\t";
		$newrow = false;

		if ( $day == gmdate('j', current_time('timestamp')) && $thismonth == gmdate('m', current_time('timestamp')) && $thisyear == gmdate('Y', current_time('timestamp')) )
			$calendar_output .= '<td id="today">';
		else
			$calendar_output .= '<td>';

		if ( in_array($day, $daywithpost))  { // any posts today?
			//Let's display assignments on calendar
			$title_str = '';
			//echo $day." - ".$ak_titles_for_day[$day]; Student had some sort of bug here ?
			if(is_array($ak_titles_for_day[$day])) {
				foreach($ak_titles_for_day[$day] as $post_data) {
					$title_str .= $post_data['post_title']."\n";
				}
			}
			if($title_str == '') {
				$calendar_output .= $day;
			} else {
				$calendar_output .= '<span id="lepress-assignments-on-day-'.$day.'" class="lepress-hidden">';
				foreach($ak_titles_for_day[$day] as $post_data) {
					$draft = false;
					if($role == 'student') {
						if(is_user_logged_in()) {
							$status = $studentWidget->subscriptions->getAssignmentStatus($post_data['post_id']);
							if($status) {
								$title = __('Work submitted', lepress_textdomain);
								$calendar_output .= '<a href="'.get_permalink($status).'#comments"><img src="'.lepress_http_abspath.'img/done.png" alt="'.$title.'" title="'.$title.'" />';
							} else {
								$post_id = $studentWidget->subscriptions->getAssignmentStatus($post_data['post_id'], true);
								$post = get_post($post_id);
								if($post && ($post->post_status == "draft" || $post->post_status == "future")) {
									$title = __('Edit work', lepress_textdomain);
									$calendar_output .= '<a href="'.add_query_arg(array('post' => $post_id, 'action' => 'edit'), admin_url().'post.php').'" title="'.$title.'">';
									$draft = true;
								} else {
									$title = __('Submit work', lepress_textdomain);
									$calendar_output .= '<a href="'.add_query_arg(array('p' => $post_data['post_id'], 'c' => $cat_ID), admin_url().'post-new.php').'" title="'.$title.'">';
								}
								
								$calendar_output .= '<img src="'.lepress_http_abspath.'img/pencil.png" alt="'.$title.'" />';
							}
						}
						if(!isSet($title)) {
							$calendar_output .= '<a href="'.$post_data['url'].'" title="'.__('View assignment on teacher\'s blog', lepress_textdomain).'">';
						}
						$calendar_output .= $post_data['post_title'].'</a>'.($draft ? ' - '.__('draft', lepress_textdomain) : '').'<br />';
					} elseif($role == 'teacher') {
						$calendar_output .= ' <a href="'.$post_data['url'].'" title="'.$post_data['post_title'].'" onmouseover="showTeacherListHandler('.$post_data['post_id'].')">'.$post_data['post_title'].'</a><br />';
						if(is_user_logged_in()) {
							$course_meta = new CourseMeta($cat_ID);
							if($course_meta->getIsCourse()) {
								$calendar_output .= '<ul class="lepress-hidden" id="lepress-assign-ul-'.$post_data['post_id'].'">';
								$display_name_order = $LePress->getDisplayNames();
								foreach($course_meta->getApprovedSubscriptions() as $subscription) {
									$name = $display_name_order == 1 ? $subscription->first_name.' '.$subscription->last_name : $subscription->last_name.' '.$subscription->first_name;
									$comment = $LePress->fetchComment($post_data['post_id'], $subscription->email, 'LePressStudent');
									$meta_data = get_post_meta($post_data['post_id'], '_lepress-student-'.md5($post_data['post_id'].$subscription->email), true);
									$calendar_output .= '<li>';
									if($comment) {
										if(!$meta_data['feedback']) {
											$title = __('Send feedback', lepress_textdomain);
											$feedback_link_img = '<img src="'.lepress_http_abspath.'img/pencil.png" alt="'.$title.'" />';
										} else {
											//Feedback given
											$title = __('Change feedback', lepress_textdomain);
											$feedback_link_img = '<img src="'.lepress_http_abspath.'img/done.png" alt="'.$title.'" />';
										}
										$calendar_output .= '<a href="'.add_query_arg(array('page' => 'lepress-classbook', 'p' => $post_data['post_id'], 'c' => $cat_ID, 's' => $subscription->id), admin_url().'admin.php').'" title="'.$title.'">'.$feedback_link_img;
									} else {
										$calendar_output .= '<img src="'.lepress_http_abspath.'img/not_done.png" alt="'.__('Not accomplished', lepress_textdomain).'" title="'.__('Not accomplished', lepress_textdomain).'" />';
									}
									if($meta_data['post_url']) {
										$calendar_output .= $name.'</a></li>';
									} else {
										$calendar_output .= $name.'</li>';
									}
								}
								$calendar_output .= '</ul>';
							}
						}
					}
				}
				$calendar_output .= '</span>';
				$calendar_output .= '<a href="#" class="lepress-assign-day" rel="'.$day.'" title="'.$LePress->date_i18n_lepress(strtotime($day.'-'.$thismonth.'-'.$thisyear)).'">'.$day.'</a>';
			}
		} else {
			$calendar_output .= $day;
		}
		$calendar_output .= '</td>';

		if ( 6 == calendar_week_mod(date('w', mktime(0, 0 , 0, $thismonth, $day, $thisyear))-$week_begins) )
			$newrow = true;
	}

	$pad = 7 - calendar_week_mod(date('w', mktime(0, 0 , 0, $thismonth, $day, $thisyear))-$week_begins);
	if ( $pad != 0 && $pad != 7 )
		$calendar_output .= "\n\t\t".'<td class="pad" colspan="'. esc_attr($pad) .'">&nbsp;</td>';

	$calendar_output .= "\n\t</tr>\n\t</tbody>\n\t</table>";

	//Add some more data, assignments, participants

	//Easy subscribe form, if teacher
	if($role == "teacher") {
		$course_meta = new CourseMeta($cat_ID);
		if($course_meta->getIsCourse()) {
			$calendar_output .= getEasySubscribeForm($cat_ID);
		}
	}
	
	//Teachers

	if($role == 'student') {
		$calendar_output .= getTeachers($cat_ID, $studentWidget);
	}

	//Participants
	$calendar_output .= getParticipantsList($cat_ID, $role, $teacherWidget, $studentWidget);

	//Assignments
	$assignments = ($role == 'teacher') ? $sorted : $student_raw_assignments;
	$calendar_output .= getAssignmentsList($assignments, $thismonth, $thisyear, $last_day, $role, $cat_ID, $studentWidget); // Have to pass $sorted array of assignments
	
	//Add division end
	$calendar_output .= '</div>';

	$cache[ $key ] = $calendar_output;
	wp_cache_set( 'get_calendar', $cache, 'lepress-calendar' );

	if ( $echo )
		echo apply_filters( 'get_calendar',  $calendar_output );
	else
		return apply_filters( 'get_calendar',  $calendar_output );

}

/** 
 * Get assignments list
 *
 * This method outputs assignments list
 * 
 * @return string html
 */

function getAssignmentsList($assignments, $thismonth, $thisyear, $last_day, $role, $cat_ID, $studentWidget = false) {
	global $LePress, $widget_instance;
	if(isSet($_GET['c'])) {
		$query_params = array('c' => $_GET['c']);
	}
	$class = '';
	if($widget_instance['collapse_assignments'] && $role == 'teacher') {
		$class = 'class="lepress-hidden"';
	}
	
	/* These parameters come with AJAX call */
	if(isSet($_GET['a'])) {
		if($_GET['a'] != 1) {
			$class = 'class="lepress-hidden"';
		}
	}
	$output = '<h3 class="widget-title">'.apply_filters('widget_title', __('Assignments', lepress_textdomain)).' <span class="expand-collapse-lepress" rel="lepress-assignments">'.getExpandCollapseIMG($class).'</span></h3>';
	$output .= '<div id="lepress-assignments" '.$class.'>';
	
	//Iterate through all the assignments
	foreach($assignments as $end_time_key => $post) {

		if($role == 'student') {
			$end_time_key = strtotime($post->end_date);
			$start_ts = strtotime($post->start_date);
		} elseif($role == 'teacher') {
			$start_ts = strtotime(get_post_meta($post->ID, '_lepress-assignment-start-date', true));
		}
		
		$assignment_added = true;
		if(is_array($post)) {
			foreach($post as $post) {
				$post_title = ($role == 'teacher' ? $post->post_title : $post->title);
				if($role == 'student') {
					$start_ts = strtotime($post->start_date);
				} elseif($role == 'teacher') {
					$start_ts = strtotime(get_post_meta($post->ID, '_lepress-assignment-start-date', true));
				}
				$output .= convertToEventhCard($post_title, $start_ts, $end_time_key);
				$output .= ' <a href="'.($role == 'teacher' ? get_permalink($post->ID) : $post->url).'" title="'.$post_title.'" onmouseover="showTeacherListHandler('.$post->ID.', true)">'.$post_title.' ('.$LePress->date_i18n_lepress($end_time_key).')</a><br />';
				if(is_user_logged_in()) {
					$course_meta = new CourseMeta($cat_ID);
					if($course_meta->getIsCourse()) {
						$output .= '<ul class="lepress-hidden" id="lepress-assign-ul-left-'.$post->ID.'">';
						$display_name_order = $LePress->getDisplayNames();
						foreach($course_meta->getApprovedSubscriptions() as $subscription) {
							$name = $display_name_order == 1 ? $subscription->first_name.' '.$subscription->last_name : $subscription->last_name.' '.$subscription->first_name;
							$comment = $LePress->fetchComment($post->ID, $student->email, 'LePressStudent');
							$meta_data = get_post_meta($post->ID, '_lepress-student-'.md5($post->ID.$subscription->email), true);
							$output .= '<li>';
							if($comment) {
								//No feedback given
								if(!$meta_data['feedback']) {
									$title = __('Send feedback', lepress_textdomain);
									$feedback_link_img = '<img src="'.lepress_http_abspath.'img/pencil.png" alt="'.$title.'" />';
								} else {
									//Feedback given
									$title = __('Change feedback', lepress_textdomain);
									$feedback_link_img = '<img src="'.lepress_http_abspath.'img/done.png" alt="'.$title.'" />';
								}
								$output .= '<a href="'.add_query_arg(array('page' => 'lepress-classbook', 'p' => $post->ID, 'c' => $cat_ID, 's' => $subscription->id), admin_url().'admin.php').'" title="'.$title.'">'.$feedback_link_img;
							} else {
								$output .= '<img src="'.lepress_http_abspath.'img/not_done.png" alt="'.__('Not accomplished', lepress_textdomain).'" title="'.__('Not accomplished', lepress_textdomain).'" />';
							}
							if($meta_data['post_url']) {
								$output .= $name.'</a></li>';
							} else {
								$output .= $name.'</li>';
							}
						}
						$output .= '</ul>';
					}
				}
			}
		} else {
			$post_title = ($role == 'teacher' ? $post->post_title : $post->title);
			$output .= convertToEventhCard($post_title, $start_ts, $end_time_key);
			if($role == 'student') {
				$draft = false;
				if(is_user_logged_in()) {
					$status = $studentWidget->subscriptions->getAssignmentStatus($post->post_id);
					if($status) {
						$title = __('Work submitted', lepress_textdomain);
						$output .= '<a href="'.get_permalink($status).'#comments"><img src="'.lepress_http_abspath.'img/done.png" alt="'.$title.'" title="'.$title.'" />';
					} else {
						$post_id = $studentWidget->subscriptions->getAssignmentStatus($post->post_id, true);
						$post_student = get_post($post_id);
						if($post_student && ($post_student->post_status == "draft" || $post_student->post_status == "future")) {
							$title = __('Edit work', lepress_textdomain);
							$output .= '<a href="'.add_query_arg(array('post' => $post_id, 'action' => 'edit'), admin_url().'post.php').'" title="'.$title.'">';
							$draft = true;
						} else {
							$title = __('Submit work', lepress_textdomain);
							$output .= '<a href="'.add_query_arg(array('p' => $post->post_id, 'c' => $cat_ID), admin_url().'post-new.php').'" title="'.$title.'">';
						}
						
						$output .= '<img src="'.lepress_http_abspath.'img/pencil.png" alt="'.$title.'" />';
					}
					$output .= $post->title.' ('.$LePress->date_i18n_lepress($end_time_key).')</a>'.($draft ? ' - '.__('draft', lepress_textdomain) : '').'<br />';
				} else {
					$output .= '<a href="'.$post->url.'" title="'.$post->title.'" target="_blank">'.$post->title.' ('.$LePress->date_i18n_lepress($end_time_key).')</a><br/>';
				}
			}
		}
	}

	if(!isSet($assignment_added)){
		$output .= __('There are no assignments in this month.', lepress_textdomain);
	}

	//Add collapse - expand wrapper div end
	$output .= '</div>';
	
	if($role == 'student') {
		$output .= '<h3 class="widget-title">'.apply_filters('widget_title', __('Graduation', lepress_textdomain)).'</h3>';
		$output .= '<div id="lepress-progress"><a href="'.add_query_arg(array('page' => 'lepress-assignments'), admin_url().'admin.php').'">'.__('My progress', lepress_textdomain).'</a></div>';
	}
	
	return $output;
}

/**
 * Convert assignments into hCard events
 *
 * @return string html
 */
 
function convertToEventhCard($post_title, $start_date_ts, $end_date_ts) {
	global $LePress;
	$end_date_8601 = date('Y-m-d', $end_date_ts)."T23:59:59+03:00";
	$start_date_8601 = date('Y-m-d', $start_date_ts)."T00:00+03:0000";
	$output = '<div class="vevent" id="hcalendar-'.str_replace(" ", "-", $post_title).'" style="display:none;">';
	$output .= '<abbr class="dtstart" title="'.$start_date_8601.'">'.$LePress->date_i18n_lepress($start_date_ts).' 10</abbr> : <abbr class="dtend" title="'.$end_date_8601.'">'.$LePress->date_i18n_lepress($end_date_ts).' 12pm</abbr> :';
	$output .= '<span class="summary">'.$post_title.'</span>';
	$output .= '</div>';
	return $output;
}

/**
 * Output collapse/expand images
 *
 * @return string html
 */
 
function getExpandCollapseIMG($class) {
	$out = '<img rel="lepress-expand" src="'.lepress_http_abspath.'img/down-arrow-grayscale.png" style="width: 12px;" alt="'.__('Expand', lepress_textdomain).'" title="'.__('Expand', lepress_textdomain).'" '.(empty($class) ? 'class="lepress-hidden"' : '').'/>';
	$out .= '<img rel="lepress-collapse" src="'.lepress_http_abspath.'img/up-arrow-grayscale.png" style="width: 12px;" alt="'.__('Collapse', lepress_textdomain).'" title="'.__('Collapse', lepress_textdomain).'" '.(empty($class) ? '' : 'class="lepress-hidden"').' />';
	return $out;
}

/** 
 * Get participants list
 *
 * This method outputs participants list
 * 
 * @return string html
 */

function getParticipantsList($cat_ID, $role, $teacherWidget, $studentWidget) {		
	global $widget_instance,$LePress;
	$class = '';
	if($widget_instance['collapse_participants']) {
		$class = 'class="lepress-hidden"';
	}
	
	/* These parameters come with AJAX call */
	if(isSet($_GET['p'])) {
		if($_GET['p'] != 1) {
			$class = 'class="lepress-hidden"';
		}
	}
	
	$output = '<h3 class="widget-title">'.apply_filters('widget_title', __('Participants', lepress_textdomain)).' <span class="expand-collapse-lepress" rel="lepress-participants">'.getExpandCollapseIMG($class).'</span></h3>';
	$output .= '<div id="lepress-participants" '.$class.'>';
	$display_name_order = $LePress->getDisplayNames();
	//If teacher role
	if($role == 'teacher') {
		$course_meta = new CourseMeta($cat_ID);
		if($course_meta->getIsCourse()) {
			foreach($course_meta->getApprovedSubscriptions() as $subscription) {
				$name = $display_name_order == 1 ? $subscription->first_name.' '.$subscription->last_name : $subscription->last_name.' '.$subscription->first_name;
				$output .= '<div id="hcard-'.str_replace(" ", "-", $name).'" class="vcard">';
				$output .= '<a class="url fn" style="display:none;" href="'.$subscription->blog_url.'">'.$name.'</a>';
				$output .= '<span class="fn" style="display:none;">'.$name.'</span>';
				$output .= '<a class="email" href="mailto:'.str_replace('@', '[at]', $subscription->email).'" onmouseover="removeEmailMask(this);" onmouseout="addEmailMask(this)"><img src="'.lepress_http_abspath.'img/email.png"alt="Email" /></a><a href="'.$subscription->blog_url.'">'.$name.'</a>';
				$output .= '</div>';
			}
		}
	} elseif($role == 'student') { //If student role
		$mates = $studentWidget->getClassmates($cat_ID);
		foreach($mates as $subscription) {
			$name = $display_name_order == 1 ? $subscription->firstname.' '.$subscription->lastname : $subscription->lastname.' '.$subscription->firstname;
			$output .= '<div id="hcard-'.str_replace(" ", "-", $name).'" class="vcard">';
			$output .= '<a class="url fn" style="display:none;" href="'.$subscription->blog_url.'">'.$name.'</a>';
			$output .= '<span class="fn" style="display:none;">'.$name.'</span>';
			$output .= '<a class="email" href="mailto:'.str_replace('@', '[at]', $subscription->email).'" onmouseover="removeEmailMask(this);" onmouseout="addEmailMask(this)"><img src="'.lepress_http_abspath.'img/email.png" alt="Email" /></a><a href="'.$subscription->blog_url.'">'.$name.'</a>';
			$output .= '</div>';
		}
	}

	//Oops, not participants found
	if(!isSet($subscription)){
		$output .= __('There are no participants in this course.', lepress_textdomain);
	}
	
	//Add collapse - expand wrapper div end
	$output .= '</div>';
	
	return $output;
}

/** 
 * Get teachers / student widget
 *
 * This method outputs teachers list,only called on student side of widget
 * 
 * @return string html
 */

function getTeachers($cat_ID, $studentWidget) {
	global $LePress;
	$output = '<h3 class="widget-title">'.apply_filters('widget_title', __('Teacher(s)', lepress_textdomain)).'</h3>';
	$teachers = $studentWidget->getTeachers($cat_ID);
	$display_name_order = $LePress->getDisplayNames();
	
	$output .= '<div id="lepress-teachers">';
	foreach($teachers as $teacher) {
		$name = $display_name_order == 1 ? $teacher->firstname.' '.$teacher->lastname : $teacher->lastname.' '.$teacher->firstname;
		$output .= '<div id="hcard-'.str_replace(" ", "-", $name).'" class="vcard">';
		if(!empty($teacher->organization)) {
			$output .= '<div class="org" style="display:none;">'.$teacher->organization.'</div>';
		}
		$output .= '<a class="url fn" style="display:none;" href="'.$teacher->course_url.'">'.$name.'</a>';
		$output .= '<span class="fn" style="display:none;">'.$name.'</span>';
		$output .= '<a class="email" href="mailto:'.str_replace('@', '[at]', $teacher->email).'" onmouseover="removeEmailMask(this);" onmouseout="addEmailMask(this)"><img src="'.lepress_http_abspath.'img/email.png" alt="Email"/></a>';
		$output .= '<a class="email" href="'.$teacher->course_url.'">'.$name.'</a></div>';
	}

	if( count( $teachers ) == 0 ){
		$output .= __('Oops, teacher(s) missing...', lepress_textdomain);
	}
	$output .= '</div>';
	return $output;
}

/**
 * Output easy subscribe form 
 *
 * This method outputs easy subscription form, depending on if user is logged in or not.
 * If user is not logged in, simple URL textbox is printed otherwise AJAX handler and auto discovered
 * blog data
 *
 * @return string html
 */

function getEasySubscribeForm($cat_ID) {
	$found_blog = false;
	if(is_user_logged_in()) {
		$current_user = wp_get_current_user();
		$course_meta = new CourseMeta($cat_ID);
		global $LePress;
		if($course_meta->getStudentSubscriptionByEmail($current_user->user_email) || $LePress->getBlogOwnerUser()->ID == $current_user->ID) {
			return '';
		}
		if(is_multisite()) {
			$user_blogs = get_blogs_of_user($current_user->ID);
			foreach($user_blogs as $blog) {
				/* Iterate through blogs and find one with active LePress plugin */
				$switched = switch_to_blog($blog->userblog_id, true);
				if($switched) {
					global $LePress;
					$student_features_enabled = $LePress->isStudentFeatures();
					if($student_features_enabled) {
						$found_blog = $blog;
						break;
					}
				}
			}
			//revert back to current blog
			restore_current_blog();
		}
	}
	
	$output = '<h3 class="widget-title">'.apply_filters('widget_title', __('Subscribe', lepress_textdomain)).' <span class="expand-collapse-lepress" rel="lepress-simple-subscribe">'.getExpandCollapseIMG(true).'</span></h3>';
	$output .= '<div id="lepress-simple-subscribe" class="lepress-hidden">';
	$output .= '<form action="'.add_query_arg(array('lepress-service' => 1), false).'" method="post" id="lepress-simple-subscribe">';
	$output .= '<input type="hidden" name="course-url" value="'.get_category_link($cat_ID).'" />';
	$output .= '<input type="hidden" name="lepress-course-id" value="'.$cat_ID.'" />';
	if(!$found_blog) {
		$output .= '<b>'.__('Enter your blog URL (http://www.example.com/blog)', lepress_textdomain).':</b>';
		$output .= '<input type="text" name="simple-subscriber-blog" class="subscriber-blog" />';
	} else {
		$output .= __('We have discovered your first active blog with LePress student roll enabled.', lepress_textdomain);
		$output .= '<br /><a href="'.get_blogaddress_by_id($found_blog->userblog_id).'">'.$found_blog->blogname.'</a><br />';
		$output .= __('By clicking "Subscribe", you will be subscribed to the course using found blog.', lepress_textdomain);
		$output .= '<input type="hidden" value="'.$found_blog->userblog_id.'" name="lepress_user_blog_id" />';
		$output .= '<input type="hidden" value="'.$current_user->ID.'" name="lepress_user_ID" />';
	}
	$output .= '<div id="lepress-simple-message"></div>';
	$output .= '<img src="'.lepress_http_abspath.'img/ajax-loader-small.gif" style="visibility:hidden;" id="small-lepress-ajax-loader"  alt="Small Ajax loader" />';
	$output .= '<input type="submit" value="'.__('Subscribe', lepress_textdomain).'" name="simple-subscriber-blog-submit" id="subscriber-blog-submit" />';
	$output .= '</form>';
	$output .= '</div>';
	return $output;
}

?>