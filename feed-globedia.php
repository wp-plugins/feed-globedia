<?php
/*
Plugin Name: Feed Globedia
Plugin URI: http://globedia.com/
Description: Plugin para la creación de un RSS completo y privado para Globedia. 
Author: Iker Barrena
Author URI: http://globedia.com
Version: 2.0
*/

define('GLB_FILE', 'feed-globedia/feed-globedia.php');
define('GLB_ADMIN_PATH', 'plugins.php?page='.GLB_FILE);
define('GLB_ADMIN_URL', get_option('siteurl').'/wp-admin/'.GLB_ADMIN_PATH);

class FeedGlobedia {

	function FeedGlobedia() {
		add_action('admin_menu', array(&$this, 'administrator'));
		add_action('admin_head', array(&$this, 'head'));
		add_filter('query_vars', array(&$this, 'add_query_vars'));
		add_action('generate_rewrite_rules', array(&$this, 'add_feed_globedia_rewrite_rules'));
		add_action('template_redirect', array(&$this, 'template_redirect_file'));
		add_action('init', array(&$this, 'add_feed_globedia'));
		register_deactivation_hook(__FILE__, array(&$this, 'deactivate_user'));
	}
	function template_redirect_file()
	{
		global $wp_query;
		if ( $hash = $wp_query->get('feed-globedia') )
		{
			$glb_hash = get_option('glb_hash');
			
			if ($hash==$glb_hash && file_exists( WP_PLUGIN_DIR . '/feed-globedia/feed-rss2.php'))
			{
				include( WP_PLUGIN_DIR . '/feed-globedia/feed-rss2.php' );
				exit;
			}
		}
	}
	function add_query_vars( $qvars )
	{
		$qvars[] = 'feed-globedia';
		return $qvars;
	}
	
	function add_feed_globedia() {
		global $wp_rewrite;
		$glb_hash = get_option('glb_hash');
		if ($glb_hash!="")
 		add_feed($glb_hash, array(&$this, 'load_feed'));
 		$wp_rewrite->flush_rules();
	}
			
	function add_feed_globedia_rewrite_rules( $wp_rewrite ) {
               // echo dirname(__FILE__);
		$new_rules = array( 'feed-globedia/(.+)' => 'index.php?feed-globedia='.$wp_rewrite->preg_index(1),
							'feed/(.+)' => 'index.php?feed='.$wp_rewrite->preg_index(1));
		//'index.php?feed='.$wp_rewrite->preg_index(1)
		$wp_rewrite->rules = $new_rules + $wp_rewrite->rules;
	}

	function load_feed() {
		// check if template exists in theme directory, use default RSS2 template otherwise
		load_template(dirname(__FILE__).'/feed-rss2.php');
	}
	
	// Funciones del administrador
	function administrator(){
		add_submenu_page('plugins.php', 'Feed Globedia', 'Feed Globedia', 9, __FILE__, array(&$this, 'settings'));
	}

	function head(){
		$css = '/wp-content/plugins/feed-globedia/style.css';
		print '<link rel="stylesheet" href="'.get_option('siteurl').$css.'?'.filemtime(dirname(__FILE__)."/style.css").'" type="text/css" />';
	}

	function settings(){
		global $wpdb;
		
		$glb_hash = get_option('glb_hash');
?>
		<div class="wrap">
		<h2>Feed Globedia</h2>
		<h3>Opciones</h3>
<?php
		if($_GET['frm_hash']){
			//Se ha insertado algo
			$frm_hash = $wpdb->escape($_GET['frm_hash']);
			//validamos contra globedia y si todo va bien

			if ($this->verify_key($frm_hash))
			{
				$glb_hash = $frm_hash;
				update_option('glb_hash', $glb_hash);

				?>
				<div class="glbexito">El plugin ha sido activado correctamente</div>
				<?php
			}
			else
			{
				?>
				<div class="glberror">Ha ocurrido un error en la verificación, compruebe que su código de verificación es correcto</div>
				<?php
			}
		}	
?>
		<form action="<?php echo(GLB_ADMIN_PATH); ?>" method='get' class="glbsettingsform">
			<input type="hidden" id="page" name="page" value="<?php print GLB_FILE; ?>" />
			<input type="hidden" id="adm" name="adm" value="settings" />

			<label for="frm_hash"><?php print 'Codigo Verificacion'; ?></label>
			<input type="text" id="frm_hash" name="frm_hash" value="<?php print $glb_hash; ?>" />
			
			<input type="submit" value="Aceptar">
		</form>
		</div>
<?php
	}
	
	function verify_key( $key ) {
		$url = urlencode( get_option('home') );
		$response = $this->http_post("key=$key&url=$url&status=1", 'globedia.com', '/wp-activate/');
		if ( !is_array($response) || !isset($response[1]) || !ereg("[0-9]+", $response[1]) ) return 0;
		return ($response[1]);
	}

	function deactivate_user() {
		$key = get_option('glb_hash');
		$url = urlencode( get_option('home') );
		$response = $this->http_post("key=$key&url=$url&status=0", 'globedia.com', '/wp-activate/');
		if ( !is_array($response) || !isset($response[1]) || !ereg("[0-9]+", $response[1]) ) return 0;
		return $response[1];
	}
         
	function http_post($request, $host, $path) {
		global $wp_version;
	
		$http_request  = "POST $path HTTP/1.0\r\n";
		$http_request .= "Host: $host\r\n";
		$http_request .= "Content-Type: application/x-www-form-urlencoded; charset=" . get_option('blog_charset') . "\r\n";
		$http_request .= "Content-Length: " . strlen($request) . "\r\n";
		$http_request .= "User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; es-ES; rv:1.9) Gecko/2008052906 Firefox/3.0 (.NET CLR 3.5.30729)\r\n";
		$http_request .= "\r\n";
		$http_request .= $request;
	
		$response = '';
		if( false != ( $fs = @fsockopen($host, 80, $errno, $errstr, 10) ) ) {
                       fwrite($fs, $http_request);
	
			while ( !feof($fs) )
				$response .= fgets($fs, 1160); // One TCP-IP packet
			fclose($fs);
			$response = explode("\r\n\r\n", $response, 2);
		}
		return $response;
	}

}
$feed_globedia = & new FeedGlobedia();

?>