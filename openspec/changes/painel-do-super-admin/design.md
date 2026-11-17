# Design

## Context

Ver proposal.md — Why. O que importa aqui do estado atual:

- `SuperAdminController::dashboard` monta a página com `TenantRepository::findAll()` e `UserRepository::findAll()`, sem ordem nem join: a ordem é a do banco e cada linha da tabela de usuários toca o tenant por lazy loading.
- A importação já tem uma separação limpa entre ler (`TenantImportParser`, sem banco) e gravar (`TenantImporter`). As correções desta change ficam todas no `TenantImporter`, menos a restrição do endereço da logo, que começa no parser.
- `Tenant` já guarda `logo` e `darkLogo` pelo Vich (mapping `tenant_logo`).
- `User` **já tem** `photo`, `photoUpdatedAt` e `whatsapp`, com o mapping Vich `user_photo` e a migration `Version20261117211500`: isso chegou pelo change `perfil-do-usuario-no-admin`, que está sendo implementado neste momento (metade das tarefas concluídas, deploy ainda não feito). Nada disso é refeito aqui.
- O banco é MySQL 5.7.41 com collation `utf8mb4_unicode_ci`.
- `/superadmin` é, por definição, a visão sem filtro de tenant: o `TenantContext` não se aplica, e o controller já exige `ROLE_SUPER_ADMIN` na classe inteira.

**Produção**: esta change é só código e template — **nenhuma escrita** no banco. A migration das colunas de foto pertence ao change de perfil e sobe com ele.

**API**: nada em `/api/biblia` muda, portanto **sem efeito no nepe-search**.

**`/admin`**: nenhuma rota de `/admin` muda nesta change. A foto é editada só em `/superadmin`, que é global por natureza; quando o change `perfil-do-usuario-no-admin` levar a edição da foto para `/admin/perfil`, é lá que a restrição por usuário terá de ser garantida, não aqui.

## Goals / Non-Goals

**Goals:**

- Uma consulta por lista, sem N+1, com a ordem decidida no banco.
- O formato do CSV exportado derivado da mesma fonte que o importador usa para reconhecer colunas, de modo que os dois não possam divergir em silêncio.
- As correções do importador sem mudar o que o super admin vê na prévia.

**Non-Goals:**

- Paginação das listas. Com 8 NEPEs e 16 usuários não se justifica; a busca resolve o caso de uso.
- Exportar editores e revisores, ou importar usuários que não sejam o administrador. A ida e volta cobre NEPE + administrador.
- Corrigir os cadastros errados que estão em produção (o usuário sem nome, o "NEPE ALCÍONE" cadastrado como pessoa). Esta change faz esses casos aparecerem; a correção é decisão do Jonas, depois.
- Trocar o visual do painel. As listas continuam com o mesmo estilo; muda a organização.

## Decisions

### 1. A foto vem do change de perfil; esta change entra depois dele

Quando esta change começou a ser escrita, `User` não tinha foto, e a ideia era criá-la aqui. Isso mudou durante o próprio planejamento: o change `perfil-do-usuario-no-admin` entrou em implementação e já trouxe `photo`, `photoUpdatedAt`, `whatsapp`, o mapping `user_photo` e a migration. Então esta change **consome** a foto e não recria nada.

Disso decorre a ordem de trabalho: esta change só começa quando aquela estiver concluída. Não é preferência de sequência, é conflito de arquivo — a tarefa 6.2 do perfil ainda vai mexer em `SuperAdminController::populateUserFromRequest` e nos templates `superadmin/user/new.html.twig` e `edit.html.twig`, que são exatamente os pontos que a edição de foto pelo super admin toca.

O que o perfil **não** cobre, e por isso fica aqui: o super admin enviar ou remover a foto de outro usuário. A spec daquela change restringe `/admin/perfil` ao usuário da sessão, "sem aceitar id, usuário ou qualquer referência a outro usuário".

*Alternativa descartada*: trabalhar em paralelo nas duas. Os dois lados editariam o mesmo controller e os mesmos dois templates na mesma semana, para ganhar poucos dias.

### 2. Limite da logo aplicado durante o download, não depois

Hoje `downloadLogo` confere o cabeçalho `content-length` e, quando ele não vem, chama `getContent()`, que traz o arquivo inteiro para a memória antes da conferência de 5 MB.

A troca é consumir a resposta em pedaços (`HttpClientInterface::stream()`), somando os bytes recebidos e cancelando assim que passar de `MAX_LOGO_BYTES`. O `content-length`, quando existe, continua servindo de corte antecipado — barato e evita começar o download.

*Alternativa descartada*: confiar só no `content-length`. É justamente o caso que falha, porque o Google Drive costuma responder sem ele.

### 3. SSRF barrado por IP de destino, não por lista de domínios

O endereço da logo vem de um CSV, e hoje qualquer `http(s)://…` é seguido. Em vez de uma lista de domínios permitidos — que quebraria links legítimos fora do Drive —, a regra é sobre o destino: resolver o host e recusar quando o IP for de loopback, rede privada, link-local ou reservado.

Como um link público pode redirecionar para um endereço interno, a verificação precisa valer a cada salto: os redirecionamentos passam a ser seguidos manualmente (`max_redirects: 0`), validando o destino antes de cada nova requisição, com um teto de saltos.

Em `dev` e `test` a verificação é desligada por parâmetro, senão o próprio `127.0.0.1` da máquina do desenvolvedor ficaria bloqueado — e os tenants `127.0.0.1` e `localhost` existem no banco.

*Alternativa descartada*: só a lista do Drive. Resolve o caso de hoje e cria um limite que ninguém vai lembrar de afrouxar.

