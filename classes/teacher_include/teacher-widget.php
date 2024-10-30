<?php

/**
 * LePress Teacher Widget class extending LePressBasicWidget
 *
 * @author Raido Kuli
 */
 
class LePressTeacherWidget extends LePressBasicWidget {
	
	/* Store LePressTeacher object on local variable */
	
	function __construct() {
		global $LePressTeacher;
		$this->LePressTeacher = $LePressTeacher;	
	}

	/**
	 * Get widget course dropdown  menu options
	 * @return string html
	 */
	 
	function getCourseSelectOptions() {
		echo '<option>'.__('-- My courses --', lepress_textdomain).'</option>';
		$cats = get_categories('hide_empty=0');
		$return_value = false;
		foreach($cats as $cat) {
			$course_meta = new CourseMeta($cat->cat_ID);
			if($course_meta instanceOf CourseMeta) {
				if($course_meta->getIsCourse() && !$course_meta->getIsClosed()) {
					if(!isSet($selected_cat_ID) && !isSet($_GET['c'])) {
						$selected_cat_ID = $cat->cat_ID;	
			 		} else {
			 			$selected_cat_ID = $this->getSelectedCatID();	
			 		}
		 		
			 		if($cat->cat_ID == $selected_cat_ID && $this->getCourseOwner('teacher')) {
			 			$selected = 'selected="selected"';	
			 			$return_value = $selected_cat_ID;
			 		} else {
			 			$selected = '';	
			 		}
					echo	'<option value="teacher-'.$cat->cat_ID.'" '.$selected.'>'.$cat->name.'</option>';
				}	
			}
		}
		return $return_value;
	}
}
?>