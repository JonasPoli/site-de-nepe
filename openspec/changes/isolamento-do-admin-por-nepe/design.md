# Design

## Context

Motivação em proposal.md (Why). Estado do código em 09/11/2026:

- `TenantSubscriber` (kernel.request, prioridade 100, antes do firewall) acha o tenant pelo host, guarda no `TenantContext` e liga o `tenant_filter`. Desde o `bd97821`, ele retorna antes de ligar o filtro quando o caminho começa com `/admin`. O commit não diz o motivo; o comentário diz "admins manage entities across tenants".
- Os controllers do painel (`AdminContentController`, `ArticleController`, `ContentApprovalController`, `MessageController`) recebem as entidades pelo `{id}` da rota (EntityValueResolver) sem conferir o tenant. Só `ContentApprovalController::denyUnlessSameTenant()`, a lista de editores e a de newsletter olham o tenant. A categoria de vídeo, estudo e artigo é gravada com `$em->getReference(Category::class, $id)`, que não consulta o banco e por isso escapa de qualquer filtro.
- `User::getRoles()` dá `ROLE_ADMIN` aos grupos 0, 1 e 2; nenhum papel separa o grupo 0. O menu esconde "Editores" e "Configurações" por `app.user.workGroup == 0`, mas as rotas aceitam `ROLE_ADMIN`. `editorNew()` grava o `workGroup` do POST sem validar e usa o tenant do usuário logado (o super admin, sem tenant, cria usuários sem tenant). `editorDelete()` compara `$editor->getTenant() === $me->getTenant()`, o que deixa um editor excluir o administrador do próprio NEPE e deixa o super admin excluir outro super admin.
- A sessão é por host: o `framework.yaml` não define `cookie_domain`. Um login em um domínio não vale nos outros, mas hoje o login é aceito em qualquer domínio.
- `Message`, `PageSection`, `PageBlock` (e os itens dele), `StudyMaterial`, `VideoMaterial`, as aprovações e `User` não implementam `TenantAwareInterface`. O filtro não os alcança.
- O ambiente de teste não tem banco: os testes possíveis são unitários, com entidades em memória e mocks.

Produção em 09/11/2026 (leitura, por SSH como root com `mysql`, sem abrir o `.env.local`):

- 13 usuários: 2 super admins (ids 1 e 6) e 11 usuários de seis NEPEs. Nenhum usuário dos grupos 1 ou 2 está sem tenant. Os ids 1 e 6 têm papéis gravados na coluna `roles` (`ROLE_SUPER_ADMIN` e `ROLE_ADMIN`), que `getRoles()` soma aos calculados.
- Dados cruzados: as categorias 19, 20, 21, 23 e 24 do nepealcione (tenant 6) são filhas da "Shorts" (id 6) do renovandoconsciencias (tenant 3). O vídeo 15 do nepealcione (pendente) usa a categoria 11 do renovandoconsciencias, e o vídeo 16 (pendente) usa a 24. O nepealcione não tem categoria raiz própria. Não há autor, aprovação, mensagem nem bloco cruzados.
- A "Shorts" tem `orphanRemoval` e `ON DELETE CASCADE` nos filhos: se o renovandoconsciencias excluir a "Shorts", as cinco subcategorias do nepealcione vão junto.

## Goals / Non-Goals

**Goals:**

- Fechar o painel no NEPE do domínio com um mecanismo central (o filtro) e cobrir com regras explícitas o que ele não alcança.
- Prender login e sessão ao domínio do NEPE do usuário, sem quebrar o super admin nem o "entrar como".
- Separar o administrador do NEPE (grupo 0) de editores e revisores com um papel próprio.
- Manter `/api/biblia` igual.

**Non-Goals:**

- Mudar quem edita ou exclui cada tipo de conteúdo dentro do mesmo NEPE. Por exemplo, a exclusão de artigo hoje só exige `ROLE_ADMIN` (um revisor exclui artigos), o que contraria o requisito "Quem edita cada tipo de conteúdo" de `aprovacao-de-conteudo`. Fica anotado para outra mudança.
- CSRF nos formulários que não têm (criar usuário, configurações, mensagens).
- Sessão única para todos os subdomínios (`cookie_domain`).
- Mexer no `BibliaService` ou em qualquer parte de `/api`.

## Decisions

### 1. O filtro de tenant volta a valer em `/admin`

O `TenantSubscriber` deixa de pular `/admin` e liga o `tenant_filter` com o tenant do domínio em toda requisição identificada. Com isso, listagens, contagens, `find()`, `findBy()`, coleções e o `{id}` das rotas (EntityValueResolver) das nove entidades com tenant passam a enxergar só o NEPE do domínio. Um id de outro NEPE vira "não encontrado", ou seja, 404.

