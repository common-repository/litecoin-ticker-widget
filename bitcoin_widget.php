<?php
/*
    Plugin Name: Litecoin Ticker Widget
    Plugin URI: http://99bitcoins.com/litecoin-price-ticker-widget-wordpress-plugin/
    Description: Displays a ticker widget on your site of latest Litecoin prices
    Author: Ofir Beigel
    Version: 1.0.2
    Author URI: ofir@nhm.co.il
*/

DEFINE("LCW_API_URL","https://btc-e.com/api/2/ltc_usd/ticker");
DEFINE("LCW_CACHE_DURATION",300); // 5 minutes, to avoide load on server

register_activation_hook( __FILE__,  "lcw_install" );

register_deactivation_hook( __FILE__ , "lcw_uninstall" );

function lcw_install(){

	/*wp_remote_post( LCW_API_URL, array(
		'method' => 'POST',
		'timeout' => 15,
		'redirection' => 5,
		'httpversion' => '1.0',
		'blocking' => false,
		'body' => array( 'name' => get_bloginfo("name"), 'url' => get_bloginfo("url") , "action" => "activate" )
	    )
	);
*/
	lcw_update_data();
}

function lcw_uninstall(){

/*	wp_remote_post( LCW_API_URL, array(
		'method' => 'POST',
		'timeout' => 15,
		'redirection' => 5,
		'httpversion' => '1.0',
		'blocking' => false,
		'body' => array( 'name' => get_bloginfo("name"), 'url' => get_bloginfo("url") , "action" => "deactivate" )
	    )
	);*/
}

function lcw_update_data(){

	$response = wp_remote_get( LCW_API_URL , array(
		"sslverify" => false,
		"timeout" => 10
	) );

	$btw_options = get_option("lcw_options");

	$update_time = time();

	if( !$btw_options ) $btw_options = array();

	if( !$btw_options["data"] )
		$btw_options["data"] = array( 
			"chart" => array() , 
			"ticker" =>array( 
				'buy' => 0,
				'sell' => 0,
				'high' => 0,
				'low' => 0,
				'volume' => 0
			),
			'updated' => $update_time
		);

	if ( is_wp_error( $response ) ):

		$btw_options["data"]["updated"] = $update_time;

		update_option( "lcw_options" , array(
			"last_updated" => $update_time,
			"data" => $btw_options["data"]
		) );
		
		return;

	endif;

	$json = json_decode( $response["body"] , true );

	if( isset( $json["error"] ) && $json["error"] == true ):

		$btw_options["data"]["updated"] = $update_time;

		update_option( "lcw_options" , array(
			"last_updated" => $update_time,
			"data" => $btw_options["data"]
		) );
	else :

		$json["updated"] = $update_time;

		update_option( "lcw_options" , array(
			"last_updated" => $update_time,
			"data" => array("litecoin" => $json)
		) );
		add_chart_data($json);
	endif;
}

function add_chart_data($ticker){

	$daily_data=get_option('btw_charts_daily');
	if(is_array($daily_data) && count($daily_data)>0)
		{
		$tmp=array(
							0=>count($daily_data)+1,
							1=>$ticker['ticker']['buy']
							);
		array_push($daily_data,$tmp);
		}
	else{
		$daily_data = array(
							0=>array(
							0=>1,
							1=>$ticker['ticker']['buy']
							)
							);
		}
	update_option('btw_charts_daily',$daily_data);
}

function lcw_get_options( $update = true){

	$btw_options = get_option( "lcw_options" );

	if( $update && ( !$btw_options || $btw_options["last_updated"] < time() - LCW_CACHE_DURATION ) ):
		lcw_update_data();
	endif;

	return $btw_options;
}

function lcw_get_daily_data($chart_data){
	$daily_chart=array_reverse($chart_data);
	if(count($daily_chart)>=288)
		$daily_chart=array_slice($daily_chart,0,287);
	return array_reverse($daily_chart);
	
}

function lcw_data(){	
	
	$btw_options = lcw_get_options();
	$chart_data=get_option('btw_charts_daily');
	$daily_data=lcw_get_daily_data($chart_data);
	$data_to_op=$btw_options["data"]["litecoin"]['chart']=array('daily'=>$daily_data);
	lcw_output_json( $btw_options["data"] );
	
}

add_action('wp_ajax_lcw_data', 'lcw_data');
add_action('wp_ajax_nopriv_lcw_data', 'lcw_data');

function lcw_output_json( $data ){

	header("Content-type:application/json");

	echo json_encode( $data );
	exit;

}
/**
 * Proper way to enqueue scripts and styles
 */
function litecoin_scripts() {
	wp_enqueue_style( 'litecoin-style',  plugin_dir_url(__FILE__) . '/css/style.css' );

        if(!wp_script_is('jquery'))
            wp_enqueue_script( 'jquery');

        wp_enqueue_script( 'litecoin-plugins', plugin_dir_url(__FILE__) . 'js/plugins.js', array('jquery'), '', true );
        wp_enqueue_script( 'litecoin-script', plugin_dir_url(__FILE__) . 'js/script.js', array('jquery'), '', true );
        wp_enqueue_script( 'googleapi' , 'https://www.google.com/jsapi' );
        wp_localize_script( 'jquery', 'ajax_url', site_url() . '/wp-admin/admin-ajax.php' );
}

add_action( 'wp_enqueue_scripts', 'litecoin_scripts' );

