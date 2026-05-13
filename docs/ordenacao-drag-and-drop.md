# Sistema de Ordenação por Drag-and-Drop

Este documento descreve o padrão completo de reordenação de registros via arrastar-e-soltar nas listagens administrativas, implementado com **SortableJS** + endpoint Symfony REST.

---

## Visão Geral da Arquitetura

O sistema funciona em três camadas que se comunicam:

```
┌─────────────────────────────────┐
│  Usuário arrasta linha na tabela │
└──────────────┬──────────────────┘
               │
               ▼
┌─────────────────────────────────┐
│  SortableJS (JavaScript)         │
│  - Detecta o fim do drag (onEnd) │
│  - Coleta os data-id na nova     │
│    ordem                         │
│  - POST JSON para /reorder       │
└──────────────┬──────────────────┘
               │ POST {ids: [3,1,5]}
               ▼
┌─────────────────────────────────┐
│  Controller Symfony (/reorder)   │
│  - Recebe array de IDs ordenados │
│  - Atribui position = index + 1  │
│  - Persiste com flush()          │
│  - Retorna {ok: true}            │
└──────────────┬──────────────────┘
               │
               ▼
┌─────────────────────────────────┐
│  Banco de dados                  │
│  campo `position` atualizado     │
│  para cada registro              │
└─────────────────────────────────┘
```

---

## Parte 1 — Entidade

### Campo obrigatório: `position`

Todo registro que será ordenável precisa do campo `position` (inteiro):

```php
// src/Entity/MinhaEntidade.php

#[ORM\Column]
private ?int $position = 0;

public function getPosition(): ?int
{
    return $this->position;
}

public function setPosition(int $position): static
{
    $this->position = $position;
    return $this;
}
```

> **Por que `= 0` como default?** Novos registros criados sem posição definida ficam com 0 e aparecem no início. Se preferir que novos registros vão para o final, implemente lógica de `MAX(position) + 1` no controller antes de persistir.

---

## Parte 2 — Controller

### 2.1 — Listagem ordenada pelo campo `position`

O `index()` deve sempre ordenar por `position ASC`:

```php
#[Route('/', name: 'app_admin_faq_index', methods: ['GET'])]
public function index(FaqRepository $faqRepository): Response
{
    return $this->render('admin/faq/index.html.twig', [
        'faqs' => $faqRepository->findBy([], ['position' => 'ASC', 'id' => 'ASC']),
    ]);
}
```

> **Dica:** Use `'id' => 'ASC'` como segundo critério de ordenação para desempatar registros com a mesma `position` (ex: todos com `position = 0`).

### 2.2 — Endpoint de reordenação

Adicione uma rota `POST /reorder` que aceita JSON com o array de IDs na nova ordem:

```php
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route('/reorder', name: 'app_admin_faq_reorder', methods: ['POST'])]
public function reorder(
    Request $request,
    FaqRepository $repo,
    EntityManagerInterface $em
): JsonResponse {
    $ids = json_decode($request->getContent(), true)['ids'] ?? [];

    foreach ($ids as $pos => $id) {
        $item = $repo->find((int) $id);
        if ($item) {
            $item->setPosition($pos + 1);  // posição começa em 1, não 0
        }
    }

    $em->flush();
    return new JsonResponse(['ok' => true]);
}
```

**Detalhes importantes:**
- A posição começa em `1` (`$pos + 1`), não em `0`. Isso é uma convenção do projeto — `position = 0` indica "não posicionado ainda".
- O `$em->flush()` é chamado **uma única vez** após o loop, não dentro dele (evita N+1 queries de flush).
- O endpoint retorna `{'ok': true}` em caso de sucesso. O JavaScript usa isso para exibir o toast de confirmação.
- Não é necessário CSRF token nesta rota (é consumida internamente pelo JS, não por um form POST de browser).

### 2.3 — Convenção de nomenclatura das rotas

Siga sempre o padrão:
```
app_admin_{entidade}_reorder    →  POST /admin/{entidade}/reorder
```

---

## Parte 3 — Template HTML

### 3.1 — Estrutura obrigatória da tabela

A tabela precisa de:
1. `id` no `<tbody>` — identificador único para o SortableJS se ancorar
2. `data-id="{{ item.id }}"` em cada `<tr>` — o JS coleta estes valores
3. Classe `sortable-row` no `<tr>` — o JS filtra as linhas pelo selector
4. Ícone de "grip" na primeira coluna — feedback visual de que é arrastável