- Alternativa considerada: conferir o tenant em cada ação dos controllers, com o filtro desligado. Foi descartada porque são mais de 50 rotas, e uma rota esquecida (ou uma rota nova) reabre a falha. O filtro protege por padrão, e as regras explícitas cobrem só o que ele não alcança.
- Alternativa considerada: desligar o filtro para o super admin, para ele ver todos os NEPEs nas listagens. Foi descartada porque o conteúdo criado no painel vai para o tenant do domínio e as prévias usam o domínio atual. Misturar NEPEs numa tela leva ao mesmo erro que esta mudança corrige. O super admin trabalha em cada NEPE pelo domínio dele (ver a decisão 4).

### 2. O tenant do painel é o do domínio, e o usuário precisa ser dele

Uma regra pura, `canUsePanel(User, Tenant)`, decide o acesso: é verdadeira para o super admin e para o usuário cujo tenant tem o mesmo id do tenant do domínio. Ela é aplicada em dois pontos:

- **No login**: um user checker (`security.yaml`, `firewalls.main.user_checker`) roda `checkPostAuth()`, ou seja, só depois da senha conferida, e lança `CustomUserMessageAccountStatusException` com o endereço `<base do NEPE do usuário>/login`. A senha errada continua com a mensagem padrão, sem revelar o NEPE. O Symfony também chama `checkPostAuth()` no usuário de destino do `_switch_user`, então um "entrar como" no domínio errado dá 403 sem derrubar a sessão do super admin.
- **Em cada requisição de `/admin`**: um subscriber de kernel.request com prioridade 7 (logo depois do firewall, que roda em 8) confere a regra para o usuário logado. Se ela falhar, ele limpa o token, invalida a sessão desse host e responde 403 com o template `admin/acesso_outro_nepe.html.twig`, que tem o link `<base do NEPE do usuário>/admin`. Esse ponto pega sessões abertas antes do deploy e trocas de tenant feitas pelo super admin com o usuário logado.
- Alternativa considerada: redirecionar o usuário para o domínio do NEPE dele. Foi descartada porque a sessão é por host: ele cairia no login sem saber o motivo, e a mensagem não atravessa domínios. A página 403 explica o que aconteceu.
- Alternativa considerada: usar o tenant do usuário em vez do tenant do domínio. Foi descartada porque o painel de B mostraria o conteúdo de A com o tema, a logo e as prévias de B.

A base do NEPE (`https://<domínio>`, ou `http://` para `localhost` e `127.0.0.1`, a mesma regra das URLs da API) fica num helper novo, `TenantUrl::base(Tenant)`. Ele é usado no user checker, na página 403, no "entrar como" e na redefinição de senha. O `BibliaService` continua com a lógica dele, para não tocar em `/api`.

### 3. Papel do administrador do NEPE

`User::getRoles()` passa a dar `ROLE_NEPE_ADMIN` ao grupo 0, com ou sem tenant. O super admin continua com tudo o que tinha e ganha esse papel. `/admin/editors`, `/admin/editors/new`, `/admin/editors/{id}/delete` e `/admin/settings` recebem `#[IsGranted('ROLE_NEPE_ADMIN')]`. O menu passa a usar `is_granted('ROLE_NEPE_ADMIN')` no lugar de `app.user.workGroup == 0`. O papel é calculado, não gravado, então não há migration. Os papéis já gravados nos ids 1 e 6 continuam somando.

- Alternativa considerada: um voter (`TENANT_MANAGE`). Foi descartada porque um papel é mais simples de testar em `getRoles()`, serve no Twig e segue o padrão que já existe (`ROLE_EDITOR`).

Regras de usuário do painel, numa política pura testável:

- O grupo aceito é 1 ou 2. Outro valor volta ao formulário com o aviso "Grupo inválido", sem gravar. O `<select>` passa a oferecer Editor e Revisor.
- O tenant do usuário criado é o do domínio (`TenantContext`), não o de quem cria. Assim, o super admin não cria mais usuários sem tenant.
- A exclusão exige um alvo do tenant do domínio e do grupo 1 ou 2. Nunca alcança um administrador, um super admin ou o próprio usuário.
- A lista usa `findBy(['tenant' => <tenant do domínio>, 'workGroup' => [1, 2]])`.

### 4. Super admin

