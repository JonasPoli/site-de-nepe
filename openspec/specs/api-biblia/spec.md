# api-biblia Specification

## Purpose
API pública e sem autenticação que devolve os conteúdos de todos os NEPEs ligados a um trecho da Bíblia (ARC) e o próprio texto do trecho. É consumida pelo nepe-search e por outros sites. Implementada em `api/BibliaApiController` (`/api/biblia/contents` e `/api/biblia/passage`), `Service/BibliaService` e `BibliaBookRepository::findBySlugOrAbbrevOrId()`; o formato completo do JSON está no README.

## Requirements
### Requirement: Livro e capítulo obrigatórios
Em `/api/biblia/contents`, o sistema SHALL exigir o livro (`book` ou `livro`) e um capítulo maior que zero (`chapter`), respondendo 400 com `status: error` quando faltam, e 404 quando o livro não é encontrado.

#### Scenario: Sem capítulo
- **WHEN** a API recebe `?book=jo` sem `chapter`
- **THEN** a resposta é 400 com a mensagem dos parâmetros obrigatórios

### Requirement: Livro por id, sigla ou nome
O sistema SHALL encontrar o livro pelo id numérico (os mesmos do nepe-search) ou, nesta ordem, pela sigla exata, pelo nome exato, pela sigla do bible.com, pelo nome sem acentos e pela sigla sem acentos, sem diferenciar maiúsculas.

#### Scenario: Nome sem acento
- **WHEN** a API recebe `book=genesis`
- **THEN** o livro encontrado é Gênesis

### Requirement: Versículos opcionais
O versículo inicial SHALL vir de `verse_start` ou `verse` e o final de `verse_end` ou `verse2`; sem final, vale só o inicial; sem inicial, o sistema SHALL buscar o capítulo inteiro. Se o final for menor que o inicial, os dois SHALL ser trocados.

#### Scenario: Capítulo inteiro
- **WHEN** a API recebe `book=lc&chapter=10` sem versículos
- **THEN** a referência devolvida é "Lucas 10 (Capítulo inteiro)" e vêm os conteúdos de qualquer versículo do capítulo

### Requirement: Trechos que se sobrepõem
Um conteúdo SHALL ser devolvido quando o trecho associado a ele se sobrepõe ao trecho pesquisado no mesmo livro e capítulo; um conteúdo associado a um versículo só (sem final) SHALL contar como esse versículo.

#### Scenario: Versículo dentro de uma perícope
- **WHEN** a API busca Lucas 10:30 e há um estudo associado a Lucas 10:25-37
- **THEN** o estudo é devolvido

### Requirement: Só conteúdo publicado
O sistema SHALL devolver artigos, vídeos e estudos somente no estado Publicado, e páginas institucionais associadas ao trecho, que não passam pelo fluxo de aprovação.

#### Scenario: Vídeo aguardando aprovação
- **WHEN** existe um vídeo pendente associado ao trecho pesquisado
- **THEN** ele não aparece nos resultados

### Requirement: Filtro por tipo
O parâmetro `type` SHALL filtrar por artigo (`article`, `noticia`, `artigo` e plurais), vídeo (`video`, `videos`, `youtube`), estudo (`study`, `material`, `estudo` e plurais) ou página (`page`, `pagina` e plurais). Um tipo fora dessas listas SHALL resultar em lista vazia.

#### Scenario: Só materiais
- **WHEN** a API recebe `type=material`
- **THEN** só estudos/materiais são devolvidos

### Requirement: Filtro por NEPE
O parâmetro `tenant` SHALL aceitar o id do tenant, o domínio exato ou, na falta deste, o primeiro tenant cujo domínio ou nome contém o texto. Quando nenhum tenant é encontrado, o sistema SHALL responder sem filtro de tenant, com conteúdos de todos, e `query.tenant_filter` nulo.

#### Scenario: Parte do nome
- **WHEN** a API recebe `tenant=renovando`
- **THEN** só vêm conteúdos do tenant renovandoconsciencias.nepebrasil.org

#### Scenario: Tenant inexistente
- **WHEN** a API recebe `tenant=nao-existe`
- **THEN** vêm os conteúdos de todos os tenants e `tenant_filter` é `null`

### Requirement: URLs completas do NEPE de origem
Cada resultado SHALL trazer as URLs completas (conteúdo, imagem, arquivos, logo) no domínio do tenant que o publicou, com `https://`, ou `http://` quando o domínio é `localhost` ou `127.0.0.1`, mais os dados do tenant, do autor, das aprovações, da categoria, da referência e o texto ARC do trecho associado.

#### Scenario: Estudo do Renovando Consciências
- **WHEN** um estudo publicado do tenant renovandoconsciencias.nepebrasil.org é devolvido
- **THEN** a `url` começa com `https://renovandoconsciencias.nepebrasil.org/`

### Requirement: Acesso de qualquer origem
As respostas da API SHALL ter o cabeçalho `Access-Control-Allow-Origin: *` e SHALL ser as mesmas em qualquer domínio que chegue ao servidor.

#### Scenario: Chamada pelo navegador de outro site
- **WHEN** uma página de outro domínio chama a API por `fetch`
- **THEN** o navegador aceita a resposta

### Requirement: Texto de um trecho
Em `/api/biblia/passage`, o sistema SHALL exigir `book` e `chapter`, aceitar o intervalo em `start`/`end` ou `verse_start`/`verse_end` e devolver o texto ARC com os dados de cada versículo. Sem versículo inicial, SHALL devolver só o versículo 1; trecho inexistente SHALL responder 404.

#### Scenario: João 3:16-17
- **WHEN** a API recebe `book=jo&chapter=3&start=16&end=17`
- **THEN** a resposta traz os versículos 16 e 17 em `data`
