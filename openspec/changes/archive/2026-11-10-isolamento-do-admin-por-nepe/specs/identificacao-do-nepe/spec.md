## ADDED Requirements

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

## REMOVED Requirements

### Requirement: Isolamento no site público
**Reason**: Substituído por "Isolamento pelo filtro de tenant", que vale também em `/admin`.
**Migration**: Nenhuma no site público: o filtro continua ligado como antes. No painel, as consultas passam a receber a condição `tenant_id`.

### Requirement: Painel sem o filtro de tenant
**Reason**: Sem o filtro, as listagens e as rotas de edição do painel alcançavam o conteúdo de todos os NEPEs (comportamento vindo do commit `bd97821`, de 08/06/2026). A decisão de 09/11/2026 é restringir o painel ao NEPE do domínio.
**Migration**: O filtro passa a valer em `/admin`. As regras do painel que o filtro não cobre (entidades sem tenant e referências enviadas no formulário) estão em `papeis-e-painel`, no requisito "Painel restrito ao NEPE do domínio" e nos seguintes.
