# politica-de-senha Specification

## Purpose
Definir o que o sistema exige de uma senha em qualquer ponto onde ela é criada ou trocada, e como os requisitos são mostrados a quem está digitando, para que a mesma regra valha no painel, no cadastro de usuários e na redefinição por e-mail.

## Requirements

### Requirement: Requisitos de uma senha aceita
O sistema SHALL aceitar uma senha somente quando ela tem mais de 8 caracteres (ao menos 9), ao menos uma letra minúscula, ao menos uma letra maiúscula e ao menos um caractere especial (qualquer caractere que não seja letra nem dígito), com no máximo 4096 caracteres. Uma senha que não cumpre todos os requisitos SHALL ser recusada sem gravar nada.

#### Scenario: Senha válida
- **WHEN** alguém define a senha `Nepe2026!`
- **THEN** a senha é aceita

#### Scenario: Oito caracteres não bastam
- **WHEN** alguém define a senha `Nepe26!x` (8 caracteres)
- **THEN** a senha é recusada por ser curta

#### Scenario: Sem maiúscula
- **WHEN** alguém define a senha `nepe2026!`
- **THEN** a senha é recusada e o aviso cita a letra maiúscula

#### Scenario: Sem minúscula
- **WHEN** alguém define a senha `NEPE2026!`
- **THEN** a senha é recusada e o aviso cita a letra minúscula

#### Scenario: Sem caractere especial
- **WHEN** alguém define a senha `Nepe20261`
- **THEN** a senha é recusada e o aviso cita o caractere especial

#### Scenario: Espaço vale como caractere especial
- **WHEN** alguém define a senha `Nepe 2026 arc`
- **THEN** a senha é aceita, porque o espaço não é letra nem dígito

### Requirement: Lista de requisitos na tela
Toda tela que define ou troca senha SHALL mostrar a lista dos requisitos da senha, com o estado de cada item (cumprido ou pendente) e, quando há campo de repetição, o item de que as duas senhas são iguais.

#### Scenario: Lista visível antes de digitar
- **WHEN** a tela de senha abre
- **THEN** os requisitos aparecem listados, todos pendentes

### Requirement: Conferência enquanto digita
As telas que definem senha SHALL conferir os requisitos em JavaScript a cada tecla digitada, marcando cada item como cumprido ou pendente e indicando se as duas senhas conferem, sem recarregar a página. Enquanto algum requisito estiver pendente ou as senhas diferirem, o botão de salvar SHALL ficar indisponível.

#### Scenario: Item cumprido ao digitar
- **WHEN** o usuário digita a nona letra da senha
- **THEN** o item do tamanho passa a cumprido na mesma hora

#### Scenario: Repetição conferida ao digitar
- **WHEN** o usuário termina de digitar no campo de repetição um valor diferente do primeiro
- **THEN** a tela aponta na mesma hora que as senhas não são iguais

#### Scenario: Botão liberado
- **WHEN** todos os requisitos ficam cumpridos e as duas senhas são iguais
- **THEN** o botão de salvar fica disponível

#### Scenario: Navegador sem JavaScript
- **WHEN** o formulário é enviado por um navegador que não executou o JavaScript, com uma senha fora da política
- **THEN** o servidor recusa a senha e mostra os requisitos não cumpridos

### Requirement: Mesma política em todo ponto de senha
A política SHALL valer na troca de senha pelo perfil, na criação de usuário pelo painel do NEPE, na criação e edição de usuários pelo super admin e na redefinição de senha pelo link enviado por e-mail. Senhas gravadas antes desta mudança SHALL continuar válidas para login, sem troca forçada.

#### Scenario: Novo editor com senha fraca
- **WHEN** o administrador do NEPE cria um usuário com a senha `editor123`
- **THEN** o usuário não é criado e o aviso lista os requisitos não cumpridos

#### Scenario: Redefinição por e-mail
- **WHEN** alguém abre o link de redefinição e informa `trocando123`
- **THEN** a senha não é gravada e os requisitos não cumpridos são mostrados

#### Scenario: Senha antiga continua entrando
- **WHEN** um usuário cuja senha foi definida antes desta mudança faz login com ela
- **THEN** o login é aceito
