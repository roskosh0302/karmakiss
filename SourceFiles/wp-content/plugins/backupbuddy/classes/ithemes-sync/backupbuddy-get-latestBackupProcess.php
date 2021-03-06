<?php
class Ithemes_Sync_Verb_Backupbuddy_Get_LatestBackupProcess extends Ithemes_Sync_Verb {
	public static $name = 'backupbuddy-get-latestBackupProcess';
	public static $description = 'Get information on the latest backup process.';
	
	private $default_arguments = array(
	);
	
	/*
	 * Return:
	 *		array(
	 *			'success'	=>	'0' | '1'
	 *			'status'	=>	'Status message.'
	 *			'overview'	=>	[array of overview information]
	 *		)
	 *
	 */
	public function run( $arguments ) {
		
		$timeoutMinutes = 5; // Minutes after which BackupBuddy assumed a backup has timed out & no longer running.
		
		$arguments = Ithemes_Sync_Functions::merge_defaults( $arguments, $this->default_arguments );
		
		if ( ! class_exists( 'backupbuddy_core' ) ) {
			require_once( pb_backupbuddy::plugin_path() . '/classes/core.php' );
		}
		
		/***** BEGIN CALCULATING CURRENT BACKUP DETAILS *****/
		
		require_once( pb_backupbuddy::plugin_path() . '/classes/fileoptions.php' );
		$backup_options = new pb_backupbuddy_fileoptions( backupbuddy_core::getLogDirectory() . 'fileoptions/' . pb_backupbuddy::$options['last_backup_serial'] . '.txt', $read_only = true );
		if ( true !== ( $result = $backup_options->is_ok() ) ) {
			$currentBackup = false;
		} else {
			$currentBackup = $backup_options->options;
		}
		
		$currentBackupStats['serial'] = $currentBackup['serial'];
		$currentBackupStats['isRunning'] = '0';
		if ( '0' == $currentBackup['finish_time'] ) { // Last backup has not finished yet or timed out.
			if ( ( time()-$currentBackup['updated_time'] ) > (60*$timeoutMinutes) ) { // Most likely timed out.
			} else { // Still chugging along possibly.
				$currentBackupStats['isRunning'] = '1';
			}
		}
		$currentBackupStats['processStarted'] = $currentBackup['start_time'];
		$currentBackupStats['processFinished'] = $currentBackup['finish_time'];
		
		$currentBackupStats['processStepTitle'] = '';
		$currentBackupStats['processStepFunction'] = '';
		$currentBackupStats['processStepElapsed'] = 0;
		foreach( (array)$currentBackup['steps'] as $step ) {
			if ( '0' == $step['finish_time'] ) {
				$currentBackupStats['processStepTitle'] = backupbuddy_core::prettyFunctionTitle( $step['function'] );
				$currentBackupStats['processStepFunction'] = $step['function'];
				$currentBackupStats['processStepElapsed'] = time() - $step['start_time'];
				break;
			}
		}
		$currentBackupStats['backupType'] = $currentBackup['type'];
		$currentBackupStats['profileTitle'] = htmlentities( $currentBackup['profile']['title'] );
		$currentBackupStats['scheduleTitle'] = $currentBackup['schedule_title'];
		if ( @file_exists( $currentBackup['archive_file'] ) ) {
			$currentBackupStats['archiveFile'] = basename( $currentBackup['archive_file'] );
		} else {
			$currentBackupStats['archiveFile'] = '';
		}
		$currentBackupStats['archiveURL'] = '';
		if ( isset( $currentBackup['archive_url'] ) ) {
			$currentBackupStats['archiveURL'] = $currentBackup['archive_url'];
		}
		
		$currentBackupStats['archiveSize'] = 0;
		if ( $currentBackup['archive_size'] == 0 ) {
			if ( file_exists( $currentBackup['temporary_zip_directory'] ) ) { // Temp zip file.
				$directory = opendir( $currentBackup['temporary_zip_directory'] );
				while( $file = readdir( $directory ) ) {
					if ( ( $file != '.' ) && ( $file != '..' ) && ( $file != 'exclusions.txt' ) && ( !preg_match( '/.*\.txt/', $file ) ) && ( !preg_match( '/pclzip.*\.gz/', $file) ) ) {
						$stats = stat( $currentBackup['temporary_zip_directory'] . $file );
						$currentBackupStats['archiveSize'] = $stats['size'];
					}
				}
				closedir( $directory );
				unset( $directory );
			}
		}
		
		$integrityIsOK = '-1';
		if ( isset( $currentBackup['integrity'] ) && isset( $currentBackup['integrity']['is_ok'] ) ) {
			$integrityIsOK = $currentBackup['integrity']['is_ok'];
		}
		$currentBackupStats['integrityStatus'] = $integrityIsOK; // true, false, -1 (unknown)
		
		$destinations = array();
		foreach( (array)$currentBackup['steps'] as $step ) {
			if ( 'send_remote_destination' == $step['function'] ) {
				$destinations[] = array(
									'id' => $step['args'][0],
									'title' => pb_backupbuddy::$options['remote_destinations'][ $step['args'][0] ]['title'],
									'type' => pb_backupbuddy::$options['remote_destinations'][ $step['args'][0] ]['type'],
								);
			}
		}
		$currentBackupStats['destinations'] = $destinations; // Index is destination ID. Empty array if none.
		
		/***** END CALCULATING CURRENT BACKUP DETAILS *****/
		
		
		
		return array(
			'version' => '4',
			'status' => 'ok',
			'message' => 'Latest backup process details retrieved successfully.',
			'latestBackupProcess' => $currentBackupStats,
			'localTime'					=> time(),
		);
		
	} // End run().
	
	
} // End class.
