## 3.1.1 (September 24, 2026)

- Changed `AssetModelTrait::updateAssetCount()` to renumber the assets `1..n` first, so a delete leaves no gap;
  `M260924100000RenumberAssetPositions` closes the gaps an installation already holds

## 3.1.0 (September 24, 2026)

- Added `Transformation::getSizeFor()`
- Replaced `imagine/imagine` with `intervention/image` 4: `Images\ImageProcessor`, `Helpers\ImageSize`; `File::updateImageInternal()`
  takes Intervention's image, `Transformation::pngCompressionLevel()` is gone, `avifQuality()` is new
- Fixed AVIF transformations of greyscale images with an ICC profile, which Chrome refused to decode
- Added `Module::$imageProcessor` and `getImageProcessor()`, and the transformation defaults `Module::$jpegQuality`,
  `$webpQuality`, `$avifQuality` and `$resolution`, which a preset's own setters override
- Changed `Module::$autorotateImages` to default to `true` and `Helpers\ImageSize::fromFile()` to answer the size an
  EXIF-oriented image is displayed at; added `File::orientImage()` and the `file/orient` command; requires `ext-exif`
- Added a `returnUrl` to the admin's `file/delete`, a path on the site; deleting a file from an asset page returns to
  the model's asset list instead of the file list
- Changed `Widgets\Media` to render a source per transformation extension and an `<img>` in the file's own format
  whenever it renders a `<picture>`, as `omitUnnecessaryPictureTag(false)` asks

## 3.0.0 (September 23, 2026)