function litecoin_head() {
	?><script type='text/javascript'>var lcw_ajax_url = "<?php echo admin_url('admin-ajax.php'); ?>"; </script><?php
}

add_action( 'wp_head', 'litecoin_head' , 1 );

/**
 * Adds Bitcoin widget.
 */

global $lcw_widget_index;

$lcw_widget_index = 0;

class Litecoin_Widget extends WP_Widget {

	/**
	 * Register widget with WordPress.
	 */
	function __construct() {
		parent::__construct(
			'litecoin_widget', // Base ID
			'Litecoin Widget', // Name
			array( 'description' => __( 'Litecoin Price Widget', 'text_domain' ), ) // Args
		);
	}

	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance ) {
		$link = apply_filters( 'widget_title', $instance['link'] );

		echo $args['before_widget'];
	
		$btw_options = lcw_get_options( false );

		global $lcw_widget_index;

		$lcw_widget_index++;
		
		?>
			<div id='litecoin-widget-<?php echo $lcw_widget_index; ?>'>
                <div id="litecoin-widget" class='litecoin-widget'>
					<div class="litecoin-logo"></div>
                    <div class="litecoin-tab-nav">
                     <a class="litecoin-tab-link litecoin-first-tab-link" href="javascript:void(0)" data-name="btce">BTC-E</a>
                     <div class="clear line"></div>
                    </div>
					<div class="litecoin-widget-tabs">
						<div class='litecoin-tab' id='litecoin-tab-litecoin' >
							<div class='litecoin-tab-content' >
								<div class="litecoin-last-price"><h2>$<?php echo number_format($btw_options["data"]["litecoin"]["ticker"]["buy"],2);?></h2></div>
								<div class="litecoin-chart"></div>
								<div class="litecoin-login-status">Last 24 hours <a href='javascript:void(0)' data-time='daily' class='active' ></a> <!--/ <a href='javascript:void(0)' data-time='weekly' >7d</a> / <a href='javascript:void(0)' data-time='monthly' >30d</a>--></div>
								<div class="litecoin-data">
									<ul>
		                                <li>Buy : $<?php echo number_format($btw_options["data"]["litecoin"]["ticker"]["buy"],2); ?></li>
		                                <li>Sell : $<?php echo number_format($btw_options["data"]["litecoin"]["ticker"]["sell"],2); ?></li>
		                                <li>High : $<?php echo number_format($btw_options["data"]["litecoin"]["ticker"]["high"],2); ?></li>
		                                <li>Low : $<?php echo number_format($btw_options["data"]["litecoin"]["ticker"]["low"],2); ?></li>
		                                <li>Volume : <?php echo number_format($btw_options["data"]["litecoin"]["ticker"]["vol_cur"],0); ?> LTC</li><?php //print_r($btw_options);?>
		                            </ul>
								</div>
								<div class="litecoin-link-row">
									<span class="litecoin-last-updated">Last updated: <span class="litecoin-timeago" ></span></span>
								</div>
							</div>
						</div>
					
					</div>	
					<hr />
					<div class="litecoin-footer">
                        <div class="litecoin-get-the-plugin"><?php if($instance['footer_link']){}else{?><a style="text-decoration: underline;" href="http://99bitcoins.com/litecoin-price-ticker-widget-wordpress-plugin/" target="_BLANK" rel="nofollow">Get the LiteCoin Ticker</a><?php } ?></div>
                    </div>
                    
                </div>
            </div>
            <script type='text/javascript' >
                jQuery(document).ready(function($){
				<?php   $chart_data=get_option('btw_charts_daily');
				$daily_data=lcw_get_daily_data($chart_data);
				$data_to_op=$btw_options["data"]["litecoin"]['chart']=array('daily'=>$daily_data);
				?>
               	var data  = <?php echo json_encode( $btw_options["data"] ); ?>;

               	$("#litecoin-widget-<?php echo $lcw_widget_index; ?>").litecoinWidget( data );

                });
            </script>
                <?php
		echo $args['after_widget'];
	}

	/**
	 * Back-end widget form.
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) {
		if ( $instance ) {
			$checkbox = esc_attr($instance['footer_link']);
		}
		
		?>
		
		<p>
		<label for="<?php echo $this->get_field_id( 'footer_link' ); ?>"><?php _e( 'Remove plugin credit:' ); ?></label> 
		<input class="widefat" id="<?php echo $this->get_field_id( 'footer_link' ); ?>" name="<?php echo $this->get_field_name( 'footer_link' ); ?>" type="checkbox" value='1' <?php checked( '1',$checkbox,  TRUE); ?>/>
		</p>
		<?php 
		
		
		return;
	}

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['link'] = ( ! empty( $new_instance['link'] ) ) ? strip_tags( $new_instance['link'] ) : '';
		 $instance['footer_link'] = strip_tags($new_instance['footer_link']);
		return $instance;
	}

} // class Bitcoin_Widget

function register_litecoin_widget(){
    register_widget( 'Litecoin_Widget' );
}
add_action( 'widgets_init', 'register_litecoin_widget');


function litecoin_activate() {

    // Activation code here...
    if(!function_exists('curl_version')){
        deactivate_plugins(__FILE__);
        wp_die('This plugin requires PHP CURL module which is not enabled on your server. Please contact your server administrator');
    }
	
}
register_activation_hook( __FILE__, 'litecoin_activate' );