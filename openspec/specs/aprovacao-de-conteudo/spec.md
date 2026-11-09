# aprovacao-de-conteudo Specification

## Purpose
Garante que artigos, vídeos e materiais/estudos de um NEPE só sejam publicados depois de revisados por outros membros do mesmo NEPE, no número de aprovações que o tenant define. Páginas institucionais não passam pelo fluxo. Implementado em `Service/ContentApprovalService`, `Entity/Trait/HasPublicationWorkflowTrait`, `Security/ArticleVoter`, `admin/ContentApprovalController` (vídeos e estudos), `admin/ArticleController` e nos repositórios usados pelo site público.

## Requirements
### Requirement: Três estados de publicação
Artigos, vídeos e materiais/estudos SHALL ter um dos estados Rascunho (`draft`), Aguardando aprovação (`pending`) ou Publicado (`published`), e SHALL nascer como Rascunho, com o usuário que criou gravado como autor e o tenant do domínio acessado.

#### Scenario: Vídeo novo
- **WHEN** um membro do NEPE cria um vídeo no painel
- **THEN** o vídeo é gravado como Rascunho, com ele como autor
- **AND** a mensagem orienta a enviar para aprovação para publicar

### Requirement: Enviar para aprovação
O sistema SHALL mudar para Aguardando aprovação só o conteúdo que está em Rascunho. Vídeos e estudos SHALL exigir que quem envia seja do mesmo tenant do conteúdo ou super admin; artigos SHALL exigir a permissão de edição (administrador ou editor).

#### Scenario: Conteúdo já publicado
- **WHEN** alguém envia para aprovação um vídeo que já está publicado
- **THEN** nada muda e o aviso diz que só rascunhos podem ser enviados

### Requirement: Quem pode aprovar
O sistema SHALL permitir aprovar a administradores (grupo 0), editores (grupo 1) e revisores (grupo 2), e SHALL recusar a aprovação quando o conteúdo não está Aguardando aprovação, quando quem aprova é o autor, quando é de outro tenant (salvo o super admin) ou quando já aprovou esse conteúdo.

#### Scenario: Autor tenta aprovar o próprio estudo
- **WHEN** o autor de um estudo pendente clica em aprovar
- **THEN** a aprovação é recusada com "Você não pode aprovar o próprio conteúdo."

#### Scenario: Membro de outro NEPE
- **WHEN** um revisor de outro tenant tenta aprovar o conteúdo
- **THEN** a aprovação é recusada com "Só membros deste site podem aprovar este conteúdo."

### Requirement: Publicação automática pelo número de aprovações
Cada aprovação SHALL ser gravada com o revisor, a data e o comentário opcional. Quando o total chega ao número de aprovações exigidas pelo tenant (no mínimo 1), o sistema SHALL mudar o conteúdo para Publicado e gravar a data de publicação; antes disso, SHALL avisar que ainda faltam aprovações.

#### Scenario: Tenant que exige duas aprovações
- **WHEN** o primeiro revisor aprova um vídeo de um tenant com duas aprovações exigidas
- **THEN** o vídeo continua Aguardando aprovação
- **AND** com a segunda aprovação de outro membro ele passa a Publicado

### Requirement: Alteração de vídeo ou estudo volta para rascunho
Ao editar um vídeo ou estudo que tem aprovações ou está publicado, o sistema SHALL apagar as aprovações, voltar o conteúdo para Rascunho e limpar a data de publicação quando mudam o título, a descrição, os materiais em HTML, a capa ou a miniatura, o vídeo do YouTube, os arquivos anexados ou a referência bíblica.

#### Scenario: Troca do PDF de um estudo publicado
- **WHEN** o autor troca o arquivo anexado de um estudo publicado
- **THEN** o estudo volta para Rascunho e sai do site e da API
- **AND** o aviso pede para enviar de novo para aprovação

### Requirement: Alteração de artigo volta para rascunho
Ao editar um artigo que tem aprovações, o sistema SHALL apagar as aprovações, voltar o artigo para Rascunho e limpar a data de publicação quando mudam o título, o resumo ou o conteúdo. Mudanças em imagem, categoria, SEO ou referência bíblica do artigo MUST NOT invalidar as aprovações.

#### Scenario: Ajuste só no SEO
- **WHEN** o editor muda só o título de SEO de um artigo publicado
- **THEN** o artigo continua publicado com as aprovações

### Requirement: Quem edita cada tipo de conteúdo
Artigos e estudos SHALL exigir a permissão de edição (administrador ou editor). Um estudo com autor SHALL ser editado ou excluído só pelo autor ou pelo super admin. Vídeos SHALL ser criados, editados e excluídos por qualquer usuário com acesso ao painel.

#### Scenario: Outro editor tenta editar um estudo
- **WHEN** um editor que não é o autor abre a edição de um estudo
- **THEN** o acesso é negado com a orientação de usar a tela de revisão

### Requirement: Só o publicado aparece no site
O site público SHALL listar e abrir só artigos, vídeos e estudos publicados. Vídeo ou estudo não publicado SHALL abrir em modo de prévia para usuários logados do painel do mesmo tenant (administrador, editor ou revisor) e para o super admin, e responder 404 para os demais. Artigo não publicado SHALL responder 404 sempre.

#### Scenario: Visitante abre o link de um vídeo pendente
- **WHEN** um visitante sem login abre `/video/<slug>` de um vídeo Aguardando aprovação
- **THEN** a resposta é 404