```twig
{# Tabela — o id no tbody é o ponto de ancoragem do SortableJS #}
<table class="min-w-full text-left text-sm">
    <thead>
        <tr>
            <th class="py-3 pr-2 w-10"></th>  {# coluna do grip, sem label #}
            <th class="py-3 pr-4 w-8 text-center">Pos.</th>
            <th class="py-3 pr-4">Pergunta</th>
            {# ... outras colunas #}
        </tr>
    </thead>

    {# O id aqui é o que será passado para o _sortable_script.html.twig #}
    <tbody id="faq-sortable">

        {% for faq in faqs %}
            {# data-id é OBRIGATÓRIO — é o que o JS envia para o controller #}
            <tr class="sortable-row border-b ..." data-id="{{ faq.id }}" style="cursor: grab;">

                {# Primeira coluna: grip (handle de arrastar) #}
                <td class="py-3 pr-2 text-slate-300 select-none">
                    <sl-icon name="grip-vertical" style="font-size:1.2rem"></sl-icon>
                </td>

                {# Badge de posição — atualizado automaticamente pelo JS após drag #}
                <td class="py-3 pr-4 text-center">
                    <span class="... ordem-badge">
                        {{ faq.position ?? loop.index }}
                    </span>
                </td>

                {# ... demais colunas ... #}
            </tr>
        {% else %}
            <tr>
                <td colspan="6" class="py-8 text-center text-slate-400">Nenhum registro encontrado.</td>
            </tr>
        {% endfor %}
    </tbody>
</table>
```

> **Sobre `ordem-badge`:** a classe `ordem-badge` não tem estilo — é apenas um selector JS para atualizar o número visualmente após o drag, sem precisar recarregar a página. Pode ser qualquer seletor único que você preferir, desde que seja consistente com o que está no `_sortable_script.html.twig`.

> **Sobre `{{ faq.position ?? loop.index }}`:** usa `loop.index` como fallback para quando `position = 0` ou nulo, exibindo a posição real na lista em vez de `0`.

### 3.2 — Incluir o script SortableJS

No bloco `{% block javascripts %}`, inclua o partial passando os dois parâmetros obrigatórios:

```twig
{% block javascripts %}
{{ parent() }}
{{ include('admin/_sortable_script.html.twig', {
    tbody_id: 'faq-sortable',
    reorder_url: path('app_admin_faq_reorder')
}) }}
{% endblock %}
```

> **Atenção:** `tbody_id` deve ser **exatamente o mesmo valor** do `id` do `<tbody>` no HTML. Uma divergência (ex: `id="faq-sortable"` mas `tbody_id: 'faqs-sortable'`) faz o SortableJS não encontrar o elemento e o drag não funcionar.

---

## Parte 4 — O Partial `_sortable_script.html.twig`

Este arquivo em `templates/admin/_sortable_script.html.twig` é reutilizável em qualquer listagem. Ele:

1. Carrega o SortableJS via CDN
2. Localiza o `<tbody>` pelo `tbody_id`
3. Inicializa o `Sortable.create()` com `handle` na primeira célula
4. No `onEnd`, coleta os `data-id` das `<tr class="sortable-row">` em ordem
5. Faz `fetch()` POST JSON para `reorder_url`
6. Atualiza os badges `.ordem-badge` visualmente (sem reload)
7. Exibe toast verde "✓ Ordem salva" por 1,5s

