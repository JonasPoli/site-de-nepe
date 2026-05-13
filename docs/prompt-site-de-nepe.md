# Projeto: Plataforma Multi-Tenant NEPE Brasil (Symfony)

Este documento especifica a arquitetura e os requisitos para a criação de um sistema de gerenciamento de conteúdo (CMS) multi-tenant em Symfony. O sistema atenderá múltiplos subdomínios (tenants), todos rodando sob a mesma base de código e o mesmo banco de dados.

## 1. Arquitetura Multi-Tenant e Isolamento de Dados

O sistema atende vários domínios (ex: `renovandoconsciencia.nepebrasil.org`, `127.0.0.1`). 
A separação dos dados ocorre de forma automática.

### 1.1. Identificação do Tenant (Event Subscriber)
Crie um `TenantSubscriber` que escuta o evento `kernel.request`.
O subscriber deve capturar o host da requisição HTTP (`$request->getHost()`).
Ele busca no banco de dados a entidade `Tenant` que possui este domínio.
Se o domínio existir, o subscriber injeta este objeto `Tenant` em um serviço global chamado `TenantContext`. Se não existir, retorna erro 404 ou redireciona para uma página padrão.

### 1.2. Isolamento de Dados (Doctrine SQL Filter)
Crie um filtro SQL do Doctrine chamado `TenantFilter`.
Este filtro adiciona automaticamente a condição `tenant_id = X` em todas as consultas SELECT, UPDATE e DELETE das entidades que implementam a interface `TenantAwareInterface`.
O `TenantSubscriber` deve habilitar este filtro e passar o ID do tenant atual logo após identificar o domínio. Isso garante que um admin nunca veja dados de outro domínio.

## 2. Especificação das Entidades (Banco de Dados)

Todas as entidades de conteúdo devem possuir uma relação ManyToOne com a entidade `Tenant` e implementar a `TenantAwareInterface`.

### 2.1. Entidades Globais
* **User**: `id`, `email`, `password`, `roles` (JSON). Pode ter um campo `tenant` (ManyToOne, nullable). Se for nulo, é SuperAdmin. Se tiver valor, é Admin daquele domínio específico.
* **Tenant**: `id`, `domain` (único, ex: `127.0.0.1` ou `portaldaluz.nepebrasil.org`), `name`, `logo` (imagem), `primaryColor` (hexadecimal), `secondaryColor` (hexadecimal), `contactEmail` (para receber os contatos do site), `youtubeLink`, `instagramLink`.

### 2.2. Entidades de Conteúdo (TenantAware)
* **HeroBanner**: `id`, `tenant`, `title`, `subtitle`, `ctaText`, `ctaLink`, `backgroundImage`. (Exibe no topo da Home).
* **ResearchLine**: `id`, `tenant`, `title`, `description`, `icon` (string para classe FontAwesome), `position` (inteiro). (Exibe as linhas de pesquisa na Home).
* **Category**: `id`, `tenant`, `name`, `slug`. (Usada para artigos e vídeos).
* **Article (Notícias/Blog)**: `id`, `tenant`, `category`, `title`, `slug`, `shortDescription`, `content` (HTML, editor rico), `image` (destaque), `publishedAt`, `seoTitle`, `seoDescription`, `imageAlt`, `canonicalUrl`, `isNoIndex`.
* **VideoSupport**: `id`, `tenant`, `category`, `title`, `slug`, `youtubeId` (código do vídeo, ex: dQw4w9WgXcQ), `description` (HTML), `materialsHtml` (HTML com links para download de PDF, etc.), `createdAt`.
* **ContactMessage**: `id`, `tenant`, `senderName`, `senderEmail`, `message`, `createdAt`, `isRead` (booleano).
* **NewsletterSubscriber**: `id`, `tenant`, `name`, `email`, `subscribedAt`.

### 2.3. Entidades do Sistema de Páginas Acessórias
O sistema de páginas funciona com páginas que contêm sessões e sessões que contêm blocos em zigue-zague.

