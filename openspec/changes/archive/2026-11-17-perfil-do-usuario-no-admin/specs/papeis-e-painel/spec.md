# Spec Delta

## MODIFIED Requirements

### Requirement: Editores do próprio NEPE
O painel SHALL criar usuários no tenant do domínio acessado, com usuário, nome, e-mail, senha que cumpre a política de senha e o grupo escolhido, que SHALL ser Editor (grupo 1) ou Revisor (grupo 2); qualquer outro grupo SHALL ser recusado sem gravar. O formulário SHALL oferecer as duas opções. A exclusão SHALL alcançar só editores e revisores desse tenant, e a lista SHALL mostrar só eles.

#### Scenario: Novo editor
- **WHEN** o administrador do tenant nepealcione cria um usuário pelo painel escolhendo Editor
- **THEN** o usuário é gravado no tenant nepealcione como editor

#### Scenario: Novo revisor
- **WHEN** o administrador do tenant nepealcione cria um usuário pelo painel escolhendo Revisor
- **THEN** o usuário é gravado no tenant nepealcione como revisor

#### Scenario: Senha fora da política
- **WHEN** o administrador cria um usuário com uma senha que não cumpre a política de senha
- **THEN** o usuário não é criado e o aviso lista os requisitos não cumpridos

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

### Requirement: Redefinição de senha
O sistema SHALL enviar, a quem informa um e-mail cadastrado em `/reset-password`, um link para definir nova senha no domínio do NEPE do usuário (no domínio onde o pedido foi feito, quando o usuário é super admin), válido por 1 hora, com no máximo um pedido a cada 15 minutos. A senha definida pelo link SHALL cumprir a política de senha. Depois de definir a senha, SHALL levar ao login com uma mensagem de sucesso.

#### Scenario: Link vencido
- **WHEN** alguém abre um link de redefinição com mais de 1 hora
- **THEN** o sistema recusa o link e volta para o pedido de redefinição com a mensagem do motivo

#### Scenario: Pedido feito no domínio de outro NEPE
- **WHEN** um editor do nepealcione pede a redefinição em `https://ueebp.nepebrasil.org/reset-password`
- **THEN** o link do e-mail aponta para `https://nepealcione.nepebrasil.org/reset-password/reset/<token>`

#### Scenario: Senha nova fora da política
- **WHEN** alguém usa um link válido e informa uma senha que não cumpre a política de senha
- **THEN** a senha não é gravada, o link continua válido e os requisitos não cumpridos são mostrados

### Requirement: Menu de gestão só para o grupo 0
O menu do painel SHALL mostrar "Editores" e as configurações do site só a usuários do grupo 0, e SHALL mostrar "Perfil" a todos os papéis do painel. As rotas `/admin/editors` (listar, criar e excluir) e `/admin/settings` (ver e salvar) SHALL exigir `ROLE_NEPE_ADMIN` e responder 403 a editores e revisores.

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

#### Scenario: Editor vê o perfil no menu
- **WHEN** um editor abre o painel
- **THEN** o menu traz "Perfil" e não traz "Editores" nem "Configurações"
