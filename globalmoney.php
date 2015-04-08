<?php
/**
 * @package GlobalMoney
 * @version 1.0
 */
/*
Plugin Name: GlobalMoney
Plugin URI: https://globalmoney.ua
Description: GlobalMoney
Armstrong: My Plugin.
Author: Global money
Version: 1.0
Author URI: https://globalmoney.ua
*/

session_start();

function globalmoney_add_admin_pages()
{
    // Add a new submenu under Options:
    add_options_page('GlobalMoney', 'GlobalMoney', 8, 'globalmoney', 'global_money_options_page');
}

// mt_options_page() displays the page content for the Test Options submenu
function global_money_options_page()
{
	echo "<h2>Настройка платежной системы GlobalMoney</h2>";
	echo "<p>Автор плагина: <a href='https://globalmoney.ua'>GlobalMoney</a></p>";

	//Изменение данных магазина
	echo "<h3>Общие настройки приложения</h3>";
	globalmoney_change_shop();

}

//Изменение данных магазина
function globalmoney_change_shop()
{
	//Если форма была отправлена, то применить изменения магазина
	if (isset($_POST['base_setup_btn']))
	{
	   if ( function_exists('current_user_can') &&
			!current_user_can('manage_options') )
				die ( _e('Hacker?', 'morkovin') );

		if (function_exists ('check_admin_referer') )
		{
			check_admin_referer('globalmonye_base_setup_form');
		}

		$globalmoney_shop_id = $_POST['globalmoney_shop_id'];
		$globalmoney_secret_key = $_POST['globalmoney_secret_key'];
		$globalmoney_status_url = $_POST['globalmoney_status_url'];
		$globalmoney_destination_wallet = $_POST['globalmoney_destination_wallet'];

		update_option('globalmoney_shop_id', $globalmoney_shop_id);
		update_option('globalmoney_secret_key', $globalmoney_secret_key);
		update_option('globalmoney_status_url', $globalmoney_status_url);
		update_option('globalmoney_destination_wallet', $globalmoney_destination_wallet);
	}

	//Форма информации о магазине
	echo
	"
		<form name='morkovin_base_setup' method='post' action='".$_SERVER['PHP_SELF']."?page=globalmoney&amp;updated=true'>
	";

	if (function_exists ('wp_nonce_field') )
	{
		wp_nonce_field('globalmonye_base_setup_form');
	}
	echo
	"
		<table>
			<tr>
				<td style='text-align:right;'>Кошелек получателя:</td>
				<td><input type='text' name='globalmoney_destination_wallet' value='".get_option('globalmoney_destination_wallet')."'/></td>
				<td style='color:#666666;'><i>email или телефон или идентификатор кошелька получателя.</i></td>
			</tr>
			<tr>
				<td style='text-align:right;'>Идентификатор приложения:</td>
				<td><input type='text' name='globalmoney_shop_id' value='".get_option('globalmoney_shop_id')."'/></td>
				<td style='color:#666666;'><i>Идентификатор приложения.</i></td>
			</tr>
			<tr>
				<td style='text-align:right;'>Секретный ключ:</td>
				<td><input type='text' name='globalmoney_secret_key' value='".get_option('globalmoney_secret_key')."'/></td>
				<td style='color:#666666;'><i>Секретный ключ можно узнать в личном кабинете GlobalMoney.</i></td>
			</tr>
			<tr>
				<td style='text-align:right;'>Redirect URI:</td>
				<td><input type='text' name='globalmoney_status_url' value='".get_option('globalmoney_status_url')."'/></td>
				<td style='color:#666666;'><i>Ссылка на страницу обработки платежа. Должен совпадать с указаным в приложении.</i></td>
			</tr>
			<tr>
				<td>&nbsp;</td>
				<td style='font-size:10px; color:#666666'>http://www.moyblog.ru/payment</td>
				<td>&nbsp;</td>
			</tr>

			<tr>
				<td>&nbsp;</td>
				<td style='text-align:center'>
					<input type='submit' name='base_setup_btn' value='Сохранить' style='width:140px; height:25px'/>
				</td>
				<td>&nbsp;</td>
			</tr>
		</table>
	</form>
	";
}


