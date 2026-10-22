<?php

declare(strict_types=1);

namespace Hirtz\Media\Controllers;

use DateTime;
use DateTimeZone;
use Exception;
use Hirtz\Media\Images\ImageProcessor;
use Hirtz\Media\Models\Forms\TransformationForm;
use Hirtz\Media\Module;
use Hirtz\Media\Modules\ModuleTrait;
use Hirtz\Skeleton\Helpers\Url;
use Hirtz\Skeleton\Models\Redirect;
use Hirtz\Skeleton\Web\Controller;
use Hirtz\Skeleton\Web\Request;
use Override;
use Yii;
use yii\db\IntegrityException;
use yii\log\Logger;
use yii\mutex\Mutex;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * @extends Controller<Module>
 */
class TransformationController extends Controller
{
    use ModuleTrait;

    public $defaultAction = 'create';

    /**
     * @var bool whether debug logging should be disabled for transformation requests. Set to `true` to disable log
     * entries for all transformation requests on a local development environment with an external file system such as
     * AWS S3.
     */
    public bool $disableLogging = false;

    /**
     * @var int seconds a request waits for another one writing the same derivative before it sends the original
     */
    public int $lockTimeout = 30;

    /**
     * @var int seconds a file whose transformation failed answers a placeholder before it is tried again: a broken or
     * oversized original fails on every request for it, and every image of a page requests one.
     */
    public int $failureDuration = 86400;

    #[Override]
    public function init(): void
    {
        if ($this->disableLogging) {
            $log = Yii::$app->getLog();

            foreach ($log->targets as $target) {
                $target->enabled = false;
            }
        }

        // `$this->request` is only resolved in `parent::init()`.
        $request = Request::current();

        if ($request) {
            $request->enableCsrfValidation = false;
        }

        parent::init();
    }

    public function actionCreate(string $path): Response|string
    {
        if (!$this->isContainedPath($path) || !$this->isTransformationPath($path)) {
            throw new NotFoundHttpException();
        }

        // Check if the transformation already exists in the file system. This is needed for external file systems such
        // as S3 which might not be cached yet or are still routed via .htaccess to "web/index.php"
        if (is_file($filePath = static::getModule()->uploadPath . $path)) {
            return $this->sendFile($filePath);
        }

        $form = TransformationForm::create();
        $form->path = $path;

        if (!$form->validate()) {
            return $this->redirectToMovedFile($form) ?? throw new NotFoundHttpException();
        }

        $filePath = $form->transformation->getFilePath();
        $cache = Yii::$app->getCache();
        $failureKey = $form->file->getTransformationFailureKey();

        if ($cache?->exists($failureKey)) {
            return $this->sendPlaceholder();
        }

        // Concurrent requests for the same derivative would each process the original: one writes it, the rest wait
        $mutex = Yii::$app->has('mutex') ? Yii::$app->get('mutex') : null;
        $lockName = 'transformation-' . $filePath;
        $isLocked = $mutex instanceof Mutex && $mutex->acquire($lockName, $this->lockTimeout);

        try {
            if ($isLocked || !$mutex instanceof Mutex) {
                if (is_file($filePath)) {
                    return $this->sendFile($filePath);
                }

                if ($form->transformation->insert()) {
                    return $this->sendFile($filePath);
                }

                if ($error = $form->transformation->getFirstError('file_id')) {
                    // Logged once per file and failure duration rather than for every request; a file too large for
                    // the host is no bug, and the file's admin page says so.
                    if (!$cache || $cache->add($failureKey, $error, $this->failureDuration)) {
                        Yii::getLogger()->log(
                            "Transformation \"$path\" failed: $error",
                            ImageProcessor::isResourceLimitError($error) ? Logger::LEVEL_WARNING : Logger::LEVEL_ERROR,
                            __METHOD__,
                        );
                    }

                    return $this->sendPlaceholder();
                }
            }
        } catch (IntegrityException) {
            // A request not holding the lock inserted the same transformation first, both have written the file
            if (is_file($filePath)) {
                return $this->sendFile($filePath);
            }
        } catch (Exception $exception) {
            Yii::error($exception->getMessage());
        } finally {
            if ($isLocked) {
                $mutex->release($lockName);
            }
        }

        // If validation failed (e.g., transformation not applicable) or the lock timed out, the original file is returned
        // instead.
        return $this->redirect($form->folder->getUploadUrl() . $form->file->getFilename());
    }

