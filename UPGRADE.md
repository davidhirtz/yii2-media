# Upgrading to 3.0

## Requirements

- PHP `^8.3`
- `davidhirtz/yii2-skeleton` `^3.0`, which brings `yiisoft/yii2-imagine` and `ext-imagick` for the image transformations
- A project giving its cms records assets needs `davidhirtz/yii2-cms` `^3.0`; `davidhirtz/yii2-media-video` `^3.0` for video files
- `composer require davidhirtz/yii2-media:^3.0`, then `./yii migrate` and `./yii search/rebuild`

## Renames

### Namespaces

| v2 | v3 |
|---|---|
| `davidhirtz\yii2\media\` | `Hirtz\Media\` |
| `davidhirtz\yii2\media\models\` | `Hirtz\Media\Models\` |
| `davidhirtz\yii2\media\models\{actions,collections,forms,interfaces,queries,traits}\` | `Hirtz\Media\Models\{Actions,Collections,Forms,Interfaces,Queries,Traits}\` |
| `davidhirtz\yii2\media\modules\admin\` | `Hirtz\Media\Modules\Admin\` |
| `davidhirtz\yii2\media\modules\admin\{controllers,data,widgets}\` | `Hirtz\Media\Modules\Admin\{Controllers,Data,Widgets}\` |
| `davidhirtz\yii2\media\console\controllers\` | `Hirtz\Media\Console\Controllers\` |
| `davidhirtz\yii2\media\{controllers,helpers,widgets,assets}\` | `Hirtz\Media\{Controllers,Helpers,Widgets,Assets}\` |
| `davidhirtz\yii2\media\migrations\` | gone; see *Data and schema* |

### Classes

| v2 | v3 |
|---|---|
| `Models\Transformation` | `Models\FileTransformation` (the row) |
| `Module::$transformations` array entries | `Transformations\Transformation` |
| `Helpers\Sizes` | `Helpers\Size` |
| `Widgets\Picture` | `Widgets\Media` |
| `Models\Interfaces\AssetParentInterface` | `Models\Interfaces\AssetModelInterface` |
| `Models\Interfaces\FileRelationInterface` | gone; `Models\Traits\FileRelationTrait` stays |
| `Models\Traits\AssetParentTrait` | `Models\Traits\AssetModelTrait` |
| `Models\Traits\AssetTrait`, `EmbedUrlTrait` | gone; `Models\Asset` is the base class |
| `Models\Traits\MetaImageTrait` | `Hirtz\Cms\Models\Traits\MetaImageTrait` |
| `Hirtz\Cms\Models\Asset` | `Hirtz\Cms\Models\EntryAsset`, `SectionAsset` (over `Hirtz\Media\Models\Asset`) |
| `Modules\Admin\Controllers\Traits\FileTrait` | `Modules\Admin\Controllers\Traits\FileControllerTrait` |
| `Modules\Admin\Controllers\Traits\FolderTrait` | `Modules\Admin\Controllers\Traits\FolderControllerTrait` |
| `Modules\Admin\Widgets\Navs\Submenu` | `Modules\Admin\Widgets\Navs\FileSubmenu`, `FileHeader`, `FolderHeader` |
| `Modules\Admin\Widgets\Panels\FileHelpPanel` | `Modules\Admin\Widgets\Navs\FileActionDropdown` |
| `Modules\Admin\Widgets\Grids\Traits\UploadTrait` | `Modules\Admin\Widgets\Buttons\FileButtonsTrait`, `FileUploadButton`, `FileImportButton` |
| `Modules\Admin\Widgets\Grids\Traits\AssetColumnsTrait` | `Modules\Admin\Widgets\Grids\AssetGridView` |
| `Modules\Admin\Widgets\Forms\FileUpload` | `Hirtz\Skeleton\Widgets\Buttons\FileUploadButton` |
| `Modules\Admin\Widgets\Forms\Fields\FilePreview`, `AssetPreview` | `Modules\Admin\Widgets\Forms\Fields\FilePreviewField`, `AssetPreviewField` |
| `Modules\Admin\Widgets\Grids\Columns\FileThumbnailColumn` (`LinkDataColumn`) | same name, over the skeleton `LinkColumn` |
| `Assets\AdminAsset`, `Assets\CropperJsAsset` | `Assets\ImageCropAssetBundle` |

### Methods and properties

| v2 | v3 |
|---|---|
| `Module::$fileRelations` | `Module::$assets` (`list<class-string<Models\Asset>>`) |
| `Module::addTransformationsFromTypeOptions()` | gone; a type's `transformations()` registers its own |
| `Module::$transformations` (public array) | `setTransformations()`, `addTransformation()`, `removeTransformation()`, `getTransformation()`, `getTransformations()`, `hasTransformation()` |
| `File::getTransformationOption()`, `getTransformationOptions()` | `Module::getTransformation()` |
| `File::upload()` | gone; the upload actions assign `File::$upload` |
| `File::copy(string $url)` | `File::copy(string $path)`, a local path |
| `File::getActiveRelatedModels()`, `getFileCountAttributeNames()`, `getRelatedModelCount()` | `File::getAssets()`, `File::$asset_count`, `updateAssetCount()` |
| `File::getHeightPercentage()` | gone; `Helpers\AspectRatio` |
| `File::getTrailModelName()`, `getTrailModelType()`, `getTrailModelAdminRoute()` | `getAdminName()`, `getAdminType()`, `getAdminRoute()` (skeleton `AdminModelInterface`) |
| `Folder::getTrailModelName()`, `getTrailModelType()`, `getTrailModelAdminRoute()` | same |
| `AssetParentInterface::getAssets(): ActiveQuery` | `AssetModelInterface::getAssets(): Models\Queries\AssetQuery` |
| `hasAssetsEnabled()` (cms `AssetParentInterface` users) | `AssetModelInterface::allowsAssets()` |
| `AssetInterface::getParent()` | `AssetInterface::getModel()` / `$asset->model` |
| `$asset->entry_id`, `$asset->section_id` | `$asset->model_class`, `$asset->model_id` |
| `Asset::getPermissionName(string $action)` | `Asset::getPermissionName()` |
| `Asset::getTypes()` (static, arrays) | `getTypes()` (instance, `list<Models\Types\AssetType>`) |
| `AssetTrait::getViewportTypes()` (arrays) | `Asset::getViewportTypes()` (`AssetType` objects) |
| `FolderCollection::$_folders`, `$_default` | `$folders`, `$default` |
| `Modules\Admin\Widgets\Grids\FileGridView::$parent`, `$folder` | `model()`, `folder()` setters |
| `Modules\Admin\Widgets\Grids\*::thumbnailColumn()` etc. (arrays) | `getThumbnailColumn()` etc. (`Column` objects) |
| `Modules\Admin\Module::$url`, `getName()`, `getNavBarItems()`, `getDashboardPanels()` | gone; `aside()` and `dashboard()` of the skeleton `ModuleInterface` |
| `Modules\Admin\Controllers\FileController::actionRelations()` | `Modules\Admin\Controllers\AssetController::actionIndex()` |

### Constants

| v2 | v3 |
|---|---|
| `File::AUTH_FILE_CREATE`, `AUTH_FILE_UPDATE`, `AUTH_FILE_DELETE` (`fileCreate`, …) | `File::AUTH_FILE` (`file`) |
| `Folder::AUTH_FOLDER_CREATE`, `AUTH_FOLDER_UPDATE`, `AUTH_FOLDER_DELETE`, `AUTH_FOLDER_ORDER` | `Folder::AUTH_FOLDER` (`folder`) |
| `Models\Transformation::NAME_ADMIN`, `NAME_OPEN_GRAPH` | `Transformations\Transformation::NAME_ADMIN`, `NAME_OPEN_GRAPH` |
| `Models\Transformation::EVENT_BEFORE_TRANSFORMATION` | `Models\FileTransformation::EVENT_BEFORE_TRANSFORMATION` |
| the `media` role | gone; grant `file` and `folder` |

### Tables and columns

| v2 | v3 |
|---|---|
| `transformation` | `file_transformation` |
| `file.alt_text_<lang>`, `file.name_<lang>` | rows in the skeleton `translation` table |
| `file.cms_asset_count` (cms), `file.hotspot_asset_count` (hotspot) | `file.asset_count` |
| `cms_asset`, `hotspot_asset` (cms, cms-hotspot) | `asset` with `model_class` / `model_id` |
| — | `file.custom_attributes`, `asset.custom_attributes` (JSON) |
| — | `asset.model_file` unique index on `(model_class, model_id, file_id)` |

### Message keys

The v2 files were keyed by English text (`Yii::t('media', 'Filename')`). Every v3 key is `UPPER_SNAKE_CASE` and domain-first:
`FILE_BASENAME_LABEL`, `FILE_FOLDER_ID_LABEL`, `FOLDER_PATH_LABEL`, `TRANSFORMATION_NAME_LABEL`, `ASSET_ALT_TEXT_LABEL`,
`FILE_SUCCESS_UPDATED`, `FOLDER_SUCCESS_CREATED`, `ASSET_SUCCESS_CREATED`, `FILE_CONFIRM_DELETE`, `COMMON_FILES`,
`COMMON_FOLDERS`, `AUTH_FILE_DESCRIPTION`, `AUTH_FOLDER_DESCRIPTION`. A project overriding a media message re-keys its
`messages/<lang>/media.php`; `Hirtz\Media\Models\File::attributeLabels()` is the reference for the labels.

### Console commands

Unchanged: `file/clear`, `transformation/index`, `transformation/delete <name>`.

### DOM ids

`Modules\Admin\Widgets\Grids\FileGridView::ID` is `files`, `AssetGridView::ID` is `asset-grid-view` and
`Modules\Admin\Widgets\Navs\AssetSubmenuItem::ID` is `assets`. The v2 `#dropzone` of `Forms\FileUpload::$dropZone` is gone.

