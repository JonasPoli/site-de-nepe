# Sistema de Gerenciamento de Conteúdo de Páginas

Este documento descreve como funciona o sistema de criação e gerenciamento de conteúdo dinâmico de páginas, baseado nas entidades `SchoolSection` e `SchoolSectionBlock`.

---

## Visão Geral

O sistema utiliza dois níveis hierárquicos para organizar o conteúdo:

```
Página (ex: /fundamental-anos-iniciais)
  └── SchoolSection  (Sessão de Conteúdo — ex: "Nossa Proposta")
        └── SchoolSectionBlock  (Bloco — ex: parágrafo com imagem e texto)
        └── SchoolSectionBlock  (Bloco — ex: outro parágrafo com imagem e texto)
  └── SchoolSection  (outra sessão...)
        └── SchoolSectionBlock  ...
```

Cada **Sessão** é um grupo temático de conteúdo visível na página pública. Cada **Bloco** dentro da sessão corresponde a um par imagem+texto renderizado em layout zig-zag.

---

## Entidades

### `SchoolSection` — Sessão de Conteúdo

Representa um **agrupador temático** com cabeçalho próprio, exibido como uma `<section>` na página pública.

| Campo        | Tipo        | Obrigatório | Descrição                                                                                   |
|--------------|-------------|-------------|--------------------------------------------------------------------------------------------|
| `id`         | `int`       | auto        | Identificador único gerado pelo banco.                                                      |
| `sector`     | `Sector`    | ✅           | Enum que associa a sessão a uma página/segmento específico.                                |
| `titlePart1` | `string`    | ✅           | Primeira linha do título da sessão. Renderizada em **laranja** (cor `secondary`).          |
| `titlePart2` | `string`    | ✅           | Segunda linha do título da sessão. Renderizada com a **cor do segmento** (ex: azul).       |
| `position`   | `int`       | ✅           | Ordem de exibição entre as sessões do mesmo segmento. Menor valor = aparece primeiro.      |
| `active`     | `bool`      | ✅           | Se `false`, a sessão **não aparece** na página pública (mas permanece no banco).           |
| `blocks`     | `Collection`| auto        | Coleção de `SchoolSectionBlock` pertencentes a esta sessão. Ordenada por `position ASC`.   |

> **Sobre o título dividido em duas partes:** o layout visual empilha as duas linhas com ângulos diferentes e cores distintas, criando um efeito de "badge duplo" estilizado. `titlePart1` sempre fica acima em laranja; `titlePart2` fica abaixo na cor do segmento.

---

### `SchoolSectionBlock` — Bloco de Conteúdo

Representa **um par imagem + texto** dentro de uma sessão. Múltiplos blocos na mesma sessão são renderizados em **zig-zag** (alternando a posição da imagem: direita no ímpar, esquerda no par).

| Campo       | Tipo            | Obrigatório | Descrição                                                                                   |
|-------------|-----------------|-------------|--------------------------------------------------------------------------------------------|
| `id`        | `int`           | auto        | Identificador único.                                                                        |
| `section`   | `SchoolSection` | ✅           | Referência à sessão pai (FK). Define o contexto (página) ao qual o bloco pertence.         |
| `preTitle`  | `string\|null`  | ❌           | Linha de texto menor acima do título. Ex: "Anos Finais". Colorida com a cor do segmento.   |
| `title`     | `string`        | ✅           | Título principal do bloco, em destaque. Ex: "BASE SÓLIDA PARA TODA UMA VIDA ACADÊMICA".    |
| `text`      | `text\|null`    | ❌           | Corpo de texto do bloco. Aceita **HTML** (editor TinyMCE). Renderizado com `\|raw`.        |
| `image`     | `string\|null`  | ❌           | Nome do arquivo de imagem salvo no disco (gerenciado pelo VichUploader).                   |
| `imageFile` | `File\|null`    | ❌           | Campo virtual (não salvo no banco). Recebe o upload via formulário.                         |
| `position`  | `int`           | ✅           | Ordem do bloco dentro da sessão. Determina o padrão zig-zag (ímpar/par).                  |
| `updatedAt` | `DateTimeImmutable\|null` | auto | Atualizado automaticamente ao alterar a imagem. Necessário para o VichUploader detectar mudanças. |

