## 3.0.0 (in development)

- **Renaming a folder's path records a redirect per file.** The path is the first segment of every file URL in
  the folder and the rename changes no file record at all, so `Skeleton\Behaviors\RedirectBehavior` — which
  compares a record's own URL across its save — never saw it, and every link into the folder simply broke.
  `Models\Actions\SaveFolderRedirects` writes them, carrying the redirects that already pointed into the old
  path along and deleting the ones a rename back makes into no-ops.

  A folder can hold far more files than a request can write rows for, so `Module::$maxFolderRedirects` (default
  1000, `false` to never record them) is the ceiling: above it the rename still happens and the redirects do not.
  Which of the two it will be is said on the path field itself, before the folder is saved, rather than flashed
  afterwards.

- **The move target is picked from a select in a modal**, not from a dropdown of one item per folder, which does
  not carry to a project with dozens of them. `Modules\Admin\Controllers\FileController::actionMoveAll()` reads
  `folder` from the body rather than from the query string with it.

- **Files are moved between folders in bulk.** `Modules\Admin\Widgets\Grids\FileGridView` renders a
  `CheckboxColumn` and a footer offering every other folder as a target, and
  `Modules\Admin\Controllers\FileController::actionMoveAll()` hands the selection to
  `Models\Actions\MoveFiles`. The column only appears where the action makes sense — more than one folder, not a
  picker, and `File::AUTH_FILE` — which `FileGridView::$showSelection` also turns off in one place. Moving is the
  only way to empty a folder, since `Models\Folder::isDeletable()` refuses a non-empty one by default.

  Three things the action encodes. It is **not** wrapped in a transaction: a move renames the file on disk, and a
  rollback would leave the records claiming the old location — a file that fails is collected and reported
  instead. A name already taken in the target folder is **renamed** by `File::validateFilename()` rather than
  refused, so the renamed files are reported separately from the moved ones. And the batch sets
  `Db\ActiveRecord::setIsBatch()` on every file, which is what turns off the per-save recalculation of the
  previous and the new folder — each a `COUNT(*)` over the folder — so a batch recalculates the folders it
  touched once, after the last file, rather than twice per file.

- **A move or a rename carries the transformations along** instead of dropping them. `Models\File::afterSave()`
  deleted every derivative whenever the file's path changed, so the next request for each had to run an image
  operation to recreate it — a folder change of a few hundred images was a few thousand of them. A derivative is
  derived from the file's *content*, and `FileTransformation` holds no path of its own, so
  `File::moveTransformations()` is a rename per derivative and no write at all. The delete stays for a save that
  really does rewrite the image — an upload, a crop, a rotation, a resize or a changed extension, which
  `File::hasChangedImage()` answers. A derivative whose file is already gone is deleted rather than carried, since
  the surviving record would fail the on-demand route's own uniqueness rule and leave the thumbnail a permanent
  404.

- **A rename rewrites the search index.** `Models\File::getSearchAttributes()` names `filename`, which is
  `getFilename()` rather than a column, so nothing in `changedAttributes` ever matched it and a renamed file kept
  its old name in the index until the next `search/rebuild`. The model names `basename` and `extension` through
  the new `Skeleton\Behaviors\SearchBehavior::$attributes`.

- **`Models\Asset` is searchable.** Its `name`, `content` and `alt_text` are custom attributes that nothing indexed,
  so a caption an editor wrote under an image was unfindable. Every asset subclass inherits the opt-in, but the
  search component is not told about any of them here: the base class has no `getModelClass()`, so each bundle
  registers its own subclasses (cms its entry and section assets, cms-hotspot its hotspot assets) and a project
  with an asset model of its own does the same. The result title names the parent record, which is polymorphic and
  therefore costs a query per shown hit, and the hit is hidden from anyone without the parent's permission.

- `Migrations\M260915140000CustomAttributesColumn` moves `file.custom_attributes` after `alt_text` and
  `asset.custom_attributes` after `file_id` — cosmetic column order only.

- **A subclass may narrow `Models\Asset::getDefaultCustomAttributes()`**, and the presentation getters tolerate it:
  `getLoading()`, `getFetchPriority()` and `getFormattedEmbedUrl()` answer `null` / `''` for an attribute the
  subclass does not declare rather than throwing, since `Widgets\Media` asks every asset for them.

- **`Models\Asset::getTypes()` is an instance method**, with every other type declaration — drop `static` from
  your own overrides, see the skeleton's `UPGRADE.md`.

- **`Grids\FileGridView` no longer leads out of itself while it is picking a file.** With a `model` set the grid
  is a picker — the new `isPicker()` says so — and its thumbnail, name and alt text check are plain content
  rather than links to the file, while the asset count badge carries no link to the asset index; all of them
  cancelled the flow the user was in. The button that used to carry `fa-image` is an external link button opening
  the file in a new tab. `getRecordUrl()` is the single place that decides. The plain file index is unchanged.

