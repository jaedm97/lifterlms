<?php
/**
 * @author 		codeBOX
 * @package 	lifterLMS/Templates
 */

if ( ! defined( 'ABSPATH' ) ) exit;

global $post, $course;

if ( ! $course ) {

	$course = new LLMS_Course( $post->ID );

}

$product_obj = new LLMS_Product($post->ID);
$single_html_price = sprintf( __( 'Single payment of %s', 'lifterlms' ), $product_obj->get_price_html() );
//$recurring_html_price = $product_obj->get_recurring_price_html();
$payment_options = $product_obj->get_payment_options();

?>
<div class="llms-price-wrapper">
	<?php if ( ! llms_is_user_enrolled( get_current_user_id(), $course->id ) ) : ?>
		<?php foreach ($payment_options as $key => $value) : ?>
			<?php if ($value == 'single') : ?>
				<h4 class="llms-price"><span><?php echo $single_html_price; ?></span></h4>
			<?php endif; ?>

			<?php if ($value == 'recurring') : ?>
				<?php $subs = $product_obj->get_subscriptions(); ?>
				<?php foreach ($subs as $id => $sub) : ?>
					<?php echo 'or'; ?>
					<h4 class="llms-price"><span><?php echo $product_obj->get_subscription_price_html($sub); ?></span></h4>
				<?php endforeach; ?>
			<?php endif; ?>

			<?php
			/**
			 * Allow addons / plugins / themes to define custom payment options
			 * This action will be called to allow them to output some custom html for the payment options
			 */
			?>
			<?php do_action( 'lifterlms_course_payment_option_'.$value, $product_obj, $value ); ?>
		<?php endforeach; ?>
	<?php endif; ?>
</div>