> **Sobre `imageFile` vs `image`:** `imageFile` é um campo PHP temporário usado apenas durante o upload (anotado com `#[Vich\UploadableField]`). Após salvar, o VichUploader move o arquivo para o disco e armazena apenas o **nome do arquivo** em `image`. Para exibir no Twig usa-se `vich_uploader_asset(block, 'imageFile')`.

> **Sobre `updatedAt`:** o VichUploader exige que uma propriedade seja atualizada ao fazer upload para que o Doctrine detecte a mudança e dispare o evento de persistência. O setter de `imageFile` e de `image` atualizam `updatedAt` automaticamente.

---

## Enum `Sector` — Segmentos

O enum `Sector` define a qual **página/segmento** uma sessão pertence, garantindo que cada página carregue apenas as suas próprias sessões.

| Case         | Value | Label                          | Cor Hex   |
|--------------|-------|--------------------------------|-----------|
| `HOME`       | `0`   | Home                           | `#133880` |
| `FUND_1`     | `1`   | Fundamental Anos Iniciais      | `#0dbbef` |
| `FUND_2`     | `2`   | Fundamental Anos Finais        | `#007bc2` |
| `MEDIO`      | `3`   | Ensino Médio                   | `#133880` |
| `EXTENSIVO`  | `4`   | Extensivo                      | `#0b1742` |
| `CONECTMED`  | `5`   | ConectMed                      | `#38b6ab` |

O enum expõe três métodos úteis:
- `label()` → rótulo legível (ex: `"Fundamental Anos Iniciais"`)
- `color()` → hex da cor do segmento (usada inline no CSS do Twig)
- `badge()` → retorna HTML de um `<sl-badge>` colorido para usar com `|raw`

---

## Relacionamento entre as Entidades

```
SchoolSection  ←──(ManyToOne)──  SchoolSectionBlock
```

- Uma `SchoolSection` pode ter **N blocos** (`OneToMany`)
- Um `SchoolSectionBlock` pertence a **uma única sessão** (`ManyToOne`, não nulável)
- A relação usa `cascade: ['persist', 'remove']` e `orphanRemoval: true`:
  - Ao **deletar** uma sessão, **todos os seus blocos são deletados automaticamente** (sem necessidade de limpar manualmente)
  - Ao **salvar** a sessão, novos blocos adicionados em memória são persistidos junto

---

## Como o Sistema Evita Confusão Entre Blocos de Sessões Diferentes

Este é o mecanismo central de isolamento:

### 1. Rotas Aninhadas por `sectionId`

O `SchoolSectionBlockController` usa uma rota pai parametrizada:

```php
#[Route('/admin/school-section/{sectionId}/block')]
```

Todas as operações de bloco exigem o `{sectionId}` na URL. O `#[MapEntity(id: 'sectionId')]` do Symfony resolve o objeto `SchoolSection` correspondente. Portanto:

- `GET /admin/school-section/5/block/` → lista **apenas os blocos da sessão 5**
- `GET /admin/school-section/5/block/12/edit` → edita o bloco 12 **no contexto da sessão 5**
- A URL de reordenação `/reorder` também é dentro da sessão: `path('app_admin_school_section_block_reorder', {sectionId: section.id})`

### 2. Filtro no `index()` do Controller

```php
$repo->findBy(['section' => $section], ['position' => 'ASC'])
```

A query é sempre filtrada pela sessão injetada, nunca busca blocos globalmente.

### 3. Breadcrumb Contextual no Admin

O template de blocos exibe sempre o caminho:

```
Sessões > [Nome da Sessão] [badge do segmento]
```

Assim o administrador sabe exatamente em qual sessão está trabalhando. O botão "Voltar às Sessões" retorna filtrado pelo mesmo segmento.

