## 3.0.0 (in development)

- `esbuild.js` uses the skeleton's shared `esbuild.config.js`
- `Console\Controllers\FileController` and `Console\Controllers\TransformationController` declare the
  namespace their directory already had. Both were missed by the v3 rename and still declared
  `Hirtz\Media\console\controllers`, which PSR-4 resolves to a directory that does not exist — the two console
  commands were unreachable on a case-sensitive filesystem
- `Models\Interfaces\AssetModelInterface` extends the skeleton `Models\Interfaces\AdminRouteInterface` instead of
  declaring `getAdminRoute()` itself. `Models\Asset`, `Models\File` and `Models\Folder` implement that interface and
  dropped their `getTrailModelAdminRoute()`, which the skeleton trait now answers
- `Modules\Admin\Controllers\AbstractAssetController` became
  `Modules\Admin\Controllers\Traits\AssetControllerTrait`, so a controller extends the skeleton `Controller`
  and types its own actions instead of matching abstract signatures it could only widen. The generic
  `AssetController` that served unregistered subclasses is gone — a subclass without a controller of its own
  has no admin pages
- `FileAssetController` is now `Modules\Admin\Controllers\AssetController` at `/admin/media/asset`: it is the
  asset controller of this bundle, and it serves one file's assets
- `Models\Asset` builds the routes of its own controller: `getAdminIndexRoute()`, `getAdminCreateRoute()` and
  `getAdminOrderRoute()`, all from `getAdminRouteParams()`, which names the model through
  `AssetModelInterface::getParamName()` — the asset actions already use `id` for the asset itself, so the
  model keeps its own name: `/admin/cms/entry-asset/index?entry=<id>`
- `Models\Asset::instantiate()` only maps `model_class` to its subclass; it no longer applies the per-type
  `class` mapping of `TypeAttributeTrait`, which no asset type used
- Added `Modules\Admin\Controllers\FileAssetController` with `actionIndex()` and `actionDelete()`, replacing
  `FileController::actionRelations()` and its view. Removing an asset from a file now stays on the file: the
  asset's own controller would redirect to the record it belongs to, which is not where the user was. The
  delete still requires that asset's own permission. The route is `/admin/media/file-asset/index?file=<id>`
- `Modules\Admin\Controllers\AbstractAssetController` holds the action bodies and no opinion on who may run
  them: `actionIndex()`, `actionCreate()`, `actionUpdate()`, `actionDelete()`, `actionDuplicate()` and
  `actionOrder()` are abstract, and a controller resolves and authorises the model or the asset the way its
  bundle does before calling `renderIndex()`, `createAsset()`, `updateAsset()`, `deleteAsset()`,
  `duplicateAsset()` or `reorderAssets()`. The `$assetClasses` property and the `getPermissionNames()` that
  derived the access rules from it are gone — a subclass that delegates its permission to another record could
  not answer them from a bare instance
- Assets moved here from `yii2-cms` and became polymorphic: one `asset` table, one base `Models\Asset`, and one
  subclass per model that has assets, dispatched on `model_class` by `instantiate()`. A subclass declares
  `getModelClass()`, `getPermissionName()` and optionally `getAdminControllerRoute()`; the model side is
  `Models\Interfaces\AssetModelInterface` + `Models\Traits\AssetModelTrait` plus an `asset_count` column and one
  entry in `Module::$assets`. `$asset->model` is the record, `$asset->model_class` the string. See `UPGRADE.md`
- `Models\Asset` has no text columns: `name`, `content`, `alt_text`, `link` and `embed_url` are custom attribute
  definitions it declares by default, so a subclass or a project can drop, add or retype them without a migration.
  `$translatableAttributes` says which of them are stored per language — inside the JSON, not in the `translation`
  table. Added `Models\CustomAttributes\AltTextCustomAttribute` and `EmbedUrlCustomAttribute`
- Added `Models\Queries\AssetQuery` (`selectSiteAttributes()`, `selectSitemapAttributes()`, `withFiles()`,
  `whereModel()`, `whereModels()`, `whereModelClass()`), `Models\Actions\DuplicateAsset`, `ReorderAssets`,
  `Actions\Traits\DuplicateAssetsTrait`, and `Asset::populateModelRelations()`, which loads the records of a mixed
  list of assets with one query per class
