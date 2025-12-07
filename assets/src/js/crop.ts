import CropperCanvas from '@cropper/element-canvas';
import CropperHandle from '@cropper/element-handle';
import CropperSelection from "@cropper/element-selection";
import type {Selection} from '@cropper/element-selection';

type propertyNames = Extract<keyof Selection, string>;

const handle = ($selection: CropperSelection, action: string, theme: string) => {
    const $handle = document.createElement('cropper-handle') as CropperHandle;
    $handle.action = action;
    $handle.themeColor = theme;
    $selection.appendChild($handle);
}

CropperCanvas.$define();
CropperSelection.$define();
CropperHandle.$define();

document.addEventListener('htmx:load', (event) => {
    const $container = (event as CustomEvent).detail.elt as HTMLElement;

    const $image = $container.querySelector('[data-id="image"]') as HTMLImageElement;
    const $form = $image.closest('form') as HTMLElement;

    const $open = $form.querySelector('[data-id="image-open"]') as HTMLButtonElement;
    const $cancel = $form.querySelector('[data-id="image-cancel"]') as HTMLButtonElement;
    const $ratio = $form.querySelector('[data-id="ratio"]') as HTMLInputElement;
    const $ratioRow = $ratio.closest('.form-row') as HTMLElement;

    const toggleElements = (open: boolean) => {
        $ratioRow.hidden = !open;
        $canvas.hidden = !open;
        $cancel.hidden = !open;
        $open.hidden = open;
    }

    const $inputs: Map<propertyNames, HTMLInputElement> = new Map();
    const properties: propertyNames[] = ['x', 'y', 'width', 'height'];

    const updateInputs = () => {
        properties.forEach((name) => {
            const value = $selection[name] * (name === 'x' || name === 'width'
                ? ($image.naturalWidth / $image.width)
                : ($image.naturalHeight / $image.height));

            return $inputs.get(name)!.value = String(Math.round(value));
        });
    }

    let $canvas: CropperCanvas
    let $selection: CropperSelection

    properties.forEach(name => {
        $inputs.set(name, $form.querySelector(`[data-id="${name}"]`) as HTMLInputElement);
    });

    $open.onclick = () => {
        if (!$canvas) {
            $canvas = document.createElement('cropper-canvas') as CropperCanvas;
            $selection = document.createElement('cropper-selection') as CropperSelection;

            $canvas.style.position = 'absolute';
            $canvas.style.inset = '0';

            $selection.style.outline = '10000px solid rgba(0, 0, 0, 0.5)';
            $selection.initialCoverage = .75;
            $selection.movable = true;
            $selection.resizable = true;

            handle($selection, 'move', 'transparent');

            ['n', 'e', 's', 'w', 'ne', 'nw', 'se', 'sw']
                .forEach(action => handle($selection, `${action}-resize`, '#fff'));

            $canvas.appendChild($selection);

            $image.parentElement!.style.position = 'relative';
            $image.parentElement!.appendChild($canvas);

            $selection.addEventListener('change', (event: Event) => {
                const bounds = $canvas.getBoundingClientRect();
                const pos = (event as CustomEvent).detail as Selection;

                if (
                    pos.x < 0
                    || pos.y < 0
                    || (pos.x + pos.width) > bounds.width
                    || (pos.y + pos.height) > bounds.height
                ) {
                    event.preventDefault();
                }

                updateInputs();
            });

            $ratio.onchange = () => {
                const ratio = parseFloat($ratio.value);
                $selection.aspectRatio = isNaN(ratio) || ratio <= 0 ? NaN : ratio;
            }

            $cancel.onclick = () => {
                $inputs.forEach($input => $input.value = '');
                toggleElements(false);
            }
        } else {
            updateInputs();
        }

        toggleElements(true);
        $canvas.scrollIntoView({behavior: 'smooth'});
    }
});
