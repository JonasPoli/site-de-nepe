# identificacao-do-nepe Specification

## Purpose
Descobre qual NEPE (Tenant) atende cada requisição pelo domínio acessado e isola os dados dele no site público. Uma só instalação serve todos os subdomínios; o tenant identificado fica no `TenantContext` para controllers e templates. Implementado em `EventSubscriber/TenantSubscriber`, `Doctrine/TenantFilter`, `Service/TenantContext` e `TenantRepository::findByDomain()`.

## Requirements

### Requirement: Tenant pelo domínio exato
O sistema SHALL identificar o tenant pelo host da requisição comparado de forma exata com o campo `domain` do Tenant, antes de qualquer controller, e SHALL guardá-lo no `TenantContext`.

#### Scenario: Subdomínio cadastrado
- **WHEN** chega uma requisição para `ciscodedeus.nepebrasil.org` e existe um tenant com esse domínio
- **THEN** o site é montado com os dados, a logo e as cores desse tenant

### Requirement: Mesmo tenant com e sem www
Quando o host não está cadastrado, o sistema SHALL procurar a outra forma do mesmo host (com ou sem `www.`). Se achar e o método for seguro (GET ou HEAD), SHALL redirecionar com 302 para o host cadastrado, mantendo protocolo, porta, caminho e query. Um POST enviado à outra forma SHALL ser atendido no tenant encontrado, sem redirecionar.

#### Scenario: Visita com www
- **WHEN** alguém abre `https://www.ueebp.nepebrasil.org/videos?page=2` e só `ueebp.nepebrasil.org` está cadastrado
- **THEN** o sistema responde 302 para `https://ueebp.nepebrasil.org/videos?page=2`

#### Scenario: Formulário enviado com www
- **WHEN** um formulário é enviado por POST para o host com `www.` e o tenant está cadastrado sem `www.`
- **THEN** o envio é processado no tenant sem redirecionamento

### Requirement: Domínio não cadastrado
O sistema SHALL responder 404 com o texto "Domínio não configurado" e o host acessado quando nem o host nem a sua outra forma (com ou sem `www.`) estão cadastrados.

#### Scenario: Alias do cPanel sem tenant
- **WHEN** chega uma requisição para `franciscodeassis.nepebrasil.org`, que aponta para o servidor mas não tem tenant
- **THEN** a resposta é 404 "Domínio não configurado"

### Requirement: API e rotas internas fora da identificação
O sistema MUST NOT exigir tenant nos caminhos que começam com `/api` ou `/_`: eles respondem em qualquer host, sem 404 de domínio e sem filtro de tenant.

#### Scenario: API chamada por outro host
- **WHEN** o nepe-search chama `/api/biblia/contents` por qualquer domínio que chegue ao servidor
- **THEN** a API responde normalmente, com conteúdos de todos os tenants

### Requirement: Isolamento pelo filtro de tenant
Em toda requisição com tenant identificado, inclusive as que começam com `/admin`, o sistema SHALL ligar o filtro `tenant_filter` do Doctrine com o id do tenant do domínio. O filtro acrescenta `tenant_id = <id>` às consultas das entidades que implementam `TenantAwareInterface`: Article, VideoSupport, Study, Category, Page, HeroBanner, ResearchLine, ContactMessage e NewsletterSubscriber.

#### Scenario: Vídeos de outro NEPE no site público
- **WHEN** um visitante abre a lista de vídeos de `nepealcione.nepebrasil.org`
- **THEN** só aparecem vídeos do tenant nepealcione

#### Scenario: Vídeos de outro NEPE no painel
- **WHEN** existem vídeos dos tenants renovandoconsciencias e nepealcione e um usuário abre `nepealcione.nepebrasil.org/admin/video`
- **THEN** a lista mostra só os vídeos do tenant nepealcione

#### Scenario: Tenant do domínio continua disponível no painel
- **WHEN** um usuário logado abre uma tela de `/admin`
- **THEN** o tenant do domínio acessado está no `TenantContext` e é o mesmo usado no filtro