function confirm_payment(){
    $code = $_GET['code'];
    if (!$code) return;
    $grant_type = 'authorization_code';
    $client_secret = get_option('globalmoney_secret_key');
    $client_id = get_option('globalmoney_shop_id');

        $curl = curl_init();

		curl_setopt($curl, CURLOPT_URL, "https://globalmoney.ua/my/permissions/token/");
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, "grant_type=".$grant_type."&client_secret=".$client_secret."&code=".$code."&client_id=".$client_id);

		$result = curl_exec($curl);
		curl_close($curl);

//    print_r($result);
//    echo $_SESSION['price'];

    $answer = json_decode($result, true);
    if (isset($answer['error']))
        echo $answer['error'];
    else{

        $destination = get_option('globalmoney_destination_wallet');
        $amount = 0;
        if(isset($_SESSION['price']))
            $amount = $_SESSION['price'];
        if (!$amount) return false;

        $comment = "application pay";
        if(isset($_SESSION['comment']))
            $comment = $_SESSION['comment'];

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, "https://api.globalmoney.ua/payment");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer '.$answer['access_token'],
            'Content-Type: application/x-www-form-urlencoded;charset=UTF-8')
		);
        curl_setopt($curl, CURLOPT_POST, true);
        $json_post = '{"destination" : "'.$destination.'","amount" : '.$amount.',"comment" : "'.$comment.'"}';
        //echo $json_post;
        curl_setopt($curl, CURLOPT_POSTFIELDS, $json_post);

        $result = curl_exec($curl);
        curl_close($curl);

        print_r($result);
    }

    if(isset($_SESSION['price']))
        unset($_SESSION['price']);
    if(isset($_SESSION['comment']))
        unset($_SESSION['comment']);

    die();
}

function no_code(){
    echo 'no_code';
    die();
}

function globalmoney_run($content)
{

	$status_url = get_option('globalmoney_status_url');
	preg_match('/^http(s)?\:\/\/[^\/]+\/(.*)$/i', $status_url, $matches);



	$real_url = $_SERVER['REQUEST_URI'];
	preg_match('/^\/([^\?]*)(\?.+)?$/i', $real_url, $real_matches);

	if($real_matches[1] == $matches[2])
	{
		if ( isset($_GET['code']) )
		{
            confirm_payment();
		}
		else
		{
		    no_code();
		}
	}
}

function globalmoney_install()
{
	//Значения по умолчанию для настроек магазина
	add_option('globalmoney_shop_id', 'Не задано');
	add_option('globalmoney_secret_key', 'Не задано');
	add_option('globalmoney_status_url', 'http://myblog.loc/status');
	add_option('globalmoney_destination_wallet', 'Не задано');
}

function globalmoney_uninstall()
{
	delete_option('globalmoney_shop_id');
	delete_option('globalmoney_secret_key');
	delete_option('globalmoney_status_url');
	delete_option('globalmoney_destination_wallet');
}

register_activation_hook( __FILE__, 'globalmoney_install');
register_deactivation_hook( __FILE__, 'globalmoney_uninstall');

add_action('admin_menu', 'globalmoney_add_admin_pages');
add_action( 'init', 'globalmoney_run' );

function shortcode_func( $atts ) {
    $a = shortcode_atts( array(
        'title' => 'title',
        'price' => '0',
    ), $atts );


	$price = (int)((float) $a['price'] * 100);
    $_SESSION['price'] = $price;
    $_SESSION['comment'] = $a['title'];

    return "<a href='https://globalmoney.ua/my/permissions/?response_type=code&client_id=".get_option('globalmoney_shop_id')."&redirect_uri=".get_option('globalmoney_status_url')."&scope=charge(".$price.")' target='_blank'>GlobalMoney</a>";
}
add_shortcode( 'globalmoney', 'shortcode_func' );

?>
