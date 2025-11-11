import type CropperHandle from '@cropper/element-handle';
import type CropperSelection from "@cropper/element-selection";

export default ($selection: CropperSelection, action: string, theme: string) => {
    const $handle = document.createElement('cropper-handle') as CropperHandle;
    $handle.action = action;
    $handle.themeColor = theme;
    $selection.appendChild($handle);
}