- Added `Modules\Admin\Controllers\AbstractAssetController` and `AssetController` with default views under
  `resources/views/admin/asset/`: a bundle serves its own subclasses by setting `$assetClasses` and shipping views,
  and gets the index, create, update, delete, duplicate and order actions for free
- Added `Modules\Admin\Data\AssetArrayDataProvider`, `Widgets\Forms\AssetActiveForm`,
  `Widgets\Grids\AssetGridView`, `FileAssetGridView`, `Columns\AssetThumbnailColumn`, `AssetCountColumn`,
  `Navs\AssetActionDropdown`, `AssetModelActionDropdown`, `AssetModelHeader`
- `Models\File` gained `asset_count`, `getAssets()` and `recalculateAssetCount()`, and deletes its assets through
  the models in `beforeDelete()` so both sides keep their counts and trails. Removed `getActiveRelatedModels()`,
  `getFileCountAttributeNames()`, `getRelatedModelCount()` and the per-relation count columns
- Removed `Models\Interfaces\FileRelationInterface`, `AssetParentInterface`, `Models\Traits\AssetTrait`,
  `AssetParentTrait`, `EmbedUrlTrait`, `MetaImageTrait` (moved to `yii2-cms`), `Module::$fileRelations` and
  `Modules\Admin\Widgets\Grids\FileRelationGridContainer` with its interface
- `M260912100000Asset` creates the table; the copy from `cms_asset` and `hotspot_asset` happens in those bundles.
  `Modules\Admin\Widgets\Grids\FileGridView::$parent` is `$model`
- `Models\File` implements `CustomAttributeInterface`. Added the `custom_attributes` column to `file`, excluded from
  the trail. `File` has no `type`, so a project declares its definitions by overriding `getCustomAttributes()`
- `FileActiveForm` renders the custom attribute fields and `FileController::actionUpdate()` skips the upload branch on
  a `Request::isFormReload()`

- Translated attributes of `File` moved from their `_xx` columns into the skeleton's `translation` table
  (`M260910130000Translations`)
- Split the file update page into tabbed sub-pages via the new `FileSubmenu` widget: transformations moved to
  `TransformationController::actionIndex()` and related models to `FileController::actionRelations()`; neither
  is rendered on the update page anymore
- `FolderController::actionOrder()` now returns a flash fragment (was `void`) and emits a success flash
  after a reorder; added the `FOLDER_SUCCESS_ORDERED` message
- Changed the transformation URL rule to a `Route` registered via `Application::addRoutes()`
- Removed `UploadTrait` in favor of `ImportFileButton` and `UploadFileButton` classes

## 2.3.4 (Jan 27, 2026)

- Added default `Transformation::NAME_OPEN_GRAPH` transformation

## 2.3.3 (Jan 26, 2026)

- Added `Picture::$enableLegacyFileFormats` which defaults to `false` to only use WEBP as image format

## 2.3.2 (Oct 20, 2025)

- Added Russian language support

## 2.3.1 (Oct 6, 2025)

- Fixed `File::validateFilename()`

## 2.3.0 (May 26, 2025)