### 4. O lote inteiro numa transação, e-mails depois do commit

`import()` passa a abrir uma transação, gravar todas as linhas válidas com um `flush` ao final e confirmar; qualquer exceção desfaz tudo. Os e-mails para definir a senha saem **depois** do commit, percorrendo os administradores criados — hoje eles saem logo após a criação de cada um, o que mandaria convite para um NEPE que o rollback apagou.

O ponto delicado é o Vich: ele copia as logos para `public/uploads` durante o flush e não desfaz isso num rollback. A solução é registrar os caminhos gravados e apagá-los quando a transação cai.

*Alternativa descartada*: manter linha a linha e apenas melhorar a mensagem. Deixa o lote pela metade, que é exatamente o que confunde na hora de reimportar.

### 5. O cabeçalho da exportação derivado de `TenantImportParser::COLUMNS`

O que garante a ida e volta é o cabeçalho ser reconhecido pelo parser, que casa colunas pelo **início** do título. Então a exportação não escreve títulos à mão: ela usa o primeiro prefixo de cada campo em `COLUMNS` como título da coluna, na ordem definida por uma constante de exportação.

Um teste unitário fecha o contrato: exporta, manda o resultado ao parser e confere que todos os campos foram reconhecidos e que os valores voltam iguais. Se alguém mudar um prefixo em `COLUMNS`, o teste cai.

*Alternativa descartada*: títulos fixos no serviço de exportação. Os dois lados divergiriam na primeira mudança de formulário.

### 6. Logo exportada como endereço público do próprio NEPE

O importador lê URL, não arquivo. Então a exportação escreve `https://<domínio do tenant>/uploads/<caminho do Vich>/<arquivo>`, que é onde a logo já está publicada.

Isso casa com a decisão 3: o domínio do NEPE é público, então a reimportação consegue baixar. Em desenvolvimento, onde a verificação de IP está desligada, continua funcionando contra `localhost`.

### 7. Agrupamento e busca no servidor, acento resolvido pela collation

A lista de usuários vira uma consulta com `JOIN` no tenant, ordenada por nome do tenant e depois por nome do usuário, com os usuários sem tenant no fim. O agrupamento é feito ao percorrer o resultado já ordenado, sem segunda consulta e sem lazy loading por linha.

A busca é um `?q=` com `LIKE` sobre nome, login e e-mail. A collation `utf8mb4_unicode_ci` já ignora acentos e maiúsculas na comparação, então "alcione" encontra "Alcíone" sem normalizar nada na aplicação.

*Alternativa descartada*: filtrar no navegador com JavaScript. Não precisaria de rota, mas perderia a insensibilidade a acentos (que daria trabalho em JS) e não serviria quando a rede crescer.

### 8. Papel calculado num só lugar

O rótulo errado do template nasce de a regra dos papéis estar escrita duas vezes: em `User::getRoles()` e no Twig. A correção é o `User` expor o papel já decidido (um método que devolve o papel do usuário, incluindo o caso inconsistente de estar sem tenant fora do grupo 0), e o template apenas exibir.

## Risks / Trade-offs

- **Rollback do lote deixa logo órfã em `public/uploads`** → os caminhos gravados pelo Vich são registrados durante a importação e apagados quando a transação cai; o que escapar é arquivo sem referência, que não aparece em lugar nenhum.
- **Transação longa com download de arquivos dentro** → o lote já roda com `set_time_limit(300)`; os downloads continuam sendo feitos antes do flush, e a transação abrange a gravação. Com CSVs de dezenas de linhas isso é irrelevante; se um dia houver centenas, o lote precisará ser dividido, e aí a decisão 4 volta à mesa.
- **A verificação de IP pode recusar um link legítimo atrás de CDN ou proxy** → o motivo aparece no resultado da importação, por linha, e o NEPE é criado sem a logo, que pode ser enviada à mão na edição do tenant.
- **Desligar a verificação em `dev` esconde o problema do desenvolvedor** → o teste que cobre a recusa roda com a verificação ligada, independentemente do ambiente.
- **A exportação carrega nome e e-mail dos administradores** → acesso restrito ao super admin, arquivo não guardado no servidor e aviso na tela; nada é enviado para fora por conta própria.
- **Conflito com o change de perfil, que está em implementação** → esta change começa só depois que aquela estiver concluída e no ar (ver decisão 1); começar antes significaria resolver conflito no mesmo controller e nos mesmos templates.
- **A listagem depende de uma pasta de upload que ainda não existe no servidor** → `public/uploads/user_photo` é criada na tarefa 9.4 do change de perfil; se ela não existir, a lista cai no caso "sem foto", que já está previsto na spec, em vez de quebrar.

## Migration Plan

1. Conferir que o change `perfil-do-usuario-no-admin` está concluído e no ar, inclusive a pasta `public/uploads/user_photo`.
2. Deploy normal, **sem migration**: `git pull`; `composer.phar install` só se o `composer.lock` mudou; `sh build.sh` pelo ea-php83.
3. Conferir o `df -h` antes (leitura) — o disco do servidor já encheu duas vezes.
4. Conferir cada domínio no ar e abrir `/superadmin` para ver as listas agrupadas e exportar um CSV de teste.
5. **Rollback**: como não há escrita no banco, reverter o código restabelece o comportamento anterior por inteiro.

## Open Questions

- Se um NEPE tiver dois administradores, a exportação leva o mais antigo e os demais ficam de fora do arquivo. Vale a pena, mais adiante, uma coluna informando quantos administradores o NEPE tem, para que a diferença fique visível em quem lê a planilha? Não muda o formato nem a ida e volta, e pode ser decidido depois de ver o primeiro arquivo exportado.
