## 2.0.3 (Nov 6, 2023)

- Added `davidhirtz\yii2\media\models\traits\AssetParentTrait` and added support for array values in HTML sizes
  attribute
- Added `Module::$breakpoints` for the HTML sizes attribute
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