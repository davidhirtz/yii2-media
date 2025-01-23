// /**
//  * Extends confirm dialog with bootbox checkbox for deleting
//  * asset file with asset.
//  */
// Skeleton.deleteFilesWithAssets = function () {
//     var _confirm = yii.confirm;
//     yii.confirm = function (message, ok, cancel) {
//
//         var $link = $(this),
//             deleteUrl = $link.data('delete-url');
//
//         if (!deleteUrl) {
//             return $.proxy(_confirm, $link, message, ok, cancel)();
//         }
//
//         bootbox.prompt({
//             title: message,
//             inputType: 'checkbox',
//             inputOptions: [{
//                 text: $link.data('delete-message'),
//                 value: 1
//             }],
//             callback: function (result) {
//                 if (result !== null) {
//                     $.post(result.length ? $link.data('delete-url') : $link.attr('href'), function () {
//                         $link.closest('tr').remove();
//                     });
//                 }
//             }
//         })
//     };
// };
//
// Skeleton.mediaFileImport = function () {
//     var _ = this;
//
//     $('.btn-import').on('click', function () {
//         var $btn = $(this),
//             url = $btn.data('url');
//
//         bootbox.prompt({
//             title: $btn.data('title'),
//             inputType: 'text',
//             placeholder: $btn.data('placeholder'),
//             buttons: {
//                 confirm: {
//                     label: $btn.data('confirm')
//                 }
//             },
//             callback: function (result) {
//                 if (result !== null && result.length && url) {
//                     $.ajax({
//                         url: url,
//                         data: {url: result},
//                         method: 'post',
//                         success: function () {
//                             var $files = $('#files');
//
//                             if ($files.length) {
//                                 _.replaceWithAjax('#files');
//                             } else {
//                                 location.reload();
//                             }
//                         }
//                     });
//                 }
//             }
//         });
//     });
// };
//
// /**
//  * Registers image crop on active form element.
//  * This only works if the JQueryCropperAsset is registered first.
//  */
// Skeleton.registerImageCrop = function () {
//     //noinspection JSUnresolvedFunction
//     var $image = $('#image'),
//         fields = ['width', 'height', 'x', 'y'],
//         $showOnCropEnd = $('.show-on-crop-end'),
//         $imageClearBtn = $('#image-clear'),
//         $ratioSelect = $('#image-ratio'),
//         cropper;
//
//     function init(ratio) {
//         if (cropper) {
//             cropper.destroy();
//         }
//
//         cropper = new Cropper($image[0], {
//             autoCrop: false,
//             guides: false,
//             minContainerHeight: 1,
//             minContainerWidth: 1,
//             modal: false,
//             movable: false,
//             rotatable: false,
//             scalable: false,
//             viewMode: 3,
//             zoomable: false,
//             aspectRatio: ratio,
//             cropend: function () {
//                 var data = cropper.getData(true);
//
//                 fields.forEach(function (field) {
//                     $('#image-' + field).val(data[field]);
//                 });
//
//                 $showOnCropEnd.show();
//             }
//         })
//     }
//
//     function reset() {
//         fields.forEach(function (field) {
//             $('#image-' + field).val('');
//         });
//
//         cropper.clear();
//     }
//
//     $imageClearBtn.click(function () {
//         reset();
//         $showOnCropEnd.hide();
//     });
//
//     $ratioSelect.change(function () {
//         init($(this).find('option:selected').val());
//         reset();
//     });
//
//     init();
// };