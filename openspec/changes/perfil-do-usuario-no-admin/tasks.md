# Tasks

## 1. Política de senha (regra pura)

- [x] 1.1 Criar `src/Security/PasswordPolicy.php` com `MIN_LENGTH = 9`, `MAX_LENGTH = 4096`, a lista de requisitos (rótulo em pt-BR + padrão) e os métodos `requirements()`, `unmetRequirements(string): array` e `isSatisfiedBy(string): bool`; verificar com `vendor/bin/phpunit` (sem banco) rodando o teste da tarefa 1.2
- [x] 1.2 Criar `tests/Security/PasswordPolicyTest.php` cobrindo os cenários da spec `politica-de-senha` (senha válida, 8 caracteres recusados, sem maiúscula, sem minúscula, sem especial, espaço valendo como especial, limite de 4096) e verificar que todos passam em `vendor/bin/phpunit tests/Security/PasswordPolicyTest.php`

## 2. Campos de foto e WhatsApp no usuário

- [x] 2.1 Acrescentar a `src/Entity/User.php` os campos `photo`, `photoUpdatedAt` e `whatsapp` com getters/setters, `#[Vich\Uploadable]`, `photoFile` no mapping `user_photo` (o setter atualiza `photoUpdatedAt`) e `__serialize`/`__unserialize` deixando `photoFile` de fora; verificar com o teste da tarefa 2.3
- [x] 2.2 Acrescentar o mapping `user_photo` em `config/packages/vich_uploader.yaml` (destino `public/uploads/user_photo`, `SmartUniqueNamer`, `inject_on_load`, `delete_on_update`, `delete_on_remove`) e verificar com `php bin/console lint:container` sem erro
- [x] 2.3 Criar `tests/Entity/UserProfileFieldsTest.php` cobrindo o ciclo `__serialize`/`__unserialize` sem o `photoFile` e os setters novos; verificar que passa em `vendor/bin/phpunit tests/Entity/UserProfileFieldsTest.php`
- [x] 2.4 Gerar a migration com `php bin/console make:migration`, conferir à mão que só acrescenta três colunas nulas (nada que o MySQL 5.7 recuse) e verificar com `php bin/console doctrine:migrations:migrate --dry-run` local

## 3. Parcial de senha com conferência ao digitar

- [x] 3.1 Criar `templates/_password_fields.html.twig` (dois campos com botão de olho `type="button"`, lista de requisitos vinda da `PasswordPolicy`, item de "senhas iguais", JS e CSS vanilla auto-contidos, botão de salvar desabilitado enquanto houver pendência e liberado com os dois campos vazios quando a senha é opcional); verificar abrindo `/admin/perfil` e a tela de redefinição no servidor local, digitando e vendo os itens mudarem de estado
- [x] 3.2 Expor os requisitos para o Twig (função ou variável passada pelos controllers) de modo que a lista do JS venha de um só lugar; verificar que mudar `MIN_LENGTH` muda o texto das duas telas sem editar template

## 4. Painel do perfil

- [x] 4.1 Criar `src/Controller/admin/ProfileController.php` (`/admin/perfil`, `ROLE_ADMIN`, GET e POST, alvo sempre `getUser()`, CSRF `profile`, sem escrever `workGroup` nem `tenant`), com gravação de nome, e-mail, usuário, WhatsApp, foto e senha opcional; verificar pelas telas e pelos testes das tarefas 4.5 e 4.6
- [x] 4.2 Tratar no controller a unicidade de `username` e `email` (consulta no `UserRepository` + captura de `UniqueConstraintViolationException`), recusando sem gravar e com aviso em pt-BR; verificar criando dois usuários locais e tentando repetir o usuário de login de um no perfil do outro
- [x] 4.3 Validar a foto no controller (JPEG, PNG ou WebP, até 4 MB, remoção por checkbox) mantendo a foto anterior quando o arquivo é recusado; verificar enviando um PDF e um JPEG de 1 MB no servidor local
- [x] 4.4 Encerrar a sessão e redirecionar ao `app_login` com flash quando `username` ou senha mudou, mantendo o usuário na tela do perfil nos outros casos, e mostrar aviso quando `IS_IMPERSONATOR`; verificar trocando a senha no local e entrando de novo com ela
- [x] 4.5 Criar `tests/Security/ProfileUpdatePolicyTest.php` para a regra pura extraída do controller (o que pode ser alterado, o que é ignorado, quando a sessão cai) e verificar que passa no phpunit
- [x] 4.6 Criar `tests/Controller/admin/ProfileRouteTest.php` conferindo a rota, o papel exigido (`ROLE_ADMIN`) e que `workGroup`/`tenant` enviados à mão não mudam nada; verificar que passa no phpunit

