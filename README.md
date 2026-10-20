# yii2-media

File and media management for the [yii2-skeleton](https://github.com/davidhirtz/yii2-skeleton) admin: a folder tree of
uploaded files, on-demand image transformations (`avif`, `webp`, resized variants), and *assets* — a polymorphic link
between any record and a file, with a caption, alt text, link and viewport type. It depends on `davidhirtz/yii2-skeleton`
and on `intervention/image` with `ext-imagick` and `ext-exif`, behind `Images\ImageProcessor`. `davidhirtz/yii2-cms` gives entries and sections assets;
`davidhirtz/yii2-media-video` adds video files.

## Installation

```bash
composer require davidhirtz/yii2-media
./yii migrate
./yii search/rebuild
```

The bundle bootstraps itself through `extra.bootstrap` (`Hirtz\Media\Bootstrap`): it registers the `media` module, the
`admin/media` submodule, the `file` and `folder` permissions on the dashboard (`DashboardController::addRoles()`; the
permissions themselves come from the baseline migration), the `file` and `transformation` console commands, the `@media`
alias, the `media` message category, `File` and `Folder` on the `search` component, and a URL rule
`<uploadPath>/<path:.*>` → `media/transformation/create` that renders a transformation the first time its URL is requested.
The web server therefore has to fall back to `index.php` for a missing file under the upload path. Upgrading from 2.x:
see `UPGRADE.md`.

ImageMagick decodes only content that starts like a raster image, but a host should still deny its vector and script
coders in `policy.xml` (`<policy domain="coder" rights="none" pattern="{MVG,MSL,SVG,PS,EPS,PDF,XPS,TEXT,URL}" />`).

## Configuration

### `modules.media`

| Property | Default | Meaning |
|---|---|---|
| `allowedExtensions` | `['gif', 'jpg', 'jpeg', 'png', 'svg']` | extensions an upload may have (`yii2-media-video` appends `mp4`, `webm`, `ogg` unless set) |
| `assetClasses` | `[]` | `Models\Asset` subclasses, one per model that has assets |
| `autorotateImages` | `true` | rewrite uploads upright by their EXIF orientation |
| `avifQuality` | `60` | AVIF quality of a transformation that sets none (`yii2-anakin` raises it to `80`) |
| `baseUrl` | `null` | URL prefix of the files; `params.cdnUrl`, then `/<uploadPath>` |
| `breakpoints` | `xs` 425, `sm` 768, `md` 1024, `lg` 1200, `xl` 1440 | names `Helpers\Size::breakpoint()` accepts; a pixel width or a media query |
| `checkExtensionByMimeType` | `false` | validate an upload by MIME type rather than extension |
| `defaultFolderOrder` | `['position' => SORT_ASC]` | order of the folder list |
| `enableRenameFolders` | `true` | allow a folder path to change (off for remote storage) |
| `enableDeleteNonEmptyFolders` | `true` | allow deleting a folder with files |
| `folderCachedQueryDuration` | `0` | seconds the folder list is cached, `0` until a folder changes; `false` disables |
| `hashDirectories` | `true` | store an upload in a directory named after its content (`3f9a2c1b/photo.jpg`), so a cached URL never serves a newer file |
| `imageProcessor` | `Images\ImageProcessor::class` | class name, configuration array or instance; `getImageProcessor()` creates it once |
| `jpegQuality` | `75` | JPEG quality of a transformation that sets none |
| `keepFilename` | `true` | keep an upload's basename; `false` renames it to a random string |
| `maxFolderRedirects` | `1000` | files a folder may hold for a rename to record a redirect per file; `false` never |
| `overwriteFiles` | `false` | replace a file of the same name on disk instead of numbering the upload; a name another record holds is still refused |
| `resolution` | `72` | pixels per inch of a transformation that sets none |
| `stripMetadata` | `true` | write transformations without EXIF, XMP and IPTC data; the colour profile is kept |
| `transformableImageExtensions` | `['jpg', 'jpeg', 'png']` | extensions transformations are generated for |
| `transformationExtensions` | `['avif', 'webp']` | extra formats a transformation is offered in; one the server cannot encode is skipped (`getTransformationExtensions()`, listed under System › Server) |
| `transformationQualityExtensions` | `null` | formats a file sets its own transformation quality for (`transformation_quality_<format>`, suffixing the basename `-webp-60`); `null` is the first encodable one |
| `transformations` | `admin` (120 wide), `og` (1200 × 630) | `Transformations\Transformation` presets, see below |
| `uploadPath` | `uploads` (set by `Bootstrap`) | directory under `webroot` and first URL segment |
| `webpQuality` | `80` | WebP quality of a transformation that sets none |
| `webroot` | `@webroot` | file system root the upload path is relative to |

`modules.admin.modules.media.cropRatios` (`?array`, default `null`) replaces the aspect ratios the file crop offers.
`params.cdnUrl` sets `baseUrl` without touching the module config.

### Transformations

A preset is a `Transformations\Transformation` with fluent setters — `width()`, `height()`, `keepAspectRatio()`, `scaleUp()`
(default `false`), `backgroundColor()`, `backgroundAlpha()`, `jpegQuality()`, `webpQuality()`, `avifQuality()`,
`resolution()`. The encoding setters fall back to the module's `jpegQuality`, `webpQuality`, `avifQuality` and
`resolution`, so a project sets its defaults once and a preset only names what differs; the geometry setters describe
the preset itself and have no module default:

```php
use Hirtz\Media\Images\ImageProcessor;
use Hirtz\Media\Transformations\Transformation;
use Intervention\Image\Drivers\Vips\Driver as VipsDriver;

'modules' => [
    'media' => [
        'avifQuality' => 75,
        'webpQuality' => 85,
        'transformations' => [
            Transformation::make('xs')->width(374),
            Transformation::make('hero')->width(1600)->height(400)->keepAspectRatio()->avifQuality(85),
        ],
        // another Intervention driver (here intervention/image-driver-vips), or a subclass of the processor
        'imageProcessor' => [
            'class' => ImageProcessor::class,
            '__construct()' => [VipsDriver::class],
        ],
    ],
],
```

A type may also name a transformation that is not configured, as long as the name describes it: `w_400`, `h_300`,
`w_200,h_300`, `w_400@2` (a modifier multiplies every dimension). `Models\Interfaces\TransformationTypeInterface::transformations()`
registers such a name, or an inline `Transformation`, on the module when the type definitions resolve. A name that neither
parses nor is configured throws. `./yii transformation/index` lists what is registered and how many files carry each.

### Container definitions

```php
'container' => [
    'definitions' => [
        Folder::class => ['types' => fn (): array => [/* Hirtz\Skeleton\Models\Types\Type objects */]],
        File::class => ['i18nAttributes' => ['name', 'alt_text']],
        EntryAsset::class => ['translatableAttributes' => ['alt_text']],
    ],
],
```

`File::$customAttributes` may declare project attributes stored in `file.custom_attributes`. An asset's `content`,
`alt_text`, `link`, `embed_url`, `loading` and `fetchpriority` are custom attributes already; all but `loading` and
`fetchpriority` are stored per language, which `translatableAttributes` on the asset subclass narrows (never
`i18nAttributes`). `link` also takes a relative URL (`/about`, `#team`). An asset declares no `name`: a project wanting one adds it in `getDefaultCustomAttributes()`. An asset
subclass ignores `types`; its types are the viewport types (`AssetInterface::TYPE_VIEWPORT_MOBILE`,
`TYPE_VIEWPORT_DESKTOP`) it declares itself.

### Serving uploads

SVG is allowed by default and is not sanitised. Opened directly rather than through `<img>`, an SVG is a document of the
site's origin, and a script in it runs with the permissions of whoever opens it: an editor's upload reaching an admin.
Uploads are static files, so the web server has to send the headers that prevent it. The transformation route never
serves an SVG: it answers only a transformation's path (`<folder>/<transformation>/<basename>.<ext>` with a transformable
or transformation extension) and is a 404 for anything else in the upload folder, so a file the web server does not
answer itself is never sent from the site's origin by PHP — the headers below are the only protection.

