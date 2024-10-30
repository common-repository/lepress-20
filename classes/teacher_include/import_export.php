<?php

/**
 * LePress course import/export handler
 */

//IF export_tpl parameter found, proceed with export
if(isSet($_GET['export_tpl'])) {
	$int = intval($_GET['export_tpl']);
	if($int > 0) {
		$course_meta = new CourseMeta($int);
		$result = $course_meta->exportAsTemplate();
		if($result) {
			$this->echoNoticeDiv(__('Template successfully exported', lepress_textdomain));
		} else {
			$this->echoNoticeDiv(__('Template could not be exported', lepress_textdomain), true);
		}
	}
}

//IF rm_tpl parameter found, proceed with removing
if(isSet($_GET['rm_tpl'])) {
	$int = intval($_GET['rm_tpl']);
	if($int > 0) {
		$course_meta = new CourseMeta(false);
		$result = $course_meta->deleteTemplate($int);
		if($result) {
			$this->echoNoticeDiv(__('Template successfully removed', lepress_textdomain));
		} else {
			$this->echoNoticeDiv(__('Error occured, could not remove template, try again...', lepress_textdomain), true);
		}
	}
}

//IF post request, this means we are handeling import
if(isSet($_POST['import_to']) && isSet($_POST['tpl_id'])) {
	$cat_id = intval($_POST['import_to']);
	if($cat_id > 0) {
		$course_meta = new CourseMeta($cat_id);
		if($course_meta->getIsCourse() && !$course_meta->getIsClosed()) {
			$tpl_id = intval($_POST['tpl_id']);
			if($tpl_id > 0) {
				$result = $course_meta->importTemplate($tpl_id);
				if($result) {
					$this->echoNoticeDiv(__('Template successfully imported', lepress_textdomain));
				} else {
					$this->echoNoticeDiv(__('Could not import template, try again...', lepress_textdomain), true);
				}
			}
		}
	}
}

//File Template file upload
if(isSet($_POST['tpl_submit'])) {
	if($_FILES["lepress_tpl_upload"]["error"] == 0) {
		$tpl_xml = file_get_contents($_FILES['lepress_tpl_upload']['tmp_name']);
		$course_meta = new CourseMeta(false);
		$course_meta->importTemplateFromFile($tpl_xml);
	}
}
?>

<div class="wrap">

	<h2><?php _e('Active courses', lepress_textdomain); ?></h2>
		<table class="widefat" cellspacing="0">
			<thead>
				<tr>
					<th scope="col" class="manage-column column-name"><?php _e('Name', lepress_textdomain)?></th>
					<th scope="col" class="manage-column column-actions"><?php _e('Actions', lepress_textdomain)?></th>
				</tr>
			</thead>
			<tbody>
				<?php
					$cats = get_categories('hide_empty=0');
					foreach($cats as $cat) {
						$course_meta = new CourseMeta($cat->cat_ID);
						if($course_meta->getIsCourse() && $course_meta->getHasAssignments()) {
							echo '<tr><td>'.$course_meta->getName().'</td>';
							echo '<td><a href="javascript:alert(\'Not implemented yet\');" title="'.__('Export current state', lepress_textdomain).'">'.__('Export current state', lepress_textdomain).'</a> &nbsp;|&nbsp; <a href="'.add_query_arg(array('page' => $_GET['page'], 'export_tpl' => $cat->cat_ID), admin_url().'admin.php').'" title="'.__('Export as template (all assignments without students related information)', lepress_textdomain).'">'.__('Export as template (all assignments without students related information)', lepress_textdomain).'</a></td></tr>';
						}
					}


					if(!isSet($cat)) {
						echo '<tr><td colspan="2">'.__('No active courses', lepress_textdomain).'</td></tr>';
					}

				?>
			</tbody>
		</table>
		
		<h2><?php _e('Available templates', lepress_textdomain); ?></h2>
		<table class="widefat" cellspacing="0">
			<thead>
				<tr>
					<th scope="col" class="manage-column column-name"><?php _e('Name', lepress_textdomain)?></th>
					<th scope="col" class="manage-column column-name"><?php _e('Import / Export date', lepress_textdomain)?></th>
					<th scope="col" class="manage-column column-actions"><?php _e('Actions', lepress_textdomain)?></th>
				</tr>
			</thead>
			<tbody>
				<?php
					$course_meta = new CourseMeta(false);
					foreach($course_meta->getTemplates() as $template) {
						echo '<tr><td>'.$template->name.'</td>';
						echo '<td>'.$this->date_i18n_lepress($template->date, false, false, true).'</td>';
						echo '<td id="action_links"><a href="#" onclick="jQuery(\'#select_category_'.$template->id.'\').toggle();return false;" title="'.__('Use template', lepress_textdomain).'">'.__('Use template', lepress_textdomain).'</a>';
						echo '&nbsp;|&nbsp; <a href="'.add_query_arg(array('page' => $_GET['page'], 'dl_tpl' => $template->id), admin_url().'admin.php').'" title="'.__('Download template', lepress_textdomain).'">'.__('Download template', lepress_textdomain).'</a>';
						echo '&nbsp;|&nbsp; <a href="'.add_query_arg(array('page' => $_GET['page'], 'rm_tpl' => $template->id), admin_url().'admin.php').'" onclick="return confirm(\''.__('Are you sure you want remove this template ?', lepress_textodmain).'\');" title="'.__('Remove template', lepress_textdomain).'">'.__('Remove template', lepress_textdomain).'</a>';
						echo '<div id="select_category_'.$template->id.'" style="display:none;">';
						?>
						
						<form action="<?php echo add_query_arg(array('page' => $_GET['page']), admin_url().'admin.php'); ?>" method="post">
						
						<?php
						$cats = get_categories('hide_empty=0');
						echo 'Import template to <select name="import_to">';
						$valid_cats = false;
						foreach($cats as $cat) {
							$course_meta = new CourseMeta($cat->cat_ID);
							if($course_meta->getIsCourse() && !$course_meta->getIsClosed()) {
								echo '<option value="'.$cat->cat_ID.'">'.$course_meta->getName().'</option>';
								$valid_cats = true;
							}
						}
						if(!$valid_cats) {
							echo '<option value="-1">'.__('No active courses', lepress_textdomain).'</option>';
						}
						echo '</select>';
						echo ' <input type="submit" value="'.__('Import', lepress_textdomain).'">';
						echo '<input type="hidden" name="tpl_id" value="'.$template->id.'" />';
						echo '</form>';
						echo '</div></td>';
						echo '</tr>';
					}


					if(!isSet($template)) {
						echo '<tr><td colspan="3">'.__('No templates found', lepress_textdomain).'</td></tr>';
					}

				?>
			</tbody>
			<?php if(isSet($template)) { ?>
			<tfoot>
				<tr>
					<td colspan="2">&nbsp;</td>
					<td><a href="<?php echo add_query_arg(array('page' => $_GET['page'], 'dl_tpl' => 'single_file'), admin_url().'admin.php'); ?>"><?php _e('Download all templates as single file', lepress_textdomain); ?></a></td>
				</tr>
			</tfoot>
			<?php } ?>
		</table>
		
		<h2><?php _e('Upload template or single templates file', lepress_textdomain); ?></h2>
		<form action="<?php echo add_query_arg(array('page' => $_GET['page']), admin_url().'admin.php'); ?>" method="post" enctype="multipart/form-data">
		<?php _e('Upload template file', lepress_textdomain); ?>: <input type="file" name="lepress_tpl_upload" />  <input type="submit" name="tpl_submit" value="<?php _e('Upload', lepress_textdomain); ?>" />
		</form>
</div>