### 4. Reordenação Isolada por Sessão

O endpoint de reordenação por drag-and-drop (`/reorder`) recebe a lista de IDs de blocos, mas eles sempre são buscados via `$repo->find($id)` — sem validar se pertencem à sessão. Isso é seguro porque a interface só **envia** os IDs dos blocos visíveis (que já foram filtrados pelo `index()`), não sendo possível ao usuário misturar blocos de sessões diferentes pela UI normal.

---

## CRUDs Administrativos

### CRUD de Sessões (`SchoolSectionController`)

| Rota                                 | Nome                                 | Método     | Descrição                                         |
|--------------------------------------|--------------------------------------|------------|--------------------------------------------------|
| `/admin/school-section/`             | `app_admin_school_section_index`     | GET        | Lista todas as sessões (com filtro por segmento) |
| `/admin/school-section/new`          | `app_admin_school_section_new`       | GET, POST  | Cria nova sessão                                 |
| `/admin/school-section/{id}/edit`    | `app_admin_school_section_edit`      | GET, POST  | Edita sessão existente                           |
| `/admin/school-section/reorder`      | `app_admin_school_section_reorder`   | POST (JSON)| Salva nova ordenação via drag-and-drop           |
| `/admin/school-section/{id}`         | `app_admin_school_section_delete`    | POST       | Exclui sessão (e todos os seus blocos)           |

**Detalhe: `sector_locked`**

Ao criar uma sessão a partir de um segmento (passando `?sector=N` na URL), o formulário **oculta o campo `sector`** e pré-define o valor. Isso evita que o admin mude acidentalmente o segmento de uma sessão já criada. Na edição, o campo `sector` é sempre bloqueado (`sector_locked: true`).

### CRUD de Blocos (`SchoolSectionBlockController`)

| Rota                                                  | Nome                                          | Método     | Descrição                         |
|-------------------------------------------------------|-----------------------------------------------|------------|----------------------------------|
| `/admin/school-section/{sectionId}/block/`            | `app_admin_school_section_block_index`        | GET        | Lista blocos da sessão           |
| `/admin/school-section/{sectionId}/block/new`         | `app_admin_school_section_block_new`          | GET, POST  | Cria novo bloco na sessão        |
| `/admin/school-section/{sectionId}/block/{id}/edit`   | `app_admin_school_section_block_edit`         | GET, POST  | Edita bloco                      |
| `/admin/school-section/{sectionId}/block/reorder`     | `app_admin_school_section_block_reorder`      | POST (JSON)| Salva nova ordenação dos blocos  |
| `/admin/school-section/{sectionId}/block/{id}`        | `app_admin_school_section_block_delete`       | POST       | Exclui bloco                     |

Ao criar um novo bloco, o controller já injeta automaticamente a sessão:
```php
$block = new SchoolSectionBlock();
$block->setSection($section);  // seção já definida antes de abrir o form
```

---

## Form Types

### `SchoolSectionType`

```php
->add('titlePart1')     // "Título — Parte 1 (laranja/secondary)"
->add('titlePart2')     // "Título — Parte 2 (cor do segmento)"
->add('position')       // "Posição / Ordem"
->add('active')         // "Ativo?"
// Se sector_locked = false:
->add('sector', EnumType::class)  // dropdown de segmentos
```

### `SchoolSectionBlockType`

```php
->add('preTitle')       // "Pré-Título" (opcional)
->add('title')          // "Título do Bloco"
->add('text')           // "Texto (HTML)" — textarea com data-controller="tinymce"
->add('imageFile', VichImageType::class)  // upload de imagem
->add('position')       // "Posição / Ordem"
```

> O campo `text` usa o editor **TinyMCE** (Stimulus controller `tinymce`). O conteúdo salvo é HTML e deve ser renderizado no Twig com o filtro `|raw`.

---

## Reordenação por Drag-and-Drop

