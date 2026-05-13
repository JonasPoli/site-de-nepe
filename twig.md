Agora, crie uma outra Skill, sobre como usar ENUMs em CRUDs (ou em outros)

Veja como este sistema de EnumExtension funciona em:
/Volumes/Dados/work/Cliente-Real/src/Twig/EnumExtension.php
no projeto /Volumes/Dados/work/Cliente-Real/
Entenda como isso é usado em:
/Volumes/Dados/work/Cliente-Real/templates/admin/banner/index.html.twig, nas linhas
```
{% set languagesEnum = enum('LanguageEnum') %}
```
e em
```
                        <td>{{ languagesEnum.getFlag(banner.language)|raw }}
```

Entenda o sistema de getFlag de /Volumes/Dados/work/Cliente-Real/src/Entity/Enum/LanguageEnum.php

Escreva uma skill que faça com que a I.A. aprenda a fazer Enums aproveitados dessa maneira.