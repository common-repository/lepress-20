<?php
/**
 * Assignment manage page
 *
 * Handles assignment and participants list for that assignment
 */
 
?>
<div class="wrap">
<h2><?php _e('Manage assignment', lepress_textdomain); ?></h2>

<?php

//Get some parameters from the URL
$course_id = intval($this->safeStringInput($_GET['c'], true));
$post_id = intval($this->safeStringInput($_GET['p'], true));

//If post request found, update dates
if(isSet($_POST['updateDates'])) {
	$this->savePostMeta($post_id);
}
echo '<p><a href="'.admin_url().'admin.php?page=lepress-classbook&c='.$course_id.'">'.__('Go back', lepress_textdomain).'</a></p>';

//IF active course, proceed
$course_meta =  new CourseMeta($course_id);
if($course_meta->getIsCourse()) {
	$posts_query = get_posts(array('meta_query' => array(array('key' => '_is-lepress-assignment', 'value' => 1)), 'p' => $post_id, 'category' => $course_id, 'post_status' => 'publish', 'numberposts' => 1));
	$post = $posts_query[0];
	if($post) {
		echo '<a href="' . get_permalink($post->ID) . '"><h3>' . $post->post_title . '</h3></a>';
		?>
	
		<form method="post" onsubmit="return validate_form(this)">
	
			<input type="hidden" name="updateDates" value="1" />
			<input type="hidden" name="is-lepress-assignment"	value="1" />
	
	
			<div><?php _e('Start', lepress_textdomain); ?>: <input  type="text" name="lepress-assignment-start-date"  id="lepress-assignment-start-date"  value="<?php
	
				echo strtotime(get_post_meta($post->ID, '_lepress-assignment-start-date', true));
	
			?>" /> <?php _e('End', lepress_textdomain); ?>: <input type="text" name="lepress-assignment-end-date" id="lepress-assignment-end-date" value="<?php
	
				echo strtotime(get_post_meta($post->ID, '_lepress-assignment-end-date', true));
	
			?>"/><input type='submit' class="button-primary" value='<?php _e('Save'); ?>' />
	
			</div>
	
	
		</form>
	
		<table id="filed-assignment-<?php echo $post->ID; ?>" class="widefat fixed" cellspacing="0">
	
			<thead>
	
				<tr>
	
					<th scope="col" class="manage-column column-name"><?php _e('Name', lepress_textdomain); ?></th>
	
					<th scope="col" class="manage-column column-status"><?php _e('Status', lepress_textdomain); ?></th>
	
					<th scope="col" class="manage-column column-grade" style="width:90px"><?php _e('Grade', lepress_textdomain); ?></th>
	
					<th scope="col" class="manage-column column-feedback"><?php _e('Feedback', lepress_textdomain); ?></th>
	
					<th scope="col" class="manage-column column-actions"><?php _e('Actions', lepress_textdomain); ?></th>
					<th scope="col" class="manage-column column-time"><?php _e('Submission time', lepress_textdomain); ?></th>
	
				</tr>
	
			</thead>
	
			<tbody id="filed-assignments-body">
	
				<?php
					$participantsFound = false;
					//Print all the participants
					$display_name_order = $this->getDisplayNames();
					foreach($course_meta->getApprovedSubscriptions() as $subscription){
						$participantsFound = true;
						$name = $display_name_order == 1 ? $subscription->first_name.' '.$subscription->last_name : $subscription->last_name.' '.$subscription->first_name;
						echo '<tr>';
						echo '<td>'.$name.'</td>';
						$comment = $this->fetchComment($post->ID, $subscription->email, 'LePressStudent');
						$meta_data = get_post_meta($post->ID, '_lepress-student-'.md5($post->ID.$subscription->email), true);
						if($comment) {
							echo '<td>'.__('Accomplished', lepress_textdomain).' <a href="'.$meta_data['post_url'].'">'.__('View', lepress_textdomain).'</a></td>';
							echo '<td>'.($meta_data ? $meta_data['grade'] : '').'</td>';
							echo '<td><a href="'.add_query_arg(array('s' => intval($subscription->id)), false).'">'.($meta_data && !$meta_data['feedback'] ? __('Grade & Feedback', lepress_textdomain) : __('Change feedback', lepress_textdomain)).'</a></td>';
						} else {
							echo '<td>'.__('Not accomplished', lepress_textdomain).'</td>';
							echo '<td>&nbsp;</td>';
							echo '<td>&nbsp;</td>';
						}
	
						echo '<td><a href="mailto:'.$subscription->email . '">'.__('Send e-mail', lepress_textdomain).'</a></td>';
						echo '<td>'.($comment ? $this->date_i18n_lepress($comment->comment_date_gmt,false, false, true) : '').'</td>';
	
	
					}
					//Assignment has no participants
					if(!$participantsFound) {
						echo '<tr><td colspan="5">'.__('There is no subscribed students who need to accomplish this assessment.', lepress_textdomain).'</td></tr>';
					}
	
				?>
	
			</tbody>
	
		</table>
	
	<?php
	
	  } else {
	  	echo "<h3>".__('This is somewhat embarrasing, isn\'t it?', lepress_textdomain)."</h3> <p>".__('It seems we can\'t find what you\'re looking for.', lepress_textdomain)."</p>";
	  }
	} else {
		echo "<h3>".__('This is somewhat embarrasing, isn\'t it?', lepress_textdomain)."</h3> <p>".__('It seems we can\'t find what you\'re looking for.', lepress_textdomain)."</p>";
	}
	?>

</div>