## Configuration

### Transformations are objects

```php
// v2
'media' => [
    'transformations' => [
        'xs' => ['width' => 374],
        'og' => ['width' => 1200, 'height' => 630, 'keepAspectRatio' => true, 'scaleUp' => false],
    ],
],

// v3
use Hirtz\Media\Transformations\Transformation;

'media' => [
    'transformations' => [
        Transformation::make('xs')->width(374),
        Transformation::make('og')->width(1200)->height(630)->keepAspectRatio(),
    ],
],
```

Every array key is a setter of the same name. `imageOptions` is `jpegQuality()`, `pngCompressionLevel()`, `webpQuality()` and
`resolution($x, $y, $units)`. `scaleUp` defaults to `false` now; add `->scaleUp()` to a preset meant to enlarge a file.
`Module::init()` merges the `admin` and `og` defaults under yours.

### Asset subclasses replace `fileRelations`

```php
// v2
'media' => ['fileRelations' => [Asset::class]],

// v3
'media' => ['assets' => [EntryAsset::class, SectionAsset::class]],
```

The cms bundle registers its own subclasses from its `Bootstrap`; a project lists only the asset classes of its own models.

### Which asset attributes are translated

```php
'container' => [
    'definitions' => [
        EntryAsset::class => ['translatableAttributes' => ['alt_text']],
    ],
],
```

