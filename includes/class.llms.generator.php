<?php
/**
 * Generate LMS Content from export files or raw arrays of data
 *
 * @package LifterLMS/Classes
 *
 * @since 3.3.0
 * @version [version]
 */

defined( 'ABSPATH' ) || exit;

/**
 * LLMS_Generator class.
 *
 * @since 3.3.0
 * @since 3.30.2 Added hooks and made numerous private functions public to expand extendability.
 * @since 3.36.3 New method: is_generator_valid()
 *               Bugfix: Fix return of `set_generator()`.
 * @since [version] Major refactor: moved almost post related methods into abstract classes.
 */
class LLMS_Generator {

	/**
	 * Name of the Generator to use for generation
	 *
	 * @var string
	 */
	private $generator = '';

	protected $generators = [];

	/**
	 * Array of generated content
	 *
	 * @var array
	 */
	protected $posts = array();

	/**
	 * Array of Stats
	 *
	 * @var int[]
	 */
	protected $stats = array(
		'authors'   => 0,
		'courses'   => 0,
		'sections'  => 0,
		'lessons'   => 0,
		'plans'     => 0,
		'quizzes'   => 0,
		'questions' => 0,
		'terms'     => 0,
	);

	/**
	 * Raw contents passed into the generator's constructor
	 *
	 * @var array
	 */
	protected $raw = array();

	/**
	 * Construct a new generator instance with data
	 *
	 * @since 3.3.0
	 *
	 * @param array|string $raw Array or a JSON string of raw content.
	 * @return void
	 */
	public function __construct( $raw ) {

		// Setup raw data.
		if ( ! is_array( $raw ) ) {
			$raw = json_decode( $raw, true );
		}
		$this->raw = $raw;

		// Instantiate an empty error object.
		$this->error = new WP_Error();

		require_once LLMS_PLUGIN_DIR . 'includes/class-llms-generator-courses.php';
		$this->generators['courses'] = new LLMS_Generator_Courses();

	}

	/**
	 * When called, generates raw content based on the defined generator
	 *
	 * @since 3.3.0
	 * @since 3.30.2 Add before and after generation hooks.
	 *
	 * @return void
	 */
	public function generate() {

		if ( ! empty( $this->generator ) ) {

			global $wpdb;

			$wpdb->hide_errors();

			$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			/**
			 * Action run immediately prior to a LifterLMS Generator running.
			 *
			 * @since 3.30.2
			 *
			 * @param LLMS_Generator $generator The generator instance.
			 */
			do_action( 'llms_generator_before_generate', $this );

			try {

				call_user_func( $this->generator, $this->raw );

			} catch ( Exception $e ) {

				$this->error->add( 'exception', $e->getMessage() );

			}

			/**
			 * Action run immediately after a LifterLMS Generator running.
			 *
			 * @since 3.30.2
			 *
			 * @param LLMS_Generator $generator The generator instance.
			 */
			do_action( 'llms_generator_after_generate', $this );

			if ( $this->is_error() ) {
				$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			} else {
				$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			}
		} else {

			return $this->error->add( 'missing-generator', __( 'No generator supplied.', 'lifterlms' ) );

		}

	}

	public function get_generator( $id ) {

		return isset( $this->generators[ $id ] ) ? $this->generators[ $id ] : false;

	}

	/**
	 * Get an array of valid LifterLMS generators
	 *
	 * @since 3.3.0
	 * @since 3.14.8 Unknown.
	 *
	 * @return array
	 */
	private function get_generators() {
		/**
		 * Filter the list of available generators.
		 *
		 * @since Unknown
		 *
		 * @param array[] $generators Array of generators. Array key is the generator name and the array value is a callable function.
		 */
		return apply_filters(
			'llms_generators',
			array(
				'LifterLMS/BulkCourseExporter'    => array( $this->generators['courses'], 'generate_courses' ),
				'LifterLMS/BulkCourseGenerator'   => array( $this->generators['courses'], 'generate_courses' ),
				'LifterLMS/SingleCourseCloner'    => array( $this->generators['courses'], 'generate_course' ),
				'LifterLMS/SingleCourseExporter'  => array( $this->generators['courses'], 'generate_course' ),
				'LifterLMS/SingleCourseGenerator' => array( $this->generators['courses'], 'generate_course' ),
				'LifterLMS/SingleLessonCloner'    => array( $this->generators['courses'], 'clone_lesson' ),
			)
		);
	}

