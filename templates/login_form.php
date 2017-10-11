<div class="login-form-container">
    <form method="post" id="login-form" >
        <div class="login-errors"></div>
        <div class="login-username">
            <label for="user_login"><?php _e( 'Логин', 'personalize-login' ); ?></label>
            <input type="text" name="log" id="user_login" required>
        </div>
        <div class="login-captha">
            <div class="g-recaptcha" data-sitekey="6LdR8CoUAAAAAO3TTIWEFkL40GIFuiXzw_tQlHCG"></div>
        </div>
        <div class="login-submit">
            <input type="submit" value="Войти">
        </div>
    </form>
</div>
<div id="sms_field"></div>