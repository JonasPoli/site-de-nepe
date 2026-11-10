# Tasks

## 1. Levantamento em produção (só leitura)

- [x] 1.1 (10/11/2026: igual a 09/11 — categorias 19, 20, 21, 23 e 24 com mãe 6 do tenant 3; vídeo 15 com a categoria 11; 0 usuários dos grupos 1 e 2 sem tenant; disco com 44 G livres.) No servidor (`ssh -p 22022 root@162.240.232.248`, leitura), rodar `df -h /home` e a consulta de dados cruzados do design (categoria, categoria-mãe e categoria relacionada de outro tenant; autor, aprovação e mensagem entre tenants; usuários dos grupos 1 e 2 sem tenant) com `mysql projetomaster_db`, sem abrir o `.env.local`. Conferido quando o resultado é igual ao de 09/11 (só as categorias 19, 20, 21, 23 e 24 e o vídeo 15) ou quando cada diferença está anotada no design antes de seguir.

## 2. Filtro no painel e acesso pelo domínio

- [x] 2.1 Criar o helper `TenantUrl::base(Tenant)` (`https://<domínio>`, com `http://` para `localhost` e `127.0.0.1`), com teste unitário dos três casos. Conferido com `vendor/bin/phpunit tests/Service/TenantUrlTest.php` passando.
- [x] 2.2 Criar a regra pura `canUsePanel(User, Tenant)` (super admin sempre; demais só com o mesmo id de tenant; sem tenant e fora do grupo 0, nunca), com teste unitário de cada caso. Conferido com o teste novo passando.
- [x] 2.3 Tirar do `TenantSubscriber` o retorno antes do filtro em `/admin` e acrescentar ao `TenantSubscriberTest` o caso "caminho `/admin` liga o filtro com o tenant do domínio". Conferido com `vendor/bin/phpunit tests/EventSubscriber/TenantSubscriberTest.php` passando.
- [x] 2.4 Criar o user checker do login (`checkPostAuth`, com a mensagem `Este usuário é de outro NEPE. Entre pelo endereço do seu site: <base>/login`) e registrá-lo em `firewalls.main.user_checker` do `security.yaml`. Testes unitários com `TenantContext` de verdade e usuários em memória: recusa outro tenant com o endereço certo, recusa usuário sem tenant fora do grupo 0, aceita o mesmo tenant e o super admin. Conferido com os testes passando e `bin/console debug:config security` mostrando o checker.
- [x] 2.5 Criar o subscriber de kernel.request (prioridade 7, só `/admin`) que, quando `canUsePanel` falha para o usuário logado, limpa o token, invalida a sessão e responde 403 com `admin/acesso_outro_nepe.html.twig` (link `<base do NEPE do usuário>/admin`). Testes unitários: 403 com token limpo e sessão invalidada; passa com o mesmo tenant e com o super admin; ignora visitante sem login e caminhos fora de `/admin`. Conferido com os testes passando e `bin/console debug:event-dispatcher kernel.request` mostrando o subscriber depois do firewall.

## 3. Administrador do NEPE e gestão de usuários

- [x] 3.1 Dar `ROLE_NEPE_ADMIN` ao grupo 0 (com e sem tenant) em `User::getRoles()`, com `tests/Entity/UserTest.php` cobrindo os grupos 0 (com e sem tenant), 1 e 2. Conferido com o teste passando.
- [x] 3.2 Pôr `#[IsGranted('ROLE_NEPE_ADMIN')]` em `editorIndex`, `editorNew`, `editorDelete` e `settings`, e trocar no `nepe_base.html.twig` o `app.user.workGroup == 0` do menu por `is_granted('ROLE_NEPE_ADMIN')`. Um teste unitário por reflexão confere o atributo nas quatro ações. Conferido com o teste passando.
- [x] 3.3 Criar a política pura de usuários do painel (grupos aceitos 1 e 2; exclusão só de grupo 1 ou 2 do tenant do domínio, nunca o próprio usuário), com teste unitário, e aplicá-la: em `editorNew` (tenant do `TenantContext`, aviso "Grupo inválido" sem gravar), em `editorDelete` e em `editorIndex` (lista do tenant do domínio). Acrescentar a opção Revisor ao `admin/editor/new.html.twig`. Conferido com o teste passando e com a criação de um revisor na conferência local (6.1).

