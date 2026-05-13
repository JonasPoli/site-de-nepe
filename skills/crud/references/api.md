# CRUD API Reference

Documentation for utility tools and components used in the project's CRUD pattern.

---

## Twig Function: `enum()`

Used to get the value or labels of a PHP Enum in Twig templates. Especially useful for status, language, or category fields.

### Usage
```twig
{# Get the enum class instance #}
{% set statusEnum = enum('StatusEnum') %}

{# Access a specific case #}
{{ statusEnum.PUBLISHED.value }}

{# Get a human-readable description (if method exists) #}
{{ statusEnum.getDescription(item.status) }}
```

### Implementation Details
The helper looks for the Enum in `\App\Enum\` or `\App\Entity\Enum\`.

---

## Shoelace Components

The project uses [Shoelace](https://shoelace.style) for its admin UI.

### Common Components
- **`sl-card`**: Grouping content and tables.
- **`sl-button`**: Standard actions. Variants: `primary`, `neutral`, `success`, `danger`, `warning`.
- **`sl-icon`**: Inline icons. Uses Bootstrap/Lucide names.
- **`sl-badge`**: Status indicators.
- **`sl-alert`**: Success/error messages.

### Standard Buttons
```twig
{# New Record #}
<sl-button variant="primary" outline href="...">
    <sl-icon slot="prefix" name="plus-lg"></sl-icon> Novo
</sl-button>

{# Save #}
<sl-button type="submit" variant="primary">Salvar</sl-button>

{# Edit (Icon only) #}
<sl-button size="small" variant="neutral" outline href="...">
    <sl-icon name="pencil-square"></sl-icon>
</sl-button>
```

---

## Image Filters

Image previews in listings and forms should use the `admin_thumb` filter via LiipImagineBundle.

### Usage
```twig
<img src="{{ vich_uploader_asset(field, 'imageFile') | imagine_filter('admin_thumb') }}" 
     alt="" class="w-10 h-10 object-cover rounded-full">
```

---

## DataTables (PT-BR)

The standard configuration for DataTables includes Portuguese translations via a CDN URL.

### CDN Resources
- **CSS**: `https://cdn.datatables.net/2.1.3/css/dataTables.dataTables.min.css`
- **JS**: `https://cdn.datatables.net/2.1.3/js/dataTables.min.js`
- **I18n**: `//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json`
