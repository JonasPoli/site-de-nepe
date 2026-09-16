# Symfony UX -- Agent Skills

![Trigger warning animals](https://repository-images.githubusercontent.com/1171056549/6dee3166-2b48-449b-ad26-667ee821ff35)

AI agent skills for the [Symfony UX](https://ux.symfony.com) frontend stack -- Stimulus, Turbo, TwigComponent, LiveComponent, UX Icons and UX Map.

By [Simon Andre](https://github.com/smnandre)

## Skills

| Skill | What it does | When the agent activates it | Refs |
|---|---|---|:---:|
| **[symfony-ux](skills/symfony-ux/)** | Orchestrator / decision tree | The developer asks "which UX tool should I use?" or a question that spans multiple packages | -- |
| **[stimulus](skills/stimulus/)** | Stimulus controllers, targets, values, actions, outlets | Client-side JS behavior -- toggles, dropdowns, modals, wrapping a JS library | api, patterns, gotchas |
| **[turbo](skills/turbo/)** | Turbo Drive, Frames, Streams, Mercure | Partial page updates, SPA-like nav, real-time server pushes -- no JS to write | api, patterns, gotchas |
| **[twig-component](skills/twig-component/)** | TwigComponent props, blocks, computed properties, anonymous components | Reusable UI building blocks -- buttons, cards, alerts, design system | api, patterns, gotchas |
| **[live-component](skills/live-component/)** | LiveComponent props, actions, data-model, forms, emit, defer/lazy | Reactive server-rendered UI -- live search, validation, dependent selects | api, patterns, gotchas |
| **[ux-icons](skills/ux-icons/)** | SVG icons via Iconify, local files, aliases, CLI | Rendering icons in Twig -- Lucide, Heroicons, Tabler, Material Design, etc. | api, patterns, gotchas |
| **[ux-map](skills/ux-map/)** | Interactive maps with Leaflet / Google Maps | Maps with markers, polygons, polylines, circles, events, LiveComponent integration | api, patterns, gotchas |

**Upstream packages:**
[symfony/stimulus-bundle](https://packagist.org/packages/symfony/stimulus-bundle) --
[symfony/ux-turbo](https://packagist.org/packages/symfony/ux-turbo) --
[symfony/ux-twig-component](https://packagist.org/packages/symfony/ux-twig-component) --
[symfony/ux-live-component](https://packagist.org/packages/symfony/ux-live-component) --
[symfony/ux-icons](https://packagist.org/packages/symfony/ux-icons) --
[symfony/ux-map](https://packagist.org/packages/symfony/ux-map)

## Installation

### Claude Code Plugin

This repository is installable as a [Claude Code plugin](https://docs.claude.com/en/docs/claude-code/plugins). Skills are automatically discovered and namespaced under `symfony-ux:`.

```bash
# Test locally
claude --plugin-dir /path/to/symfony-ux-skills

# Or install from a marketplace (if available)
claude plugin install symfony-ux
```

### Vercel's Skills CLI

```bash
npx skills add smnandre/symfony-ux-skills
```

### Manual installation

Copy each skill directory into your platform's skills location:

```bash
# Claude Code (project-level, shared via git)
mkdir -p .claude/skills && cp -r skills/* .claude/skills/

# Claude Code (user-level, available everywhere)
cp -r skills/* ~/.claude/skills/

# Gemini CLI
mkdir -p ~/.gemini/skills && cp -r skills/* ~/.gemini/skills/

# OpenAI Codex
mkdir -p .codex/skills && cp -r skills/* .codex/skills/
```

Then optionally copy the context file for your platform to your project root:

```bash
cp CLAUDE.md /path/to/project/   # Claude Code
cp AGENTS.md /path/to/project/   # OpenAI Codex
cp GEMINI.md /path/to/project/   # Gemini CLI
```

## How it works

Agent skills are structured knowledge files that teach AI coding agents *how* to use a library. Instead of relying on training data (which may be outdated or incomplete), the agent reads the skill at runtime and gets accurate, version-specific guidance: API references, common patterns, and known gotchas.

Each skill follows a **progressive disclosure** pattern:

1. **Description** (YAML frontmatter) -- always loaded; tells the agent *when* to activate the skill (~100 words)
2. **SKILL.md body** -- loaded on activation; quick-reference with the most important rules and examples
3. **references/** -- loaded on demand; deep API docs, advanced patterns, and common pitfalls

This means the agent only pulls in what it needs, keeping context windows lean.

Built on the [Agent Skills](https://agentskills.io/specification) open standard. Compatible with **Claude Code**, **Gemini CLI**, **OpenAI Codex**, **Cursor**, **Windsurf**, and any platform that supports `SKILL.md`.

## Project context files

Optional files for your project root. They give the agent a quick decision tree and key rules so it knows which skill to reach for.

| File | Platform |
|---|---|
| `CLAUDE.md` | Claude Code |
| `AGENTS.md` | OpenAI Codex |
| `GEMINI.md` | Gemini CLI |
| `llms.txt` | Web / any LLM ([llmstxt.org](https://llmstxt.org)) |

## Repository structure

```
.
├── CLAUDE.md                   # Context file for Claude Code
├── AGENTS.md                   # Context file for OpenAI Codex
├── GEMINI.md                   # Context file for Gemini CLI
├── llms.txt                    # Context file for web / LLMs
├── .claude-plugin/
│   └── plugin.json             # Claude Code plugin manifest
├── gemini-extension.json       # Gemini CLI extension manifest
└── skills/
    ├── symfony-ux/
    │   └── SKILL.md
    ├── stimulus/
    │   ├── SKILL.md
    │   └── references/
    │       ├── api.md
    │       ├── patterns.md
    │       └── gotchas.md
    ├── turbo/
    │   ├── SKILL.md
    │   └── references/
    │       ├── api.md
    │       ├── patterns.md
    │       └── gotchas.md
    ├── twig-component/
    │   ├── SKILL.md
    │   └── references/
    │       ├── api.md
    │       ├── patterns.md
    │       └── gotchas.md
    ├── live-component/
    │   ├── SKILL.md
    │   └── references/
    │       ├── api.md
    │       ├── patterns.md
    │       └── gotchas.md
    ├── ux-icons/
    │   ├── SKILL.md
    │   └── references/
    │       ├── api.md
    │       ├── patterns.md
    │       └── gotchas.md
    └── ux-map/
        ├── SKILL.md
        └── references/
            ├── api.md
            ├── patterns.md
            └── gotchas.md
```

## Coverage

Targets **Symfony UX 2.22 -- 2.28+**, Symfony 7.2 / 7.4 / 8.0, PHP 8.4+.

Documented features include `<twig:Turbo:Stream:*>` component syntax (UX 2.22), `TurboStreamResponse` helper, LiveProp URL binding with validation modifiers, `defer` / `lazy` loading for LiveComponents, UX Toolkit (copy-paste UI components), Iconify on-demand icons with `ux:icons:lock` CLI, and UX Map with Leaflet/Google Maps renderers including polygons, polylines, circles and `ComponentWithMapTrait`.

## Sistema de Indexação Bíblica & API Multi-Tenant

Este sistema conta com integração nativa com o texto bíblico na versão **Almeida Revista e Corrigida (ARC)**, permitindo indexar e relacionar Artigos, Vídeos, Materiais/Estudos e Páginas a versículos ou perícopes completas.

### 1. Importação da Base Bíblica
Os dados bíblicos (66 livros, 31.414 metadados de versículos e 31.106 versículos ARC) estão compactados no repositório em `data/biblia_arc.json.gz` (~1.8 MB). Para importar ou atualizar:

```bash
php bin/console app:biblia:import
```
> O comando é 100% idempotente (`ON DUPLICATE KEY UPDATE`) e executa em ~1 segundo.

---

### 2. Associação no Painel Administrativo
Ao criar ou editar qualquer **Artigo**, **Vídeo**, **Material/Estudo** ou **Página**, o painel administrativo disponibiliza um componente de seleção em cascata:
1. **Ativar Associação**: Marque a opção *"Associar versículo ou perícope a este conteúdo"*.
2. **Seleção em Cascata**:
   - **Testamento** (Antigo / Novo Testamento)
   - **Livro** (com busca/filtro inteligente por nome ou sigla)
   - **Capítulo** (carrega a quantidade real de capítulos)
   - **Versículo Inicial**
   - **Versículo Final** (opcional, para definir perícope/intervalo)
3. **Pré-visualização Dinâmica**: Exibe em tempo real o texto sagrado ARC formatado com números e títulos.

---

### 3. API Pública Multi-Tenant (`/api/biblia`)

A API permite que aplicações externas (como o **nepe-search**, buscadores e aplicativos bíblicos) consultem os conteúdos produzidos pelos tenants da plataforma.

> **Só conteúdo publicado.** Artigos, vídeos e materiais/estudos passam pelo fluxo de aprovação do tenant (rascunho → aguardando aprovação → publicado) e só aparecem na API — e no site — depois de receberem o número de aprovações definido em *Aprovações necessárias* do tenant. As aprovações precisam vir de outros membros do mesmo tenant: o autor não aprova o próprio conteúdo, e alterar o texto, a mídia, os arquivos anexados ou a referência bíblica de um conteúdo aprovado faz ele voltar para rascunho. Páginas institucionais não passam pelo fluxo.

#### Endpoints

| Método | Rota | Descrição |
|---|---|---|
| `GET` | `/api/biblia/contents` | Consulta conteúdos publicados associados a um capítulo, versículo ou grupo de versículos, com URLs multi-tenant, texto do conteúdo e texto bíblico referenciado |
| `GET` | `/api/biblia/passage` | Retorna o texto bíblico ARC formatado de um trecho, com os dados `ext` de cada versículo |

---

#### Parâmetros da Rota `/api/biblia/contents`

| Parâmetro | Tipo | Obrigatório? | Descrição | Exemplos |
|---|---|:---:|---|---|
| `book` / `livro` | string / int | **Sim** | ID, sigla (abbrev) ou nome do livro bíblico. Os IDs são os mesmos do nepe-search. | `jo`, `joao`, `43`, `gn`, `genesis` |
| `chapter` | int | **Sim** | Número do capítulo | `3`, `1`, `14` |
| `verse_start` / `verse` | int | Não | Versículo inicial. Se omitido, retorna **todos os conteúdos do capítulo inteiro**. | `16`, `1` |
| `verse_end` / `verse2` | int | Não | Versículo final do grupo de versículos. Se omitido, busca só o versículo inicial. | `17`, `21` |
| `type` | string | Não | Filtra pelo tipo de conteúdo: `study` (ou `material`), `article` (ou `noticia`), `video`, `page` | `study`, `material`, `video`, `article` |
| `tenant` | string / int | Não | Filtra conteúdos por domínio ou ID do tenant. Se omitido, busca em **todos os tenants**. | `renovando.nepe.org.br`, `3` |

**Como os trechos são comparados:** um conteúdo é devolvido quando o trecho associado a ele **se sobrepõe** ao trecho pesquisado. Buscar `Lucas 10:30` encontra um estudo associado a `Lucas 10:25-37`, e buscar `Lucas 10:25-37` encontra os conteúdos de qualquer versículo desse intervalo.

#### Campos de cada resultado

| Campo | Descrição |
|---|---|
| `id`, `type`, `type_label` | Identificação e tipo: `article`, `video`, `study` ou `page` |
| `title`, `slug`, `url` | Título e URL completa do conteúdo no tenant que o publicou |
| `description` | Resumo (artigos: resumo; estudos e vídeos: descrição; páginas: descrição SEO) |
| `text` | Texto completo em HTML (artigos: conteúdo; estudos e vídeos: descrição; páginas: `null`) |
| `materials_html` | Bloco "Materiais e Referências" em HTML (estudos e vídeos) |
| `image_url` | URL completa da imagem: capa do estudo, imagem do artigo ou thumb do vídeo |
| `files` | Arquivos para download (estudos e vídeos): `label`, `extension`, `url` |
| `video` | Só vídeos: `youtube_id`, `embed_url`, `thumbnail_url`, `has_custom_thumbnail` |
| `tenant` | `id`, `name`, `domain`, `logo_url`, `primary_color` |
| `author` | `name` do autor, ou `null` |
| `approved_by` | Membros que aprovaram a publicação: `name` e `approved_at` (ISO 8601). Vazio em páginas institucionais |
| `category` | `id`, `name`, `slug`, ou `null` |
| `biblical_reference` | `book_id`, `book_name`, `book_abbreviation`, `chapter`, `verse_start`, `verse_end`, `formatted` |
| `passage` | Texto ARC dos versículos referenciados: `book`, `chapter`, `verse_start`, `verse_end`, `reference_formatted`, `version` e `verses[]` (`id`, `verse`, `text`, `subject`, `ext`) |
| `passage.verses[].ext` | Metadados do versículo (tabela `biblia_verse_ext`, mesmos IDs do nepe-search): `id`, `book_id`, `chapter`, `verse`, `year`, `year_description`, `place`, `translated` |
| `published_at`, `created_at` | Datas em ISO 8601 |

---

#### Exemplos de Uso da API

##### Exemplo 1: Buscar todos os Materiais/Estudos de um Capítulo Inteiro
> **Objetivo**: Recuperar todos os materiais e estudos publicados no capítulo **10 de Lucas**:

```bash
# cURL
curl -X GET "https://seudominio.com.br/api/biblia/contents?book=lc&chapter=10&type=study"
```

**Resposta JSON** (versículos resumidos):
```json
{
  "status": "success",
  "query": {
    "book_id": 42,
    "book_name": "Lucas",
    "book_abbreviation": "lc",
    "chapter": 10,
    "verse_start": null,
    "verse_end": null,
    "reference_formatted": "Lucas 10 (Capítulo inteiro)",
    "type_filter": "study",
    "tenant_filter": null
  },
  "total": 1,
  "results": [
    {
      "id": 12,
      "type": "study",
      "type_label": "Material / Estudo",
      "title": "O bom samaritano",
      "slug": "o-bom-samaritano",
      "description": "<p>Quem é o meu próximo?</p>",
      "text": "<p>Quem é o meu próximo?</p>",
      "materials_html": "<ul><li><a href=\"https://...\">Leitura complementar</a></li></ul>",
      "url": "https://renovandoconsciencias.nepebrasil.org/estudo/o-bom-samaritano",
      "image_url": "https://renovandoconsciencias.nepebrasil.org/uploads/study_cover/o-bom-samaritano.jpg",
      "files": [
        { "label": "Roteiro do estudo", "extension": "pdf", "url": "https://renovandoconsciencias.nepebrasil.org/uploads/study_material/roteiro.pdf" }
      ],
      "tenant": {
        "id": 3,
        "name": "NEPE Renovando Consciências",
        "domain": "renovandoconsciencias.nepebrasil.org",
        "logo_url": "https://renovandoconsciencias.nepebrasil.org/uploads/tenant/logo/logo.png",
        "primary_color": "#1a56db"
      },
      "author": { "name": "Maria Souza" },
      "approved_by": [
        { "name": "João Lima", "approved_at": "2026-09-10T14:32:00-03:00" },
        { "name": "Ana Prado", "approved_at": "2026-09-11T09:05:00-03:00" }
      ],
      "category": { "id": 4, "name": "Parábolas", "slug": "parabolas" },
      "biblical_reference": {
        "book_id": 42,
        "book_name": "Lucas",
        "book_abbreviation": "lc",
        "chapter": 10,
        "verse_start": 25,
        "verse_end": 37,
        "formatted": "Lucas 10:25-37"
      },
      "passage": {
        "book": { "id": 42, "name": "Lucas", "abbrev": "lc" },
        "chapter": 10,
        "verse_start": 25,
        "verse_end": 37,
        "reference_formatted": "Lucas 10:25-37",
        "version": { "id": 2, "name": "Almeida Revista e Corrigida", "abbrev": "ARC" },
        "verses": [
          {
            "id": 25321,
            "verse": 25,
            "text": "E eis que se levantou um certo doutor da lei, tentando-o e dizendo: Mestre, que farei para herdar a vida eterna?",
            "subject": "O bom samaritano",
            "ext": { "id": 25321, "book_id": 42, "chapter": 10, "verse": 25, "year": 30, "year_description": "c. 30 d.C.", "place": "Judeia", "translated": 1 }
          }
        ]
      },
      "published_at": "2026-05-10T14:00:00+00:00",
      "created_at": "2026-05-10T12:30:00+00:00"
    }
  ]
}
```

##### Exemplo 2: Buscar Todos os Conteúdos de um Capítulo Inteiro
```bash
curl -X GET "https://seudominio.com.br/api/biblia/contents?book=jo&chapter=3"
```

##### Exemplo 3: Buscar Conteúdos por Perícope Específica (ex: João 3:16-17)
```bash
curl -X GET "https://seudominio.com.br/api/biblia/contents?book=jo&chapter=3&verse_start=16&verse_end=17"
```

##### Exemplo 4: Filtrar por Tenant Específico
```bash
curl -X GET "https://seudominio.com.br/api/biblia/contents?book=jo&chapter=3&tenant=renovando.nepe.org.br"
```

##### Exemplo 5: Consumindo via JavaScript / Fetch
```javascript
// Buscar todos os materiais do capítulo 3 de João
const response = await fetch('https://seudominio.com.br/api/biblia/contents?book=jo&chapter=3&type=study');
const data = await response.json();

if (data.status === 'success') {
  data.results.forEach(item => {
    console.log(`[${item.tenant.name}] ${item.title}`);
    console.log(`Acessar: ${item.url}`);
    console.log(`Trecho: ${item.biblical_reference.formatted}`);
  });
}
```

##### Exemplo 6: Consumindo via PHP
```php
$url = 'https://seudominio.com.br/api/biblia/contents?' . http_build_query([
    'book' => 'jo',
    'chapter' => 3,
    'type' => 'study'
]);

$response = file_get_contents($url);
$data = json_decode($response, true);

foreach ($data['results'] as $item) {
    echo "Título: {$item['title']}\n";
    echo "URL: {$item['url']}\n";
    echo "Tenant: {$item['tenant']['name']}\n";
}
```

---

## Author

Simon Andre -- [smnandre.dev](https://smnandre.dev) -- [GitHub](https://github.com/smnandre) -- [Twitter](https://x.com/simonandre)

## License

MIT