* **Page**: `id`, `tenant`, `title`, `slug`, `showInHeader` (booleano), `showInFooter` (booleano), `seoTitle`, `seoDescription`.
* **PageSection**: `id`, `page` (ManyToOne), `titlePart1` (cor secundária), `titlePart2` (cor primária), `position` (inteiro para ordenação), `active` (booleano).
* **PageBlock**: `id`, `section` (ManyToOne), `preTitle`, `title`, `text` (HTML), `image` (arquivo), `position` (inteiro para ordenação em zigue-zague).

## 3. Painéis de Administração (CRUDs)

O sistema possui dois níveis de acesso, protegidos pelas roles do Symfony.

### 3.1. Painel do SuperAdmin (`ROLE_SUPER_ADMIN`)
Acesso exclusivo a usuários sem tenant vinculado.
Pode criar, editar e excluir a entidade `Tenant`.
Pode criar os usuários administradores vinculando-os a um tenant específico.

### 3.2. Painel do Admin de Domínio (`ROLE_ADMIN`)
Acesso restrito ao tenant vinculado ao usuário logado.
Não pode ver as configurações de Tenant, apenas editar as informações básicas do seu próprio tenant (como cores e logo).
Gerencia todas as entidades de conteúdo (Banners, Categorias, Notícias, Vídeos, Páginas Acessórias, Linhas de Pesquisa).
Visualiza e gerencia a lista de contatos (`ContactMessage`) e inscritos na newsletter.

### 3.3. Sistema de Ordenação (Drag-and-Drop)
Para as listagens de `PageSection`, `PageBlock` e `ResearchLine`, implemente a ordenação por arrastar e soltar usando SortableJS.
Cada tabela deve ter um `<tbody>` com ID único e `<tr data-id="X">`.
O Javascript deve enviar um POST JSON com a nova ordem dos IDs para uma rota específica `/reorder` no controller correspondente. O controller atualiza o campo `position` de cada item e faz o flush. A listagem padrão (`index`) destas entidades deve sempre ordenar por `position ASC`.

## 4. Front-End Público (Páginas e SEO)

O front-end utiliza o `TenantContext` para carregar o logo e aplicar o esquema de cores primária e secundária via variáveis CSS nativas no layout base.

### 4.1. Estrutura da Home Page
A rota da home (`/`) deve carregar e montar a página com as seguintes seções dinâmicas:
1.  **Cabeçalho**: Logo do tenant. Menu dinâmico gerado buscando `Page` onde `showInHeader = true`. Links fixos para Notícias, Vídeos e barra de busca.
2.  **Banner de Destaque**: Busca o registro mais recente ou ativo de `HeroBanner`. Exibe título, subtítulo e o botão CTA.
3.  **Últimas Notícias**: Busca os 3 registros mais recentes de `Article`. Exibe em formato de cartões (imagem, título, data).
4.  **Vídeo em Destaque**: Busca o registro mais recente de `VideoSupport`. Incorpora o vídeo via iframe do YouTube. Exibe resumo e um botão "Acessar Materiais e Referências" que leva para a página interna deste vídeo.
5.  **Galeria de Estudos**: Busca os demais vídeos de `VideoSupport` (excluindo o mais recente). Exibe miniaturas que levam para suas respectivas páginas de suporte.
6.  **Linhas de Pesquisa**: Lista todos os registros de `ResearchLine` ordenados pela `position`.
7.  **Newsletter e Contato**: Formulário rápido para assinar newsletter. Formulário de contato que salva um registro em `ContactMessage` e envia um e-mail para o `contactEmail` do tenant.
8.  **Rodapé**: Logo, links para páginas onde `showInFooter = true`, e os ícones de redes sociais do tenant.

### 4.2. Renderização das Páginas Acessórias
A rota genérica `/pagina/{slug}` exibe a página.
Ela busca todas as `PageSection` ativas da página, ordenadas por posição.
Dentro de cada sessão, busca os `PageBlock` ordenados.
A exibição ocorre em zigue-zague, imagem na esquerda no bloco par, imagem na direita no bloco ímpar. O título da sessão possui efeito visual dividindo `titlePart1` (fundo cor secundária) e `titlePart2` (fundo cor primária).

