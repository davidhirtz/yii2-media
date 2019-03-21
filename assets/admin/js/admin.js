window.deleteFilesWithAssets = function () {

    var _confirm = yii.confirm;

    yii.confirm = function (message, ok, cancel) {

        var $link = $(this),
            deleteUrl = $link.data('delete-url');

        if(!deleteUrl) {
            return $.proxy(_confirm, $link, message, ok, cancel)();
        }

        bootbox.prompt({
            title: message,
            inputType: 'checkbox',
            inputOptions: [{
                text: $link.data('delete-message'),
                value: 1
            }],
            callback: function (result) {
                if (result !== null) {
                    $.post(result.length ? $link.data('delete-url') : $link.attr('href'), function () {
                        $link.closest('tr').remove();
                    });
                }
            }
        })
    };
};