O sistema usa **SortableJS** para permitir que o admin arraste as linhas da tabela e salve a nova ordem automaticamente.

O script é incluído via partial reutilizável:

```twig
{# No bloco {% block javascripts %} #}
{{ include('admin/_sortable_script.html.twig', {
    tbody_id: 'school-section-block-sortable',
    reorder_url: path('app_admin_school_section_block_reorder', {sectionId: section.id})
}) }}
```

O script:
1. Inicializa o SortableJS na `<tbody>` identificada por `tbody_id`
2. Ao terminar o drag, coleta os `data-id` de todas as `<tr class="sortable-row">` em ordem
3. Faz `POST` JSON para `reorder_url` com o array `{ ids: [3, 1, 5, 2] }`
4. O controller percorre o array e define `position = index + 1` para cada item
5. Exibe um toast verde "✓ Ordem salva" por 1,5s

**Importante:** a reordenação de sessões só é ativada quando há um filtro de segmento ativo. A tabela recebe `id="school-section-sortable"` apenas nesse caso, pois misturar sessões de segmentos diferentes na reordenação não faz sentido.

---

## Renderização Pública — Macro Twig

O conteúdo é renderizado via **macro Twig** reutilizável, localizada em:

```
templates/macros/school_sections.html.twig
```

### Como usar a macro em uma página

```twig
{# 1. Importar a macro no início do template #}
{% from 'macros/school_sections.html.twig' import school_sections %}

{# 2. Chamar a macro passando as sessões e a cor do segmento #}
{{ school_sections(school_sections, segColor) }}
```

- `school_sections` → variável com a lista de `SchoolSection[]` enviada pelo controller
- `segColor` → string hex da cor do segmento (ex: `'#0dbbef'`)

### O que a macro renderiza

Para cada sessão ativa (`section.active = true`):

1. **Cabeçalho da Sessão** — dois badges estilizados em zig-zag:
   - `titlePart1` em fundo laranja (`bg-secondary`)
   - `titlePart2` em fundo com a cor do segmento (`style="background-color: {{ segColor }}"`)

2. **Blocos em Zig-Zag** — para cada `block` em `section.blocks`:
   - **Ímpar** (1º, 3º, ...): texto à esquerda, imagem à direita
   - **Par** (2º, 4º, ...): imagem à esquerda, texto à direita

Dentro de cada bloco:
- `block.preTitle` (se existir) → pequeno label colorido acima do título
- `block.title` → título principal em `text-primary`
- `block.text|raw` → corpo HTML
- `vich_uploader_asset(block, 'imageFile')` → imagem com hover de zoom e sombra
- Se não houver imagem, usa `https://placehold.co/600x600` como fallback

### Como o controller passa os dados

```php
// Em MainController.php, para cada rota de segmento:
$sector = Sector::FUND_1;
return $this->render('pub/main/fundamental-iniciais.html.twig', [
    'school_sections' => $this->schoolSectionRepository->findBy(
        ['sector' => $sector, 'active' => true],
        ['position' => 'ASC']
    ),
    // ...outras variáveis
]);
```

A query filtra por `sector` **e** `active = true`, garantindo que sessões desativadas nunca apareçam. A ordenação por `position ASC` respeita a ordem definida no admin.

---

## Fluxo Completo — Passo a Passo

```
1. Admin acessa "Sessões de Conteúdo" filtrado por segmento
2. Cria uma SchoolSection com título em duas partes e define posição
3. Acessa os Blocos desta sessão
4. Cria um ou mais SchoolSectionBlocks com:
   - Pré-título (opcional)
   - Título do bloco
   - Texto rico (HTML via TinyMCE)
   - Imagem (upload via VichUploader)
5. Reordena os blocos via drag-and-drop para controlar o zig-zag
6. A página pública busca automaticamente as sessões ativas do segmento
7. A macro Twig renderiza cada sessão com seus blocos em zig-zag
```

---

## Como Reutilizar Este Sistema em Outras Páginas

