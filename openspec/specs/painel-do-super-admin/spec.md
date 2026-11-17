# painel-do-super-admin Specification

## Purpose
A visão da rede inteira em `/superadmin`: como os NEPEs e os usuários de todos eles são listados, ordenados, agrupados e identificados, para que o super admin encontre um cadastro e perceba um cadastro errado sem precisar abrir cada registro.

## Requirements

### Requirement: Ordem das listas

O painel SHALL listar os NEPEs em ordem alfabética pelo nome e, dentro de cada grupo de usuários, os usuários em ordem alfabética pelo nome, com os que estão sem nome no fim do grupo. A ordem SHALL ser a mesma a cada abertura da página.

#### Scenario: NEPEs fora de ordem de cadastro

- **GIVEN** que o NEPE Alcíone foi cadastrado depois do NEPE Cisco de Deus
- **WHEN** o super admin abre `/superadmin`
- **THEN** o NEPE Alcíone aparece antes do NEPE Cisco de Deus na lista de NEPEs

#### Scenario: Usuário sem nome

- **GIVEN** um usuário sem nome preenchido no mesmo NEPE de outros usuários
- **WHEN** o super admin abre a lista de usuários
- **THEN** esse usuário aparece no fim do grupo do NEPE dele

### Requirement: Usuários agrupados por NEPE

O painel SHALL agrupar a lista de usuários por NEPE, com um cabeçalho por grupo trazendo o nome do NEPE, a logo dele e a quantidade de usuários do grupo. Um usuário SHALL aparecer uma única vez, no grupo do NEPE a que pertence.

#### Scenario: Usuários do mesmo NEPE espalhados pela ordem de cadastro

- **GIVEN** quatro usuários do NEPE Alcíone cadastrados em momentos diferentes, entre usuários de outros NEPEs
- **WHEN** o super admin abre a lista de usuários
- **THEN** os quatro aparecem juntos, sob o cabeçalho do NEPE Alcíone

#### Scenario: NEPE sem logo cadastrada

- **GIVEN** um NEPE que não tem logo
- **WHEN** o grupo dele é exibido
- **THEN** o cabeçalho mostra o nome do NEPE sem imagem, e o grupo continua sendo exibido

#### Scenario: NEPE sem usuários

- **GIVEN** um NEPE sem nenhum usuário
- **WHEN** o super admin abre a lista de usuários
- **THEN** o NEPE aparece com a contagem zero, para que a ausência de administrador seja percebida

### Requirement: Grupo dos usuários sem NEPE

O painel SHALL reunir num grupo próprio, separado dos NEPEs, os usuários sem tenant, e SHALL exibir esse grupo por último.

#### Scenario: Super admin na lista

- **GIVEN** o usuário `superadmin`, sem tenant e do grupo 0
- **WHEN** o super admin abre a lista de usuários
- **THEN** ele aparece no grupo dos usuários sem NEPE, e não dentro de nenhum NEPE

### Requirement: Foto do usuário na lista

O painel SHALL mostrar a foto de cada usuário ao lado do nome. Quando o usuário não tem foto, SHALL mostrar as iniciais do nome; quando também não tem nome, um marcador neutro.

#### Scenario: Usuário com foto

- **GIVEN** um usuário com foto cadastrada
- **WHEN** a lista é exibida
- **THEN** a foto dele aparece na linha, recortada em tamanho único, igual para todas as linhas

#### Scenario: Usuário sem foto

- **GIVEN** o usuário "Silvia Andrea Mause de Paula", sem foto
- **WHEN** a lista é exibida
- **THEN** a linha mostra as iniciais "SM" no lugar da foto

### Requirement: Foto definida pelo super admin

O super admin SHALL poder enviar e remover a foto de qualquer usuário na tela de edição do usuário, com a foto atual visível. SHALL valer a mesma regra de arquivo do perfil do próprio usuário: JPEG, PNG ou WebP de até 4 MB, e outro tipo ou tamanho maior SHALL ser recusado com aviso, sem alterar a foto já gravada.

#### Scenario: Envio de foto

- **WHEN** o super admin envia um PNG de 1 MB na edição de um usuário e salva
- **THEN** a foto passa a aparecer na lista de usuários e na tela de edição desse usuário

#### Scenario: Arquivo maior que o limite

