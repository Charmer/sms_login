<?php

/**
 * Plugin Name:       SMS Login
 * Description:       A plugin that replaces the WordPress login flow with a
 * custom page. Version:           1.0.0 Author:            Timur Ahmedov
 * License:           GPL-2.0+ Text Domain:       sms-login
 */

global $live_time;
$live_time = "3 minutes";
global $block_time;
$block_time = "600";  //время в секундах. 600 секунд = 10 минут

class Sms_Login_Plugin {

	/**
	 * Инициализация плагина.
	 */
	public function __construct() {
		add_shortcode( 'custom-login-form', [ $this, 'render_login_form' ] );
		add_action( 'wp_logout', [ $this, 'redirect_after_logout' ] );
	}



	/**
	 * Шорткод формы входа в систеиу.
	 *
	 * @param  array $attributes Атрибуты шорткода.
	 * @param  string $content Текст шорткода.
	 *
	 * @return string  The shortcode output
	 */
	public function render_login_form( $attributes, $content = NULL ) {
		// Разбор атрибутов шорткода
		$default_attributes = [ 'show_title' => FALSE ];
		$attributes         = shortcode_atts( $default_attributes, $attributes );
		$show_title         = $attributes['show_title'];
		// Проверяем был ли осуществлён выход
		$attributes['logged_out'] = isset( $_REQUEST['logged_out'] ) && $_REQUEST['logged_out'] == TRUE;


		if ( is_user_logged_in() ) {
			return __( 'Вы уже вошли.', 'personalize-login' );
		}

		// Передаём параметр для перенаправления: по умолчанию,
		// Если будет передан валиндый параметр, то
		// обрабатываем его.
		$attributes['redirect'] = '';
		if ( isset( $_REQUEST['redirect_to'] ) ) {
			$attributes['redirect'] = wp_validate_redirect( $_REQUEST['redirect_to'], $attributes['redirect'] );
		}
		// Сообщения об ошибках
		$errors = [];
		if ( isset( $_REQUEST['login'] ) ) {
			$error_codes = explode( ',', $_REQUEST['login'] );

			foreach ( $error_codes as $code ) {
				$errors [] = $this->get_error_message( $code );
			}
		}
		$attributes['errors'] = $errors;


		// Отображаем форму входа
		return $this->get_template_html( 'login_form', $attributes );
	}

	/**
	 * Рендер шаблона формы
	 *
	 * @param string $template_name Название шаблона (без .php)
	 * @param array $attributes PHP-переменные для передачи в шаблон
	 *
	 * @return string               Содержимое шаблона
	 */
	private function get_template_html( $template_name, $attributes = NULL ) {
		if ( ! $attributes ) {
			$attributes = [];
		}
		ob_start();
		do_action( 'sms_login_before_' . $template_name );
		require( 'templates/' . $template_name . '.php' );
		do_action( 'sms_login_after_' . $template_name );
		$html = ob_get_contents();
		ob_end_clean();

		return $html;
	}



	/**
	 * Находит и возвращает сообщение об ошибки в зависимости от кода.
	 *
	 * @param string $error_code Код ошибки.
	 *
	 * @return string               Сообщение об ошибке.
	 */
	private function get_error_message( $error_code ) {
		switch ( $error_code ) {
			case 'empty_username':
				return __( 'Логин не может быть пустым', 'personalize-login' );

			case 'empty_password':
				return __( 'Вы не ввели СМС-код', 'personalize-login' );

			case 'invalid_username':
				return __(
					"Пользователь с таким логином не найден",
					'personalize-login'
				);

			case 'sms_time':
				return __(
					"Время жизни СМС-пароля истекло!",
					'personalize-login'
				);

			case 'incorrect_password':
				$err = __(
					"Введите код, полученный по СМС",
					'personalize-login'
				);

				return sprintf( $err, wp_lostpassword_url() );

			default:
				break;
		}

		return __( 'Неизвестная ошибка. Пожалуйста, попробуйте позже', 'personalize-login' );
	}

