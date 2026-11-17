# Spec Delta

## Purpose

A saída dos NEPEs cadastrados num arquivo CSV que serve de cópia de segurança do cadastro e que a própria importação lê de volta, para que a rede possa ser conferida, editada em planilha e restaurada sem digitação.

## ADDED Requirements

### Requirement: Exportação dos NEPEs em CSV

O painel do super admin SHALL oferecer o download de um CSV com todos os NEPEs cadastrados, uma linha por NEPE, gerado no momento do pedido. O arquivo MUST NOT ficar guardado no servidor depois do download.

#### Scenario: Download da exportação

- **WHEN** o super admin pede a exportação
- **THEN** o navegador baixa um CSV com uma linha para cada NEPE cadastrado, além da linha de cabeçalho

#### Scenario: Nome do arquivo

- **WHEN** a exportação é gerada
- **THEN** o nome do arquivo traz a data do dia, para que exportações de dias diferentes não se sobrescrevam

#### Scenario: Nenhum NEPE cadastrado

- **GIVEN** que não há NEPE cadastrado
- **THEN** o arquivo vem só com a linha de cabeçalho, em vez de erro

### Requirement: Colunas iguais às da importação

O CSV exportado SHALL usar como cabeçalho os mesmos títulos que a importação reconhece, na ordem em que a importação os descreve: nome da instituição, domínio, tema, cor principal, cor secundária, logo (fundo claro), logo (fundo escuro), nome completo, e-mail e WhatsApp. O arquivo SHALL ser UTF-8, sem marca BOM, com vírgula como separador.

#### Scenario: Cabeçalho reconhecido pela importação

- **WHEN** o arquivo exportado é enviado na tela de importação
- **THEN** a importação reconhece todas as colunas, sem erro de coluna obrigatória não encontrada

#### Scenario: Valores que contêm vírgula

- **GIVEN** um NEPE cujo nome contém vírgula
- **WHEN** o arquivo é gerado
- **THEN** o valor sai entre aspas, de modo que a importação leia o nome inteiro num campo só

### Requirement: Conteúdo exportado de cada NEPE

Cada linha SHALL trazer os dados do NEPE e os do administrador dele. Quando o NEPE tem mais de um administrador, SHALL ser exportado o mais antigo; quando não tem nenhum, as colunas do administrador SHALL sair vazias.

#### Scenario: NEPE com um administrador

- **GIVEN** o NEPE Alcíone, com a administradora Silvia Andrea
- **WHEN** a exportação é gerada
- **THEN** a linha traz o nome, o domínio, o tema e as cores do NEPE e o nome e o e-mail da Silvia Andrea

#### Scenario: NEPE com vários administradores

- **GIVEN** um NEPE com dois usuários do grupo 0
- **WHEN** a exportação é gerada
- **THEN** sai uma única linha para esse NEPE, com o administrador mais antigo

#### Scenario: Editores e revisores não saem

- **GIVEN** um NEPE com um administrador e três editores
- **WHEN** a exportação é gerada
- **THEN** a linha traz apenas o administrador, e a tela avisa que a exportação cobre os NEPEs e os administradores, não todos os usuários

#### Scenario: WhatsApp cadastrado

- **GIVEN** um NEPE com o telefone `(19) 99999-0000`
- **WHEN** a exportação é gerada
- **THEN** a coluna WhatsApp traz um número que a importação volta a reconhecer

### Requirement: Logo exportada como endereço público

As colunas de logo SHALL trazer o endereço público do arquivo no site do próprio NEPE, de modo que a importação consiga baixá-lo. Quando o NEPE não tem logo, a coluna SHALL sair vazia.

#### Scenario: NEPE com logo

- **GIVEN** um NEPE com logo de fundo claro cadastrada
- **WHEN** a exportação é gerada
- **THEN** a coluna traz o endereço completo da logo, com o domínio do NEPE

#### Scenario: NEPE sem logo escura

- **GIVEN** um NEPE que tem só a logo de fundo claro
- **WHEN** a exportação é gerada
- **THEN** a coluna da logo de fundo escuro sai vazia e a linha continua válida

### Requirement: Ida e volta sem duplicar

O arquivo exportado e reenviado sem alteração SHALL ser aceito pela importação e MUST NOT criar NEPE nem usuário repetido. A linha de um NEPE com domínio válido e administrador SHALL aparecer na prévia como já importada; a linha que a importação não aceita SHALL ser recusada na prévia com o motivo, sem gravar nada.

#### Scenario: Reimportação do arquivo exportado

- **GIVEN** um CSV recém-exportado, sem nenhuma alteração
- **WHEN** o super admin o envia na tela de importação e confirma
- **THEN** nenhum NEPE novo é criado
- **AND** cada NEPE com domínio válido e administrador aparece como "Já importado"

#### Scenario: Tenants de desenvolvimento no arquivo

- **GIVEN** os tenants `localhost` e `127.0.0.1`, que existem no banco e cujos domínios a importação nunca aceitou
- **WHEN** o arquivo exportado é reimportado
- **THEN** essas linhas aparecem na prévia como recusadas, com o motivo do domínio inválido
- **AND** nenhum NEPE é criado ou alterado por causa delas

#### Scenario: NEPE sem administrador no arquivo

- **GIVEN** um NEPE exportado com as colunas do administrador vazias
- **WHEN** o arquivo é reimportado
- **THEN** a linha é recusada na prévia por faltar nome e e-mail do administrador
- **AND** o NEPE que já existe continua como está

#### Scenario: Linha editada na planilha

- **GIVEN** o CSV exportado com uma linha nova, acrescentada à mão
- **WHEN** o arquivo é importado
- **THEN** só o NEPE da linha nova é criado, e os demais continuam como já importados

### Requirement: Exportação restrita e registrada como dado pessoal

A exportação SHALL exigir `ROLE_SUPER_ADMIN` e a tela SHALL avisar que o arquivo contém nome e e-mail dos administradores e deve ser tratado como dado pessoal.

#### Scenario: Usuário sem permissão

- **GIVEN** um administrador de NEPE autenticado
- **WHEN** ele chama a rota da exportação direto pelo endereço
- **THEN** o sistema responde 403 e nenhum arquivo é gerado

#### Scenario: Aviso na tela

- **WHEN** o super admin vê o botão de exportar
- **THEN** há junto a informação de que o arquivo traz dados pessoais dos administradores