## 4. Rotas que o filtro não cobre

- [x] 4.1 Criar nos repositórios de `PageSection` e `PageBlock` a busca por id com `LEFT JOIN` na página e na categoria donas (o filtro descarta o dono de outro tenant), mais a regra pura `tenantIdOf(PageSection|PageBlock)`, com teste unitário da regra em entidades em memória (seção de página, seção de categoria, bloco de cada uma). Usar as duas em `section*`, `block*` e nas reordenações de seção e bloco (ids de outro dono ignorados). Conferido com o teste passando e com os casos de seção e bloco da conferência local (6.1).
- [x] 4.2 Trocar o `getReference(Category::class, …)` de vídeo, estudo e artigo por `CategoryRepository::find()` (id informado e não encontrado dá 404, sem gravar) e aplicar o mesmo ao `relatedCategoryId` do `populateBlock()`. Conferido com `rg -n "getReference\(" src/Controller` sem nenhuma entidade com tenant (só `BibliaBook`) e com o caso de categoria de outro NEPE da conferência local (6.1).
- [x] 4.3 Criar a regra pura `MessageAccess` (conversa do NEPE pela mensagem inicial; fora do NEPE dá 404; do NEPE, permitido a participante ou `ROLE_NEPE_ADMIN`, senão 403), com teste unitário. Aplicá-la em `read`, `reply` e `setStatus`; trocar `findAllConversations()` por `findAllConversations(Tenant)`; e recusar em `send` o destinatário de outro tenant com 404 `Recipient not found`. Conferido com o teste passando e com os casos de mensagem da conferência local (6.1).

## 5. Super admin e redefinição de senha

- [x] 5.1 Fazer o `SuperAdminController::impersonate()` redirecionar para `TenantUrl::base(<tenant do usuário>) . '/admin?_switch_user=<usuário>'` (no domínio atual, se o usuário não tem tenant), com a montagem da URL num método estático coberto por teste unitário. Conferido com o teste passando.
- [x] 5.2 Fazer o `ResetPasswordController::request()` mandar o link na base do NEPE do usuário (no host do pedido, para o super admin), com a escolha da base num método coberto por teste unitário. Conferido com o teste passando.

## 6. Conferência local

- [x] 6.1 (10/11/2026: cópia local do banco, site_de_nepe_isolamento, com os tenants 127.0.0.1 e localhost, conferida por curl com sessões separadas; todos os cenários passaram, inclusive o revisor criado pelo painel, a sessão de outro NEPE encerrada com 403 e o link de redefinição capturado num SMTP local. Os únicos 500 vieram do cache local compilado antes da mudança nos controllers e sumiram com o cache:clear.) Rodar o site local com os tenants `localhost` e `127.0.0.1`, com um administrador e um editor em cada (banco local), e conferir pelo navegador cada cenário de `papeis-e-painel` e `identificacao-do-nepe`: login cruzado recusado com o endereço; super admin nos dois; 404 por id de outro tenant em banner, vídeo, seção, bloco, categoria enviada e mensagem; reordenação com ids misturados; 403 do editor em `/admin/settings` e `/admin/editors/new`; grupo 0 recusado; "entrar como" no domínio errado com 403; link de redefinição no domínio do usuário. Conferido com a lista de cenários marcada, sem nenhum erro 500.
- [x] 6.2 Rodar `vendor/bin/phpunit` completo. Conferido com todos os testes sem banco passando (o `SuperTestFieldsControllerTest`, que exige banco, fica de fora como hoje).

## 7. Correção dos dados cruzados em produção (escrita, com aprovação)