	/**
	 * Get the results of the generate function
	 *
	 * @since 3.3.0
	 *
	 * @return int[]|WP_Error Array of stats on success and an error object on failure.
	 */
	public function get_results() {

		if ( $this->is_error() ) {
			return $this->error;
		} else {
			return $this->stats;
		}

	}

	/**
	 * Determines if there was an error during the running of the generator
	 *
	 * @since 3.3.0
	 * @since 3.16.11 Unknown.
	 *
	 * @return boolean Returns `true` when there was an error and `false` if there's no errors.
	 */
	public function is_error() {
		return ( 0 !== count( $this->error->get_error_messages() ) );
	}

	/**
	 * Determine if a generator is a valid generator.
	 *
	 * @since 3.36.3
	 *
	 * @param string $generator Generator name.
	 * @return bool
	 */
	protected function is_generator_valid( $generator ) {

		return in_array( $generator, array_keys( $this->get_generators() ), true );

	}

	/**
	 * Records a generated post id
	 *
	 * @since 3.14.8
	 * @since [version] Modified method access from `private` to `protected`.
	 *               Add IDs to the `generated` variable in favor of `posts`.
	 *
	 * @param int    $id  WP Post ID of the generated post.
	 * @param string $key Key of the stat to increment.
	 * @return void
	 */
	protected function record_generation( $id, $key ) {

		if ( ! isset( $this->posts[ $key ] ) ) {
			$this->posts[ $key ] = array();
		}

		$this->posts[ $key ] = $id;

	}


	/**
	 * Increments a stat in the stats object
	 *
	 * @since 3.3.0
	 * @since 3.30.2 Made publicly accessible; change to automatically add new items to the stats if they aren't set.
	 *
	 * @param string $type key of the stat to increment.
	 * @return void
	 */
	public function increment( $type ) {
		if ( ! isset( $this->r[ $type ] ) ) {
			$this->stats[ $type ] = 0;
		}
		$this->stats[ $type ]++;
	}

	/**
	 * Configure the default post status for generated posts at runtime
	 *
	 * @since 3.7.3
	 *
	 * @param string $status Any valid WP Post Status.
	 * @return void
	 */
	public function set_default_post_status( $status ) {
		call_user_func( array( $this->generator[0], 'set_default_post_status' ), $status );
	}

	/**
	 * Sets the generator to use for the current instance
	 *
	 * @since 3.3.0
	 * @since 3.36.3 Fix error causing `null` to be returned instead of expected `WP_Error`.
	 *               Return the generator name on success instead of void.
	 *
	 * @param string $generator Generator string, eg: "LifterLMS/SingleCourseExporter"
	 * @return string|WP_Error Name of the generator on success, otherwise an error object.
	 */
	public function set_generator( $generator = null ) {

		// Interpret the generator from the raw data.
		if ( empty( $generator ) ) {

			// No generator can be interpreted.
			if ( ! isset( $this->raw['_generator'] ) ) {

				$this->error->add( 'missing-generator', __( 'The supplied file cannot be processed by the importer.', 'lifterlms' ) );
				return $this->error;

			}

			// Set the generator using the interpreted data.
			return $this->set_generator( $this->raw['_generator'] );

		}

		// Invalid generator.
		if ( ! $this->is_generator_valid( $generator ) ) {
			$this->error->add( 'invalid-generator', __( 'The supplied generator is invalid.', 'lifterlms' ) );
			return $this->error;
		}

		// Set the generator.
		$generators      = $this->get_generators();
		$this->generator = $generators[ $generator ];

		// Return the generator name.
		return $generator;

	}

}
