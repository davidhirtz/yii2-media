/**
 * Extends confirm dialog with bootbox checkbox for deleting
 * asset file with asset.
 */
Skeleton.deleteFilesWithAssets = function () {
    var _confirm = yii.confirm;
    yii.confirm = function (message, ok, cancel) {

        var $link = $(this),
            deleteUrl = $link.data('delete-url');

        if (!deleteUrl) {
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

Skeleton.mediaFileImport = function () {
    var _ = this;

    $('.btn-import').on('click', function () {
        var $btn = $(this),
            url = $btn.data('url');

        bootbox.prompt({
            title: $btn.data('title'),
            inputType: 'text',
            placeholder: $btn.data('placeholder'),
            buttons: {
                confirm: {
                    label: $btn.data('confirm')
                }
            },
            callback: function (result) {
                if (result !== null && result.length && url) {
                    $.ajax({
                        url: url,
                        data: {url: result},
                        method: 'post',
                        success: function () {
                            _.replaceWithAjax('#files');
                        }
                    });
                }
            }
        });
    });
};

/**
 * Registers image crop on active form element.
 * This only works if the JQueryCropperAsset is registered first.
 */
Skeleton.registerImageCrop = function () {
    //noinspection JSUnresolvedFunction
    var $image = $('#image'),
        fields = ['width', 'height', 'x', 'y'],
        $imageClearBtn = $('#image-clear'),
        cropper = new Cropper($image[0], {
            autoCrop: false,
            guides: false,
            minContainerHeight: 1,
            minContainerWidth: 1,
            modal: false,
            movable: false,
            rotatable: false,
            scalable: false,
            viewMode: 3,
            zoomable: false,
            cropend: function () {
                var data = cropper.getData(true);

                fields.forEach(function (field) {
                    $('#image-' + field).val(data[field]);
                });

                $imageClearBtn.show();
            }
        });

    $imageClearBtn.click(function () {
        fields.forEach(function (field) {
            $('#image-' + field).val('');
        });

        cropper.clear();
        $imageClearBtn.hide();
    });
};