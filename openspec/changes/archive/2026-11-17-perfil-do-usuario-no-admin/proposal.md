# Proposal

## Why

Hoje ninguém consegue corrigir os próprios dados no painel: usuário, nome e e-mail só são definidos na criação do usuário (`/admin/editors/new`, feita pelo administrador do NEPE), que não tem tela de edição, e a única forma de trocar a senha é pelo link de "esqueci minha senha" por e-mail. O usuário também não tem foto nem um contato público próprio, então o autor de um artigo aparece na API apenas como um nome. Além disso, a senha hoje é aceita com 8 caracteres quaisquer, sem exigência de maiúsculas, minúsculas ou caractere especial.

## What Changes

- Novo painel **Perfil** em `/admin/perfil`, no menu do admin, aberto a todo usuário que entra no painel (administrador do NEPE, editor e revisor, além do super admin quando está num domínio de NEPE). Cada um edita **só os próprios dados**.
- Campos do perfil: nome, e-mail, usuário de login, WhatsApp (destinado a ficar público) e foto (upload, com a foto atual visível e opção de remover).
- Troca de senha opcional na mesma tela, em dois campos ("nova senha" e "repetir"), cada um com o ícone de olho para ver e ocultar. Os dois em branco = a senha não muda.
- Nova **política de senha** exigida em toda definição de senha do sistema: mais de 8 caracteres (mínimo 9), ao menos uma letra minúscula, ao menos uma letra maiúscula e ao menos um caractere especial. Aplicada no perfil, na criação de usuário pelo painel (`/admin/editors/new`), na redefinição por e-mail (`/reset-password/reset/{token}`) e na criação/edição de usuários pelo super admin.
- Lista de requisitos de senha visível na tela, conferida **em JavaScript enquanto o usuário digita** (cada item vira cumprido ou pendente, mais a conferência de que as duas senhas são iguais). O servidor confere as mesmas regras de novo e é ele quem decide.
- Trocar o usuário de login ou a senha encerra a sessão: o sistema grava e leva ao `/login` com a mensagem de que é preciso entrar de novo (o Symfony invalida o token quando o identificador ou o hash da senha muda).
- Foto e WhatsApp do autor passam a sair no `author` da API `/api/biblia` (campos novos `photoUrl` e `whatsapp`, acréscimo compatível).
- **Não entra nesta change**: exibir autor, foto ou WhatsApp nas páginas públicas do site — hoje nenhum template público mostra autoria. Fica para uma change futura.

## Capabilities

### New Capabilities
- `perfil-do-usuario`: o usuário do painel vê e edita os próprios dados (nome, e-mail, usuário de login, WhatsApp e foto) e troca a própria senha, sem alcançar dados de outro usuário.
- `politica-de-senha`: as regras que uma senha precisa cumprir em qualquer ponto do sistema que define senha, e como elas são mostradas e conferidas.

### Modified Capabilities
- `papeis-e-painel`: a criação de usuário pelo painel e a redefinição de senha por e-mail passam a exigir a política de senha (hoje o requisito fala em "no mínimo 8 caracteres"); o menu do painel ganha o item "Perfil", visível a todos os papéis, ao lado dos itens de gestão que continuam restritos ao grupo 0.
- `api-biblia`: o `author` de artigo, vídeo e material passa a trazer `photoUrl` e `whatsapp`, além do `name`.

## Impact

- **Banco**: duas colunas novas em `user` (`photo`, `whatsapp`) — migration conferida contra MySQL 5.7.
- **Código**: `Entity/User` (campos, Vich `user_photo`), `config/packages/vich_uploader.yaml`, novo `Controller/admin/ProfileController`, nova regra pura `Security/PasswordPolicy`, `Controller/admin/AdminContentController::editorNew`, `Controller/ResetPasswordController`, `Controller/superadmin/SuperAdminController`, `Service/BibliaService::formatAuthor`, `templates/admin/nepe_base.html.twig` (menu), novo `templates/admin/profile/edit.html.twig`, parcial de senha reaproveitada pelas telas de senha e um controlador Stimulus (ou script do padrão atual do admin) para a conferência ao digitar.
- **Arquivos**: nova pasta `public/uploads/user_photo` (fora do git, como as outras de upload).
- **API**: `/api/biblia` ganha campos no `author`; o consumidor `nepe-search` (search.nepebrasil.org) precisa ser conferido — acréscimo de campo, sem renomear nem remover nada.
- **Sessão**: quem trocar usuário de login ou senha é levado ao `/login`.
- **Sem impacto** em aprovação de conteúdo, cadastro de NEPEs ou identificação do tenant.