O super admin entra em `/admin` por qualquer domínio (`canUsePanel` é verdadeira para ele) e vê o NEPE desse domínio, com o mesmo filtro. Continua com `/superadmin` para cadastrar NEPEs e usuários. O "entrar como" (`SuperAdminController::impersonate()`) redireciona para `TenantUrl::base(<tenant do usuário>) . '/admin?_switch_user=<usuário>'` (no domínio atual, se o usuário não tem tenant). Sem sessão naquele host, o firewall leva ao login e depois volta para a URL guardada, já com o `_switch_user`.

### 5. Como cada rota de `/admin` fica restrita ao tenant

"Filtro" quer dizer que a rota recebe a entidade pelo `{id}` ou pelo repositório com o `tenant_filter` ligado: registro de outro NEPE dá 404, e reordenação ignora o id.

| Rotas | Restrição |
|---|---|
| `/admin` (dashboard) | Filtro nas contagens de contatos e linhas de pesquisa. Mensagens não lidas são do próprio usuário. |
| `/banner*`, `/research-line*` (index, new, edit, delete, reorder) | Filtro. `new` grava o tenant do `TenantContext` (já é assim). |
| `/category*`, `/category/{id}/sub*`, `/subcategory/{id}/*` | Filtro, inclusive na categoria-mãe da rota. |
| `/category/{catId}/section*`, `/page/{pageId}/section*` | Filtro na categoria ou página da rota (`find()`). |
| `/section/{id}/edit\|delete`, `/section/reorder`, `/section/{sectionId}/block*`, `/block/{id}/edit\|delete`, `/block/reorder` | Seção e bloco não têm tenant. São carregados por uma consulta de repositório que junta a página e a categoria donas (`LEFT JOIN`). O filtro entra nas junções, e a seção cujo dono é de outro tenant some (404). Depois, a regra pura `tenantIdOf(PageSection\|PageBlock)` confere de novo contra o tenant do domínio. Na reordenação, ids de outro dono são ignorados. Imagens, depoimentos, logos e membros só são alterados pela coleção do bloco já conferido. |
| `/video*`, `/study*` (index, new, edit, delete) e `/article*` (index, new, edit, show, delete) | Filtro. A categoria enviada passa de `getReference()` para `CategoryRepository::find()` (com filtro): id informado e não encontrado dá 404, sem gravar. As listas de categorias dos formulários (`findAll()`) já saem filtradas. Materiais e imagens de galeria só são removidos pela coleção do conteúdo já conferido. |
| `/study/{id}/submit\|review\|approve`, `/video/{id}/submit\|approve`, `/article/{id}/submit\|approve` | Filtro. Ficam também `denyUnlessSameTenant()` e a regra do `ContentApprovalService` (outro tenant é recusado). |
| `populateBlock()`: `relatedCategoryId`, lista de páginas e categorias | `find()`/`findBy()` com filtro. Id informado e não encontrado dá 404. |
| `/contact*`, `/newsletter*` | Filtro. |
| `/page*` | Filtro. |
| `/editors*`, `/settings` | `ROLE_NEPE_ADMIN` (decisão 3). Tenant do `TenantContext`, que a decisão 2 garante ser o do usuário (ou o do domínio, para o super admin). |
| `/message` (index, widget) | Entrada e enviados são do próprio usuário. A visão geral do administrador passa a ser `findAllConversations(Tenant)`: mensagem inicial com remetente ou destinatário do tenant. |
| `/message/{id}`, `/message/{id}/reply`, `/message/{id}/status/{status}` | `Message` não tem tenant. A regra pura `MessageAccess` decide sobre a mensagem inicial da conversa: se não é do NEPE do domínio, dá 404; se é do NEPE e o usuário não participa nem tem `ROLE_NEPE_ADMIN`, dá 403. Hoje `reply` e `status` não conferem nada. |
| `/message/send` | O destinatário precisa ser do tenant do domínio; senão a resposta é 404 `Recipient not found` (a mesma de hoje para id inexistente). |
| `/admin/api/biblia/*` | Sem mudança: texto bíblico, igual para todos os NEPEs. |

Em `/superadmin`, o filtro já estava ligado (só `/admin` era pulado) e não afeta as telas, que leem `Tenant` e `User`, sem tenant.

### 6. Redefinição de senha no domínio do NEPE

`ResetPasswordController::request()` passa ao `PasswordResetMailer` a base `TenantUrl::base($user->getTenant())`. Para o super admin, que não tem tenant, continua `$request->getSchemeAndHttpHost()`. Sem isso, um pedido feito no domínio errado geraria um link para um login que agora recusa o usuário.

### 7. Correção dos dados cruzados antes do deploy