- Renamed the namespace from `davidhirtz\yii2\media\` to `Hirtz\Media\` and every directory from lowercase to StudlyCase
  (`models\File` → `Models\File`, `modules\admin` → `Modules\Admin`, `console\controllers` → `Console\Controllers`)
- Moved the v2 → v3 database migrations out of the bundle into `davidhirtz/yii2-upgrade`; the bundle ships
  `Migrations\M260101000300MediaBaseline` for a fresh install only
- Moved assets here from `yii2-cms` and made them polymorphic: one `asset` table keyed by `model_class` / `model_id`, one base
  `Models\Asset`, one subclass per model that has assets, registered in `Module::$assets` (was `$fileRelations`)
- Removed `Models\Interfaces\FileRelationInterface`, `AssetParentInterface`, `Models\Traits\AssetParentTrait`, `AssetTrait`
  and `EmbedUrlTrait`; the model side is `Models\Interfaces\AssetModelInterface` + `Models\Traits\AssetModelTrait`
- Moved `Models\Traits\MetaImageTrait` to `yii2-cms` (`Hirtz\Cms\Models\Traits\MetaImageTrait`)
- Renamed `Models\Transformation` to `Models\FileTransformation` and its table `transformation` to `file_transformation`
- Replaced the transformation config arrays with `Transformations\Transformation` objects (`Transformation::make('xs')->width(300)`);
  `Module::$transformations` is read through `setTransformations()`, `addTransformation()`, `removeTransformation()`,
  `getTransformation()`, `getTransformations()` and `hasTransformation()`
- Removed `Module::addTransformationsFromTypeOptions()`, `File::getTransformationOption()` and `getTransformationOptions()`; a type
  declares `transformations()` and `sizes()` through `Models\Interfaces\TransformationTypeInterface` and registers a
  self-describing name (`w_400`, `w_200,h_300@2`) or an inline `Transformation` itself
- Replaced `Helpers\Sizes::format()` with `Helpers\Size` (`Size::breakpoint()`, `Size::mediaQuery()`, `Size::value()`)
- Replaced `Widgets\Picture` with `Widgets\Media` (`asset()`, `sizes()`, `transformations()`, `extension()`, `lazyLoading()`,
  `aspectRatio()`, `image()`, `picture()`)
- Replaced `File::AUTH_FILE_CREATE`, `AUTH_FILE_UPDATE` and `AUTH_FILE_DELETE` with `File::AUTH_FILE` (`file`), and the four
  `Folder::AUTH_FOLDER_*` constants with `Folder::AUTH_FOLDER` (`folder`); removed the `media` role
- Changed `Modules\Admin\Controllers\Traits\FileTrait` / `FolderTrait` to `FileControllerTrait` / `FolderControllerTrait`;
  `findFile()` and `findFolder()` take no permission argument
- Removed `File::upload()`, `getHeightPercentage()`, `getActiveRelatedModels()`, `getFileCountAttributeNames()` and
  `getRelatedModelCount()`; added `file.asset_count`, `File::getAssets()` and `updateAssetCount()`
- Changed `File::copy()` to take a path and build a `Hirtz\Skeleton\Web\CopiedUploadedFile`; `File::$upload` is typed
  `Hirtz\Skeleton\Web\AbstractUploadedFile|ChunkedUploadedFile|null` and the URL import runs under the skeleton `upload` component
- Moved the translated attributes of `File` from `_xx` columns into the skeleton `translation` table (`TranslationInterface`)
- Added `file.custom_attributes` and `asset.custom_attributes`; `File` and `Asset` implement `CustomAttributeInterface`
- Changed `Asset` to hold `name`, `content`, `alt_text`, `link`, `embed_url`, `loading` and `fetchpriority` as custom attributes
  declared in `getDefaultCustomAttributes()`, translated through `translatableAttributes` rather than `i18nAttributes`; added
  `Models\CustomAttributes\AltTextCustomAttribute` and `EmbedUrlCustomAttribute`
- Changed `AssetModelInterface::hasAssetsEnabled()` to `allowsAssets()`, which folds in the type's `allowAssets(false)` declared
  through `Models\Interfaces\AssetModelTypeInterface` (`Models\Types\AssetModelType`); `Models\Types\AssetType` is the asset's own type
- Changed `getTypes()` to an instance method returning `Models\Types\AssetType` objects
- Changed `Asset::getPermissionName()` to take no argument and default to the model's own permission; `getParamName()` moved to
  the skeleton `AdminModelInterface`
- Changed `getTrailModelName()`, `getTrailModelType()` and `getTrailModelAdminRoute()` to `getAdminName()`, `getAdminType()` and
  `getAdminRoute()` of the skeleton `AdminModelInterface`, which `Asset`, `File` and `Folder` implement
- Changed every message key to `UPPER_SNAKE_CASE` (`FILE_BASENAME_LABEL`, `FOLDER_SUCCESS_CREATED`, `AUTH_FILE_DESCRIPTION`);
  the English-text keys are gone
- Replaced the array-configured admin widgets with the skeleton widget system: `Modules\Admin\Widgets\Navs\Submenu` →
  `FileSubmenu`, `Panels\FileHelpPanel` and `Grids\Traits\UploadTrait` → `Navs\FileActionDropdown`, `Buttons\FileUploadButton`
  and `FileImportButton`, `Forms\Fields\FilePreview` / `AssetPreview` → `FilePreviewField` / `AssetPreviewField`,
  `Forms\FileUpload` → the skeleton upload; grid `*Column()` array methods are `get*Column()` returning `Column` objects
- Renamed `Grids\FileGridView::$parent` and `$folder` to the `model()` and `folder()` setters; added `asset()` for replacing a file
- Replaced `FileController::actionRelations()` with `Modules\Admin\Controllers\AssetController` (`/admin/media/asset/index?file=<id>`)
- Removed `Modules\Admin\Module::$url`, `getName()`, `getNavBarItems()` and `getDashboardPanels()`; the module implements the
  skeleton `ModuleInterface` (`aside()`, `dashboard()`) and renders `Widgets\Navs\MediaNavItem`
- Removed `Assets\AdminAsset` and `CropperJsAsset`; the cropper is `Assets\ImageCropAssetBundle`
- Changed `Module::$keepFilename` to default to `true`; a taken name is numbered by `File::getNumberedBasename()` and
  `Module::$overwriteFiles` replaces instead of refusing
- Changed `Module::$transformationExtensions` to `['avif', 'webp']`; `Widgets\Media` renders `avif` sources by default
- Changed `scaleUp` to default to `false` for every transformation
- Changed a folder delete to delete its files through `Models\Actions\DeleteFiles`, so assets, redirects and search documents go with them
- Changed a file move or rename to carry its transformations along (`File::moveTransformations()`) instead of deleting them
- Changed `Asset` to hold a file once: `(model_class, model_id, file_id)` is unique, the asset `duplicate` action is gone
  and the POST-only `remove` action replaces it in the file picker
- Added `Module::$maxFolderRedirects` and `Models\Actions\SaveFolderRedirects`, recording a redirect per file when a folder path changes
- Added bulk actions: `FileController::actionDeleteAll()`, `actionMoveAll()` (`Models\Actions\MoveFiles`), the asset `delete-all`
  (`Models\Actions\DeleteAssets`) and `status` actions; `FileGridView::$showDeleteButton` (default `false`) restores the per-row button
- Added replacing an asset's file through the `create` action's `asset` parameter (`AssetControllerTrait::replaceAssetFile()`)
- Added `File`, `Folder` and `Asset` to the fulltext search (`SearchableInterface`); `Bootstrap` registers the first two
- Added `Models\Queries\AssetQuery`, `Models\Actions\DuplicateAsset`, `ReorderAssets`, `Modules\Admin\Controllers\Traits\AssetControllerTrait`,
  `Data\AssetArrayDataProvider`, `Forms\AssetActiveForm`, `Grids\AssetGridView`, `FileAssetGridView`, `Columns\AssetThumbnailColumn`,
  `AssetCountColumn`, `Navs\AssetHeader`, `AssetActionDropdown`, `AssetModelActionDropdown`, `AssetSubmenuItem`, `FileHeader`,
  `FolderHeader`, `FolderActionDropdown`, `Buttons\FolderCreateButton` and `FolderDeleteButton`

## 2.3.6 (Jun 25, 2026)

- Enhanced `Module::addTransformationsFromTypeOptions()` to support width/height definitions and PPI modifiers in
  formats like `w_{pixel}`, `h_{pixel}`, `w_{pixel}@{modifier}` and `w_{pixel},h_{pixel}@{modifier}`

## 2.3.5 (Jun 25, 2026)

- Added `Module::addTransformationsFromTypeOptions()` to add transformations from type options

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

- Added `Hirtz\Media\Models\Forms\TransformationForm`
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