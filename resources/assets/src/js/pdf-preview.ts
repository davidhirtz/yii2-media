// The skeleton's `FileUploadPrepareDetail`, declared here: its sources are not part of a Composer install.
type FileUploadPrepareDetail = {
    file: File,
    input: HTMLInputElement,
    fields: Promise<[string, Blob] | null>[],
};

const MAX_WIDTH = 640;
const TIMEOUT = 10000;

const toBlob = ($canvas: HTMLCanvasElement): Promise<Blob | null> =>
    new Promise((resolve) => $canvas.toBlob(resolve, 'image/webp', 0.85));

const timeout = (): Promise<never> =>
    new Promise((_, reject) => setTimeout(() => reject(new Error('The PDF took too long to render')), TIMEOUT));

// pdf.js is imported only once a PDF is chosen. A file it cannot render (encrypted, broken) stays without a preview.
const render = async (file: File): Promise<Blob | null> => {
    const url = URL.createObjectURL(file);
    let task: { destroy(): Promise<void> } | null = null;

    try {
        const {getDocument, GlobalWorkerOptions} = await import('pdfjs-dist');
        GlobalWorkerOptions.workerSrc = new URL('./pdf-worker.js', import.meta.url).href;

        const loading = getDocument({url});
        task = loading;

        const draw = async (): Promise<Blob | null> => {
            const page = await (await loading.promise).getPage(1);
            const viewport = page.getViewport({scale: MAX_WIDTH / page.getViewport({scale: 1}).width});

            const $canvas = document.createElement('canvas');
            $canvas.width = Math.round(viewport.width);
            $canvas.height = Math.round(viewport.height);

            await page.render({canvas: $canvas, viewport}).promise;

            return $canvas.width && $canvas.height ? toBlob($canvas) : null;
        };

        return await Promise.race([draw(), timeout()]);
    } catch {
        return null;
    } finally {
        await task?.destroy();
        URL.revokeObjectURL(url);
    }
};

document.addEventListener('file-upload:prepare', (event) => {
    const {file, input, fields} = (event as CustomEvent<FileUploadPrepareDetail>).detail;
    const name = input.name.replace(/\[upload]$/, '[preview]');

    if (file.type === 'application/pdf' && name !== input.name) {
        fields.push(render(file).then((blob) => blob ? [name, blob] : null));
    }
});
