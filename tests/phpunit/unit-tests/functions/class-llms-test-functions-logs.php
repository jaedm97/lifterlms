<?php
/**
 * Test Logging Functions
 *
 * @package LifterLMS/Tests/Functions
 *
 * @group functions
 * @group functions_logs
 *
 * @since [version]
 */
class LLMS_Test_Functions_Logs extends LLMS_UnitTestCase {

	public function setUp() {
		parent::setUp();
		add_filter( 'llms_log_max_filesize', array( $this, 'shrink_max_log_size' ) );
	}

	/**
	 * Teardown
	 *
	 * Clean log files from the log directory.
	 *
	 * This isn't strictly necessary when running tests in a CI but if you run tests
	 * locally without regular manual cleanup you'll see a lot of trash logs generated as a result
	 * and this teardown prevents that.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function tearDown() {

		parent::tearDown();
		foreach ( glob( LLMS_LOG_DIR . '*.log*' ) as $file ) {
			unlink( $file );
		}

		remove_filter( 'llms_log_max_filesize', array( $this, 'shrink_max_log_size' ) );

	}

	/**
	 * Create a mock log file with a target size
	 *
	 * @since [version]
	 *
	 * @param string  $handle      Log file's handle.
	 * @param integer $target_size Target logfile size (in MB). The created file will be at least this big and more than likely a little bigger.
	 * @return void
	 */
	protected function create_mock_log_file( $handle, $target_size = 1 ) {

		// Convert target size to MB.
		$target_size = 1 * 1000 * 1000;
		$file        = llms_get_log_path( $handle );

		$size = 0;
		while ( $size < $target_size ) {

			$i = 0;
			while ( $i <= 20 ) {
				llms_log( str_repeat( '01', 999 ), $handle );
				++$i;
			}

			clearstatcache( true, $file );
			$size = filesize( $file );
		}

	}

	/**
	 * Mock the max allowed file size to be 1MB (instead of default 5MB)
	 *
	 * @since [version]
	 *
	 * @param int $size Default max file size.
	 * @return int
	 */
	public function shrink_max_log_size( $size ) {
		return 1;
	}

	/**
	 * Test llms_get_log_path()
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_llms_get_log_path() {

		$handle = 'testhandle';
		$expected_hash = wp_hash( $handle );

		$expected_file = sprintf( '%1$s-%2$s.log', $handle, $expected_hash );

		$path = llms_get_log_path( $handle );

		$this->assertEquals( $expected_file, basename( $path ) );
		$this->assertEquals( untrailingslashit( LLMS_LOG_DIR ), dirname( $path ) );

		$this->assertEquals( LLMS_LOG_DIR . $expected_file, $path );

	}

	/**
	 * Test llms_log() when logging a string
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_llms_log_string() {

		$this->assertTrue( llms_log( 'Test message', 'teststringlog' ) );

		$logs = explode( ' - ', file_get_contents( llms_get_log_path( 'teststringlog' ) ) );

		$this->assertTrue( date_create( $logs[0] ) instanceof DateTime );

		$this->assertEquals( "Test message\n", $logs[1] );

	}

	/**
	 * Test llms_log() when logging an array
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_llms_log_array() {

		$this->assertTrue( llms_log( array( 'Test message' ), 'testarrlog' ) );

		$logs = explode( ' - ', file_get_contents( llms_get_log_path( 'testarrlog' ) ) );

		$this->assertTrue( date_create( $logs[0] ) instanceof DateTime );

		$this->assertEquals( "Array
(
    [0] => Test message
)

", $logs[1] );

	}

	/**
	 * Test llms_log() when logging an object
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_llms_log_object() {

		$this->assertTrue( llms_log( (object) array( 'Test' => 1 ), 'testobjlog' ) );

		$logs = explode( ' - ', file_get_contents( llms_get_log_path( 'testobjlog' ) ) );

		$this->assertTrue( date_create( $logs[0] ) instanceof DateTime );

		$this->assertEquals( "stdClass Object
(
    [Test] => 1
)

", $logs[1] );

	}

	/**
	 * Test llms_backup_log
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_llms_backup_log() {

		$actions = did_action( 'llms_log_file_backup_created' );

		$handle = 'logtobackup';
		$file   = llms_get_log_path( $handle );

		// File doesn't exist, no need to backup.
		$this->assertNull( llms_backup_log( $handle ) );

		llms_log( str_repeat( '01', 999 ), $handle );

		// File does exist but doesn't need to be backup yet.
		$this->assertNull( llms_backup_log( $handle ) );

		$this->create_mock_log_file( $handle );

		// Get the contents of the original to compare later.
		$original = file_get_contents( $file );

		// Split the file.
		$copy = llms_backup_log( $handle );

		// We made a copy.
		$this->assertTrue( false !== $copy );

		// Return should be different than than the original.
		$this->assertNotEquals( $copy, $file );

		// Copy exists.
		$this->assertTrue( file_exists( $copy ) );

		// Original has been removed.
		$this->assertFalse( file_exists( $file ) );

		// Compare copy contents to the original.
		$this->assertEquals( $original, file_get_contents( $copy ) );

		// Action ran.
		$this->assertEquals( ++$actions, did_action( 'llms_log_file_backup_created' ) );

	}

	/**
	 * Test llms_backup_logs()
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_llms_backup_logs() {

		$actions = did_action( 'llms_log_file_backup_created' );

		llms_log( 'message', 'notbackedup1' );
		llms_log( 'message', 'notbackedup2' );
		$this->create_mock_log_file( 'tobackup1' );
		$this->create_mock_log_file( 'tobackup2' );

		llms_backup_logs();

		$this->assertEquals( $actions + 2, did_action( 'llms_log_file_backup_created' ) );

	}

}