- Requires PHP 8.3+
- Fixed "empty name" error on invalid file import (Issue #17)
- Fixed consecutive file imports (Issue #14)
- Fixed replace file import (Issue #15)

## 2.2.4 (May 5, 2025)

- Fixed `File::$folder_id` database schema, removed `NOT NULL` and `DEFAULT` constraints

## 2.2.3 (Jan 23, 2025)

- Changed `Bootstrap` I18N configuration
- Updated composer dependencies

## 2.2.2 (Dev 12, 2024)

- Enhanced `EmbedUrlTrait` to support YouTube live and shared shorthand URLs

## 2.2.1 (Dec 4, 2024)

- Added `$options` parameter to `AssetColumnsTrait::getFileUpdateButton()`
- Fixed `AssetColumnsTrait::getDeleteButton()` signature
- Fixed search input position in `FolderGridView`
- Forced strict types in all files

## 2.2.0 (Nov 28, 2024)

- Extracted `AssetParentInterface::getFile()` to `FileRelationInterface`
- Removed unused `type` parameter from `FileActiveDataProvider` and `FileController`
- Removed `AssetParentInterface::getParentName()`
- Removed `AssetTrait::updateOrDeleteFileByAssetCount()` and related `AssetTrat::$deleteFileOnDelete`, if this
  functionality is needed, it can be implemented via the `File` model
- Removed `File::recalculateAssetCountByRelation()`
- Renamed `File::getAssetModels()` to `File::getActiveRelatedModels()`
- Renamed `File::getAssetCount()` to `File::getRelatedModelCount()`
- Renamed `Module::$assets` to `Module::fileRelations`
- Replaced `AssetParentInterface::getFileCountAttribute()` with `FileRelationInterface::getFileCountAttributeNames()`
- Replaced `AssetParentInterface::getParentGridView()` with `FileRelationInterface::getFilePanelClass()`

## 2.1.25 (Nov 19, 2024)

- Added `AspectRatio` helper class
- Improved YouTube embed URL detection in `EmbedUrlTrait`

## 2.1.24 (Oct 1, 2024)

- Enhanced `Bootstrap` to prioritize the transformation routes with the new `prepend` rules option

## 2.1.23 (Oct 1, 2024)

- Extracted `Html::prepareLinkOptions()` from `Html::a()` to make it easier to extend the link options
- Fixed `FileGridView::getRoute()`

## 2.1.22 (Aug 19, 2024)

- Changed `Bootstrap` to use `ApplicationTrait::addUrlManagerRules()` to prevent the initialization of the URL manager
  before the bootstrap is completed

## 2.1.21 (Jul 11, 2024)

- Changed `Hirtz\Media\Modules\Admin\Module::$name` to `Module::getName()` to prevent translation issues
- Enhanced `Hirtz\Media\Module::$breakpoints` to also support string values

## 2.1.20 (Apr 22, 2024)

- Fixed `Folder` default type

## 2.1.19 (Apr 5, 2024)

- Updated admin according to `Hirtz\Skeleton\Modules\Admin\ModuleInterface`

## 2.1.18 (Mar 21, 2024)

- Added `DateTimeBehavior` to `Folder` and `File` models
- Added `File::getUrlWithVersion()` (Issue #13)
- Removed `Folder::getDefault()` in favor of `FolderCollection::getDefault()`

## 2.1.17 (Mar 4, 2024)

- Added `EmbedUrlTrait::$embedUrlMaxLength`

## 2.1.16 (Feb 1, 2024)

- Enhanced `DuplicateFile` action to return an error if the file could not be duplicated

## 2.1.15 (Feb 1, 2024)

- Added `EVENT_INIT` events to modules

## 2.1.14 (Feb 1, 2024)

- Dependency updates and minor enhancements

## 2.1.13 (Jan 26, 2024)

- Added `File::getTransformationOption()` in favor of the second argument of `File::getTransformationOptions()`

## 2.1.12 (Jan 25, 2024)

- Fixed `AssetActiveFormTest`
- Fixed `EmbedUrlTrait`

## 2.1.11 (Jan 24, 2024)

- Changed `<source src>` with a `<picture>` parent to `<source srcset>`
- Enhanced `EmbedUrlTrait`

## 2.1.10 (Jan 13, 2024)

- Fixed `AssetTrait::getAltText()` to work even if the related file does not exist anymore
- Replaced `'data-method'=>'select'` with `'data-method'=>'add'` in `FileGridView`

## 2.1.9 (Jan 12, 2024)

- Enhanced `M231211093758Indexes` migration to mMake sure duplicate transformations are resolved before applying (Issue
  #11)
- Enhanced `TransformationController::actionCreate()` disabling session start (Issue #6)

## 2.1.8 (Jan 9, 2024)

- Fixed Rector (Issue #10)

## 2.1.7 (Jan 8, 2024)

- Added `Hirtz\Media\Modules\Admin\Widgets\Grids\Traits\AssetColumnsTrait`
- Renamed `UploadTrait::getCreateRoute()` to `UploadTrait::getFileUploadRoute()` to avoid conflicts with asset grids

## 2.1.6 (Jan 8, 2024)

- Added `Hirtz\Media\Modules\Admin\Widgets\Forms\Fields\AssetPreview`
  and `Hirtz\Media\Modules\Admin\Widgets\Grids\Columns\Thumbnail` to make it easier for extensions to extend
  the asset preview

## 2.1.5 (Jan 7, 2024)

- Changed `Picture` widget to use `Picture::widget()` instead of `Picture::tag()`

## 2.1.4 (Jan 7, 2024)

- Added `Hirtz\Media\Helpers\Srcset` helper class
- Changed signature of `File::getSrcset()` to always return an array
- Changed `Picture` namespace to `Hirtz\Media\widgets\Picture` and enabled configuration via DI container

## 2.1.3 (Jan 6, 2024)

- Added template declaration to `FolderCollection`
- Removed `AssetPreview` in favor of `Hirtz\Media\Modules\Admin\Widgets\Forms\Fields\FilePreview`

## 2.1.2 (Dec 20, 2023)

- Enhanced asset annotations for static analysis

## 2.1.1 (Dec 19, 2023)

- Changed `Yii::createObject()` calls with arrays back to `Yii::$container->get()` for better IDE support

## 2.1.0 (Dec 18, 2023)

- Added Codeception test suite
- Added GitHub Actions CI workflow
- Moved `DuplicateButtonTrait` from `yii2-cms` to `yii2-media`

## 2.0.9 (Dec 11, 2023)

- Fixed a bug in `FileQuery::matching` signature

## 2.0.8 (Dec 11, 2023)

- Added `Hirtz\Media\Models\forms\TransformationForm`
- Added unique indexes for `path` column in `folder` table, `basename` column in `file` table and `name` column
  in `transformation` table
- Enhanced `Hirtz\Media\Models\Collections\FolderCollection` to use cached queries

## 2.0.7 (Nov 14, 2023)

- Added `HTML` helper class with automatic `download`, `rel` and `target` attributes for links

## 2.0.6 (Nov 7, 2023)

- Fixed `Picture::addSrcset()` parameter type hinting

## 2.0.5 (Nov 7, 2023)

- Added `MetaImageTrait`

## 2.0.4 (Nov 7, 2023)

- Added `File::getTransformationNames()` as a fallback to find all valid transformations
- Fixed bug in migration introduced in commit 1e02c03
- Renamed `AssetParentTrait::getSizes()` to `getAssetSizes()` and `AssetParentTrait::getTransformationNames()`
  to `getAssetTransformationNames()`

## 2.0.3 (Nov 6, 2023)

- Added `Hirtz\Media\Module::$breakpoints` for the HTML sizes attribute
- Added `Hirtz\Media\Models\Traits\AssetParentTrait`
- Added `Hirtz\Media\Helpers\Sizes`
- Renamed `getSrcsetSizes()` to `getSizes()`

## 2.0.2 (Nov 6, 2023)

- Added `File::isAudio()` and `File::isVideo()`
- Moved `Bootstrap` class to base package namespace for consistency
- Removed `File::clone()`, use `Hirtz\Media\Models\Actions\DuplicateFile` instead
- Removed `Folder::updatePosition()`, use `Hirtz\Media\Models\Actions\ReorderFolder` instead
- Removed unused `File::recalculateAssetCount()` method

## 2.0.1 (Nov 3, 2023)

- Changed namespaces for model interfaces to `Hirtz\Media\Models\Interfaces`

## 2.0.0 (Nov 3, 2023)

- Added `AssetPreview` to display a preview of the asset, this makes it easier to extend the preview for user
- Changed namespaces from `Hirtz\Media\admin\widgets\grid`
  to `Hirtz\Media\admin\widgets\grids` and `Hirtz\Media\admin\widgets\nav`
  to `Hirtz\Media\admin\widgets\navs`
- Moved source code to `src` folder
- Moved all models, data providers and widgets out of `base` folder, to override them use Yii's dependency injection
  container
- Removed `FolderDropdownTrait` in favor of `FolderCollection::getAll()`
  implementations as well as other packages such as `davidhirtz/yii2-cms-hotspot`
- Removed `ActiveForm::getActiveForm()`, to override the active forms, use Yii's dependency injection
  container

## 1.3.3 (Nov 4, 2023)

- Locked `davidhirtz/yii2-skeleton` to version `1.9`, upgrade to version 2 to use the new media library