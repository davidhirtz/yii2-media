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

- Added `davidhirtz\yii2\media\modules\admin\widgets\grids\traits\AssetColumnsTrait`
- Renamed `UploadTrait::getCreateRoute()` to `UploadTrait::getFileUploadRoute()` to avoid conflicts with asset grids

## 2.1.6 (Jan 8, 2024)

- Added `davidhirtz\yii2\media\modules\admin\widgets\forms\fields\AssetPreview`
  and `davidhirtz\yii2\media\modules\admin\widgets\grids\columns\Thumbnail` to make it easier for extensions to extend
  the asset preview

## 2.1.5 (Jan 7, 2024)

- Changed `Picture` widget to use `Picture::widget()` instead of `Picture::tag()`

## 2.1.4 (Jan 7, 2024)

- Added `davidhirtz\yii2\media\helpers\Srcset` helper class
- Changed signature of `File::getSrcset()` to always return an array
- Changed `Picture` namespace to `davidhirtz\yii2\media\widgets\Picture` and enabled configuration via DI container

## 2.1.3 (Jan 6, 2024)

- Added template declaration to `FolderCollection`
- Removed `AssetPreview` in favor of `davidhirtz\yii2\media\modules\admin\widgets\forms\fields\FilePreview`

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

- Added `davidhirtz\yii2\media\models\forms\TransformationForm`
- Added unique indexes for `path` column in `folder` table, `basename` column in `file` table and `name` column
  in `transformation` table
- Enhanced `davidhirtz\yii2\media\models\collections\FolderCollection` to use cached queries

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

- Added `davidhirtz\yii2\media\Module::$breakpoints` for the HTML sizes attribute
- Added `davidhirtz\yii2\media\models\traits\AssetParentTrait`
- Added `davidhirtz\yii2\media\helpers\Sizes`
- Renamed `getSrcsetSizes()` to `getSizes()`

## 2.0.2 (Nov 6, 2023)

- Added `File::isAudio()` and `File::isVideo()`
- Moved `Bootstrap` class to base package namespace for consistency
- Removed `File::clone()`, use `davidhirtz\yii2\media\models\actions\DuplicateFile` instead
- Removed `Folder::updatePosition()`, use `davidhirtz\yii2\media\models\actions\ReorderFolder` instead
- Removed unused `File::recalculateAssetCount()` method

## 2.0.1 (Nov 3, 2023)

- Changed namespaces for model interfaces to `davidhirtz\yii2\media\models\interfaces`

## 2.0.0 (Nov 3, 2023)

- Added `AssetPreview` to display a preview of the asset, this makes it easier to extend the preview for user
- Changed namespaces from `davidhirtz\yii2\media\admin\widgets\grid`
  to `davidhirtz\yii2\media\admin\widgets\grids` and `davidhirtz\yii2\media\admin\widgets\nav`
  to `davidhirtz\yii2\media\admin\widgets\navs`
- Moved source code to `src` folder
- Moved all models, data providers and widgets out of `base` folder, to override them use Yii's dependency injection
  container
- Removed `FolderDropdownTrait` in favor of `FolderCollection::getAll()`
  implementations as well as other packages such as `davidhirtz/yii2-cms-hotspot`
- Removed `ActiveForm::getActiveForm()`, to override the active forms, use Yii's dependency injection
  container

## 1.3.3 (Nov 4, 2023)

- Locked `davidhirtz/yii2-skeleton` to version `1.9`, upgrade to version 2 to use the new media library