# Design

## Context

Ver `proposal.md` — Why. O que molda a solução:

- `/admin` é um painel de controllers "à mão": `AdminContentController` lê `$r->request->get(...)`, grava com o `EntityManager` e redireciona com flash. Não há `src/Form/` (só `Form/DataTransformer`), nem Stimulus no painel: `templates/admin/nepe_base.html.twig` carrega Shoelace e Font Awesome por CDN e tem o JS em `<script>` inline, com o bloco `{% block javascripts %}` no fim. As telas de login e de redefinição usam outro layout, `templates/security/base.html.twig`, sem Shoelace.
- `User` (grupo `workGroup` + `tenant`) calcula os papéis em `getRoles()`. Hoje o usuário é criado em dois lugares — `/admin/editors/new` (grupos 1 e 2, `PanelUserPolicy`) e `/superadmin/user/new|edit` — e a senha é trocada só pelo `ResetPasswordController`, que tem `MIN_PASSWORD_LENGTH = 8` e `MAX_PASSWORD_LENGTH = 4096` como constantes privadas.
- O provider de segurança busca o usuário por `username`. O `ContextListener::hasUserChanged()` do Symfony compara o hash da senha, os papéis e o identificador a cada requisição: mudar `username` ou `password` do usuário logado derruba a sessão na requisição seguinte.
- Uploads de imagem usam VichUploader com um mapping por finalidade em `config/packages/vich_uploader.yaml`, gravando em `public/uploads/<pasta>` (fora do git), com miniaturas pelo Liip (filtro `admin_thumb` já existe, 200×200 inset). `BibliaService::formatAuthor()` devolve hoje `['name' => ...]` e é o único ponto onde o autor sai na API.
- Produção: ea-php83 por CGI, MySQL 5.7.41, deploy por `git pull` + `build.sh`, sem worker. Testes PHPUnit sem banco e sem e-mail.

## Goals / Non-Goals

**Goals:**

- Uma única regra de senha, pura e testável, usada pelo servidor em todos os pontos de senha e espelhada no JavaScript das telas.
- A tela do perfil não precisa de nenhuma peça nova de infraestrutura: mesmo estilo, mesmo jeito de gravar e mesmo padrão de upload das telas que já existem.
- Nenhum caminho pelo qual o perfil alcance outro usuário, outro grupo ou outro tenant.

**Non-Goals:**

- Não introduzir `symfony/form`, Stimulus ou validação por atributos neste painel: seria um padrão novo, destoando do resto do `/admin`.
- Não mexer na aparência pública do site nem criar página de autor.
- Não forçar a troca das senhas antigas, que continuam válidas para login.
- Não criar histórico de alterações do perfil nem auditoria.

## Decisions

### 1. `App\Security\PasswordPolicy`: regra pura, uma fonte só

Classe final com constantes (`MIN_LENGTH = 9`, `MAX_LENGTH = 4096`) e dois métodos sem dependências: `unmetRequirements(string $password): array` (lista de rótulos pendentes, em pt-BR) e `isSatisfiedBy(string $password): bool`. Os quatro requisitos: mais de 8 caracteres, uma minúscula, uma maiúscula, um caractere especial (`\p{L}` e `\p{N}` negados, com `u`), em cima de `mb_strlen`.

- *Por que uma classe pura*: o projeto já faz isso em `PanelUserPolicy` e `TenantOwnership`, e as regras do OpenSpec pedem teste PHPUnit unitário sem banco. Um validador de constraint do Symfony exigiria o componente de validação nos controllers, que hoje não o usam.
- *"Mais de 8 caracteres" = mínimo 9*: o pedido foi literal ("mais de 8"). Fica numa constante, fácil de mudar para 8 se o Jonas preferir.
- *Alternativa descartada*: `Symfony\Component\Validator\Constraints\PasswordStrength` — mede entropia, não dá a lista de itens pendentes que a tela precisa mostrar.
- O `ResetPasswordController` passa a usar a política em vez das próprias constantes, que são removidas (o template dele recebe a lista de requisitos no lugar de `min_length`).

### 2. Uma parcial Twig de senha, com JS vanilla, servindo os dois layouts

`templates/_password_fields.html.twig` recebe `required` (se a senha é obrigatória na tela), os nomes dos campos e o texto do botão associado, e desenha os dois campos com o botão de olho, a lista de requisitos e o `<script>` que confere ao digitar. O JS é vanilla auto-contido (sem Shoelace, sem Stimulus, com CSS inline próprio), porque a mesma parcial é usada no `/admin` (layout com Shoelace) e nas telas de `security` (layout sem). O olho é um `<button type="button">` que alterna `type="password"`/`type="text"` — não pode ser `<a>` nem botão sem `type`, que submeteria o formulário.