- **`Grids\Columns\AssetThumbnailColumn` renders a link again.** It assigned the thumbnail to the column's
  content rather than its value, which left `LinkColumn::getLink()` unreached and the url `Grids\AssetGridView`
  hands it unused.

- **The `media` role is dropped.** It grouped nothing but `file` and `folder`, and
  `Migrations\M260914200000MediaRole` grants those two to every parent and every assignee the role had before
  removing it. `yii2-cms` adds them to its `author` role, so an editor keeps the media library.

- **`Modules\Admin\Widgets\Navs\AssetHeader` is the header of a page scoped to one asset.** Its title and link
  are the asset's, its breadcrumbs are built from `Models\Asset::$model` through `AdminModelInterface`, so they
  lead back to the record the asset belongs to without naming its bundle. `yii2-cms-hotspot`'s `HotspotHeader`
  extends it

- **An asset's file can be replaced.** The asset action dropdown gained a "Replace file" item; it opens the file
  picker the create action already renders, and the file picked there replaces the asset's own instead of adding a
  second asset, so the name, the caption and every other custom attribute survive the swap.
  `Controllers\Traits\AssetControllerTrait::createAsset()` takes the asset as its fifth argument and hands it to
  the new `replaceAssetFile()`, which is what a controller's `create` action passes its own `asset` parameter to;
  `Widgets\Grids\FileGridView::asset()` is what puts the grid into that mode. The asset counts of both files are
  recalculated by `Models\Asset::afterSave()`, which has always watched `file_id`. `AssetActionDropdown`'s
  `canDeleteAsset()` is `canManageAsset()` — it never asked about deleting, only about the asset's permission, and
  the replace item asks the same question.

- **`Models\Transformation` is `Models\FileTransformation`, and a transformation preset is
  `Transformations\Transformation`.** The row and the configuration were one class with two lives: the record
  carried `scaleUp`, `keepAspectRatio`, `backgroundColor`, `backgroundAlpha` and an untyped `imageOptions` array
  that `beforeSave()` copied off the module, turning a misspelled key into an `UnknownPropertyException` at the
  moment an image was first rendered. The preset is now a fluent object — `Transformation::make('xs')->width(300)`,
  with `height()`, `keepAspectRatio()`, `scaleUp()`, `backgroundColor()`, `backgroundAlpha()` and the typed image
  options `jpegQuality()`, `pngCompressionLevel()`, `webpQuality()` and `resolution()` — and the two predicates
  that read those values moved onto it as `isApplicableTo(File)` and `getWidthFor(File)`. `scaleUp` now defaults
  to `false` everywhere, which is what `File::isValidTransformation()` always assumed; the record's own default
  said `true` and only the image writer saw it. The table is `file_transformation`, renamed by
  `M260914170000FileTransformation`; the relation, `File::$transformation_count`, `EVENT_BEFORE_TRANSFORMATION`
  and the `transformation/*` routes keep their names. `Module::$transformations` holds `Transformation` objects
  and is reached through `setTransformations()`, `addTransformation()`, `removeTransformation()`,
  `getTransformation()`, `getTransformations()` and `hasTransformation()`; `File::getTransformationOptions()` is
  gone with the arrays. See UPGRADE.md
- **A type declares its own transformations, and `Module::addTransformationsFromTypeOptions()` is gone.**
  `Models\Types\Traits\AssetModelTypeTrait::transformations(Transformation|string ...)` takes a configured preset
  name, a self-describing one (`w_400`, `w_200,h_300@2`, parsed by `Transformation::fromName()`) or an inline
  definition, and registers the last two on the module when the type definitions resolve — so no project has to
  call anything from its config, and a name that neither parses nor is configured throws instead of silently
  dropping its srcset entry. The module resolves the types of every registered asset model lazily, the first time
  it is asked for a transformation — through `instance()`, so the model a project maps over the bundle's in the
  container is the one whose types register
- **`Helpers\Sizes::format()` is `Helpers\Size`.** One `Size` is one `<media-condition> <length>` pair, built by
  `Size::breakpoint()` (validated against `Module::$breakpoints` at declaration time), `Size::mediaQuery()` or
  `Size::value()` for the bare length that has to come last — a browser ignores every entry after it, which a
  type's `validate()` now refuses. `sizes(Size|string ...)` on the type takes them, a plain string being the bare
  default, and `Widgets\Media::sizes()` is variadic in the same shape. A `'sizes' => 'string'` used to render
  nothing at all, since `format()` answered `null` for anything but an array
- `Models\Types\AssetType` and `Models\Interfaces\AssetModelTypeInterface`: an asset carries the sizes and
  transformations of the model it belongs to, so a hotspot asset renders through the hotspots' presets.
  `Models\Interfaces\AssetModelInterface::FIELD_ASSETS` replaces the magic `'#assets'` string

- `Models\Collections\FolderCollection::reset()` only drops what the collection holds; invalidating the shared
  cache is `invalidateCache()`, which now resets the collection too, as the cms, location and tenant ones already
  did — `getAll()` used to keep serving a folder list a save had already invalidated. `Bootstrap` resets it, so
  the folders of one request never reach the next; `Test\TestCase` was the only thing that cleared it before and
  no longer does