- **WHEN** o super admin envia uma imagem de 6 MB
- **THEN** o sistema recusa com o motivo e mantém a foto anterior do usuário

#### Scenario: Arquivo que não é imagem

- **WHEN** o super admin envia um PDF como foto
- **THEN** o sistema recusa com o motivo e mantém a foto anterior do usuário

#### Scenario: Remoção da foto

- **WHEN** o super admin marca a opção de remover a foto e salva
- **THEN** o usuário volta a aparecer com as iniciais na lista

### Requirement: Rótulo do papel conforme os papéis reais

O painel SHALL rotular cada usuário pelo papel que ele realmente tem: "Super Admin" somente quando o usuário está sem NEPE **e** no grupo 0; "Admin" no grupo 0 com NEPE; "Editor" no grupo 1; e "Revisor" no grupo 2.

#### Scenario: Editor sem NEPE não é super admin

- **GIVEN** um usuário sem tenant e do grupo 1
- **WHEN** a lista é exibida
- **THEN** ele NÃO é rotulado como "Super Admin"

#### Scenario: Revisor com NEPE

- **GIVEN** um usuário do grupo 2 ligado a um NEPE
- **WHEN** a lista é exibida
- **THEN** ele é rotulado como "Revisor"

### Requirement: Cadastro inconsistente destacado

O painel SHALL destacar como cadastro inconsistente o usuário sem NEPE que não é do grupo 0, explicando que ele não consegue entrar em nenhum painel, porque o login só aceita usuário do tenant do domínio ou super admin.

#### Scenario: Usuário órfão

- **GIVEN** um usuário sem tenant e do grupo 2
- **WHEN** a lista é exibida
- **THEN** a linha dele aparece destacada como inconsistente, com a explicação de que ele não consegue entrar em painel nenhum

### Requirement: Dados de identificação na lista

A lista de usuários SHALL mostrar, em cada linha, o nome, o usuário de login e o e-mail. Quando o usuário não tem e-mail cadastrado, a ausência SHALL ficar visível.

#### Scenario: Usuário com e-mail

- **GIVEN** o usuário `nepealcione@nepebrasil.org`
- **WHEN** a lista é exibida
- **THEN** o e-mail dele aparece na linha, sem precisar abrir a edição

#### Scenario: Usuário sem e-mail

- **GIVEN** um usuário cadastrado sem e-mail
- **WHEN** a lista é exibida
- **THEN** a linha mostra que não há e-mail, em vez de um espaço vazio

### Requirement: Busca nas listas

O painel SHALL oferecer uma busca que filtra NEPEs por nome e domínio e usuários por nome, usuário de login e e-mail, sem diferenciar maiúsculas nem acentos. Com a busca preenchida, SHALL mostrar apenas o que corresponde, mantendo o agrupamento por NEPE.

#### Scenario: Busca por parte do nome

- **WHEN** o super admin busca "alcione"
- **THEN** a lista mostra o NEPE Alcíone e os usuários dele, e esconde os demais

#### Scenario: Busca sem acento

- **WHEN** o super admin busca "alcione" e o NEPE está cadastrado como "NEPE Alcíone"
- **THEN** o NEPE é encontrado

#### Scenario: Busca sem resultado

- **WHEN** o super admin busca um texto que não corresponde a nada
- **THEN** o painel informa que nada foi encontrado e oferece limpar a busca

### Requirement: Contagem de usuários por NEPE

A lista de NEPEs SHALL mostrar, em cada NEPE, quantos usuários ele tem, e o NEPE sem nenhum usuário SHALL ficar visualmente destacado, porque fica sem quem administre o site.

#### Scenario: NEPE sem administrador

- **GIVEN** um NEPE cadastrado sem nenhum usuário
- **WHEN** a lista de NEPEs é exibida
- **THEN** ele aparece com zero usuários e destacado

### Requirement: Acesso restrito ao super admin

Todas as telas e ações deste painel SHALL exigir `ROLE_SUPER_ADMIN`. Um usuário sem esse papel SHALL receber 403, inclusive nas rotas de busca e de exportação.

#### Scenario: Administrador de NEPE tenta abrir o painel global

- **GIVEN** um usuário do grupo 0 ligado a um NEPE
- **WHEN** ele abre `/superadmin`
- **THEN** o sistema responde 403