```nginx
location /uploads/ {
    add_header X-Content-Type-Options nosniff always;

    location ~* \.svg$ {
        add_header X-Content-Type-Options nosniff always;
        add_header Content-Security-Policy "script-src 'none'; sandbox" always;
    }
}
```

```apacheconf
<Directory "/path/to/web/uploads">
    Header always set X-Content-Type-Options nosniff
    <FilesMatch "\.svg$">
        Header always set Content-Security-Policy "script-src 'none'; sandbox"
    </FilesMatch>
</Directory>
```

nginx does not inherit `add_header` into a block that declares its own, hence the repetition. A CDN in front of the
uploads (`params.cdnUrl`) needs the same two headers configured there.

## Console commands

- `file/delete-unused` — deletes every file neither an asset nor a custom attribute uses, after one confirmation;
  `--dryRun` (`-d`) lists them without deleting
- `file/references` — rebuilds the file references of every class in `fileReferenceModelClasses` and of the asset classes, and
  removes those of deleted records
- `file/orient` — rewrites the images stored sideways by their EXIF orientation upright, correcting their width and height
  and deleting their transformations
- `transformation/index` — lists every transformation name with its file count; a name no longer configured is shown in red
- `transformation/delete <name>` — deletes the rows and directories of one transformation, so it is regenerated on demand
- `transformation/delete-unused` — deletes every transformation the module no longer configures, after one confirmation
- `transformation/delete-all` — deletes every transformation, configured or not, after one confirmation
- `--extension=jpg` limits the deletes to the files of that extension; a directory is removed once nothing else is left in it
- `--sleep=1` pauses that many seconds after every 100 deleted records, so a large delete does not exhaust the database (`0` disables it)
- `--dryRun` (`-d`) lists what the transformation deletes would remove, without asking or deleting

