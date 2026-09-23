# yii2-media

File and media management for the [yii2-skeleton](https://github.com/davidhirtz/yii2-skeleton) admin: a folder tree of
uploaded files, on-demand image transformations (`avif`, `webp`, resized variants), and *assets* — a polymorphic link
between any record and a file, with a caption, alt text, link and viewport type. It depends on `davidhirtz/yii2-skeleton`,
which brings `yiisoft/yii2-imagine` and `ext-imagick`. `davidhirtz/yii2-cms` gives entries and sections assets;
`davidhirtz/yii2-media-video` adds video files.

## Installation

```bash
composer require davidhirtz/yii2-media
./yii migrate
./yii search/rebuild
```

The bundle bootstraps itself through `extra.bootstrap` (`Hirtz\Media\Bootstrap`): it registers the `media` module, the
`admin/media` submodule, the `file` and `folder` permissions, the `file` and `transformation` console commands, the `@media`
alias, the `media` message category, `File` and `Folder` on the `search` component, and a URL rule
`<uploadPath>/<path:.*>` → `media/transformation/create` that renders a transformation the first time its URL is requested.
The web server therefore has to fall back to `index.php` for a missing file under the upload path. Upgrading from 2.x:
see `UPGRADE.md`.

## Configuration

### `modules.media`

| Property | Default | Meaning |
|---|---|---|
| `allowedExtensions` | `['gif', 'jpg', 'jpeg', 'png', 'svg']` | extensions an upload may have (`yii2-media-video` appends `mp4`, `webm`, `ogg` unless set) |
| `assets` | `[]` | `Models\Asset` subclasses, one per model that has assets |
| `autorotateImages` | `false` | rotate uploads by their EXIF orientation |
| `baseUrl` | `null` | URL prefix of the files; `params.cdnUrl`, then `/<uploadPath>` |
| `breakpoints` | `xs` 425, `sm` 768, `md` 1024, `lg` 1200, `xl` 1440 | names `Helpers\Size::breakpoint()` accepts; a pixel width or a media query |
| `checkExtensionByMimeType` | `false` | validate an upload by MIME type rather than extension |
| `defaultFolderOrder` | `['position' => SORT_ASC]` | order of the folder list |
| `enableRenameFolders` | `true` | allow a folder path to change (off for remote storage) |
| `enableDeleteNonEmptyFolders` | `true` | allow deleting a folder with files |
| `folderCachedQueryDuration` | `0` | seconds the folder list is cached; `false` disables |
| `keepFilename` | `true` | keep an upload's basename; `false` renames it to a random string |
| `maxFilesPerFolder` | `false` | split uploads into numbered subdirectories of that size |
| `maxFolderRedirects` | `1000` | files a folder may hold for a rename to record a redirect per file; `false` never |
| `overwriteFiles` | `false` | replace a file of the same name instead of numbering the upload |
| `transformableImageExtensions` | `['jpg', 'jpeg', 'png']` | extensions transformations are generated for |
| `transformationExtensions` | `['avif', 'webp']` | extra formats a transformation is offered in |
| `transformations` | `admin` (120 wide), `og` (1200 × 630) | `Transformations\Transformation` presets, see below |
| `uploadPath` | `uploads` (set by `Bootstrap`) | directory under `webroot` and first URL segment |
| `webroot` | `@webroot` | file system root the upload path is relative to |

`modules.admin.modules.media.cropRatios` (`?array`, default `null`) replaces the aspect ratios the file crop offers.
`params.cdnUrl` sets `baseUrl` without touching the module config.

### Transformations

A preset is a `Transformations\Transformation` with fluent setters — `width()`, `height()`, `keepAspectRatio()`, `scaleUp()`
(default `false`), `backgroundColor()`, `backgroundAlpha()`, `jpegQuality()`, `pngCompressionLevel()`, `webpQuality()`,
`resolution()`:

```php
use Hirtz\Media\Transformations\Transformation;

'modules' => [
    'media' => [
        'transformations' => [
            Transformation::make('xs')->width(374),
            Transformation::make('hero')->width(1600)->height(400)->keepAspectRatio(),
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

`File::$customAttributes` may declare project attributes stored in `file.custom_attributes`. An asset's `name`, `content`,
`alt_text`, `link`, `embed_url`, `loading` and `fetchpriority` are custom attributes already, so which of them are stored per
language is `translatableAttributes` on the asset subclass, never `i18nAttributes`. An asset subclass ignores `types`; its types
are the viewport types (`AssetInterface::TYPE_VIEWPORT_MOBILE`, `TYPE_VIEWPORT_DESKTOP`) it declares itself.

## Console commands

- `file/clear` — deletes every file no asset references
- `transformation/index` — lists every transformation name with its file count; a name no longer configured is shown in red
- `transformation/delete <name>` — deletes the rows and directories of one transformation, so it is regenerated on demand

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
'modules' => ['media' => ['assets' => [RecipeAsset::class]]],
```

`AssetModelTrait` supplies `getAssets()`, `updateAssetCount()`, `populateAssetRelations()` and the type readers;
`typeAllowsAssets()` honours a type implementing `Models\Interfaces\AssetModelTypeInterface` (`Models\Types\AssetModelType`
or `Models\Types\Traits\AssetModelTypeTrait` on your own type) that declares `allowAssets(false)`, `sizes()` or
`transformations()`. `Asset::getPermissionName()` answers the model's own permission, so the subclass declares nothing more.

The admin pages are the subclass's: a controller using `Modules\Admin\Controllers\Traits\AssetControllerTrait` with one
`AccessControl` rule over `index`, `create`, `update`, `delete`, `delete-all`, `order`, `remove` and `status`, plus `index`,
`create` and `update` views rendering `Modules\Admin\Widgets\Grids\AssetGridView`, `FileGridView` and
`Widgets\Forms\AssetActiveForm` under `Widgets\Navs\AssetHeader`. `Hirtz\Cms\Modules\Admin\Controllers\EntryAssetController`
and `resources/views/admin/entry-asset/` in `yii2-cms` are the template. Register the subclass on the `search` component
so its captions are findable.

On the site, `Widgets\Media::make()->asset($asset)` renders the `<picture>` with a source per transformation extension and
an `<img>` carrying `srcset`, `sizes`, `alt`, `loading` and `fetchpriority`; `Models\Queries\AssetQuery::withFiles()` and
`Asset::populateModelRelations()` load the files and the records of a mixed list in one query per class.
