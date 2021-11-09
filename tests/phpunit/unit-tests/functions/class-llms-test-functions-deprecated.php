<?php
/**
 * Tests for deprecated functions
 *
 * @group functions
 * @group deprecated
 *
 * @since [version]
 */
class LLMS_Test_Functions_Deprecated extends LLMS_UnitTestCase {

	/**
	 * Test earned engagement deprecated keys against an invalid post type.
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_llms_engagement_handle_deprecated_meta_keys_invalid_post() {

		$attachment = $this->create_attachment( 'yura-timoshenko-R7ftweJR8ks-unsplash.jpeg' );
		$post_title = 'Actual Title';
		$meta_input = array(
			'_llms_achievement_image' => 123,
			'_llms_certificate_image' => 123,
			'_llms_achievement_title' => 'Not the Title',
			'_llms_certificate_title' => 'Not the Title	',
		);

		$post = $this->factory->post->create( compact( 'meta_input', 'post_title' ) );
		set_post_thumbnail( $post, $attachment );

		foreach ( $meta_input as $key => $value ) {
			$this->assertEquals( $value, get_post_meta( $post, $key, true ) );
		}

	}

	/**
	 * Test llms_my_certificate deprecated keys
	 *
	 * @since [version]
	 *
	 * @expectedDeprecated LLMS_User_Certificate meta key 'certificate_image'
	 * @expectedDeprecated LLMS_User_Certificate meta key 'certificate_title'
	 *
	 * @return void
	 */
	public function test_llms_engagement_handle_deprecated_meta_keys_certificate_post() {

		$attachment = $this->create_attachment( 'yura-timoshenko-R7ftweJR8ks-unsplash.jpeg' );
		$post_type  = 'llms_my_certificate';
		$post_title = 'Actual Title';
		$meta_input = array(
			'_llms_certificate_image' => $attachment + 1,
			'_llms_certificate_title' => 'Not the Title	',
		);

		$post = $this->factory->post->create( compact( 'meta_input', 'post_title', 'post_type' ) );
		set_post_thumbnail( $post, $attachment );

		// Via `get_post_meta()`.
		$this->assertEquals( $post_title, get_post_meta( $post, '_llms_certificate_title', true ) );
		$this->assertEquals( $attachment, get_post_meta( $post, '_llms_certificate_image', true ) );

		// Via LLMS_User_Certificate->get().
		$obj = new LLMS_User_Certificate( $post );
		$this->assertEquals( $post_title, $obj->get( 'achievement_title' ) );
		$this->assertEquals( $attachment, $obj->get( 'achievement_image' ) );

	}

	/**
	 * Test llms_my_certificate deprecated keys
	 *
	 * @since [version]
	 *
	 * @expectedDeprecated LLMS_User_Achievement meta key 'achievement_image'
	 * @expectedDeprecated LLMS_User_Achievement meta key 'achievement_title'
	 *
	 * @return void
	 */
	public function test_llms_engagement_handle_deprecated_meta_keys_achievement_post() {

		$attachment = $this->create_attachment( 'yura-timoshenko-R7ftweJR8ks-unsplash.jpeg' );
		$post_type  = 'llms_my_achievement';
		$post_title = 'Actual Title';
		$meta_input = array(
			'_llms_achievement_image' => $attachment + 1,
			'_llms_achievement_title' => 'Not the Title	',
		);

		$post = $this->factory->post->create( compact( 'meta_input', 'post_title', 'post_type' ) );
		set_post_thumbnail( $post, $attachment );

		// Via `get_post_meta()`.
		$this->assertEquals( $post_title, get_post_meta( $post, '_llms_achievement_title', true ) );
		$this->assertEquals( $attachment, get_post_meta( $post, '_llms_achievement_image', true ) );

		// Via LLMS_User_Achievement->get().
		$obj = new LLMS_User_Achievement( $post );
		$this->assertEquals( $post_title, $obj->get( 'achievement_title' ) );
		$this->assertEquals( $attachment, $obj->get( 'achievement_image' ) );

	}

}
