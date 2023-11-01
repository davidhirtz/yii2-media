# Version v2.0.0

- Moved source code to `src` folder
- Moved all models, data providers and widgets out of `base` folder, to override them use Yii's dependency injection
  container
- Changed namespaces from `davidhirtz\yii2\media\admin\widgets\grid`
  to `davidhirtz\yii2\media\admin\widgets\grids` and `davidhirtz\yii2\media\admin\widgets\nav`
  to `davidhirtz\yii2\media\admin\widgets\navs`
- Removed `FolderDropdownTrait` in favor of `FolderCollection::getAll()`