```twig
{#
  Parâmetros:
    tbody_id    — id do elemento <tbody> (ex: 'faq-sortable')
    reorder_url — URL para o endpoint POST de reordenação
#}
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var tbody = document.getElementById('{{ tbody_id }}');
    var REORDER_URL = '{{ reorder_url }}';

    if (!tbody || typeof Sortable === 'undefined') {
        console.warn('[sortable] tbody #{{ tbody_id }} not found or SortableJS not loaded');
        return;  // ← falha silenciosa, não quebra a página
    }

    Sortable.create(tbody, {
        handle: 'td:first-child, td:first-child *',  // grip na 1ª célula
        animation: 150,
        ghostClass: 'opacity-40',
        onEnd: saveOrder
    });

    function saveOrder() {
        var rows = tbody.querySelectorAll('tr.sortable-row');
        var ids = Array.from(rows).map(function(r) {
            return parseInt(r.dataset.id, 10);
        });

        // Feedback visual imediato
        rows.forEach(function(r, i) {
            var badge = r.querySelector('.ordem-badge');
            if (badge) badge.textContent = i + 1;
        });

        fetch(REORDER_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ ids: ids })
        })
        .then(function(r) { return r.json(); })
        .then(function() {
            // Toast de confirmação
            var flash = document.createElement('div');
            flash.textContent = '✓ Ordem salva';
            flash.style.cssText = 'position:fixed;bottom:1.5rem;right:1.5rem;...';
            document.body.appendChild(flash);
            setTimeout(function() {
                flash.style.opacity = '0';
                setTimeout(function() { flash.remove(); }, 500);
            }, 1500);
        })
        .catch(function(err) { console.error('[sortable] reorder failed', err); });
    }
});
</script>
```

---

## Parte 5 — Armadilhas Documentadas (Erros e Acertos)

Esta seção documenta os problemas reais encontrados durante a implementação no projeto.

### ❌ Erro 1 — `tbody_id` não corresponde ao `id` do elemento HTML

**Sintoma:** Arrasta a linha mas nada acontece. Console mostra:
```
[sortable] tbody #nome-errado not found or SortableJS not loaded
```

**Causa:** O `tbody_id` passado no `include()` é diferente do atributo `id` no `<tbody>`.

**Exemplo errado:**
```twig
{# Template — id="faq-sortable" #}
<tbody id="faq-sortable">

{# Javascripts — tbody_id: 'faqs-sortable' (plural com s) #}
{{ include('admin/_sortable_script.html.twig', {
    tbody_id: 'faqs-sortable',   ← ERRADO
    reorder_url: path('...')
}) }}
```

**Correção:** Os dois valores devem ser idênticos.

---

### ❌ Erro 2 — Ativar o sortable em tabelas filtradas (sem `id` no tbody)

**Sintoma:** O sortable funciona em uma aba mas não na outra.

**Causa:** No caso das `SchoolSection`, a tabela só recebe `id="school-section-sortable"` quando há um filtro de segmento ativo (`current_sector`). Sem o filtro, a tabela não tem `id` e o SortableJS não consegue se ancorar.

**Código correto:**
```twig
{# Só coloca o id quando tem filtro ativo #}
<table class="sortable-table min-w-full text-left text-sm"
       {% if current_sector is defined and current_sector %}
       id="school-section-sortable"
       {% endif %}>
```

**Por que?** Reordenar sessões de **segmentos diferentes** juntas não faz sentido — a ordenação é por segmento. Sem filtro, a tabela mostra sessões de segmentos misturados e o sortable seria confuso. É correto não ativar o drag nessa situação.

**Regra:** Se a listagem pode ser filtrada e a ordenação só faz sentido dentro de um contexto, o sortable deve ser ativado **somente** quando esse contexto está definido.

---

### ❌ Erro 3 — Falta de `data-id` na `<tr>`

**Sintoma:** Após drag, o array enviado para `/reorder` contém `NaN` ou `0`.

**Causa:** O `data-id` foi omitido ou escrito errado no `<tr>`.

**Exemplo errado:**
```twig
<tr class="sortable-row" data-item-id="{{ faq.id }}">  ← ERRADO (data-item-id, não data-id)
```

**Código correto:**
```twig
<tr class="sortable-row border-b ..." data-id="{{ faq.id }}">
```

O JS lê especificamente `r.dataset.id` — qualquer outro nome de atributo resultará em `undefined` / `NaN`.

---

### ❌ Erro 4 — Classe `sortable-row` faltando nas linhas

**Sintoma:** O drag funciona (o SortableJS foi iniciado) mas o array de IDs vem vazio ou com apenas um elemento.

**Causa:** O `querySelectorAll('tr.sortable-row')` no JS não encontra as linhas porque a classe `sortable-row` está ausente.

**Código correto:**
```twig
<tr class="sortable-row ..." data-id="{{ faq.id }}">  ← sortable-row OBRIGATÓRIO
```

---

### ❌ Erro 5 — Rota `/reorder` conflitando com rota `/{id}`

**Sintoma:** Ao acessar `/admin/faq/reorder`, Symfony lança erro 404 ou tenta encontrar um FAQ com `id = "reorder"`.

