# Upgrade Guide

## 3.0.0 — An asset page is titled with the asset

`Modules\Admin\Widgets\Navs\AssetHeader` extends the skeleton's `Widgets\Navs\ModelHeader` and titles the
page with the **asset**; `Models\Asset::getAdminParent()` carries the owning record into the path above the
title and into the breadcrumb bar. A project view that rendered the owner's header over an asset page passes the
asset to `AssetHeader` instead, and keeps the owner's submenu — which tab the page shows does not change:

```php
echo AssetHeader::make()
    ->model($asset)
    ->content(AssetActionDropdown::make()->model($asset));

echo ProductSubmenu::make()
    ->model($asset->model);
```

A project's own asset model needs nothing: `getAdminParent()` and `getAdminIndexBreadcrumb()` are declared on
`Models\Asset` for the whole family.

## 3.0.0 — A model holds a file once

Before v3 a record could carry the same file any number of times: an entry could name one file as its preview
*and* as a gallery image, as two `asset` rows. Nothing in the estate used it, and the admin had no way to show
what a second row meant, so the relation is unique from this release — `(model_class, model_id, file_id)`.

`Migrations\M260916100000AssetUnique` removes the duplicates an installation already has **before** it creates
the index. The row with the lowest `position` survives, which is the one the record has shown at the top of its
asset list all along; the others are deleted with their search documents, and the `asset_count` of every file and
every model is recomputed. The migration prints how many rows it removed — a project that cannot afford to lose
them exports `SELECT * FROM asset` first.

Three consequences for a project:

- **The asset `duplicate` action is gone.** It existed to add the same file to the same record a second time,
  which is what no longer happens. `Models\Actions\DuplicateAsset` is unchanged — duplicating an *entry*,
  section or hotspot still carries its assets to the new record — so only the controller action, its access rule
  and the button in `Modules\Admin\Widgets\Navs\AssetActionDropdown` went. A project's own asset controller
  drops `actionDuplicate()` and the `'duplicate'` entry from its `AccessControl` rule.

- **A `remove` action replaces it**, POST-only, which the file picker's button posts to. A project's own asset
  controller adds it beside the others:

  ```php
  public function actionRemove(
      ?int $entry = null,
      ?int $file = null,
      ?int $folder = null,
      ?string $q = null,
  ): Response|string {
      return $this->removeAsset($this->findEntryWithAssets($entry), $file, $folder, $q);
  }
  ```

  and names `'remove'` in its access rule in place of `'duplicate'`.

- **Inserting a file a record already has is a validation error**, not a second row —
  `Yii::t('media', 'ASSET_FILE_ID_ERROR')` on `file_id`. Code that inserted an asset without checking the result
  now has one to report. Replacing an asset's file with one the record already holds is refused the same way, and
  the picker offers no button for it.

## 3.0.0 — `hasAssetsEnabled()` is `allowsAssets()`, and it answers for the type

`AssetModelInterface::hasAssetsEnabled()` reported the installation's flag alone, so a caller wanting the type's
answer too had to add `isAttributeVisible(FIELD_ASSETS)` itself. The frontend did; `AssetControllerTrait` and
`AssetCountColumn` did not, which let an entry type that declared no assets still take one through the admin.

```php
// before
$model->hasAssetsEnabled() && $model->isAttributeVisible(AssetModelInterface::FIELD_ASSETS)

// after
$model->allowsAssets()
```

`FIELD_ASSETS` is gone. A type declares it with `AssetModelTypeInterface::allowAssets(false)`, and a model
implementing the interface itself ANDs its module flag with `AssetModelTrait::typeAllowsAssets()`.

**The type interface split in two.** `AssetModelTypeInterface` kept the name and gained the capability;
the sizes and transformations it used to declare are `TransformationTypeInterface` +
`Models\Types\Traits\TransformationTypeTrait`, which it extends. `Models\Types\AssetType` implements only that
one now — an asset declares how it renders but has no assets of its own — so a type class named as an
*asset model's* type needs `AssetModelTypeInterface`, for which `Models\Types\AssetModelType` is the ready-made
class. The `validate()` alias a using class needs is `validateTransformationType`.

## 3.0.0 — `File::copy()` takes a path, and the URL import is guarded

`Models\File::copy()` builds a `Skeleton\Web\CopiedUploadedFile` from the path it is given — the class that opens
a file the application names, a stream wrapper's included. It used to build a `StreamUploadedFile`, which from this
release fetches a URL under the policy of the `upload` component and is no longer the right thing for a path.

A project importing a remote file through `copy()` builds the upload itself:

```php
$file->upload = new StreamUploadedFile(['url' => $url, 'allowedExtensions' => $file->allowedExtensions]);
```

`File::$upload` is typed `Skeleton\Web\AbstractUploadedFile|Skeleton\Web\ChunkedUploadedFile|null`.

