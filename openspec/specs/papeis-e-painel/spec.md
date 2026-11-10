# papeis-e-painel Specification

## Purpose
Define quem entra no painel e o que cada papel alcança: super admin, administrador, editor e revisor de um NEPE. Há um único login para todos os domínios, e o papel vem do grupo (`workGroup`) e do tenant do usuário. Implementado em `Entity/User::getRoles()`, `config/packages/security.yaml`, `admin/AdminContentController`, `superadmin/SuperAdminController` e `ResetPasswordController`. Os requisitos sobre o alcance do painel entre tenants descrevem o código de 09/11/2026 e estão como dúvida aberta no relatório da adoção do OpenSpec.

## Requirements

### Requirement: Papéis pelo grupo e pelo tenant
O sistema SHALL dar `ROLE_SUPER_ADMIN`, `ROLE_NEPE_ADMIN` e `ROLE_ADMIN` ao usuário sem tenant do grupo 0; `ROLE_NEPE_ADMIN` e `ROLE_ADMIN` ao usuário com tenant do grupo 0 (administrador); e `ROLE_EDITOR` mais `ROLE_ADMIN` aos grupos 1 (editor) e 2 (revisor).

#### Scenario: Revisor entra no painel
- **WHEN** um usuário do grupo 2 faz login
- **THEN** ele acessa `/admin`, mas não `/superadmin`
- **AND** ele não recebe `ROLE_NEPE_ADMIN`

#### Scenario: Administrador do NEPE
- **WHEN** um usuário do grupo 0 com tenant faz login no domínio do seu NEPE
- **THEN** ele recebe `ROLE_NEPE_ADMIN` e `ROLE_ADMIN`, mas não `ROLE_SUPER_ADMIN`

### Requirement: Áreas protegidas
O sistema SHALL exigir `ROLE_SUPER_ADMIN` em `/superadmin` e `ROLE_ADMIN` em `/admin`, com login por usuário e senha em `/login` e token CSRF no formulário.

#### Scenario: Visitante sem login
- **WHEN** um visitante abre `/admin`
- **THEN** ele é levado para `/login`

### Requirement: Conteúdo novo vai para o tenant do domínio
Banners, linhas de pesquisa, categorias, subcategorias, páginas, artigos, vídeos e estudos criados no painel SHALL receber o tenant do domínio acessado (`TenantContext`), e as configurações do site (`/admin/settings`) SHALL editar esse mesmo tenant.

#### Scenario: Banner criado no painel do Cisco de Deus
- **WHEN** um usuário cria um banner em `ciscodedeus.nepebrasil.org/admin`
- **THEN** o banner é gravado no tenant ciscodedeus

### Requirement: Editores do próprio NEPE
O painel SHALL criar usuários no tenant do domínio acessado, com usuário, nome, e-mail, senha de no mínimo 8 caracteres e o grupo escolhido, que SHALL ser Editor (grupo 1) ou Revisor (grupo 2); qualquer outro grupo SHALL ser recusado sem gravar. O formulário SHALL oferecer as duas opções. A exclusão SHALL alcançar só editores e revisores desse tenant, e a lista SHALL mostrar só eles.

#### Scenario: Novo editor
- **WHEN** o administrador do tenant nepealcione cria um usuário pelo painel escolhendo Editor
- **THEN** o usuário é gravado no tenant nepealcione como editor

#### Scenario: Novo revisor
- **WHEN** o administrador do tenant nepealcione cria um usuário pelo painel escolhendo Revisor
- **THEN** o usuário é gravado no tenant nepealcione como revisor

#### Scenario: Grupo 0 enviado à mão
- **WHEN** o administrador envia o formulário de novo usuário com `workGroup=0`
- **THEN** o usuário não é criado e o aviso diz que o grupo é inválido

#### Scenario: Super admin cria um editor
- **WHEN** um super admin cria um editor pelo painel de `ciscodedeus.nepebrasil.org`
- **THEN** o editor é gravado no tenant ciscodedeus, e não sem tenant

#### Scenario: Excluir usuário de outro NEPE
- **WHEN** um administrador tenta excluir um editor de outro tenant
- **THEN** nada é excluído e ele volta para a lista

#### Scenario: Excluir outro administrador
- **WHEN** um administrador tenta excluir um usuário do grupo 0 do mesmo tenant
- **THEN** nada é excluído e ele volta para a lista

### Requirement: Menu de gestão só para o grupo 0
O menu do painel SHALL mostrar "Editores" e as configurações do site só a usuários do grupo 0. As rotas `/admin/editors` (listar, criar e excluir) e `/admin/settings` (ver e salvar) SHALL exigir `ROLE_NEPE_ADMIN` e responder 403 a editores e revisores.

#### Scenario: Editor abre a URL das configurações
- **WHEN** um editor, que não vê o item no menu, abre `/admin/settings` diretamente
- **THEN** a resposta é 403

