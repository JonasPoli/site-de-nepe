# cadastro-de-nepes Specification

## Purpose
Permite ao super admin cadastrar NEPEs (tenant + primeiro administrador) em lote, pelo CSV das respostas do formulário NEPE Brasil no Google Forms, ou um a um. Cada administrador recebe um e-mail com o link para definir a senha. Implementado em `superadmin/TenantImportController` (`/superadmin/tenant/import` e `/superadmin/tenant/onboard`), `Service/TenantImport/*` e no comando `app:tenant:import`, que usa as mesmas regras. O cadastro não cria DNS nem alias no cPanel.

## Requirements
### Requirement: Só o super admin cadastra
O sistema SHALL restringir a importação e o cadastro avulso a usuários com `ROLE_SUPER_ADMIN` (usuário sem tenant e do grupo 0).

#### Scenario: Administrador de NEPE tenta importar
- **WHEN** um administrador de um tenant abre `/superadmin/tenant/import`
- **THEN** o acesso é negado

### Requirement: Envio, prévia e confirmação do CSV
O sistema SHALL aceitar só arquivo `.csv` ou `.txt` de até 2 MB, detectar o separador (vírgula, ponto e vírgula ou tabulação) e mostrar uma prévia linha a linha antes de gravar. A gravação SHALL acontecer só na confirmação, com token CSRF válido.

#### Scenario: Arquivo grande demais
- **WHEN** o super admin envia um CSV de 3 MB
- **THEN** o envio é recusado com "O arquivo passa de 2 MB." e nada é gravado

### Requirement: CSV guardado por pouco tempo
O CSV enviado SHALL ficar em `var/tenant-import` só até a confirmação: ele é apagado depois de importado, e os não confirmados SHALL ser apagados no próximo envio quando tiverem mais de 1 dia.

#### Scenario: Importação confirmada
- **WHEN** o super admin confirma a importação
- **THEN** o arquivo do CSV é apagado do servidor

### Requirement: Colunas pelo título da pergunta
O sistema SHALL reconhecer as colunas pelo início do título, sem acentos e sem diferenciar maiúsculas: "Nome da instituição" ou "Nome do NEPE", "Domínio" ou "SubDomínio", "Tema", "Cor principal", "Cor secundária", "Logo (fundo claro)", "Logo (fundo escuro)", "Nome completo", "E-mail", "WhatsApp" e "Autorizo".

#### Scenario: Coluna obrigatória ausente
- **WHEN** o CSV não tem a coluna do e-mail do administrador
- **THEN** o envio é recusado com a lista das colunas obrigatórias não encontradas e os cabeçalhos do arquivo

#### Scenario: Duas colunas para o mesmo campo
- **WHEN** duas perguntas começam com "Domínio"
- **THEN** o envio é recusado pedindo para renomear as perguntas

### Requirement: Campos obrigatórios e LGPD
Cada linha SHALL ter nome da instituição, nome e e-mail válido do administrador. Quando o CSV tem a coluna "Autorizo" e a resposta está vazia, a linha SHALL ser recusada com "Sem autorização para uso dos dados (LGPD)".

#### Scenario: Resposta sem autorização
- **WHEN** uma linha tem a coluna "Autorizo" em branco
- **THEN** a linha não é importada e a prévia mostra o motivo

### Requirement: Subdomínio completado pelo domínio base
Com um domínio base (no painel o padrão é `nepebrasil.org`), o sistema SHALL completar respostas que trazem só o subdomínio e SHALL entender variações do domínio base, como `ciscodedeus.nepebrasil.org.br` ou `ciscodedeus . nepebrasil`, como o subdomínio sobre o domínio base, com aviso na prévia.

#### Scenario: Só o subdomínio
- **WHEN** a resposta do domínio é `renovandoconsciencias`
- **THEN** o domínio gravado é `renovandoconsciencias.nepebrasil.org`

#### Scenario: Domínio base digitado errado
- **WHEN** a resposta é `ciscodedeus.nepebrasil.org.br`
- **THEN** o domínio gravado é `ciscodedeus.nepebrasil.org`
- **AND** a prévia avisa que o domínio foi entendido assim

### Requirement: Limpeza do domínio
O sistema SHALL remover acentos, protocolo, caminho, porta e `www.` do domínio, com aviso, e SHALL recusar o que não for um domínio válido. Sem resposta de domínio e com domínio base, SHALL gerar `<nome-do-nepe-sem-acentos>.<domínio base>`, com aviso.

#### Scenario: Domínio com acento
- **WHEN** a resposta é `nepealcíone`
- **THEN** o domínio gravado é `nepealcione.nepebrasil.org` com o aviso "Acentos removidos do domínio"