    /**
     * A renamed or moved file redirects its own URL, and its derivatives follow that redirect rather than each holding
     * a row of their own.
     */
    private function redirectToMovedFile(TransformationForm $form): ?Response
    {
        if (!$form->basename || !$form->extension) {
            return null;
        }

        $module = static::getModule();

        $extensions = in_array($form->extension, $module->transformationExtensions, true)
            ? $module->transformableImageExtensions
            : [$form->extension];

        $requestUris = array_map(
            fn (string $extension): string => Url::sanitize("$module->baseUrl$form->folderPath/$form->basename.$extension"),
            $extensions
        );

        $url = Redirect::find()
            ->select(['url'])
            ->where(['request_uri' => $requestUris])
            ->limit(1)
            ->scalar();

        $baseUrl = Url::sanitize($module->baseUrl);
        $path = $baseUrl === '' ? $url : (is_string($url) && str_starts_with($url, "$baseUrl/") ? substr($url, strlen($baseUrl) + 1) : null);

        if (!is_string($path) || !str_contains($path, '/') || !str_contains($path, '.')) {
            return null;
        }

        [$folderPath, $filename] = explode('/', $path, 2);
        $basename = substr($filename, 0, (int)strrpos($filename, '.'));

        return $this->redirect("$module->baseUrl$folderPath/$form->transformationName/$basename.$form->extension", 301);
    }

    /**
     * The path is URL-decoded, so `%2e%2e/` reaches the file system unless refused here. Checked by segment rather than
     * with `realpath()`, which fails for a remote file system behind a stream wrapper.
     */
    private function isContainedPath(string $path): bool
    {
        if (str_contains($path, "\0")) {
            return false;
        }

        return !in_array('..', explode('/', str_replace('\\', '/', $path)), true);
    }

    /**
     * Only the shape of a transformation's path is checked, without a query, so a transformation already written to a
     * remote file system is sent as cheaply as before. Anything else in the upload folder — an SVG, a document — is
     * never sent from the site's origin by this route.
     */
    private function isTransformationPath(string $path): bool
    {
        $module = static::getModule();
        $parts = explode('/', $path);

        if (count($parts) < 3 || !in_array($parts[1], $module->getTransformationNames(), true)) {
            return false;
        }

        $extensions = [...$module->transformableImageExtensions, ...$module->transformationExtensions];
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), $extensions, true);
    }

    /**
     * Not written to disk, so the transformation is made once the original or the host is fixed.
     */
    private function sendPlaceholder(): Response
    {
        $headers = $this->response->getHeaders();
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Cache-Control', 'no-cache');

        $this->response->format = Response::FORMAT_RAW;
        $headers->set('Content-Type', 'image/svg+xml');
        $this->response->content = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="240" height="240">'
            . '<rect width="24" height="24" fill="#eaeaea"/>'
            . '<path d="M6 6h12v12H6z M6 15l4-4 3 3 2-2 3 3" fill="none" stroke="#a0a0a0" stroke-width="1.2" stroke-linejoin="round"/>'
            . '<path d="M4 20 20 4" stroke="#d93f45" stroke-width="1.2"/></svg>';

        return $this->response;
    }

    private function sendFile(string $filePath): Response
    {
        $headers = $this->response->getHeaders();
        $headers->set('X-Content-Type-Options', 'nosniff');

        $headers->set('Expires', (new DateTime(' + 1 year', new DateTimeZone('GMT')))
            ->format('D, d M Y H:i:s \G\M\T'));

        return $this->response->sendFile($filePath, null, [
            'inline' => true,
        ]);
    }
}