- As regras do JS vêm do servidor: a parcial recebe os requisitos de `PasswordPolicy` (rótulo + expressão) e monta a lista, para não haver duas listas para manter.
- O botão de salvar fica `disabled` enquanto houver item pendente ou as senhas diferirem. Nas telas onde a senha é opcional (perfil e edição pelo super admin), com os dois campos vazios o botão fica liberado — senão não daria para salvar só o nome.
- *Alternativa descartada*: um controlador Stimulus. O painel não carrega o `app.js`; incluí-lo só por isso traria o importmap e o build de assets para dentro do admin.

### 3. Campos novos no `User` e mapping Vich `user_photo`

`photo` (`string(255)`, nulo) com `photoFile` em `#[Vich\UploadableField(mapping: 'user_photo', fileNameProperty: 'photo')]` e `whatsapp` (`string(30)`, nulo). O mapping grava em `public/uploads/user_photo` com `SmartUniqueNamer`, `inject_on_load`, `delete_on_update` e `delete_on_remove`, como `video_thumb` e `study_cover`.

- `User` ganha `#[Vich\Uploadable]` e um `updatedAt` próprio não é necessário: o Vich só exige um campo que mude quando o arquivo muda, e `photo` muda. Para forçar o `flush` quando só o arquivo muda, o setter de `photoFile` atualiza `photoUpdatedAt` (`datetime_immutable`, nulo) — o mesmo truque do `Tenant::setLogoFile()`.
- `User` é serializado na sessão: `photoFile` (um `File`) não é serializável. Como `User` não define `__serialize`/`__sleep` hoje, a change acrescenta `__serialize`/`__unserialize` deixando `photoFile` de fora, no mesmo espírito do `Tenant`.
- A validação do arquivo (JPEG/PNG/WebP, 4 MB) é feita no controller com `UploadedFile::getMimeType()` e `getSize()`, antes de entregar ao Vich, porque não há componente de validação no fluxo.
- *Alternativa descartada*: entidade `UserProfile` separada. Dois campos não justificam outra tabela, e o autor da API já carrega o `User`.

### 4. `ProfileController` próprio, sempre pelo usuário da sessão

Novo `src/Controller/admin/ProfileController.php` com `#[Route('/admin/perfil', name: 'admin_profile')]`, `#[IsGranted('ROLE_ADMIN')]`, GET e POST. O alvo é sempre `$this->getUser()`: nenhum parâmetro de rota, nenhum id lido do request. `workGroup` e `tenant` nunca são escritos. Fica fora do `AdminContentController` (já com ~1000 linhas e todo voltado a conteúdo do tenant) e não entra no `/admin/editors`, que é a gestão de outras pessoas pelo administrador.

- CSRF por token do formulário (`isCsrfTokenValid('profile', ...)`), como no resto do painel.
- Unicidade: antes de gravar, busca no `UserRepository` por `username` e por `email` e recusa se o achado for outro usuário. O banco tem índice único em `username` e em `email`, mas a checagem explícita dá o aviso em pt-BR em vez de erro 500; a corrida improvável que escapar cai no `UniqueConstraintViolationException`, capturado e transformado no mesmo aviso.
- A busca por `username`/`email` não pode esbarrar no filtro de tenant do Doctrine; como o filtro não vale em `/admin` (contexto do projeto), a consulta já alcança o sistema todo — é exatamente o que a regra "um e-mail pertence a um único usuário no sistema todo" exige.

### 5. Trocar login ou senha encerra a sessão

Quando `username` ou `password` muda, o controller grava, invalida a sessão (`$request->getSession()->invalidate()`) e redireciona para `app_login` com um flash explicando que é preciso entrar de novo. Sem isso, o `hasUserChanged()` derrubaria a sessão sozinho na requisição seguinte, levando a um `/login` sem explicação nenhuma.

- *Alternativa descartada*: refazer o login na hora com `Security::login()`. Mantém a sessão, mas se comporta mal durante impersonação (`SwitchUserToken`) e troca um fluxo previsível por um caminho que precisaria de caso especial. Mudança de senha pedindo login de novo é comportamento esperado.
- Durante impersonação o super admin veria a sessão dele encerrada ao salvar o perfil de outra pessoa — por isso a tela do perfil mostra um aviso quando `IS_IMPERSONATOR` está presente.