`name`, `content`, `alt_text`, `link` and `embed_url` are custom attributes stored inside `asset.custom_attributes`, so they
must not be named in `i18nAttributes` — `Hirtz\Skeleton\Models\Traits\CustomAttributesTrait` throws for a definition
colliding with a translated column. `File` keeps `i18nAttributes` for `name` and `alt_text`, which are columns.

### Changed defaults

- `Module::$keepFilename` is `true` (was `false`): an upload keeps its basename, transliterated and numbered on collision.
  Set it back to `false` for the random eight-character names.
- `Module::$transformationExtensions` is `['avif', 'webp']` (was `['webp']`).
- `Module::$maxFolderRedirects` (new, `1000`) caps the redirects a folder rename records; `false` records none.
- `Module::$overwriteFiles` now overwrites; it used to report a validation error.
- `params.cdnUrl` and `Modules\Admin\Module::$cropRatios` are unchanged.

## Code changes

### A model with assets

`AssetParentInterface` + `AssetParentTrait` became `AssetModelInterface` + `AssetModelTrait`, and the asset class is declared
on the model rather than through `fileRelations`:

```php
class Recipe extends ActiveRecord implements AssetModelInterface
{
    use AssetModelTrait;

    public function getAssetClass(): string
    {
        return RecipeAsset::class;
    }

    public function allowsAssets(): bool
    {
        return $this->typeAllowsAssets();
    }
}
```

