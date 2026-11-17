# Spec Delta

## MODIFIED Requirements

### Requirement: Tema, cores e logos
O tema SHALL ser `moderno` quando a resposta começa com "moderno" e `nepe` nos demais casos. Cor inválida SHALL manter a cor padrão, com aviso. Link do Google Drive SHALL virar link de download; a logo SHALL ter até 5 MB e ser PNG, JPG, WebP ou GIF pelo conteúdo do arquivo, senão o NEPE é criado sem ela e o motivo aparece no resultado. O limite de 5 MB SHALL ser aplicado durante o recebimento do arquivo, de modo que um arquivo maior seja interrompido em vez de carregado inteiro.

#### Scenario: Pasta do Drive sem compartilhamento
- **WHEN** o link da logo devolve a página de login do Google
- **THEN** o NEPE é cadastrado sem a logo
- **AND** o resultado pede para compartilhar a pasta como "Qualquer pessoa com o link"

#### Scenario: Arquivo grande sem tamanho informado
- **GIVEN** um link de logo cujo servidor não informa o tamanho do arquivo
- **WHEN** o arquivo passa de 5 MB durante o download
- **THEN** o download é interrompido antes de terminar
- **AND** o NEPE é cadastrado sem a logo, com o motivo no resultado

### Requirement: Tenant e administrador criados juntos
Para cada linha válida, o sistema SHALL criar o tenant e um administrador do grupo 0 ligado a ele, com usuário e e-mail iguais ao e-mail informado e uma senha aleatória que ninguém conhece. A importação SHALL gravar o lote inteiro de uma vez: se a gravação de qualquer linha falhar, nenhum NEPE do lote SHALL ser criado e o resultado SHALL dizer qual linha causou a falha.

#### Scenario: NEPE novo
- **WHEN** a linha de "NEPE Cisco de Deus" é confirmada
- **THEN** o tenant `ciscodedeus.nepebrasil.org` e o administrador são gravados

#### Scenario: Falha no meio do lote
- **GIVEN** um CSV com cinco linhas válidas
- **WHEN** a gravação da quarta linha falha
- **THEN** nenhum dos cinco NEPEs fica cadastrado
- **AND** o resultado aponta a quarta linha como a causa

#### Scenario: E-mails só depois da gravação
- **GIVEN** um lote com envio de e-mail ligado
- **WHEN** a gravação do lote falha
- **THEN** nenhum e-mail para definir a senha é enviado

## ADDED Requirements

### Requirement: Destino do link da logo restrito
O sistema SHALL baixar a logo apenas de endereços públicos. Um endereço que aponte para o próprio servidor ou para a rede interna SHALL ser recusado, e o NEPE SHALL ser criado sem a logo, com o motivo no resultado.

#### Scenario: Link para a rede interna
- **GIVEN** uma linha cuja coluna de logo traz um endereço da rede interna do servidor
- **WHEN** a importação é confirmada
- **THEN** nenhuma requisição é feita a esse endereço
- **AND** o NEPE é criado sem a logo, com o motivo no resultado

#### Scenario: Redirecionamento para a rede interna
- **GIVEN** um link público que redireciona para um endereço da rede interna
- **WHEN** a importação tenta baixar a logo
- **THEN** o download é recusado antes de ler o conteúdo

### Requirement: Marca BOM ignorada no CSV
O sistema SHALL reconhecer as colunas mesmo quando o arquivo começa com a marca BOM, que editores de planilha costumam acrescentar ao salvar em UTF-8.

#### Scenario: CSV salvo pelo Excel
- **GIVEN** um CSV cujo primeiro byte é a marca BOM
- **WHEN** o super admin envia o arquivo
- **THEN** a primeira coluna é reconhecida normalmente, sem erro de coluna obrigatória não encontrada
