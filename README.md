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

A API permite que aplicações externas (como buscadores e aplicativos bíblicos) consultem os conteúdos produzidos pelos tenants da plataforma.

#### Endpoints

| Método | Rota | Descrição |
|---|---|---|
| `GET` | `/api/biblia/contents` | Consulta conteúdos associados a um capítulo, versículo ou perícope com URLs multi-tenant |
| `GET` | `/api/biblia/passage` | Retorna o texto bíblico ARC formatado de um trecho |

---

#### Parâmetros da Rota `/api/biblia/contents`

| Parâmetro | Tipo | Obrigatório? | Descrição | Exemplos |
|---|---|:---:|---|---|
| `book` | string / int | **Sim** | ID, sigla (abbrev) ou nome do livro bíblico | `jo`, `joao`, `43`, `gn`, `genesis` |
| `chapter` | int | **Sim** | Número do capítulo | `3`, `1`, `14` |
| `verse_start` / `verse` | int | Não | Versículo inicial. Se omitido, retorna **todos os conteúdos do capítulo inteiro**. | `16`, `1` |
| `verse_end` | int | Não | Versículo final da perícope. Se omitido, assume o valor de `verse_start`. | `17`, `21` |
| `type` | string | Não | Filtra pelo tipo de conteúdo: `study` (ou `material`), `article` (ou `noticia`), `video`, `page` | `study`, `material`, `video`, `article` |
| `tenant` | string / int | Não | Filtra conteúdos por domínio ou ID do tenant. Se omitido, busca em **todos os tenants**. | `renovando.nepe.org.br`, `3` |

---

#### Exemplos de Uso da API

##### Exemplo 1: Buscar todos os Materiais/Estudos de um Capítulo Inteiro
> **Objetivo**: Recuperar todos os materiais e estudos cadastrados no capítulo **3 de João**:

```bash
# cURL
curl -X GET "https://seudominio.com.br/api/biblia/contents?book=jo&chapter=3&type=study"
```

**Resposta JSON:**
```json
{
  "status": "success",
  "query": {
    "book_id": 43,
    "book_name": "João",
    "book_abbreviation": "jo",
    "chapter": 3,
    "verse_start": null,
    "verse_end": null,
    "reference_formatted": "João 3 (Capítulo inteiro)",
    "type_filter": "study",
    "tenant_filter": null
  },
  "total": 1,
  "results": [
    {
      "id": 12,
      "type": "study",
      "type_label": "Material / Estudo",
      "title": "Estudo Detalhado sobre o Novo Nascimento",
      "slug": "estudo-novo-nascimento",
      "description": "Análise teológica e histórica do diálogo com Nicodemos.",
      "url": "https://tenant-a.nepe.org.br/estudo/estudo-novo-nascimento",
      "image_url": "https://tenant-a.nepe.org.br/uploads/study/capa-nicodemos.jpg",
      "tenant": {
        "id": 2,
        "name": "NEPE São Paulo",
        "domain": "tenant-a.nepe.org.br",
        "logo_url": "https://tenant-a.nepe.org.br/uploads/tenant/logo.png",
        "primary_color": "#1a56db"
      },
      "biblical_reference": {
        "book_id": 43,
        "book_name": "João",
        "book_abbreviation": "jo",
        "chapter": 3,
        "verse_start": 1,
        "verse_end": 21,
        "formatted": "João 3:1-21"
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

