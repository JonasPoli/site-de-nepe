# papeis-e-painel Specification

## Purpose
Define quem entra no painel e o que cada papel alcança: super admin, administrador, editor e revisor de um NEPE. Há um único login para todos os domínios, e o papel vem do grupo (`workGroup`) e do tenant do usuário. Implementado em `Entity/User::getRoles()`, `config/packages/security.yaml`, `admin/AdminContentController`, `superadmin/SuperAdminController` e `ResetPasswordController`. Os requisitos sobre o alcance do painel entre tenants descrevem o código de 09/11/2026 e estão como dúvida aberta no relatório da adoção do OpenSpec.

## Requirements
### Requirement: Papéis pelo grupo e pelo tenant
O sistema SHALL dar `ROLE_SUPER_ADMIN` e `ROLE_ADMIN` ao usuário sem tenant do grupo 0; `ROLE_ADMIN` ao usuário com tenant do grupo 0 (administrador); e `ROLE_EDITOR` mais `ROLE_ADMIN` aos grupos 1 (editor) e 2 (revisor).

#### Scenario: Revisor entra no painel
- **WHEN** um usuário do grupo 2 faz login
- **THEN** ele acessa `/admin`, mas não `/superadmin`

### Requirement: Áreas protegidas
O sistema SHALL exigir `ROLE_SUPER_ADMIN` em `/superadmin` e `ROLE_ADMIN` em `/admin`, com login por usuário e senha em `/login` e token CSRF no formulário.

#### Scenario: Visitante sem login
- **WHEN** um visitante abre `/admin`
- **THEN** ele é levado para `/login`

### Requirement: Login sem conferir o domínio
O login SHALL valer em qualquer domínio da plataforma: o sistema MUST NOT conferir se o tenant do usuário é o tenant do domínio acessado.

#### Scenario: Administrador entra pelo domínio de outro NEPE
- **WHEN** o administrador do tenant A faz login em `https://<domínio do tenant B>/login`
- **THEN** o login é aceito e ele entra no `/admin` desse domínio

### Requirement: Conteúdo novo vai para o tenant do domínio
Banners, linhas de pesquisa, categorias, subcategorias, páginas, artigos, vídeos e estudos criados no painel SHALL receber o tenant do domínio acessado (`TenantContext`), e as configurações do site (`/admin/settings`) SHALL editar esse mesmo tenant.

#### Scenario: Banner criado no painel do Cisco de Deus
- **WHEN** um usuário cria um banner em `ciscodedeus.nepebrasil.org/admin`
- **THEN** o banner é gravado no tenant ciscodedeus

### Requirement: Listagens do painel sem filtro de tenant
As listagens do painel de banners, linhas de pesquisa, categorias, vídeos, estudos, artigos, mensagens de contato e páginas SHALL trazer os registros de todos os tenants, porque o filtro de tenant do Doctrine não vale em `/admin`. A lista de inscritos na newsletter e a de editores SHALL trazer só o tenant atual.

#### Scenario: Vídeos de dois NEPEs
- **WHEN** existem vídeos dos tenants renovandoconsciencias e nepealcione e um administrador abre `/admin/video`
- **THEN** a lista mostra os vídeos dos dois tenants

### Requirement: Editores do próprio NEPE
O painel SHALL criar usuários no tenant de quem está logado, com usuário, nome, e-mail, senha de no mínimo 8 caracteres e o grupo enviado (o formulário oferece só Editor, grupo 1), e SHALL excluir só usuários desse tenant. A lista SHALL mostrar só editores e revisores do tenant de quem está logado.

#### Scenario: Novo editor
- **WHEN** o administrador do tenant nepealcione cria um usuário pelo painel
- **THEN** o usuário é gravado no tenant nepealcione como editor

#### Scenario: Excluir usuário de outro NEPE
- **WHEN** um administrador tenta excluir um editor de outro tenant
- **THEN** nada é excluído e ele volta para a lista

### Requirement: Menu de gestão só para o grupo 0
O menu do painel SHALL mostrar "Editores" e as configurações do site só a usuários do grupo 0. As rotas `/admin/editors` e `/admin/settings` SHALL exigir apenas `ROLE_ADMIN`, que editores e revisores também têm.

#### Scenario: Editor abre a URL das configurações
- **WHEN** um editor, que não vê o item no menu, abre `/admin/settings` diretamente
- **THEN** a tela de configurações do site abre

### Requirement: Gestão pelo super admin
O super admin SHALL criar, editar e excluir tenants e usuários de qualquer tenant, e SHALL poder entrar no painel como outro usuário (`_switch_user`). Um usuário salvo com um tenant que não existe mais SHALL dar 404, para não virar super admin.

#### Scenario: Entrar como o administrador de um NEPE
- **WHEN** o super admin usa "entrar como" num administrador
- **THEN** ele é redirecionado para `/admin?_switch_user=<usuário>` e vê o painel como esse usuário

### Requirement: Redefinição de senha
O sistema SHALL enviar, a quem informa um e-mail cadastrado em `/reset-password`, um link para definir nova senha no domínio onde o pedido foi feito, válido por 1 hora, com no máximo um pedido a cada 15 minutos. Depois de definir a senha, SHALL levar ao login com uma mensagem de sucesso.

#### Scenario: Link vencido
- **WHEN** alguém abre um link de redefinição com mais de 1 hora
- **THEN** o sistema recusa o link e volta para o pedido de redefinição com a mensagem do motivo