	/**
	 * Отображение собственной страницы аутентификации после выхода.
	 */
	public function redirect_after_logout() {
		$redirect_url = home_url( 'member-login?logged_out=true' );
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Возвращает URL на который должен быть перенаправлен пользователь после
	 * успешной аутентификации.
	 *
	 * @param string $redirect_to URL для перенаправления.
	 * @param string $requested_redirect_to Запрашиваемый URL.
	 * @param WP_User|WP_Error $user Объект WP_User если аутентификация прошла
	 *     успешна или объект WP_Error в обратном случае.
	 *
	 * @return string URL для перенаправления
	 */



}

// Инициализация плагина
$personalize_login_pages_plugin = new Sms_Login_Plugin();

// Создаём страницы при активации плагина
register_activation_hook( __FILE__, [
	'Sms_Login_Plugin',
	//'plugin_activated',
] );

wp_deregister_script('jquery');
wp_enqueue_script('jquery','//ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js','','',true);
wp_enqueue_script( 'sms-login', plugin_dir_url( __FILE__ ) . 'templates/sms-login.js', '', '', TRUE );
//Зарегистрируем AJAX-запрос для НЕавторизированных на высылание СМС
add_action( 'wp_ajax_nopriv_get_sms_pass', 'get_sms_pass' );
add_action( 'wp_ajax_nopriv_check_sms_pass', 'check_sms_pass' );

//Меняем пароль и высылаем его по СМС
function get_sms_pass() {

	//Проверим капчу
	$secret    = "6LdR8CoUAAAAAKWcQclnOzD15iIymjvwNuw9OkH9";
	$recaptcha = $_POST['captcha'];
	$url       = "https://www.google.com/recaptcha/api/siteverify?secret=" . $secret . "&response=" . $recaptcha . "&remoteip=" . $_SERVER['REMOTE_ADDR'];
	$status    = 1;
	if ( ! empty( $recaptcha ) ) {
		$curl = curl_init();
		if ( ! $curl ) {
			$status = 2;
		} else {
			curl_setopt( $curl, CURLOPT_URL, $url );
			curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
			curl_setopt( $curl, CURLOPT_TIMEOUT, 10 );
			curl_setopt( $curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.2.16) Gecko/20110319 Firefox/3.6.16" );
			$curlData = curl_exec( $curl );
			curl_close( $curl );
			$curlData = json_decode( $curlData, TRUE );
			if ( $curlData['success'] ) {
				$status = 0;
			}
		}
	}
	if ( $status === 1 ) {
		$responce = [
			'status'  => FALSE,
			'message' => "Проверка на бота не пройдена",
		];
    header( 'Content-Type: application/json' );
		echo json_encode( $responce );
		die();
	} else if ( $status === 2 ) {
		$responce = [
			'status'  => FALSE,
			'message' => "Ошибка при проверке на бота",
		];
		header( 'Content-Type: application/json' );
		echo json_encode( $responce );
		die();
	}
	//Найдем пользователя по полученному логину
	$user = get_user_by( 'login', $_POST['login'] );
	//Запишем ему в мета СМС-код
	if ( ! $user ) {
		$responce = [
			'status'  => FALSE,
			'message' => "Пользователь с табельным номером " . $_POST['login'] . " не найден",
		];
		header( 'Content-Type: application/json' );
		echo json_encode( $responce );
		die();
	}
	global $block_time;
	$blocked =  get_user_meta( $user->ID, "blocked", TRUE );
	//if($blocked != "" && (time() - $blocked)  < $block_time)
	if((time() - $blocked)  < $block_time)
	{
		update_user_meta($user->ID, 'fails', 0 );
		$responce = [
			'status'  => FALSE,
			'message' => "Ваш аккаунт заблокирован! Время до разблокировки:",
			'blocked' => (time() - ($blocked + $block_time)) * (-1)
		];
		header( 'Content-Type: application/json' );
		echo json_encode( $responce );
		die();
	}

	$password = rand( 1000, 9999 );
	update_user_meta( $user->ID, 'code', $password );
	update_user_meta( $user->ID, 'gen_time', time() );
	global $live_time;
	//mail( $user->user_email, "Код для входа", "Ваш код для входа: " . $password );
	$last_generation = get_user_meta( $user->ID, "getn_time", TRUE );
	$live            = ( $last_generation + strtotime( "+" . $live_time ) - time() );
	$responce        = [
		'status'   => TRUE,
		'livetime' => $live,
		"pass"     => $password,
	];
	header( 'Content-Type: application/json' );
	echo json_encode( $responce );
	die();
}

function check_sms_pass() {
	//Найдем пользователя по присланному логину
	$user                  = get_user_by( "login", $_POST['login'] );
	$creds                  = [];
	$creds['user_login']    = $_POST['login'];
	$creds['user_password'] = $_POST['pass'];
	$creds['remember']      = FALSE;
	global $live_time;
	global $block_time;
	//Проверим время жизни пароля
	//Если пользователь найден, залогиним его по присланному логину/паролю
	if ( $user ) {
		$fails =  get_user_meta( $user->ID, "fails", TRUE );
		if($fails == ""){$fails=0;}
		if($fails > 1)
		{
			update_user_meta($user->ID, 'blocked', time() );
			$responce = [
				'status'  => FALSE,
				'message' => "Пользователь заблокирован, осталось: ",
				'blocked' => $block_time
			];
			header( 'Content-Type: application/json' );
			echo json_encode( $responce );
			die();
		}
		$last_generation = get_user_meta( $user->ID, "gen_time", TRUE );
		$now             = strtotime( "-" . $live_time );
		if ( $now > $last_generation ) {
			$responce = [
				'status'  => FALSE,
				'message' => "Превышено время действия одноразового пароля",
			];
			header( 'Content-Type: application/json' );
			echo json_encode( $responce );
			die();
		}
		$code = get_user_meta( $user->ID, "code", TRUE );
		if ( $_POST['pass'] == $code ) {
			wp_set_auth_cookie( $user->ID );
			update_user_meta($user->ID, 'fails', 0 );
			$responce = [ 'status' => TRUE ];
			header( 'Content-Type: application/json' );
			echo json_encode( $responce );
      die();
		} else {
			$fails++;
			update_user_meta($user->ID, 'fails', $fails );
			$responce = [
				'status'  => FALSE,
				'message' => "Код из СМС указан неверно",
				'fails' => $fails
			];
			header( 'Content-Type: application/json' );
			echo json_encode( $responce );
			die();
		}

	}


}
