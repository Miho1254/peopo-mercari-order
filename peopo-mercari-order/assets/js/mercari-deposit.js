(function ($) {
    'use strict';

    function formatCurrency(value) {
        if (window.Intl && Intl.NumberFormat) {
            return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);
        }
        return value.toLocaleString('vi-VN') + ' ₫';
    }

    function formatNumber(value) {
        if (window.Intl && Intl.NumberFormat) {
            return new Intl.NumberFormat('vi-VN').format(value);
        }
        return value.toLocaleString('vi-VN');
    }

    function fillSummary(container, data) {
        var summary = container.find('.peopo-mercari-deposit__summary').empty();

        var items = [
            { label: 'Giá gốc (JPY)', value: formatNumber(data.price_jpy) + ' ¥' },
            { label: 'Tỷ giá VCB', value: formatNumber(data.rate) + ' (' + data.rate_type + ')' },
            { label: 'Tiền hàng quy đổi', value: formatCurrency(data.deposit_vnd) },
            { label: 'Phí dịch vụ ' + data.service_fee_percent + '%', value: formatCurrency(data.service_fee) },
            { label: 'Tạm tính (chưa cân nặng)', value: formatCurrency(data.subtotal_without_weight) },
            { label: 'Phí cân nặng tham khảo', value: formatCurrency(data.weight_fee_perkg) + ' /kg (chưa tính)' }
        ];

        items.forEach(function (item) {
            summary.append($('<dt>').text(item.label));
            summary.append($('<dd>').text(item.value));
        });
    }

    function updateHiddenFields(container, data) {
        var form = container.find('.peopo-mercari-deposit__form');
        form.find('input[name="mercari_title"]').val(data.title || '');
        form.find('input[name="mercari_image"]').val(data.image || '');
        form.find('input[name="mercari_url"]').val(data.url || '');
        form.find('input[name="price_jpy"]').val(data.price_jpy || 0);
        form.find('input[name="rate_value"]').val(data.rate || '');
        form.find('input[name="rate_type"]').val(data.rate_type || '');
        form.find('input[name="deposit_vnd"]').val(data.deposit_vnd || 0);
        form.find('input[name="service_fee_percent"]').val(data.service_fee_percent || 0);
        form.find('input[name="service_fee"]').val(data.service_fee || 0);
        form.find('input[name="subtotal_without_weight"]').val(data.subtotal_without_weight || 0);
        form.find('input[name="weight_fee_perkg"]').val(data.weight_fee_perkg || 0);
    }

    function displayData(container, data) {
        var result = container.find('.peopo-mercari-deposit__result').removeAttr('hidden');
        var image = result.find('.peopo-mercari-deposit__image');
        var title = result.find('.peopo-mercari-deposit__title');
        var price = result.find('.peopo-mercari-deposit__price');

        if (data.image) {
            image.attr('src', data.image).attr('alt', data.title || 'Mercari item').removeAttr('hidden');
        } else {
            image.attr('src', '').attr('alt', '').attr('hidden', true);
        }

        title.text(data.title || '');
        price.text(data.price_jpy ? formatNumber(data.price_jpy) + ' ¥' : '');

        fillSummary(container, data);
        updateHiddenFields(container, data);
    }

    function showMessage(container, message, type) {
        var messages = container.find('.peopo-mercari-deposit__messages');
        messages.removeClass('error success').addClass(type).text(message);
    }

    function recalcManualData(baseData, manual) {
        var updated = $.extend({}, baseData);
        if (manual.title) {
            updated.title = manual.title;
        }
        if (manual.image) {
            updated.image = manual.image;
        }
        if (manual.price_jpy) {
            updated.price_jpy = manual.price_jpy;
        }

        updated.deposit_vnd = Math.round(updated.price_jpy * baseData.rate);
        updated.service_fee = Math.round(updated.deposit_vnd * (baseData.service_fee_percent / 100));
        updated.subtotal_without_weight = updated.deposit_vnd + updated.service_fee;

        return updated;
    }

    $(function () {
        $('.peopo-mercari-deposit').each(function () {
            var container = $(this);
            var fetchButton = container.find('.peopo-mercari-fetch');
            var defaultFetchText = fetchButton.text();
            var manualBox = container.find('.peopo-mercari-deposit__manual');
            var resultBox = container.find('.peopo-mercari-deposit__result');
            var manualApply = container.find('.peopo-mercari-apply-manual');
            var urlField = container.find('#peopo-mercari-url');
            var baseData = null;

            fetchButton.on('click', function () {
                var mercariUrl = urlField.val();
                if (!mercariUrl) {
                    showMessage(container, PeopoMercariDeposit.i18n.unknownError, 'error');
                    return;
                }

                fetchButton.prop('disabled', true).text(PeopoMercariDeposit.i18n.loading);
                showMessage(container, '', '');

                $.ajax({
                    url: PeopoMercariDeposit.ajaxUrl,
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        action: fetchButton.data('action'),
                        nonce: PeopoMercariDeposit.nonce,
                        url: mercariUrl
                    }
                }).done(function (response) {
                    if (!response || !response.success || !response.data) {
                        var errorMsg = PeopoMercariDeposit.i18n.unknownError;
                        if (response && response.data && response.data.message) {
                            errorMsg = response.data.message;
                        }
                        showMessage(container, errorMsg, 'error');
                        baseData = null;
                        manualBox.attr('hidden', true);
                        resultBox.attr('hidden', true);
                        return;
                    }

                    baseData = response.data.data;
                    baseData.url = mercariUrl;
                    displayData(container, baseData);

                    if (response.data.needs_manual) {
                        manualBox.removeAttr('hidden');
                        showMessage(container, PeopoMercariDeposit.i18n.fetchError, 'error');
                    } else {
                        manualBox.attr('hidden', true);
                        showMessage(container, '', '');
                    }
                }).fail(function () {
                    showMessage(container, PeopoMercariDeposit.i18n.unknownError, 'error');
                    baseData = null;
                    manualBox.attr('hidden', true);
                    resultBox.attr('hidden', true);
                }).always(function () {
                    fetchButton.prop('disabled', false).text(defaultFetchText);
                });
            });

            manualApply.on('click', function () {
                if (!baseData) {
                    return;
                }

                var manual = {
                    title: manualBox.find('.peopo-mercari-manual-title').val(),
                    price_jpy: parseInt(manualBox.find('.peopo-mercari-manual-price').val(), 10) || baseData.price_jpy,
                    image: manualBox.find('.peopo-mercari-manual-image').val()
                };

                baseData = recalcManualData(baseData, manual);
                displayData(container, baseData);
                manualBox.attr('hidden', true);
                showMessage(container, '', '');
            });

            container.find('.peopo-mercari-deposit__form').on('submit', function (event) {
                if (!baseData || !baseData.price_jpy) {
                    event.preventDefault();
                    showMessage(container, PeopoMercariDeposit.i18n.fetchError, 'error');
                }
            });
        });
    });
})(jQuery);
