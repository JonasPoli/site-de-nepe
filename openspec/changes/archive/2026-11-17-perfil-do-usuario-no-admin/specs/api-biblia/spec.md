# Spec Delta

## ADDED Requirements

### Requirement: Foto e WhatsApp do autor na API
O `author` de artigo, vídeo e material SHALL trazer, além do `name`, o campo `photoUrl` com a URL completa da foto do autor no domínio do tenant que publicou o conteúdo e o campo `whatsapp` com o WhatsApp público do autor. Sem foto ou sem WhatsApp gravados, cada campo SHALL vir nulo. Conteúdo sem autor SHALL continuar com `author` nulo, e nenhum campo existente da resposta SHALL ser renomeado ou removido.

#### Scenario: Autor com foto e WhatsApp
- **WHEN** um artigo publicado do tenant nepealcione.nepebrasil.org, cujo autor tem foto e WhatsApp, é devolvido
- **THEN** o `author` traz `name`, `photoUrl` começando com `https://nepealcione.nepebrasil.org/uploads/` e `whatsapp` preenchido

#### Scenario: Autor sem foto
- **WHEN** o autor do conteúdo devolvido não tem foto nem WhatsApp gravados
- **THEN** `author.photoUrl` e `author.whatsapp` vêm nulos e `author.name` continua preenchido

#### Scenario: Conteúdo sem autor
- **WHEN** um conteúdo sem autor é devolvido
- **THEN** `author` vem nulo, como antes