The import form itself is unchanged, but the fetch behind it now accepts `http` and `https` only and refuses a
loopback, private or reserved address — see the skeleton's guide for `Upload::$enableStreamUploads` and
`$allowPrivateStreamUploadHosts`. `Modules\Admin\Widgets\Buttons\FileImportButton` renders nothing while the
feature is off.

## 3.0.0 — The `media` role is dropped

`Migrations\M260914200000MediaRole` removes it, granting `File::AUTH_FILE` and `Folder::AUTH_FOLDER` to every
parent and every assignee it had, so nobody loses the media library. A project that names `'media'` in an
`AccessRule`, a nav item's `roles()` or its own migration names the two permissions instead.


## 3.0.0 — Transformations and sizes

Read the skeleton's guide on typed type definitions first; this is the media half of it.

### The record and the preset are two classes

`Hirtz\Media\Models\Transformation` is `Hirtz\Media\Models\FileTransformation`, on the table
`file_transformation` (renamed by `M260914170000FileTransformation`). The relation `File::getTransformations()`,
the `transformation_count` column, `EVENT_BEFORE_TRANSFORMATION` and the admin `transformation/*` routes are
unchanged — a route is a URL and a relation is a word, neither is the class.

The presets themselves are `Hirtz\Media\Transformations\Transformation`, which is where `NAME_ADMIN` and
`NAME_OPEN_GRAPH` now live:

```php
// before, in config/prod.php
'transformations' => [
    'xs' => ['width' => 374],
    'og' => ['width' => 1200, 'height' => 630, 'keepAspectRatio' => true, 'scaleUp' => false],
],

// after
'transformations' => [
    Transformation::make('xs')->width(374),
    Transformation::make('og')->width(1200)->height(630)->keepAspectRatio(),
],
```

The array keys were the record's property names, so every one of them is a setter of the same name. The raw
`imageOptions` array is four typed setters — `jpegQuality()`, `pngCompressionLevel()`, `webpQuality()` and
`resolution($x, $y, $units)` — with the same defaults.

**`scaleUp` defaults to `false`.** It always did as far as `File::isValidTransformation()` was concerned; the
record's own property said `true`, so the same preset meant two different things depending on which of the two
asked. Add `->scaleUp()` to any preset that is meant to enlarge a file.

The module's property is setter-backed: `setTransformations()`, `addTransformation()`, `removeTransformation()`,
`getTransformation()`, `getTransformations()` (sorted by width) and `hasTransformation()`.
`File::getTransformationOptions()` is gone — ask the module for the preset instead.

### A type registers its own transformations

`Module::addTransformationsFromTypeOptions()` is gone, and so is the `config/prod.php` line that called it:

```php
// before
$module->addTransformationsFromTypeOptions(Entry::getTypes());
$module->addTransformationsFromTypeOptions(Section::getTypes());
```

A type's `transformations()` takes a configured preset name, a self-describing one, or an inline definition, and
registers the last two itself the first time the module is asked for a transformation:

```php
SectionType::make(self::TYPE_HEADLINE)
    ->name('Headline')
    ->transformations('xs', 'w_1200', Transformation::make('headline')->width(1600)->height(400))
```

A name that is neither configured nor self-describing now throws at declaration time. It used to leave
`getTransformationUrl()` answering `null`, and the `<img>` silently lost that srcset entry.

### `sizes` is a list of objects

`Helpers\Sizes::format()` is gone. `Helpers\Size` is one `<media-condition> <length>` pair:

```php
// before
'sizes' => [
    'sm' => '100vw',
    '(max-width: 1023px)' => '75vw',
    0 => '960px',
],

// after
->sizes(
    Size::breakpoint('sm', '100vw'),
    Size::mediaQuery('(max-width: 1023px)', '75vw'),
    '960px',
)
```

`Size::breakpoint()` validates the name against `Module::$breakpoints` when it is called, so a renamed
breakpoint is an exception rather than a dropped condition. A plain string is shorthand for `Size::value()`, the
bare length — and it must come last, which the type's `validate()` enforces: a browser ignores every entry after
the first bare one. A `'sizes' => '100vw'` string used to render nothing at all, because `format()` answered
`null` for anything that was not an array.

`Widgets\Media::sizes()` is variadic in the same shape (`sizes(Size|string ...)`), as is the skeleton's
`Html\Traits\TagImageAttributesTrait::sizes()`, which now takes `Stringable` too.


## 3.0.0 — Assets

Assets moved from `yii2-cms` into this bundle and became polymorphic. There is one `asset` table, one
base model, and one subclass per model that has assets.

```
asset
  id, status, type
  model_class, model_id        the record the asset belongs to
  file_id, position
  custom_attributes            name, content, alt_text, link, embed_url live here
  updated_by_user_id, updated_at, created_at
```