### Requirement: WhatsApp do NEPE
O sistema SHALL importar o WhatsApp como telefone formatado e link `https://wa.me/<dígitos>`: número brasileiro com 10 ou 11 dígitos recebe o 55 e vira `(DD) NNNNN-NNNN`; número com `+` ou `00` é aceito como internacional. Número não reconhecido SHALL ser ignorado com aviso, sem recusar a linha.

#### Scenario: Celular sem DDI
- **WHEN** o WhatsApp informado é `19 99999-0000`
- **THEN** o telefone gravado é `(19) 99999-0000` e o link é `https://wa.me/5519999990000`

### Requirement: Tema, cores e logos
O tema SHALL ser `moderno` quando a resposta começa com "moderno" e `nepe` nos demais casos. Cor inválida SHALL manter a cor padrão, com aviso. Link do Google Drive SHALL virar link de download; a logo SHALL ter até 5 MB e ser PNG, JPG, WebP ou GIF pelo conteúdo do arquivo, senão o NEPE é criado sem ela e o motivo aparece no resultado.

#### Scenario: Pasta do Drive sem compartilhamento
- **WHEN** o link da logo devolve a página de login do Google
- **THEN** o NEPE é cadastrado sem a logo
- **AND** o resultado pede para compartilhar a pasta como "Qualquer pessoa com o link"

### Requirement: Respostas repetidas no arquivo
Quando o mesmo domínio aparece em mais de uma linha válida, o sistema SHALL importar só a última. Quando o mesmo e-mail de administrador aparece em mais de uma linha válida, SHALL importar só a primeira.

#### Scenario: Correção enviada depois
- **WHEN** o NEPE respondeu o formulário duas vezes com o mesmo subdomínio
- **THEN** só a última resposta é importada e a anterior aparece como substituída

### Requirement: Conflitos com o que já está cadastrado
O sistema SHALL recusar a linha cujo domínio já pertence a outro tenant ou cujo e-mail já pertence a outro usuário. Quando o tenant e o administrador já existem e estão ligados, a linha SHALL aparecer como "Já importado" e ser ignorada, ou receber o e-mail de novo quando o super admin escolhe reenviar.

#### Scenario: Importar o mesmo arquivo de novo
- **WHEN** o super admin importa outra vez um CSV já importado, sem reenviar o e-mail
- **THEN** nenhum tenant ou usuário é criado de novo

### Requirement: Tenant e administrador criados juntos
Para cada linha válida, o sistema SHALL criar o tenant e um administrador do grupo 0 ligado a ele, com usuário e e-mail iguais ao e-mail informado e uma senha aleatória que ninguém conhece. Um erro de gravação SHALL interromper a importação nas linhas seguintes e avisar isso no resultado.

#### Scenario: NEPE novo
- **WHEN** a linha de "NEPE Cisco de Deus" é confirmada
- **THEN** o tenant `ciscodedeus.nepebrasil.org` e o administrador são gravados

### Requirement: E-mail para definir a senha
Com o envio de e-mail ligado, o sistema SHALL mandar a cada administrador criado o e-mail "Seu acesso ao site <NEPE>" com o link para definir a senha no domínio do próprio NEPE, válido por 7 dias. Uma falha no envio MUST NOT desfazer o cadastro; ela aparece no resultado com a orientação de importar de novo escolhendo reenviar.

#### Scenario: Servidor de e-mail fora do ar
- **WHEN** o tenant é gravado e o envio do e-mail falha
- **THEN** o tenant e o administrador continuam cadastrados
- **AND** o resultado mostra a falha para reenviar depois

### Requirement: Cadastro avulso
O sistema SHALL oferecer em `/superadmin/tenant/onboard` o cadastro de um NEPE com o administrador, com as mesmas regras do CSV, logos enviadas como arquivo e a opção de enviar o e-mail. Em caso de sucesso, SHALL voltar ao painel do super admin com o domínio e o e-mail do administrador na mensagem.

#### Scenario: Cadastro com só o subdomínio
- **WHEN** o super admin informa o domínio `rodrigolobato` com o domínio base `nepebrasil.org`
- **THEN** o NEPE é cadastrado em `rodrigolobato.nepebrasil.org`

### Requirement: Excluir um NEPE exclui os usuários
Ao excluir um tenant pelo painel do super admin, o sistema SHALL excluir junto todos os usuários ligados a ele, para que nenhum administrador fique sem tenant e vire super admin.

#### Scenario: Exclusão de tenant
- **WHEN** o super admin exclui um tenant com dois usuários
- **THEN** o tenant e os dois usuários são removidos e a mensagem informa a quantidade
