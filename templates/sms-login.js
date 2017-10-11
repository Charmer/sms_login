(function($){

    $(document).on('keypress', '#user_login, #sms_code', function (e) {
        if (e.which != 8 && e.which != 0 && (e.which < 48 || e.which > 57)) {
            return false;
        }
    });
    $(document).on('paste', '#user_login, #sms_code', function(){
        return false;
    });

    var sms_sended = false, sms_timer, unlock_timer;
    $('#login-form button[type="submit"]').on('click', function(event) {

        event.preventDefault();
        if( !$(this).is(':disabled') ){

            $('.login-errors').html('');

            if( $('#user_login').val() == '' ){
                $('.login-errors').append('<div class = "login-error">Поле "Логин" не заполнено</div>');
            } else if( $('#sms_code').val() == '' ){
                $('.login-errors').append('<div class = "login-error">Не указан код из SMS</div>');
            } else {
                if( !sms_sended ){
                    $.ajax({
                        url: '/wp-admin/admin-ajax.php',
                        data: {'action': 'get_sms_pass', 'login': $('#user_login').val(), 'captcha': grecaptcha.getResponse() },
                        type: 'POST',
                        success: function(response){
                            if( !response.status ){
                                sms_sended = false;
                                $('.login-errors').append('<div class = "login-error">'+ response.message +'</div>');
                                // пользователь заблокирован
                                if( typeof response.blocked != 'undefined' ){
                                    renderBlockTimer(response.blocked);
                                }

                            } else {

                                // убираем капчу, отправляем пароль
                                $('.login-captha').remove();
                                $('.login-username').after('<div class = "login-sms"><label for="user_login">Код из SMS<div class = "sms-timer"></div></label><input type="text" id="sms_code" required></div>');
                                renderSMSTimer(response.livetime);
                                alert(response.pass);
                                sms_sended = true;
                            }

                            console.log(response);
                        }
                    });
                } else {
                    $.ajax({
                        url: '/wp-admin/admin-ajax.php',
                        data: {'action': 'check_sms_pass', 'login': $('#user_login').val(), 'pass': $('#sms_code').val() },
                        type: 'POST',
                        success: function(response){
                            if( !response.status ){
                                $('.login-errors').append('<div class = "login-error">'+ response.message +'</div>');
                                // пользователь заблокирован
                                if( typeof response.blocked != 'undefined' ){
                                    clearInterval(sms_timer);
                                    $('.sms-timer, .login-sms').remove();
                                    renderBlockTimer(response.blocked);
                                }
                            } else {
                                location.href = "/";
                            }
                            console.log(response);
                        }
                    });
                }
            }
        }
    });

    function renderSMSTimer(time) {
        var seconds = time % 60;
        if( seconds < 10 ) seconds += '0';
        var minutes = ( time - seconds ) / 60;

        sms_timer = setInterval(function(){
            var seconds = time % 60;
            if( seconds < 10 ) seconds += '0';
            var minutes = ( time - seconds ) / 60;
            $('.sms-timer').html(minutes + ':' + seconds);
            time -= 1;
        }, 1000);
        setTimeout(function(){
            window.location.reload();
        }, time * 1000);
    }

    function renderBlockTimer(time) {
        $('#login-form button[type="submit"]').prop('disabled', true);
        $('.login-error').append('<div class = "unlock-timer"></div>');

        unlock_timer = setInterval(function(){
            var seconds = time % 60;
            if( seconds < 10 ) seconds += '0';
            var minutes = ( time - seconds ) / 60;
            $('.unlock-timer').html(minutes + ':' + seconds);
            time -= 1;
        }, 1000);
        setTimeout(function(){
            window.location.reload();
        }, time * 1000);
    }

})(jQuery);