Take a database dump before migrating. `M260912110000Assets` (cms) and `M260912120000Assets`
(cms-hotspot) copy `cms_asset` and `hotspot_asset` into `asset`, assert their own result and roll
back on a mismatch — but they return `false` from `safeDown()`, because the trail rewrite is not
cleanly reversible.

### Attaching assets to a model of your own

```php
class Recipe extends ActiveRecord implements AssetModelInterface
{
    use AssetModelTrait;

    public function getAssetClass(): string
    {
        return RecipeAsset::class;
    }

    public function hasAssetsEnabled(): bool
    {
        return true;
    }
}

class RecipeAsset extends Asset
{
    public static function getModelClass(): string
    {
        return Recipe::class;
    }

    public function getPermissionName(string $action): string
    {
        return match ($action) {
            'create' => Recipe::AUTH_RECIPE_ASSET_CREATE,
            'delete' => Recipe::AUTH_RECIPE_ASSET_DELETE,
            'order' => Recipe::AUTH_RECIPE_ASSET_ORDER,
            'update' => Recipe::AUTH_RECIPE_ASSET_UPDATE,
        };
    }
}
```

The model needs an `asset_count` column, and the subclass one line in the module configuration:

```php
'media' => [
    'assets' => [RecipeAsset::class],
],
```

That is enough for file counts, trail parents, duplication and reordering. The admin pages are the
subclass's own: give it a `getAdminControllerRoute()`, write a controller that uses
`Modules\Admin\Controllers\Traits\AssetControllerTrait`, and ship `index`, `create` and `update`
views. The controller declares its own access rules, resolves and authorises the record, and calls
`renderIndex()`, `createAsset()`, `updateAsset()`, `deleteAsset()`, `duplicateAsset()` or
`reorderAssets()`. Views come from the module the controller is mounted under, so nothing needs to
declare a view path.

### The text attributes are custom attributes

`name`, `content`, `alt_text`, `link` and `embed_url` have no columns. The base declares them in
`getDefaultCustomAttributes()`, so a subclass can drop, add or retype them without a migration.
Which of them are stored per language is configured per subclass:

```php
EntryAsset::class => [
    'translatableAttributes' => ['alt_text'],
],
```

**Do not** put those names into `$i18nAttributes`: `createCustomAttributeDefinitions()` throws when a
definition name is also a translated column attribute. A translatable definition keeps its `_xx`
name but is stored inside the JSON, so an asset no longer writes to the `translation` table at all.

### Replacements

| Before | After |
|---|---|
| `Hirtz\Cms\Models\Asset` | `Hirtz\Cms\Models\EntryAsset` / `SectionAsset` |
| `$asset->entry_id` / `$asset->section_id` | `$asset->model_class` / `$asset->model_id` |
| `$asset->parent` | `$asset->model` |
| `$asset->isEntryAsset()` | `$asset instanceof EntryAsset` |
| `$asset->contentType` | `$asset->getCustomAttribute('content') instanceof HtmlCustomAttribute` |
| `Module::$fileRelations` | `Module::$assets` |
| `file.cms_asset_count` / `hotspot_asset_count` | `file.asset_count` |
| `FileRelationInterface`, `AssetParentInterface` | gone; `AssetModelInterface` replaces the second |
| `AssetParentTrait`, `AssetTrait`, `EmbedUrlTrait` | gone; `AssetModelTrait` replaces the first |
| `AssetParentInterface::getParamName()` | `AssetModelInterface::getParamName()`, unchanged |
| `Hirtz\Media\Models\Traits\MetaImageTrait` | `Hirtz\Cms\Models\Traits\MetaImageTrait` |

### What to watch out for

- **`updateAll()` and `deleteAll()` are static and unscoped.** Every subclass shares one table, so
  such a call must name `model_class` itself. `find()` on a subclass is scoped; the base is not, and
  returns mixed subclasses from one query.
- A subclass is picked by `instantiate()` from the row's `model_class`, so a row whose class is not
  registered comes back as the base `Asset`.
- `getCustomAttributes()` runs for every loaded record with neither `file` nor `model` populated.
  Nothing a definition does may read them unguarded.

### Validating the copy

Right after migrating, before editing content — the old tables still carry their `ON DELETE CASCADE`
keys, so content changes silently prune them:

1. Re-run the assertion queries from the two migrations by hand and compare with their stdout.
2. Filter the admin trail index to `EntryAsset`, `SectionAsset` and `HotspotAsset` and spot-check a
   create row, a child row on an entry, and a row for an asset deleted before the migration.
3. Compare one entry's, one section's and one hotspot's asset page against `cms_asset` /
   `hotspot_asset` ordered by `position`.
4. Check a file's relations page against `file.asset_count` and the old counts from the dump.

`cms_asset` and `hotspot_asset` are kept on purpose and dropped by a later migration once every
project is upgraded. Never read them from application code. A project that added columns to them is
covered by `docs/plans/v3-upgrade-toolchain.md` §8.