**Causa:** A rota `/{id}` é muito genérica e captura `/reorder` antes que a rota específica de `/reorder` seja testada.

**Solução:** A rota `/reorder` deve ser declarada **antes** da rota `/{id}` no controller (Symfony avalia rotas na ordem de declaração dentro de um mesmo prefix):

```php
// ✅ CORRETO — /reorder declarada ANTES de /{id}
#[Route('/reorder', name: 'app_admin_faq_reorder', methods: ['POST'])]
public function reorder(...): JsonResponse { ... }

#[Route('/{id}', name: 'app_admin_faq_delete', methods: ['POST'])]
public function delete(...): Response { ... }
```

Alternativamente, adicione um requirement à rota `/{id}`:
```php
#[Route('/{id}', requirements: ['id' => '\d+'], methods: ['POST'])]
```

---

### ❌ Erro 6 — `flush()` dentro do loop (problema de performance)

**Sintoma:** Em listas grandes, o reorder trava ou demora.

**Causa:** Chamar `$em->flush()` dentro do `foreach` dispara uma query UPDATE por registro.

**Exemplo errado:**
```php
foreach ($ids as $pos => $id) {
    $item = $repo->find($id);
    if ($item) {
        $item->setPosition($pos + 1);
        $em->flush();  // ← ERRADO: flush por registro
    }
}
```

**Correto:**
```php
foreach ($ids as $pos => $id) {
    $item = $repo->find($id);
    if ($item) {
        $item->setPosition($pos + 1);  // só marca como dirty
    }
}
$em->flush();  // ← um único flush com batch de UPDATEs
```

---

### ⚠️ Aviso — O drag não persiste ao recarregar a página sem flush

**Contexto:** O toast "✓ Ordem salva" aparece após o fetch resolver. Se o servidor retornar erro 500 (sem `ok: true`), o toast não aparece e o `.catch()` é acionado.

**Como diagnosticar:** Abra o DevTools → Network → filtre por XHR → arraste uma linha. Veja a request para `/reorder` e a response. Se for 500, o problema está no controller (entity não encontrada, campo sem setter, etc.).

---

### ⚠️ Aviso — SortableJS via CDN (sem versionamento local)

O script usa:
```html
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
```

Isso depende de conexão com internet e da disponibilidade do CDN. Em ambiente sem internet (desenvolvimento offline), o drag não funcionará. Para ambientes críticos, baixe o arquivo localmente e use o AssetMapper do Symfony.

---

## Parte 6 — Checklist de Implementação

Use este checklist ao adicionar drag-and-drop a uma nova entidade:

### Na entidade
- [ ] Campo `position INT DEFAULT 0` adicionado
- [ ] Getter `getPosition()` e setter `setPosition()` implementados
- [ ] Migração criada e executada

### No controller
- [ ] `index()` usa `findBy([], ['position' => 'ASC'])`
- [ ] Rota `reorder` declarada com `methods: ['POST']`
- [ ] Rota `reorder` declarada **antes** de qualquer rota `/{id}` com POST
- [ ] `flush()` chamado **uma vez** fora do loop
- [ ] Retorna `new JsonResponse(['ok' => true])`

### No template (`index.html.twig`)
- [ ] `<tbody id="nome-unico-sortable">` — id único na página
- [ ] Cada `<tr>` tem `class="sortable-row"` e `data-id="{{ item.id }}"`
- [ ] Primeira `<td>` contém o ícone `grip-vertical` (handle)
- [ ] Badge de posição tem classe `ordem-badge`
- [ ] Bloco `{% block javascripts %}` inclui o partial com os dois parâmetros corretos
- [ ] `tbody_id` no `include()` é **idêntico** ao `id` do `<tbody>`

---

## Parte 7 — Guia de Implementação Genérico

Para adicionar a ordenação em uma nova listagem em outro projeto Symfony com este padrão:

### Passo 1 — Entidade

```php
// src/Entity/MeuItem.php
#[ORM\Column]
private ?int $position = 0;

public function getPosition(): ?int { return $this->position; }
public function setPosition(int $position): static {
    $this->position = $position;
    return $this;
}
```