The model needs an `asset_count` column and `AdminModelTrait` (for `getAdminName()`, `getAdminType()`, `getParamName()`),
plus `getAdminRoute()` and `getPermissionName()` of its own. See `README.md` for the subclass and the controller.

### The asset subclass

A cms `Asset` with `entry_id` / `section_id` is one `Models\Asset` subclass per model, extending the polymorphic base:

```php
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

`getPermissionName()` defaults to the model's own, `getAdminType()` to the class name. `$asset->parent` is `$asset->model`;
`$asset->isEntryAsset()` is `$asset instanceof EntryAsset`. `updateAll()` and `deleteAll()` are unscoped on the base and
must name `model_class`; `where()` on a subclass query replaces the scope, so filter with `andWhere()`.

### The asset controller and views

`Modules\Admin\Controllers\Traits\AssetControllerTrait` holds the action bodies; the controller resolves the record, declares
one `AccessControl` rule and ships `index`, `create` and `update` views. Actions are `index`, `create`, `update`, `delete`,
`delete-all`, `order`, `remove` and `status`; `duplicate` is gone. `Hirtz\Cms\Modules\Admin\Controllers\EntryAssetController`
is the template. `create` takes an `asset` parameter for replacing an asset's file. A project view renders
`Modules\Admin\Widgets\Navs\AssetHeader` with the asset and the owner's submenu with `$asset->model`.

### Types declare what they allow and render

```php
// v2 (array type options)
'transformations' => ['xs', 'w_1200'],
'sizes' => ['sm' => '100vw', 0 => '960px'],

// v3
EntryType::make(1)
    ->transformations('xs', 'w_1200', Transformation::make('hero')->width(1600)->height(400))
    ->sizes(Size::breakpoint('sm', '100vw'), '960px')
    ->allowAssets(false)
```

`allowAssets(false)` replaces the cms `hiddenFields` marker for assets; `allowsAssets()` on the model is the single reader.
A bare string in `sizes()` is `Size::value()` and has to come last, which `validate()` enforces. A type of an asset model
implements `AssetModelTypeInterface` (`Models\Types\AssetModelType` or `Models\Types\Traits\AssetModelTypeTrait`); the
asset's own type is `Models\Types\AssetType`, which implements `TransformationTypeInterface` only.

### `Widgets\Picture` is `Widgets\Media`

```php
// v2
Picture::widget(['asset' => $asset, 'sizes' => '100vw', 'imgOptions' => ['class' => 'img']]);

// v3
Media::make()
    ->asset($asset)
    ->sizes('100vw')
    ->image(fn (Img $img): Img => $img->addClass('img'));
