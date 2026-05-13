# Enum Patterns

Detailed examples for implementing and using the Enum system.

---

## PHP Enum Implementation (Rich Data)

Add static methods to your Enums to provide rich data like flags and human-readable descriptions.

```php
// src/Entity/Enum/StatusEnum.php
namespace App\Entity\Enum;

enum StatusEnum: int
{
    case DRAFT = 0;
    case PUBLISHED = 1;
    case ARCHIVED = 2;

    public static function getDescription(int $v): string
    {
        return match ($v) {
            self::DRAFT->value => 'Rascunho',
            self::PUBLISHED->value => 'Publicado',
            self::ARCHIVED->value => 'Arquivado',
            default => '',
        };
    }

    public static function getFlag(int $v): string
    {
        return match ($v) {
            self::DRAFT->value => '<sl-badge variant="neutral">Rascunho</sl-badge>',
            self::PUBLISHED->value => '<sl-badge variant="success">Ativo</sl-badge>',
            self::ARCHIVED->value => '<sl-badge variant="danger">Arquivado</sl-badge>',
            default => '',
        };
    }
}
```

---

## Twig Proxy Usage

The `enum()` function provides a proxy to these static methods, which makes reading listings much cleaner.

```twig
{# templates/admin/post/index.html.twig #}
{% set statusEnum = enum('StatusEnum') %}

<table id="crud-table">
    <thead>
        <tr>
            <th>Título</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        {% for post in posts %}
            <tr>
                <td>{{ post.title }}</td>
                <td>{{ statusEnum.getFlag(post.status)|raw }}</td>
            </tr>
        {% endfor %}
    </tbody>
</table>
```

---

## Form Type Integration

Use the `getOptions()` method to populate Symfony forms effortlessly.

```php
// src/Form/PostType.php
use App\Entity\Enum\StatusEnum;

public function buildForm(FormBuilderInterface $builder, array $options): void
{
    $builder
        ->add('status', ChoiceType::class, [
            'choices' => StatusEnum::getOptions(),
        ]);
}
```

Or directly in a LiveComponent:

```twig
<select data-model="status">
    {% for label, value in enum('StatusEnum').getOptions() %}
        <option value="{{ value }}">{{ label }}</option>
    {% endfor %}
</select>
```
