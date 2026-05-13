---
name: enum
description: Use PHP Enums in Twig templates for rich rendering (flags, labels) and easy form integration. Trigger when the user asks about "using enums in twig", "getting flag from enum", "enum label", or "enum choices in form". This skill documents the `EnumExtension` pattern found in `Cliente-Real`.
license: MIT
metadata:
  author: Simon Andre
  email: smn.andre@gmail.com
  url: https://smnandre.dev
  version: "1.0"
---

# Enum System

Bridge PHP Enums with Twig templates for high-fidelity rendering and seamless form integration.

## Core Rules

1.  **Backing Type**: Enums must be `int` backed (e.g., `enum StatusEnum: int`).
2.  **Required Static Methods**:
    -   `getOptions()`: Standard labels for `ChoiceType` forms.
    -   `getDescription($i)`: Human-readable text for a given value.
    -   `getFlag($i)`: HTML snippet with icon/flag and label.
3.  **Twig Access**: Use the `enum('EnumName')` function to get an instance of the enum proxy in Twig.
4.  **Rendering**: Always use the `|raw` filter when rendering `getFlag()` to ensure icons are displayed.

## Enum Definition Pattern

```php
namespace App\Entity\Enum;

enum LanguageEnum: int
{
    case PT = 1;
    case ES = 2;

    public static function getOptions(): array
    {
        return [
            'Português' => self::PT->value,
            'Espanhol' => self::ES->value,
        ];
    }

    public static function getFlag($i): string
    {
        $map = [
            self::PT->value => '<img src="/flags/br.svg" width="20"> PT',
            self::ES->value => '<img src="/flags/es.svg" width="20"> ES',
            "" => '',
        ];
        return $map[$i] ?? '';
    }
}
```

## Twig Usage Pattern

```twig
{# 1. Initialize #}
{% set languageEnum = enum('LanguageEnum') %}

{# 2. Use in Table #}
<td>{{ languageEnum.getFlag(item.language)|raw }}</td>

{# 3. Use in Form (via choices) #}
{{ form_row(form.language, {
    choices: enum('LanguageEnum').getOptions()
}) }}
```

## Related Skills

- **crud**: Use Enums to enhance status and category columns in administrative listings.
- **twig-component**: Wrap Enum rendering in reusable components.
