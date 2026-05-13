---
name: crud
description: Create and customize Symfony CRUDs using the project's specific pattern—Shoelace components, Tailwind CSS, and DataTables for listings. Trigger when the user asks to "create a CRUD", "generate admin pages", "make:crud", or "add a listing with DataTables". This skill documents the "Wab" pattern observed in projects like `site-base` and `procordis-site`.
license: MIT
metadata:
  author: Simon Andre
  email: smn.andre@gmail.com
  url: https://smnandre.dev
  version: "1.0"
---

# CRUD Pattern

Standardized CRUD generation for administrative areas using Shoelace Web Components, Tailwind CSS, and DataTables.

## Core Rules

1.  **Use Custom Maker**: If the project has `make:custom-crud`, prefer it over the default `make:crud`.
2.  **Layout**: All admin CRUDs must extend `admin/base.html.twig` or `admin/base_admin.html.twig`.
3.  **UI Components**: Use **Shoelace** (`sl-*`) for buttons, cards, icons, and badges. Avoid plain HTML buttons.
4.  **Listings (Index)**:
    -   Must use **DataTable** (v2.x) with Tailwind CSS support.
    -   Include DataTables and jQuery via CDN in the `stylesheets` and `javascripts` blocks.
    -   The table must have `id="crud-table"`.
    -   Initialize with Portuguese translations and responsive behavior.
5.  **Forms**: Use a 2-column grid (`grid-cols-2`) for desktop layouts.
6.  **Enums**: Use the `enum()` Twig function to render labels for Enum fields.
7.  **Images**: Use `vich_uploader_asset` and `imagine_filter('admin_thumb')` for consistent image previews.

## Quick Patterns

### Listing (Index)
Ensure the `index.html.twig` has the `#crud-table` and the script block:

```twig
{% block javascripts %}
    {{ parent() }}
    <script src="https://cdn.datatables.net/2.1.3/js/dataTables.min.js"></script>
    <script>
        new DataTable('#crud-table', {
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json' },
            responsive: true
        });
    </script>
{% endblock %}
```

### Form (Edit/New)
Wrap fields in a grid and use Shoelace for actions:

```twig
<sl-card>
    {{ form_start(form) }}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{ form_rest(form) }}
        </div>
        <div class="flex justify-between mt-6">
            <sl-button type="submit" variant="primary">Salvar</sl-button>
        </div>
    {{ form_end(form) }}
</sl-card>
```

## Anti-Patterns

- **Don't use the default `make:crud` without layout adjustments.** The default look is not consistent with the project's UI.
- **Don't forget the Portuguese translations for DataTables.** Admin users expect localized labels.
- **Don't hardcode height on tables.** Let DataTables and Tailwind handle the responsive layout.
- **Don't use icon fonts.** Use `<sl-icon name="..." />` or `<twig:ux:icon />`.

## Related Skills

- **twig-component**: For reusable UI elements like `DataTable` or `AdminMenu`.
- **ux-icons**: For custom SVG icons used in action buttons.
