<?php

/**
 * LePress custom cron class for schedueling events
 *
 * LePress cron handles student side assignments, 
 * course metadata and classmates periodical update.
 * Update interval can be change by the student.
 *
 * @author Raido Kuli
 *
 */
 
class LePressCron {
	private $cron_table;

	/**
	 * Init class and run schedule check, to see if we need to run anything
	 */
	 
	function __construct() {
		global $wpdb, $LePressStudent;
		$this->cron_table = $LePressStudent->get_wpdb_prefix().'cron';
		$this->wpdb = $wpdb;
		//Check for anything to run
		$this->check_schedules_to_run();

	}
	
	/**
	 * Add new schedule hook to cron 
	 */
	 
	function add_schedule($hook, $time) {
		$data = array('hook' => $hook, 'scheduled_time' => $time);
		$this->wpdb->insert($this->cron_table, $data);
	}
	
	/**
	 * Delete schedule hook from cron 
	 */
	 
	function delete_schedule($hook) {
		return $this->wpdb->query('DELETE FROM '.$this->cron_table.' WHERE hook="'.esc_sql($hook).'"');
	}

	/**
	 * Check schedule hooks to run
	 *
	 * If schedule time has passed, init hook and update option "lepress-cron-is-running"
	 * do be sure we don't double up cron jobs.
	 */
	 
	function check_schedules_to_run() {
		$cron_time_running = time() - get_option('lepress-cron-is-running', 0);
		if(($jobs = $this->getJobs()) && $cron_time_running > 10) {
			foreach($jobs as $schedule_obj) {
				if($schedule_obj->scheduled_time <= time()) {
					$deleted = $this->delete_schedule($schedule_obj->hook);
					if($deleted) {
						do_action($schedule_obj->hook);
					}
				}
			}
			update_option('lepress-cron-is-running', time());
		}
	}

	/**
	 * Get next hook schedule time
	 *
	 * @return return next runtime timestamp from database
	 */
	 
	function get_next_scheduled($hook) {
		return $this->wpdb->get_var('SELECT scheduled_time FROM '.$this->cron_table.' WHERE hook = "'.esc_sql($hook).'"');
	}

	/**
	 * Get all the cron jobs
	 *
	 * @return all the cron jobs as arrary of objects;
	 */
	 
	function getJobs() {
		return $this->wpdb->get_results('SELECT * FROM '.$this->cron_table);
	}
}

?>