#### Scenario: Revisor muda o número de aprovações à mão
- **WHEN** um revisor envia por POST para `/admin/settings` um novo `requiredApprovals`
- **THEN** a resposta é 403 e o número de aprovações do tenant não muda

#### Scenario: Editor cria um usuário à mão
- **WHEN** um editor envia por POST para `/admin/editors/new` um usuário com `workGroup=0`
- **THEN** a resposta é 403 e nenhum usuário é criado

#### Scenario: Administrador abre as configurações
- **WHEN** o administrador do NEPE abre `/admin/settings`
- **THEN** a tela de configurações do site do tenant do domínio abre

### Requirement: Gestão pelo super admin
O super admin SHALL criar, editar e excluir tenants e usuários de qualquer tenant, e SHALL poder entrar no painel como outro usuário (`_switch_user`) no domínio do NEPE desse usuário (`https://<domínio>/admin?_switch_user=<usuário>`, com `http://` para `localhost` e `127.0.0.1`); para um usuário sem tenant, no domínio atual. Um usuário salvo com um tenant que não existe mais SHALL dar 404, para não virar super admin.

#### Scenario: Entrar como o administrador de um NEPE
- **WHEN** o super admin, no painel global aberto em renovandoconsciencias, usa "entrar como" no administrador do nepealcione
- **THEN** ele é redirecionado para `https://nepealcione.nepebrasil.org/admin?_switch_user=<usuário>`
- **AND** se ainda não tem sessão nesse domínio, faz login lá e volta para o mesmo endereço, vendo o painel como esse usuário

#### Scenario: Entrar como no domínio errado
- **WHEN** o super admin abre à mão `https://ueebp.nepebrasil.org/admin?_switch_user=<usuário do nepealcione>`
- **THEN** a resposta é 403 e a troca não acontece
- **AND** a sessão do super admin em ueebp continua aberta

### Requirement: Redefinição de senha
O sistema SHALL enviar, a quem informa um e-mail cadastrado em `/reset-password`, um link para definir nova senha no domínio do NEPE do usuário (no domínio onde o pedido foi feito, quando o usuário é super admin), válido por 1 hora, com no máximo um pedido a cada 15 minutos. Depois de definir a senha, SHALL levar ao login com uma mensagem de sucesso.

#### Scenario: Link vencido
- **WHEN** alguém abre um link de redefinição com mais de 1 hora
- **THEN** o sistema recusa o link e volta para o pedido de redefinição com a mensagem do motivo

#### Scenario: Pedido feito no domínio de outro NEPE
- **WHEN** um editor do nepealcione pede a redefinição em `https://ueebp.nepebrasil.org/reset-password`
- **THEN** o link do e-mail aponta para `https://nepealcione.nepebrasil.org/reset-password/reset/<token>`

### Requirement: Login só no domínio do próprio NEPE
O login SHALL ser aceito só quando o usuário é do tenant do domínio acessado ou é super admin. Com usuário e senha corretos no domínio de outro NEPE, ou de um usuário que não é super admin e não tem tenant, o sistema SHALL recusar o login com uma mensagem que traz o endereço de login do NEPE do usuário, quando ele tem um. Com senha errada, a mensagem SHALL ser a de credenciais inválidas de sempre.

#### Scenario: Administrador entra pelo domínio de outro NEPE
- **WHEN** o administrador do renovandoconsciencias informa usuário e senha corretos em `https://nepealcione.nepebrasil.org/login`
- **THEN** o login é recusado com "Este usuário é de outro NEPE. Entre pelo endereço do seu site: https://renovandoconsciencias.nepebrasil.org/login"
- **AND** nenhuma sessão é aberta em nepealcione

#### Scenario: Senha errada no domínio de outro NEPE
- **WHEN** alguém informa o usuário do administrador do renovandoconsciencias com a senha errada em `https://nepealcione.nepebrasil.org/login`
- **THEN** a mensagem é a de credenciais inválidas, sem citar o NEPE do usuário

#### Scenario: Super admin em qualquer domínio
- **WHEN** um super admin faz login em `https://ciscodedeus.nepebrasil.org/login`
- **THEN** o login é aceito e ele entra no `/admin` do ciscodedeus

### Requirement: Sessão de outro NEPE encerrada no painel
Quando um usuário logado que não é super admin abre `/admin` num domínio cujo tenant não é o dele, o sistema SHALL encerrar a sessão nesse domínio e responder 403 com uma página que diz de qual NEPE é o acesso dele e traz o link do painel desse NEPE. Isso cobre sessões abertas antes desta mudança e a troca de tenant feita pelo super admin com o usuário logado.

#### Scenario: Sessão aberta antes da mudança
- **WHEN** um editor do nepealcione, logado em `ueebp.nepebrasil.org` antes do deploy, abre `ueebp.nepebrasil.org/admin`
- **THEN** a resposta é 403 com o link `https://nepealcione.nepebrasil.org/admin`
- **AND** a sessão dele em ueebp é encerrada