### 4.3. Regras de SEO (Microdados e Metatags)
Aplicar as regras nas páginas de Artigos (`Article`) e Páginas Acessórias (`Page`).
Utilizar as tags dinâmicas no cabeçalho do Twig.
Substituir o `<title>` pelo campo `seoTitle` (com fallback para `title`).
Substituir o `<meta name="description">` pelo campo `seoDescription`.
Incluir `og:title`, `og:image`, `twitter:card`.
Adicionar tag genérica estruturada JSON-LD (`Schema.org/NewsArticle` para notícias, `Schema.org/WebPage` para páginas).
Incluir `<link rel="canonical">` se o campo `canonicalUrl` estiver preenchido.
Aplicar `<meta name="robots" content="noindex">` se `isNoIndex` for verdadeiro.

## 5. Preparação do Ambiente Local de Testes

Para facilitar os testes locais, o sistema precisa de um script SQL de carga inicial (Fixtures ou script puro).

O script deve criar na tabela `Tenant`:
1.  **Tenant A**:
    * Domínio: `127.0.0.1`
    * Nome: Tenant Local IP
    * Cores: `#0044cc` (primária) e `#ffaa00` (secundária)
2.  **Tenant B**:
    * Domínio: `localhost`
    * Nome: Tenant Localhost
    * Cores: `#008800` (primária) e `#333333` (secundária)

Desta forma, ao iniciar o servidor Symfony (`symfony server:start`) e acessar `http://127.0.0.1:8000` ou `http://localhost:8000`, o Event Subscriber identificará os domínios perfeitamente.

O script também deve criar um usuário SuperAdmin (sem tenant associado) e um usuário Admin associado ao Tenant A, para permitir o acesso imediato aos painéis.

## 6. o superadmin
deve ter um dashboard paraGerenciar os Tenants
Deve ter a opção de impersonar o admin do tenant.
quando estiver impersonando, deve ter uma opção no menu principal do admin de "voltar para o superadmin"
no tenant de teste não existe a opção de criar tenants, então o tenant deve ser criado manualmente.

### Admin
O super admin cadastra admin e informa qual o tenant que aquele admin poderá gerenciar.

### Layout
Pensando que cada tenant deve ter uma identidade visual única (cores, logo), vamos pensar em uma estrutura que permita isso. O super admin deve poder informar:
 - qual o arquivo base.html.twig deve ser usado pelo tenant
 - qual o arquivo para o home.html.twig deve ser usado pelo tenant
 - qual o arquivo para o article.html.twig deve ser usado pelo tenant
 - qual o arquivo para o video.html.twig deve ser usado pelo tenant
 - qual o arquivo para o page.html.twig deve ser usado pelo tenant
 - qual o arquivo para o contact.html.twig deve ser usado pelo tenant
 - qual o arquivo para o newsletter.html.twig deve ser usado pelo tenant
 - qual o arquivo para o footer.html.twig deve ser usado pelo tenant
 - qual o arquivo para o header.html.twig deve ser usado pelo tenant
 Para isso, você já deve gerar 2 opções de arquivos para cada um.
 Use as melhores técnicas de SEO, semântica e acessibilidade
 USe os melhores frameworks modernos e tecnologias que suportem a criação de interfaces bonitas e funcionais.
 Quero o layout lindo, perfeito, primoroso, consiso.

## 7. Usuários Editores
O Tenant (admin) pode adicionar e remover usuários editores.
Os usuários editores podem adicionar (e o próprio administrador) podem adicionar / remover / editar os artigos/notícias e as páginas acessórias dos vídeos.

Só que para que um artigo seja publicado ele deve passar por validações dos outros usuários editores.
No painel do super-admin, no cadastro do Tenent, o super-admin define um valor numérico para "quantidade de aprovações necessárias". Logo, um artigo/notícia só será publicado quando ele tiver o número de aprovações necessárias.

Toda a lógica de comunicação e de validação de artigos/noticias está descrita no documento
Atente-se ao fato de que os editores podem trocar mensagens entre si e com o admin (do mesmo tenant)

## 


