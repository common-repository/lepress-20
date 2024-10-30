<?php

/**
 * Classbook 
 *
 * Handles all the assignments and converting previous posts into assignments
 */

//IF post request found, we are trying to convert previous posts into assignments
if(isSet($_POST['is-lepress-assignment'])) {
	$post_ids = $_POST['post_id'];
	if(is_array($post_ids)) {
		//iterate through all the selected post_ids
		foreach($post_ids as $post_id) {
			$this->savePostMeta($post_id);
		}
	}
}

//Page switcher - manage-assignemt or send feedback or just classbook
if(isSet($_GET['p']) && isSet($_GET['c']) && !isSet($_GET['s'])) {
	require_once('manage-assignment.php');
} else if(isSet($_GET['p']) && isSet($_GET['c']) && isSet($_GET['s'])) {
	require_once('send-feedback.php');
} else {
?>
	<div class="wrap">

			<h2><?php _e('Manage assignments', lepress_textdomain); ?></h2>
				<p><?php _e('Assignments marked as <b>drafts</b> are <b>not visible</b> to the students.', lepress_textdomain); ?></p>
				<form method="post" id="manage_assignments_form" action="<?php echo admin_url().'admin.php?page=lepress-classbook';?>">

					<div>

						<?php _e('Course', lepress_textdomain); ?>: <select name="course_id" id="course_id" onChange="this.form.submit()">

						<?php
							$selected_cat_ID = 0;
							$cats = get_categories('hide_empty=0');
							//Print course dropdown menu
						   	foreach($cats as $cat) {
						   		$selected = "";
						   		$course_meta = new CourseMeta($cat->cat_ID);
							   	if($course_meta instanceOf CourseMeta) {
							   		if($course_meta->getIsCourse()) {
						   				if($selected_cat_ID == 0) {
								   			$selected_cat_ID = $cat->cat_ID;
								   		}
								   		if(isSet($_POST['course_id']) || isSet($_GET['c'])) {
								   			$selected_cat_ID = intval($_POST['course_id'] ? $_POST['course_id'] : $_GET['c']);
								   		}

								   		if($cat->cat_ID == $selected_cat_ID) {
								   			$selected = 'selected="selected"';
								   		}
							   			echo	'<option value="'.$cat->cat_ID.'" '.$selected.'>'.$cat->name.'</option>';
							   			$course_exist = true;
							   		}
							   	}
						   	}

						if(!$course_exist) {
							echo '<option value="-1">'.__('No courses', lepress_textdomain).'</option>';
						}
						?>

						</select>

					</div>

				</form>

				<div>
					<?php
						$course_meta = new CourseMeta($selected_cat_ID);
						$assignments = array();
						//Get all assignments for selected curse
						if($course_meta->getIsCourse()) {
							$assignments = get_posts(array('meta_query' => array(array('key' => '_is-lepress-assignment', 'value' => 1)), 'post_status' => 'draft|future|publish', 'numberposts' => -1, 'category' => $selected_cat_ID));
						}
							?>

								<table id="list-assignments" class="widefat fixed" cellspacing="0" style="margin-top: 10px;">

									<thead>

										<tr>

											<th scope="col" class="manage-column column-assignment"><?php _e('Assignment', lepress_textdomain); ?></th>
											<th scope="col" class="manage-column column-start"><?php _e('Start', lepress_textdomain); ?></th>
											<th scope="col" class="manage-column column-end"><?php _e('End', lepress_textdomain); ?></th>

											<th scope="col" class="manage-column column-ungraded"><?php _e('Ungraded', lepress_textdomain); ?></th>
											<th scope="col" class="manage-column column-students"><?php _e('View', lepress_textdomain); ?></th>

										</tr>

									</thead>

									<tbody id="list-assignments-body">

						<?php

							if( count( $assignments ) == 0 ){

								echo '<tr><td colspan="5">'.__('There is no assessments in this course.', lepress_textdomain).'</td></tr>';

							}

							foreach( $assignments as $post ) {

								echo '<tr><td><a href="	'.admin_url().'admin.php?page=lepress-classbook&p='.$post->ID.'&c='.$selected_cat_ID.'"><b>' . $post->post_title.'</b></a><b>'.($post->post_status == 'draft' ? ' - '.__('draft', lepress_textdomain) : '').'</b></td>';
								echo '<td>'.$this->date_i18n_lepress(strtotime(get_post_meta($post->ID, '_lepress-assignment-start-date', true))).'</td><td>'.$this->date_i18n_lepress(strtotime(get_post_meta($post->ID, '_lepress-assignment-end-date', true))).'</td>';
								echo '<td>'.$course_meta->getUngradedCount($post->ID).'</td><td><a href="' . get_permalink($post->ID) . '" target="_blank">'.__('View assignment', lepress_textdomain).'</a></td></tr>';
							}
						?>
									</tbody>
								</table>


				</div>

					<div>

					<h2><?php _e('Available posts', lepress_textdomain); ?></h2>
					<p><?php _e('If post is in <b>multiple categories</b>, it is <b>moved to current</b> chosen category only.', lepress_textdomain); ?></p>
					<form method="post" action="<?php echo add_query_arg(array('page' => $_GET['page']), admin_url()."admin.php"); ?>">
						<input type="hidden" name="is-lepress-assignment"	value="1" />
						<input type="hidden" name="course_id" value="<?php echo $selected_cat_ID; ?>"/>
						<table class="widefat fixed" cellspacing="0">
							<thead>

										<tr>

											<th scope="col" class="manage-column check-column"><input type="checkbox"/></th>

											<th scope="col" class="manage-column column-title"><?php _e('Title', lepress_textdomain); ?></th>
											<th scope="col" class="manage-column column-excerpt"><?php _e('Excerpt', lepress_textdomain); ?></th>
											<th scope="col" class="manage-column column-link"><?php _e('View', lepress_textdomain); ?></th>

										</tr>

									</thead>
							<tbody id="available-posts">



							<?php
							//Print regular posts table, for converting them later to assignments
							$regular_posts = array();
							if($course_meta->getIsCourse()) {
								$regular_posts = get_posts(array('post_status' => 'publish|draft|future', 'numberposts' => -1, 'category' => $selected_cat_ID));
							}
							$reqular_posts_found = false;

							foreach($regular_posts as $post){
								//Have make sure, $post really is a regular post
								$is_assignment  = get_post_meta($post->ID, '_is-lepress-assignment', true);
								$student_answer_meta = get_post_meta($post->ID, '_lepress-assignment-meta', true);
								//if metadata not found, proceed
								if(!$is_assignment && !$student_answer_meta && $post->post_status != 'auto-draft') {
									$regular_posts_found = true;
									if($post->post_status == "draft") {
										$post->post_title .= ' ('.__('draft', lepress_textdomain).')';
									}
									echo '<tr><th scope="row" class="check-column"><input type="checkbox" name="post_id[]" value="'.$post->ID.'"/></th><td class="column-title">'.$post->post_title.'</td>';
									echo '<td class="column-excerpt">'.$post->post_excerpt.'</td><td class="column-link"><a href="'.get_permalink($post->ID).'" target="_blank">'.__('View post', lepress_textdomain).'</a></td></tr>';

								}
							}
							if(!$regular_posts_found) {
								echo '<tr><td colspan="4">'.__('No regular posts in this course', lepress_textdomain).'</td></tr>';
							}

							?>

						</tbody>
						</table>
						<?php if($regular_posts_found) { ?>

						<input type='submit' class="button-primary" value='<?php _e('Make into assignment', lepress_textdomain); ?>' />
						<?php } ?>

					</form>

					</div>



	</div>
<?php
}
?>