### Requirement: Painel restrito ao NEPE do domínio
No painel, listagens, contagens do dashboard, telas de edição, exclusões, reordenações, envio para aprovação e aprovação SHALL alcançar só registros do tenant do domínio. Uma rota com o id de um registro de outro tenant SHALL responder 404 sem alterar nada, e uma reordenação SHALL ignorar os ids de outro tenant.

#### Scenario: Mensagens de contato
- **WHEN** o administrador do ciscodedeus abre `/admin/contact` e existem mensagens de contato de vários NEPEs
- **THEN** a lista e o número de não lidas do dashboard trazem só as mensagens do ciscodedeus

#### Scenario: Edição pelo id de outro NEPE
- **WHEN** um usuário do nepealcione abre `nepealcione.nepebrasil.org/admin/banner/<id>/edit` com o id de um banner do renovandoconsciencias
- **THEN** a resposta é 404 e o banner não muda

#### Scenario: Exclusão pelo id de outro NEPE
- **WHEN** um usuário envia por POST a exclusão de um vídeo de outro tenant, com token CSRF válido
- **THEN** a resposta é 404 e nada é excluído

#### Scenario: Reordenação com ids misturados
- **WHEN** a reordenação de banners recebe ids do NEPE do domínio e de outro NEPE
- **THEN** só os banners do NEPE do domínio mudam de posição

### Requirement: Itens sem tenant próprio seguem o dono
Seções, blocos (com imagens, depoimentos, logos e membros de equipe), arquivos de materiais e aprovações SHALL pertencer ao tenant da página, da categoria ou do conteúdo a que estão ligados. Ver, criar, editar ou excluir um desses itens pelo painel de outro tenant SHALL responder 404, e uma reordenação SHALL ignorar os itens de outro tenant.

#### Scenario: Bloco de uma página de outro NEPE
- **WHEN** um usuário do ueebp abre `ueebp.nepebrasil.org/admin/block/<id>/edit` com o id de um bloco de uma página do renovandoconsciencias
- **THEN** a resposta é 404 e o bloco não muda

#### Scenario: Nova seção numa categoria de outro NEPE
- **WHEN** um usuário envia o formulário de nova seção para a categoria de outro tenant
- **THEN** a resposta é 404 e nenhuma seção é criada

### Requirement: Referências só do NEPE do painel
Ao gravar vídeo, estudo, artigo ou bloco, a categoria ou a categoria relacionada enviada SHALL ser do tenant do domínio. Um id de outro tenant ou inexistente SHALL responder 404 sem gravar nada. As listas de escolha dos formulários do painel SHALL mostrar só categorias e páginas desse tenant.

#### Scenario: Categoria de outro NEPE enviada à mão
- **WHEN** um usuário do nepealcione salva um vídeo com o id de uma categoria do renovandoconsciencias
- **THEN** a resposta é 404 e o vídeo não é gravado

#### Scenario: Lista de categorias do formulário
- **WHEN** um usuário do nepealcione abre o formulário de novo vídeo
- **THEN** a lista de categorias traz só as categorias do nepealcione

### Requirement: Mensagens internas do próprio NEPE
O destinatário de uma mensagem nova SHALL ser usuário do tenant do domínio. Uma conversa é do NEPE quando o remetente ou o destinatário da mensagem inicial é usuário dele. Ler, responder ou mudar o estado de uma conversa SHALL ser permitido a quem participa dela e ao administrador do NEPE, se a conversa for do NEPE; a visão geral do administrador SHALL listar só as conversas do NEPE.

#### Scenario: Destinatário de outro NEPE
- **WHEN** um editor do nepealcione envia uma mensagem com o id de um usuário do renovandoconsciencias
- **THEN** a resposta é 404 e nada é enviado

#### Scenario: Visão geral do administrador
- **WHEN** o administrador do renovandoconsciencias abre `/admin/message`
- **THEN** a aba de todas as conversas mostra só conversas do renovandoconsciencias

#### Scenario: Conversa de outro NEPE pelo id
- **WHEN** o administrador do ciscodedeus abre `/admin/message/<id>` de uma conversa entre usuários do nepealcione
- **THEN** a resposta é 404

### Requirement: Super admin no painel de qualquer NEPE
O super admin SHALL entrar no painel por qualquer domínio cadastrado. Em cada um, SHALL ver, criar e alterar os registros do NEPE desse domínio, com as mesmas restrições de tenant dos demais usuários. Para trabalhar em outro NEPE, ele usa o domínio desse NEPE ou o `/superadmin`.

#### Scenario: Super admin no painel do nepealcione
- **WHEN** um super admin abre `nepealcione.nepebrasil.org/admin/video`
- **THEN** a lista mostra só os vídeos do nepealcione
