# Tasks

## 1. Pré-requisito

- [x] 1.1 Conferir que o change `perfil-do-usuario-no-admin` está concluído e no ar: `openspec status --change perfil-do-usuario-no-admin` sem tarefa pendente, `photo` e `photo_updated_at` presentes em `user` no servidor e a pasta `public/uploads/user_photo` criada; sem isso, não começar esta change (ver design.md, decisão 1)
- [x] 1.2 Conferir que `src/Controller/superadmin/SuperAdminController.php` e `templates/superadmin/user/{new,edit}.html.twig` estão sem alteração pendente no git, para não trabalhar sobre a edição inacabada daquela change

## 2. Correções do importador

- [x] 2.1 Trocar em `TenantImporter::downloadLogo` o `getContent()` por consumo em pedaços com `HttpClientInterface::stream()`, somando os bytes e cancelando ao passar de `MAX_LOGO_BYTES`; verificar com teste unitário usando `MockHttpClient` que responde sem `content-length` e com 6 MB, esperando o cancelamento e a mensagem no resultado
- [x] 2.2 Criar a regra de destino público (recusa de loopback, rede privada, link-local e reservado), com a verificação desligável por parâmetro em `dev` e `test`; verificar com teste unitário cobrindo `127.0.0.1`, `10.0.0.1`, `169.254.169.254` e um endereço público
- [x] 2.3 Aplicar a regra no download da logo seguindo os redirecionamentos à mão (`max_redirects: 0`, com teto de saltos) e validando cada salto; verificar com teste unitário de um link público que redireciona para endereço interno, esperando recusa antes de ler o conteúdo
- [x] 2.4 Envolver `TenantImporter::import()` numa transação com flush único e mover o envio dos e-mails para depois do commit; verificar com teste que força falha na quarta de cinco linhas e espera nenhum tenant gravado e nenhum e-mail enviado
- [x] 2.5 Apagar no rollback as logos que o Vich já copiou para `public/uploads`; verificar no teste da 2.4 que a pasta não fica com arquivo órfão
- [x] 2.6 Ignorar a marca BOM no início do CSV em `TenantImportParser::parseFile`; verificar com teste unitário lendo um CSV com BOM e esperando a primeira coluna reconhecida
- [x] 2.7 Atualizar `docs/` ou o texto da tela de importação onde ele descreve o comportamento de falha no meio do lote, que deixou de valer; verificar lendo a tela `/superadmin/tenant/import`

## 3. Exportação dos NEPEs

- [x] 3.1 Criar o serviço de exportação que monta as linhas a partir dos tenants e do administrador mais antigo de cada um, com o cabeçalho derivado de `TenantImportParser::COLUMNS`; verificar com teste unitário que confere cabeçalho, ordem das colunas e aspas em valor com vírgula
- [x] 3.2 Montar o endereço público da logo no domínio do próprio tenant, vazio quando não há logo; verificar no teste da 3.1 com um tenant com logo e outro sem
- [x] 3.3 Criar o teste de ida e volta: exportar, passar o resultado ao `TenantImportParser` e conferir que todos os campos são reconhecidos e voltam iguais; verificar que o teste quebra ao mudar um prefixo de `COLUMNS` (fazer a mudança, ver falhar, desfazer)
- [x] 3.4 Criar a rota de download em `TenantImportController`, com `ROLE_SUPER_ADMIN`, nome de arquivo com a data e sem guardar o arquivo; verificar baixando pelo painel local e conferindo que nada ficou em `var/`
- [x] 3.5 Acrescentar o botão de exportar no painel, com o aviso de dado pessoal e a observação de que a exportação cobre NEPEs e administradores, não todos os usuários; verificar abrindo `/superadmin`
- [x] 3.6 Conferir a ida e volta de verdade: exportar do painel local, reimportar o arquivo e esperar todas as linhas como "Já importado", sem criar nada

## 4. Listagens

