# Spec Delta

## Purpose
Permitir que cada pessoa que entra no painel de um NEPE mantenha os próprios dados em dia — nome, e-mail, usuário de login, WhatsApp de contato público e foto — e troque a própria senha, sem alcançar o cadastro de ninguém.

## ADDED Requirements

### Requirement: Painel do próprio perfil
O painel SHALL oferecer em `/admin/perfil` a tela do perfil do usuário logado, exigindo `ROLE_ADMIN` (todos os papéis que entram no painel: administrador do NEPE, editor, revisor e super admin). A tela SHALL mostrar e permitir alterar apenas os dados do usuário da sessão, sem aceitar id, usuário ou qualquer referência a outro usuário.

#### Scenario: Editor abre o perfil
- **WHEN** um editor do nepealcione, logado em `nepealcione.nepebrasil.org`, abre `/admin/perfil`
- **THEN** a tela abre com os dados dele preenchidos

#### Scenario: Revisor abre o perfil
- **WHEN** um revisor abre `/admin/perfil`
- **THEN** a tela abre com os dados dele, sem exigir `ROLE_NEPE_ADMIN`

#### Scenario: Visitante sem login
- **WHEN** alguém sem login abre `/admin/perfil`
- **THEN** é levado para `/login`

#### Scenario: Tentativa de editar outro usuário
- **WHEN** um usuário envia o formulário do perfil com o id ou o usuário de outra pessoa junto dos campos
- **THEN** só o usuário da sessão é alterado e o outro cadastro não muda

### Requirement: Item "Perfil" no menu do painel
O menu do painel SHALL trazer o item "Perfil", apontando para `/admin/perfil`, visível a todo usuário logado no painel, independente do grupo, e marcado como ativo quando a tela aberta é a do perfil.

#### Scenario: Menu do editor
- **WHEN** um editor abre qualquer tela do painel
- **THEN** o menu mostra "Perfil", e não mostra "Editores" nem "Configurações"

#### Scenario: Menu do administrador do NEPE
- **WHEN** o administrador do NEPE abre o painel
- **THEN** o menu mostra "Perfil" junto dos itens de gestão

### Requirement: Dados editáveis do perfil
O perfil SHALL permitir alterar nome, e-mail, usuário de login, WhatsApp e foto. Nome e usuário SHALL ser obrigatórios; e-mail, WhatsApp e foto SHALL ser opcionais e podem ficar vazios. O papel (grupo) e o NEPE (tenant) do usuário SHALL permanecer inalterados, mesmo que venham no envio do formulário.

#### Scenario: Correção do nome e do WhatsApp
- **WHEN** um editor salva o perfil com um nome novo e o WhatsApp preenchido
- **THEN** os dois são gravados e a tela confirma que o perfil foi salvo

#### Scenario: Grupo enviado à mão
- **WHEN** um editor envia o formulário do perfil com `workGroup=0`
- **THEN** o perfil é salvo sem alterar o grupo, e ele continua editor

#### Scenario: Nome em branco
- **WHEN** o usuário salva o perfil com o nome vazio
- **THEN** nada é gravado e a tela avisa que o nome é obrigatório

#### Scenario: E-mail apagado
- **WHEN** o usuário salva o perfil com o campo de e-mail vazio
- **THEN** o usuário fica sem e-mail gravado

### Requirement: Usuário de login e e-mail únicos
O perfil SHALL recusar um usuário de login ou um e-mail já usado por outra pessoa no sistema, sem gravar nenhuma alteração, com um aviso que diz qual dos dois está em uso. Manter o próprio usuário ou o próprio e-mail SHALL ser aceito.

#### Scenario: Usuário de login já existe
- **WHEN** o usuário salva o perfil com um usuário de login que pertence a outra pessoa
- **THEN** nada é gravado e o aviso diz que esse usuário de login já está em uso

#### Scenario: E-mail de outro NEPE
- **WHEN** o usuário salva um e-mail já cadastrado para alguém de outro NEPE
- **THEN** nada é gravado e o aviso diz que o e-mail já está em uso