Com o filtro no painel, o nepealcione ficaria sem nenhuma categoria raiz visível, e as telas que mostram a categoria dos vídeos 15 e 16 tentariam carregar uma categoria filtrada, o que dá `EntityNotFoundException` (500). Por isso a correção vem antes do deploy, como escrita em produção com aprovação do Jonas. A proposta:

- criar no tenant 6 uma categoria raiz "Shorts" (slug `shorts`, sem cabeçalho e sem rodapé, como a original);
- mover para ela as categorias 23 ("Maria") e 24 ("Evangelho e Plenitude", usada pelo vídeo 16);
- excluir as categorias 19, 20 e 21, que não têm nome, slug, conteúdo nem filhas;
- tirar a categoria do vídeo 15 (`category_id = NULL`), para o nepealcione escolher de novo.

Decidido pelo Jonas em 09/11/2026 (tarefa 7.1).

Os vídeos 15 e 16 estão pendentes, então nada disso aparece no site nem na API.

### 8. Efeito no nepe-search e em `/api/biblia`

- O `TenantSubscriber` continua pulando `/api` antes de qualquer coisa, então a API segue sem tenant e sem filtro, com as mesmas rotas, parâmetros e JSON. O nepe-search (`NepeTenantsContentService`) chama só `https://renovandoconsciencias.nepebrasil.org/api/biblia/contents` com `livro`/`chapter`/`verse`/`verse2`, e nada disso muda.
- Efeito indireto: o que a API devolve passa a ter sido criado, editado e aprovado só por membros do próprio NEPE. A correção da decisão 7 mexe só em conteúdo pendente, que a API não devolve. O cache do nepe-search (10 minutos) não precisa ser limpo.

## Risks / Trade-offs

- [Restar algum dado cruzado além dos encontrados em 09/11 e dar 500 no painel quando o filtro carregar a relação] → A consulta de conferência é refeita logo antes do deploy (tarefa 7.2) e precisa voltar zerada. Depois do deploy, as telas de cada NEPE são abertas uma a uma.
- [Uma rota de entidade sem tenant ficar sem conferência] → A tabela da decisão 5 é a lista de conferência da implementação. As regras puras (`tenantIdOf`, `MessageAccess`, política de usuários) têm teste unitário, e a conferência local com dois tenants cobre as rotas.
- [O super admin precisa entrar em cada domínio, e o painel não mostra mais tudo numa tela] → É o preço do isolamento (decisão 1). O `/superadmin` continua global, e o "entrar como" leva ao domínio certo.
- [Usuários com sessão aberta no domínio errado perdem a sessão no deploy] → É o esperado. A página 403 explica e dá o link.
- [Sem banco nos testes, o 404 por id de outro NEPE não tem teste automático] → Conferência manual local com os tenants `localhost` e `127.0.0.1` (tarefa 6.1) e em produção pelo "entrar como" (tarefa 8.4).
- [O `getReference()` sobrar em algum ponto e gravar uma categoria de outro NEPE] → A tarefa 4.3 procura todos os `getReference(` de entidades com tenant em `src/Controller/admin` e troca por consulta com filtro.

## Migration Plan

1. Leitura em produção: `df -h /home`, tamanho do `projetomaster_db` e consulta de dados cruzados.
2. Escrita em produção, com aprovação do Jonas: backup do banco (`mysqldump --single-transaction`), aplicação da correção da decisão 7 e consulta de conferência zerada.
3. Deploy (escrita, com aprovação): `git push origin main`; no servidor, como `projetomaster`, anotar `git rev-parse HEAD`, rodar `git pull` e `sh build.sh` (só as três últimas linhas, com ea-php83, têm efeito, e o build limpa o `var/cache`). Sem `composer.phar install` (o `composer.lock` não muda) e sem migrations (não há mudança de esquema). Nunca rodar `git checkout`, `stash` ou `reset` no `public/.htaccess`.
4. Conferência (leitura): em cada domínio, a home, o `/admin` (302 para `/login`) e a `/api/biblia/contents`. Depois, os testes de painel pelo "entrar como" e uma busca no search.nepebrasil.org.
5. Volta atrás: `git revert` do commit da implementação, push, `git pull` e `sh build.sh` no servidor. Os dados voltam pelo backup das tabelas `category` e `video_support`, se o Jonas quiser desfazer a correção (ela é compatível com o código antigo, então não precisa ser desfeita para voltar o código).

## Open Questions

- Resolvidas em 09/11/2026 (tarefa 7.1): as categorias 19, 20 e 21 são excluídas e o vídeo 15 fica sem categoria.