- [x] 7.1 Aprovado pelo Jonas em 09/11/2026: criar a "Shorts" do nepealcione, mover para ela as subcategorias 23 e 24, excluir as categorias vazias 19, 20 e 21 e deixar o vídeo 15 sem categoria.
- [x] 7.2 (10/11/2026: banco com 16,4 MB, 44 G livres, dados cruzados iguais aos da 1.1; nenhuma seção, bloco ou conteúdo aponta para 19, 20 e 21.) Leitura: `df -h /home` com folga para o dump e a consulta de dados cruzados igual à da tarefa 1.1. Conferido com espaço livre maior que o tamanho do banco e nenhum dado cruzado novo.
- [x] 7.3 (10/11/2026: /root/backup-nepe-isolamento-20261110.sql, 6,4 MB, e /root/backup-nepe-isolamento-20261110-category-video.sql, ambos 600 e com "Dump completed".) Backup: `mysqldump --single-transaction projetomaster_db > /root/backup-nepe-isolamento-<AAAAMMDD>.sql` e cópia só das tabelas `category` e `video_support` em outro arquivo. Conferido com os arquivos com tamanho maior que zero e `grep -c 'INSERT INTO .category.'` maior que zero.
- [x] 7.4 (10/11/2026, 09:19: nova "Shorts" do tenant 6 com id 25; 23 e 24 movidas para ela; 19, 20 e 21 excluídas; vídeo 15 com category_id NULL; COMMIT sem erro.) Escrita: numa transação, criar a "Shorts" raiz do tenant 6, mover as categorias 19, 20, 21, 23 e 24 para ela e aplicar a decisão do Jonas sobre o vídeo 15 e as categorias sem nome. Conferido com o `COMMIT` sem erro.
- [x] 7.5 (10/11/2026: consulta de dados cruzados zerada; tenant 6 com 1 categoria raiz; home, /videos e /categoria/shorts dos dois NEPEs e /categoria/maria e /categoria/evangelho-e-plenitude do nepealcione com 200.) Conferência: a consulta de dados cruzados volta zerada, e a home e as páginas de categoria do nepealcione e do renovandoconsciencias respondem 200, como antes. Conferido com o resultado anotado aqui.

## 8. Deploy (escrita, com aprovação)

- [ ] 8.1 Com a aprovação do Jonas para o deploy, rodar `git push origin main`. Conferido com o commit no `origin/main`.
- [ ] 8.2 Backup antes do deploy, como `projetomaster`: anotar `git rev-parse HEAD` e `git status --short` (o `public/.htaccess` modificado precisa aparecer) e copiar o `public/.htaccess` para `/root/backup-nepe-htaccess-<AAAAMMDD>`. O dump da tarefa 7.3 vale se for do mesmo dia; senão, fazer outro. Conferido com o hash anotado e a cópia existente.
- [ ] 8.3 Deploy, como `projetomaster` em `/home/projetomaster/repositories/site-de-nepe`: `git pull`; sem `composer.phar install` (`git diff --stat <hash anterior>..HEAD -- composer.lock` vazio); sem migrations (`git diff --stat <hash anterior>..HEAD -- migrations` vazio); `sh build.sh` (as três últimas linhas usam o ea-php83); qualquer outro comando com `/opt/cpanel/ea-php83/root/usr/bin/php bin/console … --env=prod`; nunca `git checkout`, `stash` ou `reset` no `public/.htaccess`. Conferido com o `git status --short` igual ao de antes, sem nada além do `.htaccess`, `.user.ini` e `php.ini` de sempre.
- [ ] 8.4 Conferência em cada domínio (renovandoconsciencias, nepebrasilia, ciscodedeus, rodrigolobato, nepealcione e ueebp `.nepebrasil.org`): home 200, `/admin` 302 para `/login` e `/api/biblia/contents?book=jo&chapter=3` 200 com JSON. Depois, como super admin: as listagens de dois NEPEs mostram só o próprio NEPE; "entrar como" num editor do renovandoconsciencias leva ao domínio dele e `/admin/settings` dá 403; "entrar como" à mão no domínio errado dá 403; as categorias e os vídeos 15 e 16 do nepealcione abrem sem erro. Conferido com cada item anotado e nenhum erro novo no log do Apache do domínio.
- [ ] 8.5 Conferir no search.nepebrasil.org uma passagem com conteúdo dos NEPEs (por exemplo, Lucas 10) e ver os cartões dos NEPEs como antes. Conferido com os cartões na página.
- [ ] 8.6 Registrar na ficha do servidor (`/Volumes/Dados/work/runcloud/docs/outros/nepebrasil-cpanel.md`) a data do deploy, o hash anterior, os backups e a correção de dados. Conferido com a ficha atualizada.

## Workflow follow-up

- Depois do deploy conferido, arquivar a mudança (`openspec archive isolamento-do-admin-por-nepe`) para levar os deltas a `openspec/specs/` e conferir com `openspec validate --specs --strict`.
- Abrir outra mudança para a exclusão de artigo, que hoje não exige `ARTICLE_EDIT` (está nos Non-Goals do design).
