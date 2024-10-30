<?php

/**
 * LePress student widget class, extends LePressBasicWidget class
 *
 * Provides some wrapper methods for LePressSubscriptions class
 */

class LePressStudentWidget extends LePressBasicWidget {

	/**
	 * Init class 
	 *
	 * Load subscriptions class from global $LePressStudent object
	 */
	 
	function __construct() {
		global $LePressStudent;
		$this->LePressStudent = $LePressStudent;
		$this->subscriptions = $this->LePressStudent->subscriptions;
	}

	/**
	 * Get widget dropdown menu options
	 */
	 
	function getCourseSelectOptions() {
		echo '<option>'.__('-- My subscriptions --', lepress_textdomain).'</option>';
		$return_value = false;
		foreach($this->LePressStudent->subscriptions->getApproved() as $course) {
			$selected_cat_ID = $this->getSelectedCatID();

	   		if($course->ID == $selected_cat_ID && $this->getCourseOwner('student')) {
	   			$selected = 'selected="selected"';
	   			$return_value = $selected_cat_ID;
	   		} else {
	   			$selected = '';
	   		}
			echo	'<option value="student-'.$course->ID.'" '.$selected.'>'.$course->course_name.'</option>';
		}
		return $return_value;
	}

	/**
	 * Get assignmentstable string
	 *
	 * @return string
	 */
	 
	function getAssignmentsTable() {
		return $this->LePressStudent->subscriptions->assignments_table;
	}

	/**
	 * Get course URL
	 *
	 * @param $local_course_id int local course id
	 */
	 
	function getCourseURL($course_id) {
		$course = $this->LePressStudent->subscriptions->getCourse($course_id);
		if($course) {
			return $course->course_url;
		}
		return false;
	}

	/**
	 * Get classmates of the course
	 *
	 * @param $local_course_id int local course id
	 */
	 
	function getClassmates($local_course_id) {
		return $this->LePressStudent->subscriptions->getClassmates($local_course_id);
	}

	/**
	 * Get teacher of the course
	 *
	 * @param $local_course_id int local course id
	 */
	 
	function getTeachers($local_course_id) {
		return $this->LePressStudent->subscriptions->getTeachers($local_course_id);
	}

}
?>