- `Console\Controllers\TransformationController::actionDelete()` refuses a name that is not a plain path segment
  instead of sanitizing it, and reports how many records and directories it actually removed. It used to strip the
  dots and take the `basename()`, so a mistyped or renamed name reported the same success as a real one while the
  outdated files stayed on disk, and a name carrying a dot deleted a different transformation than the one asked for
- `Models\Interfaces\AssetInterface` and `Models\Asset` are generic over the model the asset belongs to
  (`@template TModel of AssetModelInterface`). A subclass declares `@extends Asset<Entry>` instead of overriding
  `getModel()` to narrow its return type; `getModel()` and `$asset->model` still resolve to that model, so the
  narrowing overrides and their `@var` casts are gone. The native return type is `AssetModelInterface` everywhere
- The folder index translates through `COMMON_FOLDERS` instead of an English literal
- **One permission per admin-managed model.** `Models\File::AUTH_FILE` (`file`) replaces `AUTH_FILE_CREATE`,
  `AUTH_FILE_UPDATE` and `AUTH_FILE_DELETE`; `Models\Folder::AUTH_FOLDER` (`folder`) replaces the four folder
  ones. `Models\Asset::getPermissionName()` lost its `$action` parameter, and so did the `can()` of
  `Modules\Admin\Widgets\Grids\AssetGridView` and `FileAssetGridView`, which now takes only the asset.
  `findFile()` and `findFolder()` lost their permission argument, and no `can()` call takes a record any more.
  `Migrations\M260914120000AuthItems` grants the new item to every parent and assignee of any old one
- `Models\Actions\ReorderAssets` and `ReorderFolder` pass a skeleton `I18n\Message` to `Trail::createOrderTrail()`
- `Modules\Admin\Widgets\Buttons\FileImportButton`'s form carries `data-busy`: the server fetches the file
  while the request is open, which the skeleton's `includes/busy.ts` now says on screen
- `Models\Asset`, `Models\File` and `Models\Folder` implement the skeleton's
  `Models\Interfaces\AdminModelInterface`, which `Models\Interfaces\AssetModelInterface` now extends in place of
  `AdminRouteInterface`: `getTrailModelName()` and `getTrailModelType()` are `getAdminName()` and `getAdminType()`.
  `Asset::getModelName()` is gone — an asset's model is an `AdminModelInterface`, so
  `$asset->model->getAdminName()` is the name, which is what `Modules\Admin\Widgets\Grids\FileAssetGridView`
  reads. An asset with a `name` is named by it in the trail
- `Models\File` indexes `filename` rather than `basename`, so a search for `photo.jpg` finds the file, and
  `Models\Queries\FileQuery::matching()` matches the filename as a whole for the admin grid, which had the same
  gap
- `Models\File` and `Folder` are searchable: they implement the skeleton's
  `Models\Interfaces\SearchableInterface`, declare their indexed attributes and gate their hit on `fileUpdate`
  and `folderUpdate`. `Bootstrap` registers them on the `search` component
- Added `Modules\Admin\Widgets\Navs\AssetSubmenuItem`, the asset count of a record in whichever submenu shows
  it. It owns the `assets` dom id the file upload names as an out-of-band swap, so the counter follows the grid
  (`Modules\Admin\Widgets\Buttons\FileButtonsTrait::getFileUploadSelectOob()`)
- `Models\Asset` has `loading` and `fetchpriority` custom attributes, offered only for a file with a preview and
  `null` by default, so the renderer's own lazy loading rule (the cms `Widgets\Artwork::$lazyLoadingPosition`)
  still decides as long as the asset says nothing. `Models\Interfaces\AssetInterface` declares `getLoading()`
  and `getFetchPriority()` for them, and `Widgets\Media` renders both on the `img`
- `Modules\Admin\Widgets\Buttons\FileButtonsTrait` declares an abstract `getFileUploadTarget()` beside
  `getFileUploadRoute()` instead of hardcoding `#files`. The upload swaps its target with the element of the same id
  in the response, so a page that shows the asset grid has to name that grid (`Modules\Admin\Widgets\Grids\AssetGridView::ID`)
  and not the file grid the create route renders — uploading from an asset index replaced nothing and fell back to
  swapping the whole `body`. `Modules\Admin\Widgets\Grids\FileGridView::ID` and `AssetGridView::ID` name the two grid ids
- `Modules\Admin\Controllers\Traits\AssetControllerTrait::createAsset()` renders the index after an upload or
  import, so the response carries the asset grid the upload swaps; a `file` given by the picker still returns the
  picker. A partial chunk no longer reaches `File::hasErrors()` on `null`
- `Models\Collections\FolderCollection::$_folders` and `$_default` are `$folders` and `$default`, dropping the
  underscore prefix a private or protected property no longer carries
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