#### Scenario: Salvar sem mexer no usuário
- **WHEN** o usuário salva o perfil mantendo o próprio usuário de login e o próprio e-mail
- **THEN** o perfil é salvo normalmente

### Requirement: Foto do perfil
O perfil SHALL aceitar o envio de uma imagem JPEG, PNG ou WebP de até 4 MB como foto do usuário, mostrar a foto atual na tela e oferecer a remoção dela. Um arquivo de outro tipo ou maior que o limite SHALL ser recusado com aviso, sem alterar a foto que já está gravada.

#### Scenario: Envio da foto
- **WHEN** o usuário escolhe um JPEG de 1 MB e salva o perfil
- **THEN** a foto é gravada e passa a aparecer na tela do perfil e no menu do painel

#### Scenario: Arquivo não é imagem
- **WHEN** o usuário envia um PDF no campo da foto
- **THEN** o perfil não é salvo, o aviso diz que o arquivo precisa ser uma imagem e a foto anterior continua

#### Scenario: Remoção da foto
- **WHEN** o usuário marca a remoção da foto e salva
- **THEN** o usuário fica sem foto e o painel volta a mostrar a inicial do nome

### Requirement: Troca de senha opcional no perfil
O perfil SHALL trazer dois campos de senha ("nova senha" e "repetir a nova senha"), cada um com um botão de olho que alterna entre ver e ocultar o que foi digitado. Com os dois campos em branco, salvar o perfil SHALL gravar os demais dados sem mudar a senha. Com qualquer um preenchido, a senha nova SHALL cumprir a política de senha e os dois campos SHALL ser iguais.

#### Scenario: Senha em branco
- **WHEN** o usuário salva o perfil com os dois campos de senha vazios
- **THEN** os outros dados são gravados e a senha antiga continua valendo

#### Scenario: Senha repetida diferente
- **WHEN** o usuário informa duas senhas diferentes
- **THEN** nada é gravado e o aviso diz que as senhas não são iguais

#### Scenario: Senha fora da política
- **WHEN** o usuário informa `segredo1` nos dois campos
- **THEN** nada é gravado e o aviso lista os requisitos não cumpridos

#### Scenario: Senha trocada
- **WHEN** o usuário informa a mesma senha válida nos dois campos
- **THEN** a senha nova passa a valer para o login

#### Scenario: Olho nos dois campos
- **WHEN** o usuário clica no olho do campo da nova senha
- **THEN** o conteúdo digitado nesse campo fica visível e o clique seguinte volta a ocultá-lo

### Requirement: Sessão encerrada ao mudar login ou senha
Quando o perfil é salvo com um usuário de login novo ou uma senha nova, o sistema SHALL gravar as alterações, encerrar a sessão e levar ao `/login` com a mensagem de que é preciso entrar de novo com os dados atualizados. Quando nenhum dos dois muda, o usuário SHALL continuar no painel, na tela do perfil, com a confirmação de que foi salvo.

#### Scenario: Troca de senha
- **WHEN** o usuário salva o perfil com uma senha nova válida
- **THEN** ele é levado ao `/login` com a mensagem para entrar de novo
- **AND** o login com a senha nova é aceito

#### Scenario: Troca do usuário de login
- **WHEN** o usuário salva o perfil com um usuário de login novo
- **THEN** ele é levado ao `/login` e entra com o usuário novo

#### Scenario: Só o nome mudou
- **WHEN** o usuário salva o perfil mudando apenas o nome
- **THEN** ele continua logado na tela do perfil, com a confirmação de que foi salvo

### Requirement: WhatsApp do perfil é dado público
O WhatsApp gravado no perfil SHALL ser tratado como dado de contato público do usuário, podendo ser publicado pelo site e pela API, e a tela SHALL avisar isso ao usuário. O e-mail e o usuário de login SHALL continuar servindo só ao acesso ao sistema.

#### Scenario: Aviso na tela
- **WHEN** o usuário abre a tela do perfil
- **THEN** o campo do WhatsApp traz a indicação de que ele pode aparecer publicamente no site
