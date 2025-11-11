import CropperCanvas from '@cropper/element-canvas';
import CropperHandle from '@cropper/element-handle';
import CropperSelection from "@cropper/element-selection";
import type {Selection} from '@cropper/element-selection';

import handle from "./image/handle";

export default (selector: string) => {
    const $form = document.querySelector(selector) as HTMLElement;
    const $image = $form.querySelector('[data-id="image"]') as HTMLImageElement;
    const $widthInput = $form.querySelector('[data-id="width"]') as HTMLInputElement;
    const $heightInput = $form.querySelector('[data-id="height"]') as HTMLInputElement;
    const $xInput = $form.querySelector('[data-id="x"]') as HTMLInputElement;
    const $yInput = $form.querySelector('[data-id="y"]') as HTMLInputElement;

    const $ratio = $form.querySelector('[data-id="ratio"]') as HTMLInputElement;

    const $wrap = $form.querySelector('[data-id="image-wrap"]') as HTMLElement;
    const $open = $form.querySelector('[data-id="image-open"]') as HTMLButtonElement;
    const $cancel = $form.querySelector('[data-id="image-cancel"]') as HTMLButtonElement;

    const $inputs = new Map<string, HTMLInputElement>([
        ['width', $widthInput],
        ['height', $heightInput],
        ['x', $xInput],
        ['y', $yInput],
    ]);

    const toggleElements = (open: boolean) => {
        $wrap.hidden = !open;
        $canvas.hidden = !open;
        $cancel.hidden = !open;
        $open.hidden = open;
    }

    let $canvas: CropperCanvas
    let $selection: CropperSelection

    $open.onclick = () => {
        if (!$canvas) {
            $canvas = document.createElement('cropper-canvas') as CropperCanvas;
            $selection = document.createElement('cropper-selection') as CropperSelection;

            $canvas.style.position = 'absolute';
            $canvas.style.inset = '0';

            $selection.style.outline = '10000px solid rgba(0, 0, 0, 0.5)';
            $selection.precise = true;
            $selection.initialCoverage = .75;
            $selection.movable = true;
            $selection.resizable = true;

            const actions = [
                'n-resize',
                'e-resize',
                's-resize',
                'w-resize',
                'ne-resize',
                'nw-resize',
                'se-resize',
                'sw-resize',
            ];

            handle($selection, 'move', 'transparent');
            actions.forEach(action => handle($selection, action, '#fff'));

            $canvas.appendChild($selection);

            $image.parentElement.style.position = 'relative';
            $image.parentElement.appendChild($canvas);

            $selection.addEventListener('change', (event: CustomEvent) => {
                const bounds = $canvas.getBoundingClientRect();
                const pos = event.detail as Selection;

                if (
                    pos.x < 0
                    || pos.y < 0
                    || (pos.x + pos.width) > bounds.width
                    || (pos.y + pos.height) > bounds.height
                ) {
                    event.preventDefault();
                }

                $widthInput.value = Math.min(pos.width, bounds.width).toString();
                $heightInput.value = Math.min(pos.height, bounds.height).toString();
                $xInput.value = Math.max(0, pos.x).toString();
                $yInput.value = Math.max(0, pos.y).toString();
            });

            $ratio.addEventListener('change', () => {
                const ratio = parseFloat($ratio.value);
                $selection.aspectRatio = isNaN(ratio) || ratio <= 0 ? NaN : ratio;
            });

            $cancel.onclick = () => {
                toggleElements(false);
            }
        }

        toggleElements(true);
        $canvas.scrollIntoView({behavior: 'smooth'});
    }
}

CropperCanvas.$define();
CropperHandle.$define();
CropperSelection.$define();