```

`picture(Closure)` does the same for the `<picture>` tag; `extension()` (default `avif`), `lazyLoading()`, `aspectRatio()`
and `transformations()` replace the public properties. `Widgets\Media` also renders the asset's `loading` and
`fetchpriority` attributes.

### Permissions

Three file and four folder permissions are one each. An `AccessRule`, a nav item or a `can()` names `File::AUTH_FILE` or
`Folder::AUTH_FOLDER`, takes no record and no `folder` param; `findFile()` and `findFolder()` take the id alone. A rule naming
the `media` role names the two permissions instead.

### Uploads and imports

`File::copy()` opens a path the application names (a stream wrapper's included); a URL is fetched through
`Hirtz\Skeleton\Web\StreamUploadedFile` under the skeleton `upload` component's policy (`http`/`https` only, no private
addresses unless `allowPrivateStreamUploadHosts`):

```php
$file->upload = new StreamUploadedFile(['url' => $url, 'allowedExtensions' => $file->allowedExtensions]);
```

`Modules\Admin\Widgets\Buttons\FileImportButton` renders nothing while `enableStreamUploads` is off.

### Admin views and widgets

The v2 `Panel::widget()` / `Submenu::widget()` pages are `FileHeader::make()->model($file)` + `FileSubmenu::make()->model($file)`
+ `GridContainer` / `FormContainer`, as `resources/views/admin/file/*.php` show. A project extending `FileActiveForm` or
`FolderActiveForm` declares its fields in `getDefaultRows()` and a grid its columns in `configure()`; there is no `init()`
and no `renderFields()`. A bulk delete or move of files is the grid's selection (`FileGridView::$showDeleteButton` restores
the per-row button); the transformations of a file are `TransformationController::actionIndex()`, its assets
`AssetController::actionIndex()`.

### Search

`File`, `Folder` and every `Asset` subclass are `SearchableInterface`. The bundle registers `File` and `Folder`; a project
registers its own asset subclass on the `search` component and runs `./yii search/rebuild` once.

## Data and schema

The bundle ships `Migrations\M260101000300MediaBaseline` for a fresh install only. An existing database is upgraded by the
migrations under `migrations/yii2-media/` of `davidhirtz/yii2-upgrade`, in this order:

1. `M260910130000Translations` moves every `file.<attribute>_<lang>` column into the skeleton `translation` table.
2. `M260911140000CustomAttributes` adds `file.custom_attributes`.
3. `M260912100000Asset` creates the polymorphic `asset` table and adds `file.asset_count`. The rows come from the cms and
   cms-hotspot upgrade migrations, which copy `cms_asset` and `hotspot_asset` into it, fold `file.cms_asset_count` into
   `asset_count` and keep the old tables for validation.
4. `M260914120000AuthItems` adds the `file` and `folder` permissions under the `media` role and replaces `fileCreate`,
   `fileUpdate`, `fileDelete`, `folderCreate`, `folderUpdate`, `folderDelete` and `folderOrder` in every assignment and parent.
5. `M260914170000FileTransformation` renames `transformation` to `file_transformation`.
6. `M260914200000MediaRole` grants `file` and `folder` directly to every parent and assignee of the `media` role and removes it.
7. `M260915140000CustomAttributesColumn` reorders the two `custom_attributes` columns (cosmetic).
8. `M260916100000AssetUnique` deletes duplicate `(model_class, model_id, file_id)` rows, keeping the lowest position, with
   their search documents, recounts `file.asset_count` and every model's `asset_count`, then adds the unique index.

Before: a database dump. After: `./yii search/rebuild`, and a check of the counts the migrations print. What is lost: the
`media` role and which accounts held it as a role rather than as two permissions (the down migration cannot rebuild the
assignments), and every second asset row a record held for the same file. The transformation files on disk are untouched.

## Removed

- The `media` role
- The asset `duplicate` action and its button
- `File::upload()`, `File::getHeightPercentage()`, `File::getTransformationOption()`, `File::getTransformationOptions()`
- `Module::$fileRelations`, `Module::addTransformationsFromTypeOptions()`
- `Widgets\Picture::$webpOptions`, `$imgOptions`, `$pictureOptions`, `$defaultImageLoading`, `$enableWebpTransformations`, `$enableLegacyFileFormats`
- `Modules\Admin\Widgets\Panels\FileHelpPanel`, the folder grid's per-row delete button, `FileController::actionRelations()` and its view
- `Assets\AdminAsset`, `Assets\CropperJsAsset` and the jQuery `admin.js`
