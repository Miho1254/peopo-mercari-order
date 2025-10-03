(function ($) {
    'use strict';

    $(function () {
        var button = $('#peopo-mercari-test-rate');
        var result = $('#peopo-mercari-test-rate-result');

        if (!button.length) {
            return;
        }

        var defaultText = button.text();

        button.on('click', function () {
            button.prop('disabled', true).text(PeopoMercariAdmin.i18n.testing);
            result.removeClass('error success').text('');

            $.ajax({
                url: PeopoMercariAdmin.ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: PeopoMercariAdmin.action,
                    nonce: PeopoMercariAdmin.nonce
                }
            }).done(function (response) {
                if (response && response.success && response.data) {
                    var rate = response.data.rate;
                    var type = response.data.type;
                    var formattedRate = rate;

                    if (window.Intl && Intl.NumberFormat) {
                        formattedRate = new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 2 }).format(rate);
                    }

                    var message = PeopoMercariAdmin.i18n.success;
                    message = message.replace('%s', formattedRate).replace('%s', type);

                    result.addClass('success').text(message);
                } else if (response && response.data && response.data.message) {
                    var errorMessage = PeopoMercariAdmin.i18n.error.replace('%s', response.data.message);
                    result.addClass('error').text(errorMessage);
                } else {
                    var generic = PeopoMercariAdmin.i18n.error.replace('%s', 'Unknown error');
                    result.addClass('error').text(generic);
                }
            }).fail(function () {
                var error = PeopoMercariAdmin.i18n.error.replace('%s', 'Network error');
                result.addClass('error').text(error);
            }).always(function () {
                button.prop('disabled', false).text(defaultText);
            });
        });
    });
})(jQuery);