```bash
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

### Passo 2 — Controller

```php
// src/Controller/Admin/MeuItemController.php
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route('/admin/meu-item')]
final class MeuItemController extends AbstractController
{
    #[Route('/', name: 'app_admin_meu_item_index', methods: ['GET'])]
    public function index(MeuItemRepository $repo): Response
    {
        return $this->render('admin/meu_item/index.html.twig', [
            'items' => $repo->findBy([], ['position' => 'ASC', 'id' => 'ASC']),
        ]);
    }

    // Declare ANTES de qualquer /{id} com POST
    #[Route('/reorder', name: 'app_admin_meu_item_reorder', methods: ['POST'])]
    public function reorder(Request $request, MeuItemRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $ids = json_decode($request->getContent(), true)['ids'] ?? [];
        foreach ($ids as $pos => $id) {
            $item = $repo->find((int) $id);
            if ($item) {
                $item->setPosition($pos + 1);
            }
        }
        $em->flush();
        return new JsonResponse(['ok' => true]);
    }

    #[Route('/{id}', name: 'app_admin_meu_item_delete', methods: ['POST'])]
    public function delete(...): Response { ... }
}
```

### Passo 3 — Template

```twig
{# templates/admin/meu_item/index.html.twig #}
{% extends 'admin/base.html.twig' %}

{% block body %}
<sl-card>
    <table class="min-w-full text-left text-sm">
        <thead>
            <tr>
                <th class="py-3 pr-2 w-10"></th>        {# handle #}
                <th class="py-3 pr-4 w-8 text-center">Pos.</th>
                <th class="py-3 pr-4">Nome</th>
                <th class="py-3 pr-4 text-right">Ações</th>
            </tr>
        </thead>

        {# id deve ser único na página — use "nome-entidade-sortable" #}
        <tbody id="meu-item-sortable">
            {% for item in items %}
                {# class="sortable-row" e data-id OBRIGATÓRIOS #}
                <tr class="sortable-row border-b ..." data-id="{{ item.id }}" style="cursor:grab">

                    {# Handle #}
                    <td class="py-3 pr-2 text-slate-300 select-none">
                        <sl-icon name="grip-vertical" style="font-size:1.2rem"></sl-icon>
                    </td>

                    {# Badge de posição com class="ordem-badge" #}
                    <td class="py-3 pr-4 text-center">
                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-slate-100 text-xs font-bold ordem-badge">
                            {{ item.position ?? loop.index }}
                        </span>
                    </td>

                    <td class="py-3 pr-4">{{ item.name }}</td>

                    <td class="py-3 pr-4 text-right">
                        <sl-button size="small" variant="primary" outline
                            href="{{ path('app_admin_meu_item_edit', {id: item.id}) }}">
                            Editar
                        </sl-button>
                    </td>
                </tr>
            {% else %}
                <tr>
                    <td colspan="4" class="py-8 text-center text-slate-400">Nenhum item cadastrado.</td>
                </tr>
            {% endfor %}
        </tbody>
    </table>
</sl-card>
{% endblock %}

{% block javascripts %}
{{ parent() }}
{# tbody_id DEVE ser idêntico ao id="..." do <tbody> acima #}
{{ include('admin/_sortable_script.html.twig', {
    tbody_id: 'meu-item-sortable',
    reorder_url: path('app_admin_meu_item_reorder')
}) }}
{% endblock %}
```

### Passo 4 — Copiar o partial (se for um projeto novo)

Se o arquivo `templates/admin/_sortable_script.html.twig` não existir ainda no projeto, copie-o deste projeto ou crie com o conteúdo descrito na Parte 4 deste documento.

---

## Parte 8 — Exemplos de Uso no Projeto

| Módulo                  | `tbody_id`                        | Rota de reorder                             |
|-------------------------|-----------------------------------|---------------------------------------------|
| FAQ                     | `faq-sortable`                    | `app_admin_faq_reorder`                     |
| Sessões de Conteúdo     | `school-section-sortable`         | `app_admin_school_section_reorder`          |
| Blocos de Sessão        | `school-section-block-sortable`   | `app_admin_school_section_block_reorder`    |
| Diferenciais            | (padrão similar)                  | `app_admin_differential_reorder`            |
| Banners                 | (padrão similar)                  | `app_admin_banner_reorder`                  |
| Fotos da Estrutura      | (padrão similar)                  | `app_admin_structure_photo_reorder`         |

Todos seguem exatamente o mesmo padrão — basta substituir os nomes.
