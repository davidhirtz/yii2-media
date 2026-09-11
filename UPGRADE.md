# Upgrade Guide

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