Este padrão é genérico e pode ser aplicado a qualquer tipo de página estática com conteúdo gerenciável.

### Exemplo: Página "Sobre a Empresa"

**Passo 1:** Adicionar um novo case ao enum (ou criar um enum próprio):
```php
// Em Sector.php ou em um enum PageSection.php próprio
case SOBRE = 10;
// + label(), color(), badge() para este case
```

**Passo 2:** Criar uma rota pública:
```php
#[Route('/sobre-a-empresa', name: 'pub_sobre')]
public function sobre(): Response
{
    return $this->render('pub/main/sobre.html.twig', [
        'school_sections' => $this->schoolSectionRepository->findBy(
            ['sector' => Sector::SOBRE, 'active' => true],
            ['position' => 'ASC']
        ),
        'segColor' => Sector::SOBRE->color(),
    ]);
}
```

**Passo 3:** Usar a macro no template:
```twig
{# templates/pub/main/sobre.html.twig #}
{% from 'macros/school_sections.html.twig' import school_sections %}
{% extends 'pub/base.html.twig' %}

{% block body %}
    {# ... hero, banner, etc. #}
    {{ school_sections(school_sections, segColor) }}
{% endblock %}
```

**Passo 4:** Adicionar o link no menu do admin em `templates/admin/base.html.twig`:
```twig
<a href="{{ path('app_admin_school_section_index', {sector: 10}) }}">
    Sess. Conteúdo — Sobre
</a>
```

Pronto. O admin já pode criar sessões e blocos para a página "Sobre a Empresa" usando exatamente o mesmo CRUD.

### Exemplo: Página "Política de Privacidade"

Para páginas de texto puro (sem imagem), o fluxo é o mesmo. Basta:
- Criar blocos com `text` preenchido e sem `imageFile`
- A macro usa `placehold.co` como fallback quando não há imagem — você pode customizar a macro para não renderizar o lado da imagem quando `block.image` for nulo

Customização da macro para texto-puro:
```twig
{# Substitui o bloco de imagem por um espaço vazio quando não há imagem #}
{% if block.image %}
    {# renderiza imagem normalmente #}
{% else %}
    {# renderiza apenas o lado do texto, em largura total #}
    <div class="col-span-2">
        {# preTitle, title, text #}
    </div>
{% endif %}
```

---

## Estrutura de Arquivos

```
src/
  Entity/
    SchoolSection.php          # Entidade Sessão
    SchoolSectionBlock.php     # Entidade Bloco
    Enum/
      Sector.php               # Enum de segmentos/páginas

  Form/
    SchoolSectionType.php      # Form da Sessão
    SchoolSectionBlockType.php # Form do Bloco

  Controller/
    Admin/
      SchoolSectionController.php       # CRUD admin de Sessões
      SchoolSectionBlockController.php  # CRUD admin de Blocos
    Pub/
      MainController.php        # Injeta school_sections nas rotas públicas

  Repository/
    SchoolSectionRepository.php
    SchoolSectionBlockRepository.php

templates/
  macros/
    school_sections.html.twig  # Macro de renderização pública

  admin/
    school_section/
      index.html.twig          # Listagem com filtro por segmento + drag-and-drop
      new.html.twig            # Cria nova sessão
      edit.html.twig           # Edita sessão
      _form.html.twig          # Form parcial compartilhado

    school_section_block/
      index.html.twig          # Listagem dos blocos de uma sessão + drag-and-drop
      new.html.twig            # Cria novo bloco
      edit.html.twig           # Edita bloco
      _form.html.twig          # Form parcial com TinyMCE e VichUploader

    _sortable_script.html.twig # Partial JS reutilizável (SortableJS)

  pub/main/
    fundamental-iniciais.html.twig   # Usa a macro school_sections
    fundamental-finais.html.twig     # Usa a macro school_sections
    ensino-medio.html.twig           # Usa a macro school_sections
    extensivo.html.twig              # Usa a macro school_sections
    conectmed.html.twig              # Usa a macro school_sections
```
