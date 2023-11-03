## 2.0.0 (Nov 3, 2023)

- Moved source code to `src` folder
- Moved all models, data providers and widgets out of `base` folder, to override them use Yii's dependency injection
  container
- Changed namespaces from `davidhirtz\yii2\media\admin\widgets\grid`
  to `davidhirtz\yii2\media\admin\widgets\grids` and `davidhirtz\yii2\media\admin\widgets\nav`
  to `davidhirtz\yii2\media\admin\widgets\navs`
- Removed `FolderDropdownTrait` in favor of `FolderCollection::getAll()`
- Added `AssetPreview` to display a preview of the asset, this makes it easier to extend the preview for user
  implementations as well as other packages such as `davidhirtz/yii2-cms-hotspot`
- Removed `ActiveForm::getActiveForm()`, to override the active forms, use Yii's dependency injection
  container