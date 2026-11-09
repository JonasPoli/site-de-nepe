# Proposal

## Why

Desde o commit `bd97821` (08/06/2026, "banners"), o filtro de tenant do Doctrine não é ligado em `/admin`. As listagens do painel mostram conteúdo, contatos e conversas de todos os NEPEs, as rotas de edição aceitam o id de qualquer NEPE, e o login vale em qualquer domínio. Na prática, o administrador de um NEPE edita conteúdo e configurações de outro, seja entrando pelo domínio dele, seja pelos ids na própria URL. Os dados de produção já mostram o efeito: em 09/11/2026, o nepealcione tinha 5 subcategorias penduradas na categoria "Shorts" do renovandoconsciencias e um vídeo ligado a uma categoria dele.

Além disso, `/admin/editors` e `/admin/settings` exigem apenas `ROLE_ADMIN`, que editores e revisores também têm. Com um POST montado à mão, um editor ou revisor cria um usuário do grupo 0 e muda o número de aprovações do NEPE, que é a regra que segura a publicação. Decisão do Jonas (09/11/2026): corrigir as duas falhas, mantendo o super admin com acesso a todos os NEPEs.

## What Changes

- **BREAKING** O painel `/admin` de um domínio passa a mostrar e alterar só o NEPE desse domínio: listagens, contagens do dashboard, telas de edição, exclusões, reordenações, envio para aprovação, aprovação e mensagens internas. Id de registro de outro NEPE responde 404.
- **BREAKING** O login só é aceito no domínio do NEPE do usuário. Em outro domínio, a senha certa recebe a recusa com o endereço do NEPE dele. Uma sessão já aberta no domínio errado é encerrada no primeiro acesso ao painel, com uma página que leva ao painel certo.
- O super admin continua entrando no painel de qualquer NEPE, pelo domínio dele, e em `/superadmin`. Em cada domínio, ele vê e cria conteúdo desse NEPE. O "entrar como" leva ao domínio do NEPE do usuário escolhido.
- Referências enviadas no formulário (categoria de um vídeo, estudo ou artigo, categoria relacionada de um bloco) só aceitam registros do NEPE do painel.
- **BREAKING** `/admin/editors` (listar, criar e excluir) e `/admin/settings` passam a exigir o administrador do NEPE (grupo 0, incluindo o super admin). Editores e revisores recebem 403.
- A criação de usuário pelo painel aceita só Editor (grupo 1) ou Revisor (grupo 2), sempre no NEPE do painel. O formulário passa a oferecer Revisor. A exclusão alcança só editores e revisores desse NEPE.
- O link de redefinição de senha passa a apontar para o domínio do NEPE do usuário, não para o domínio onde o pedido foi feito.
- Correção única, em produção, das categorias e dos vídeos do nepealcione que apontam para o renovandoconsciencias, antes de ligar o filtro no painel.
- Sem mudança em `/api/biblia`: rotas, parâmetros e JSON continuam iguais para o nepe-search.

## Capabilities

### New Capabilities

Nenhuma.

### Modified Capabilities

- `identificacao-do-nepe`: o filtro de tenant passa a valer também em `/admin`. Saem os requisitos "Isolamento no site público" e "Painel sem o filtro de tenant", e entra "Isolamento pelo filtro de tenant".
- `papeis-e-painel`: novo papel do administrador do NEPE. O login e a sessão ficam presos ao domínio do NEPE, e o painel fica restrito ao NEPE do domínio (inclusive referências e mensagens). Gestão de usuários e configurações só para o grupo 0, criação de usuário limitada a editor ou revisor, "entrar como" no domínio do usuário e link de redefinição de senha no domínio do NEPE. Saem "Login sem conferir o domínio" e "Listagens do painel sem filtro de tenant".

## Impact

- Código: `EventSubscriber/TenantSubscriber`, `Entity/User::getRoles()`, `config/packages/security.yaml` (user checker), um novo verificador de login, um novo guarda do painel, `admin/AdminContentController`, `admin/ArticleController`, `admin/ContentApprovalController`, `admin/MessageController`, `superadmin/SuperAdminController::impersonate()`, `ResetPasswordController`, os templates `admin/nepe_base.html.twig` e `admin/editor/new.html.twig`, uma página de acesso negado e os testes em `tests/`.
- Usuários: quem hoje entra pelo domínio de outro NEPE precisa usar o do próprio NEPE. Em 09/11/2026 havia 13 usuários: 2 super admins e 11 de seis NEPEs.
- Produção: escrita de dados (correção das categorias do nepealcione) e deploy, com backup antes e conferência depois. Sem migration e sem mudança no `composer.lock`.
- nepe-search: sem mudança de contrato. O efeito indireto está no design.