## 5. Tela do perfil e item no menu

- [x] 5.1 Criar `templates/admin/profile/edit.html.twig` no padrão do `/admin` (cards, classes `af`, `ai`, `btn`), com a foto atual, o campo de upload, a remoção, o aviso de que o WhatsApp pode ficar público e a parcial de senha; verificar abrindo `/admin/perfil` local em telas larga e estreita
- [x] 5.2 Acrescentar o item "Perfil" ao menu de `templates/admin/nepe_base.html.twig`, visível a todo usuário logado e marcado como ativo em `/admin/perfil`, e usar a foto do usuário no bloco `.sb-user` (com a inicial como alternativa); verificar entrando como editor, revisor e administrador local

## 6. Política nos outros pontos de senha

- [x] 6.1 Aplicar a `PasswordPolicy` em `AdminContentController::editorNew` e usar a parcial em `templates/admin/editor/new.html.twig`; verificar criando um usuário com `editor123` (recusado, com a lista) e com uma senha válida
- [x] 6.2 Aplicar a política em `SuperAdminController::populateUserFromRequest` (senha vazia na edição continua "manter") e usar a parcial em `templates/superadmin/user/new.html.twig` e `edit.html.twig`; verificar criando e editando um usuário pelo `/superadmin`
- [x] 6.3 Trocar as constantes privadas do `ResetPasswordController` pela política, passar os requisitos ao template e usar a parcial em `templates/security/reset_password.html.twig`; verificar que `vendor/bin/phpunit tests/Controller/ResetPasswordLinkTest.php` passa e redefinindo uma senha local pelo link
- [x] 6.4 Criar os testes unitários das recusas por senha nesses três pontos (ou estender os existentes) e verificar que passam no phpunit

## 7. Autor na API `/api/biblia`

- [x] 7.1 Acrescentar `photoUrl` e `whatsapp` ao `BibliaService::formatAuthor()`, com a URL no domínio do tenant do conteúdo e nulo quando não há foto; verificar com `curl` local em `/api/biblia/content?type=article` vendo os campos no `author`
- [x] 7.2 Criar/estender o teste unitário do formato do autor (nome só, nome com foto, autor nulo) e verificar que passa no phpunit
- [x] 7.3 Conferir em `/Volumes/Dados/work/nepe-serach-github` (leitura) todo uso de `author` e registrar no relatório que o acréscimo não quebra o nepe-search

## 8. Conferência local completa

- [x] 8.1 Rodar `vendor/bin/phpunit` inteiro e `php bin/console lint:twig templates` + `lint:container`, com tudo verde
- [x] 8.2 Conferir no servidor local, logado como editor: perfil salvo, foto enviada e removida, WhatsApp gravado, senha trocada com a lista de requisitos ao digitar, login de volta com a senha nova e usuário de login novo aceito
- [x] 8.3 Conferir que o editor não alcança `/admin/editors` nem `/admin/settings` (403) e que o perfil de outro usuário não é alcançável por nenhum parâmetro

## 9. Deploy em produção (escrita — com aprovação do Jonas)

- [ ] 9.1 Conferir espaço em disco no servidor (`df -h`, leitura) e fazer o backup do banco com `mysqldump` antes de qualquer escrita; verificar o tamanho do arquivo gerado
- [ ] 9.2 `git push origin main` (local) e, no servidor como `projetomaster`, `git pull`; verificar que o `git log -1` do servidor é o commit novo
- [ ] 9.3 Rodar a migration com o ea-php83 (`--dry-run` antes, depois `migrate --env=prod`) e verificar as três colunas em `user` com `doctrine:query:sql "SHOW COLUMNS FROM user"`
- [ ] 9.4 Rodar `sh build.sh` e criar `public/uploads/user_photo` com dono e permissões iguais às outras pastas de upload; verificar com `ls -la public/uploads`
- [ ] 9.5 Conferir no ar: `/admin/perfil` em nepealcione (menu, upload, troca de senha e login de volta), uma tela de senha de cada tipo, os 6 domínios respondendo 200 e `author` com os campos novos em `/api/biblia`; registrar `df -h` depois do deploy

## 10. Documentação

- [x] 10.1 Atualizar o contexto do `openspec/config.yaml` com a pasta de upload nova e a política de senha única, se couber; verificar com `openspec validate --strict`

## Workflow follow-up

- Sincronizar os delta specs com as specs principais e arquivar a change depois do deploy conferido.