- [x] 4.1 Criar em `TenantRepository` a consulta dos NEPEs ordenada por nome com a contagem de usuários, e em `UserRepository` a consulta com `JOIN` no tenant ordenada por nome do tenant e nome do usuário, com os sem tenant no fim e os sem nome no fim do grupo; verificar com teste de repositório ou, na falta de banco de teste, conferindo o SQL gerado e a ordem no painel local
- [x] 4.2 Trocar os `findAll()` do `SuperAdminController::dashboard` pelas consultas novas e montar os grupos percorrendo o resultado já ordenado; verificar no profiler local que a página faz uma consulta por lista, sem N+1
- [x] 4.3 Acrescentar a busca `?q=` sobre nome e domínio do NEPE e nome, login e e-mail do usuário; verificar no painel local que "alcione" encontra "NEPE Alcíone" e que uma busca sem resultado mostra o aviso com a opção de limpar
- [x] 4.4 Refazer a tabela de usuários como lista agrupada, com o cabeçalho de cada NEPE trazendo logo, nome e contagem, e o grupo dos sem NEPE por último; verificar no painel local com os 8 NEPEs
- [x] 4.5 Mostrar a foto do usuário na linha, com as iniciais quando não há foto e marcador neutro quando não há nome; verificar com o usuário `jonas`, que está sem nome, e com um usuário com foto
- [x] 4.6 Mostrar o e-mail na linha e deixar visível quando não há; verificar no painel local
- [x] 4.7 Marcar o NEPE sem nenhum usuário na lista de NEPEs; verificar criando um tenant de teste sem usuário no ambiente local

## 5. Papel e foto na edição

- [x] 5.1 Expor em `User` o papel já decidido, incluindo o caso de estar sem tenant fora do grupo 0, e usar isso no template no lugar do teste `u.tenant == null`; verificar com teste unitário cobrindo os quatro papéis e o caso inconsistente
- [x] 5.2 Destacar na lista o usuário inconsistente com a explicação de que ele não entra em painel nenhum; verificar criando no ambiente local um usuário sem tenant do grupo 2
- [x] 5.3 Acrescentar o envio e a remoção da foto na tela de edição de usuário do super admin, com a mesma regra do perfil (JPEG, PNG ou WebP até 4 MB); verificar enviando um PNG, depois um PDF (recusado, foto anterior mantida) e depois removendo
- [x] 5.4 Criar o teste unitário da recusa por tipo e por tamanho na edição pelo super admin e verificar que passa no phpunit

## 6. Fechamento e deploy

- [x] 6.1 Rodar `vendor/bin/phpunit` inteiro, `php bin/console lint:twig templates` e `lint:container`, com tudo verde
- [x] 6.2 Conferir no ambiente local, logado como super admin: listas agrupadas e ordenadas, busca, foto e iniciais, papéis corretos, exportação baixada e reimportada sem duplicar, e uma importação com logo grande e com link interno recusados pelo motivo certo
- [ ] 6.3 Conferir o `df -h` no servidor (leitura) antes de qualquer envio; sem espaço, parar e avisar o Jonas
- [ ] 6.4 `git push origin main` (local) e, no servidor como `projetomaster`, `git pull`; verificar que o `git log -1` do servidor é o commit novo. Não há migration nesta change; `composer.phar install` só se o `composer.lock` mudou
- [ ] 6.5 Rodar `sh build.sh` pelo ea-php83 e verificar que as três últimas linhas (tailwind, asset-map, liip) terminaram sem erro
- [ ] 6.6 Conferir no ar: os 6 domínios respondendo, `/superadmin` com as listas agrupadas, uma exportação baixada do servidor e reimportada em prévia (sem confirmar), e o `df -h` depois do deploy

## Workflow follow-up

- Arquivar a change depois da conferência no ar, sincronizando os delta specs com as specs principais.
- Conferir, no arquivamento, se o requisito de falha no meio do lote foi substituído corretamente na spec `cadastro-de-nepes`.