### 6. Política aplicada nos outros três pontos de senha

`AdminContentController::editorNew`, `SuperAdminController::populateUserFromRequest` (senha vazia na edição continua significando "manter") e `ResetPasswordController::reset` passam a chamar `PasswordPolicy::unmetRequirements()` e a recusar com a lista. Os três templates recebem a parcial da decisão 2, substituindo os `minlength="8"` soltos.

- *Por que agora*: senha forte só no perfil seria contornável pelos outros caminhos — inclusive pelo próprio usuário, via "esqueci minha senha". O custo é pequeno (três chamadas e a parcial já escrita).

### 7. `author` da API ganha dois campos

`BibliaService::formatAuthor()` passa a devolver `['name', 'photoUrl', 'whatsapp']`, com `photoUrl` montada como as outras URLs do serviço (`$baseUrl . '/uploads/user_photo/' . $photo`, `$baseUrl` já é o domínio do tenant do conteúdo). Sem foto, `photoUrl` é nulo. Acréscimo de campo, sem renomear nem remover: o `nepe-search` continua lendo `author.name`. A conferência no `nepe-search` (`/Volumes/Dados/work/nepe-serach-github`) é uma tarefa de leitura antes do deploy.

- A API devolve o arquivo original, não a miniatura do Liip, como já faz com imagem de artigo e capa de estudo.
- **Conferido no nepe-search em 17/11/2026 (leitura)**: o único consumidor é `src/Service/NepeTenantsContentService.php`, que chama `/api/biblia/contents` e, em `normalize()`, lê apenas `title`, `url`, `type`, `description`/`text`, `image_url`, `biblical_reference.formatted`, `published_at` e `tenant.*`. O `author` não é lido em nenhum ponto do projeto, então o acréscimo não tem efeito nenhum lá.

### 8. Migration para MySQL 5.7

Uma migration só, com três colunas (`photo`, `photo_updated_at`, `whatsapp`), todas `NULL`, sem índice novo e sem alterar coluna existente — nada que o MySQL 5.7 recuse. Conferida com `doctrine:migrations:migrate --dry-run` no servidor antes de rodar de verdade.

## Risks / Trade-offs

- **O usuário troca o `username` e esquece o novo** → o aviso na tela e o flash de "entre de novo" citam o usuário gravado; o administrador do NEPE e o super admin continuam podendo ver o usuário no cadastro.
- **Senha forte barra o administrador acostumado com 8 caracteres fracos ao criar um editor** → a lista de requisitos aparece na tela antes de ele digitar, e o texto do aviso diz exatamente o que falta.
- **`User` passa a ter campo não serializável na sessão** → `__serialize`/`__unserialize` deixam `photoFile` fora; o teste unitário da entidade cobre o ciclo de serialização.
- **Um `nepe-search` que leia `author` com lista fixa de campos** → conferência de leitura no código do nepe-search antes do deploy; o acréscimo não muda nada do que já existia.
- **Foto grande enchendo o disco do servidor, que já encheu duas vezes** → limite de 4 MB por arquivo, uma foto por usuário, `delete_on_update` apagando a anterior, e `df -h` conferido antes e depois do deploy.
- **A parcial de senha carregada em dois layouts diferentes** → CSS e JS auto-contidos, sem depender das classes do `nepe_base` nem do Shoelace; conferência manual na tela de redefinição, que usa o outro layout.

## Migration Plan

1. Local: implementar, rodar `vendor/bin/phpunit` e conferir as telas (perfil, novo editor, redefinição, usuário do super admin) com o servidor local.
2. Conferir o `nepe-search` (leitura) quanto ao uso de `author`.
3. `git push origin main` com aprovação do Jonas.
4. No servidor, como `projetomaster`: `df -h` → backup do banco (`mysqldump`) → `git pull` → `doctrine:migrations:migrate --dry-run` e depois `migrate` com o ea-php83 → `sh build.sh` → criar `public/uploads/user_photo` com as permissões das outras pastas de upload.
5. Conferir no ar: `/admin/perfil` em nepealcione (menu, upload, troca de senha e login de volta), uma tela de senha de cada tipo, os 6 domínios respondendo 200 e `/api/biblia` com `author`.
6. **Rollback**: `git reset --hard <commit anterior>` + `sh build.sh`. A migration é aditiva e as colunas novas podem ficar no banco sem efeito; se for preciso desfazer o banco, `doctrine:migrations:migrate prev`. Fotos já enviadas ficam em `public/uploads/user_photo` sem referência.
