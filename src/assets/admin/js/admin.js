
// /**
//  * Registers image crop on active form element.
//  * This only works if the JQueryCropperAsset is registered first.
//  */
Skeleton.registerImageCrop = function () {
    //noinspection JSUnresolvedFunction
    var $image = $('#image'),
        fields = ['width', 'height', 'x', 'y'],
        $showOnCropEnd = $('.show-on-crop-end'),
        $imageClearBtn = $('#image-clear'),
        $ratioSelect = $('#image-ratio'),
        cropper;

    function init(ratio) {
        if (cropper) {
            cropper.destroy();
        }

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
            aspectRatio: ratio,
            cropend: function () {
                var data = cropper.getData(true);

                fields.forEach(function (field) {
                    $('#image-' + field).val(data[field]);
                });

                $showOnCropEnd.show();
            }
        })
    }

    function reset() {
        fields.forEach(function (field) {
            $('#image-' + field).val('');
        });

        cropper.clear();
    }

    $imageClearBtn.click(function () {
        reset();
        $showOnCropEnd.hide();
    });

    $ratioSelect.change(function () {
        init($(this).find('option:selected').val());
        reset();
    });

    init();
};