## MCP

The bundle registers the [MCP](https://github.com/davidhirtz/yii2-skeleton#mcp-server) resources `file` and `folder`, which are updated
but not created, and the tool `attach_file`: it puts a file into the library (into `folder`, the default folder
otherwise) and attaches it to a record of any resource whose model takes assets. The file is a URL, fetched by
`StreamUploadedFile` with its address policy and limits (refused without `Upload::$enableStreamUploads`), or base64
`content` with a `filename`, written by `ContentUploadedFile` up to `AttachFileTool::$maxContentSize` (10 MB).
`get_record` on an image file shows it, scaled to `Module::$mcpPreviewSize` (1024 px, `null` for none) from the stored
file, so Claude can describe it. `Mcp\AssetResources::make('entry_asset', EntryAsset::class, 'entry')` registers an asset subclass under its
owner (updated, reordered, never moved), and the tool `detach_file` removes one, keeping the file.

`generate_image` creates an image on the server and attaches it as a draft asset, with one of four services; each is
offered once there is a key to pay with, a user's own, added under *Account → API keys*, or else the site's:

| Service | `imageGenerators` key | Site key in `params` |
|---|---|---|
| OpenAI (`gpt-image-1`) | `openai` | `openaiApiKey` |
| Google Gemini (`gemini-2.5-flash-image`) | `google` | `geminiApiKey` |
| Stability AI (Stable Image Core) | `stability` | `stabilityApiKey` |
| Adobe Firefly | `firefly` | `fireflyClientId` and `fireflyClientSecret` (a user enters both) |

```php
'media' => [
    'imageGenerators' => [
        'firefly' => null, // removes one; or a config with another `model`, or a service of your own
    ],
    'imageGeneratorKeys' => 'user-then-site', // or 'site' (the site's alone) or 'user' (each user's own)
    'imageGeneratorDailyLimit' => 20, // per user and day, on the site's keys only
],
```

The tool lists the services the user has a key for, the first by default, and the result names whose key paid. A
service of your own extends `Images\Generators\ImageGenerator`.

A file the user has in the chat cannot reach the server through the model; `upload_link` answers the admin page to
upload it on instead, a record's images or a folder, where the user signs in as usual.

## Giving a model assets

Five pieces: the interface and trait on the model, an `asset_count` column, an `Asset` subclass, and its registration.

```php
use Hirtz\Media\Models\Interfaces\AssetModelInterface;
use Hirtz\Media\Models\Traits\AssetModelTrait;
use Hirtz\Skeleton\Models\Traits\AdminModelTrait;

class Recipe extends ActiveRecord implements AssetModelInterface
{
    use AdminModelTrait;
    use AssetModelTrait;

    final public const string AUTH_RECIPE = 'recipe';

    public function getAssetClass(): string
    {
        return RecipeAsset::class;
    }

    public function allowsAssets(): bool
    {
        return $this->typeAllowsAssets();
    }

    public function getAdminRoute(): array
    {
        return ['/admin/recipe/update', 'id' => $this->id];
    }

    public function getPermissionName(): string
    {
        return static::AUTH_RECIPE;
    }
}
```

```php
use Hirtz\Media\Models\Asset;

/**
 * @extends Asset<Recipe>
 */
class RecipeAsset extends Asset
{
    public static function getModelClass(): string
    {
        return Recipe::class;
    }

    public static function getAdminControllerRoute(): string
    {
        return '/admin/recipe-asset';
    }
}
```

```php
'modules' => ['media' => ['assetClasses' => [RecipeAsset::class]]],
```

`AssetModelTrait` supplies `getAssets()`, `updateAssetCount()`, `populateAssetRelations()` and the type readers;
`typeAllowsAssets()` honours a type implementing `Models\Interfaces\AssetModelTypeInterface` (`Models\Types\AssetModelType`
or `Models\Types\Traits\AssetModelTypeTrait` on your own type) that declares `allowAssets(false)`, `sizes()` or
`transformations()`. `Asset::getPermissionName()` answers the model's own permission, so the subclass declares nothing more.

The admin pages are the subclass's: a controller using `Modules\Admin\Controllers\Traits\AssetControllerTrait` with one
`AccessControl` rule over `index`, `create`, `update`, `delete`, `delete-all`, `duplicate`, `order`, `remove` and `status`,
plus `index`, `create` and `update` views rendering `Modules\Admin\Widgets\Grids\AssetGridView`, `FileGridView` and
`Widgets\Forms\AssetActiveForm` under `Widgets\Navs\AssetHeader`. `Hirtz\Cms\Modules\Admin\Controllers\EntryAssetController`
and `resources/views/admin/entry-asset/` in `yii2-cms` are the template. Register the subclass on the `search` component
so its captions are findable.

On the site, `Widgets\Media::make()->asset($asset)` renders an AVIF `<img>` carrying `srcset`, `sizes`, `alt`, `loading`
and `fetchpriority`. Asked for a `<picture>` (`omitUnnecessaryPictureTag(false)`, a `picture()` closure or
`extension(null)`), it renders a source per transformation extension and an `<img>` in the file's own format.
`Models\Queries\AssetQuery::withFiles()` and `Asset::populateModelRelations()` load the files and the records of a mixed
list in one query per class.

## A file as a custom attribute

`Models\CustomAttributes\FileCustomAttribute` holds the id of a file, picked from a set of folders or the folders of a
folder type (`types` configured on `Folder` through the container), on any custom attribute owner but a file.

```php
FileCustomAttribute::make('hero')
    ->folderType(Folder::TYPE_HERO)   // or ->folders(3, 7)
    ->extensions('jpg', 'png', 'webp')
    ->translatable()
```

```php
FileCustomAttribute::getFile($section, 'hero')?->getUrl();
```

Read in the current language, it falls back to the source language. Inside a group, read the items, not the raw value:
`FileCustomAttribute::getFile($item, 'image')` for each of `$section->getCustomAttributeItems('gallery')`. Only a changed
value has to be within the scope, so a file moved out of it since blocks no other edit. A list rendered outside the cms
preload loads its files in one query through `Models\Actions\LoadCustomAttributeFiles::create($models)`.

Every use is a `file_reference` row, written when its record is saved and counted in `File::$reference_count`: the file's
Uses tab lists them, the delete confirmation names them, and deleting the file clears the attribute on every record, a
required one included. A write outside `save()` (`updateAttributes()`, `updateAll()`) skips the rows; `file/references`
rebuilds them for the classes a bundle appends to `Module::$fileReferenceModelClasses`.
