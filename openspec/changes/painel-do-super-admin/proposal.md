# Proposal

## Why

O painel `/superadmin` é a única visão da rede inteira, mas hoje mostra tenants e usuários em duas tabelas simples, sem ordem definida, sem busca e sem agrupamento: os usuários saem na ordem em que foram criados, de modo que os quatro usuários do NEPE Alcíone aparecem separados por usuários de outros NEPEs. Com 8 NEPEs e 16 usuários isso já atrapalha, e é essa falta de visão que deixa passar cadastros errados que estão no ar agora — um usuário sem nome e um registro chamado "NEPE ALCÍONE" cadastrado como se fosse uma pessoa, duplicando a administradora do mesmo NEPE. Além disso, a lista rotula como "Super Admin" todo usuário sem tenant, enquanto o papel real exige também o grupo 0: um editor sem tenant aparece com um poder que não tem.

Na importação, que é como os NEPEs entram na rede, três defeitos continuam abertos: a logo é baixada inteira na memória antes de conferir o limite de 5 MB quando o servidor de origem não informa o tamanho; uma falha de gravação no meio do lote deixa os NEPEs anteriores já criados e interrompe o resto; e o endereço da logo é seguido sem restrição, o que permite apontar o servidor para a própria rede interna. E não existe nenhuma forma de tirar os NEPEs do sistema: não há exportação, então não há cópia de segurança do cadastro nem como editar vários NEPEs de uma vez fora do painel.

## What Changes

**Exportação de NEPEs (nova)**

- Botão e rota que baixam um CSV com todos os NEPEs e seus administradores, nas mesmas colunas que o importador lê, de modo que o arquivo exportado possa ser enviado de volta na importação sem edição.
- A exportação traz dados pessoais dos administradores (nome e e-mail) e fica restrita ao super admin.

**Correções da importação**

- A logo passa a ser recebida com limite durante o download, em vez de carregada inteira na memória antes da conferência.
- O endereço da logo passa a ser restrito a destinos públicos: endereços da rede interna do servidor são recusados com o motivo no resultado.
- O lote passa a ser gravado em transação: ou todos os NEPEs válidos entram, ou nenhum entra. **BREAKING** em relação ao comportamento documentado hoje, em que um erro de gravação deixa as linhas anteriores já criadas e interrompe as seguintes.

**Listagens do painel**

- Tenants e usuários ganham ordem definida, busca e contagem de usuários por NEPE.
- A lista de usuários passa a ser agrupada por NEPE, com a logo do NEPE no cabeçalho de cada grupo e os usuários sem tenant num grupo próprio.
- Cada usuário aparece com a foto; sem foto, com as iniciais do nome.
- O rótulo do papel passa a seguir a regra real dos papéis: "Super Admin" só para quem tem os dois requisitos (sem tenant e grupo 0). Quem está sem tenant e fora do grupo 0 aparece destacado como cadastro inconsistente, porque não consegue entrar em painel nenhum.
- O e-mail do usuário passa a aparecer na lista, hoje visível só na tela de edição.

**Foto do usuário**

- A foto **não** nasce nesta change: ela vem de `perfil-do-usuario-no-admin`, que está em implementação e já criou em `User` os campos `photo`, `photo_updated_at` e `whatsapp`, o mapping Vich `user_photo` e a migration. Aqui a foto é apenas consumida nas listas.
- O que falta e entra aqui: o super admin poder enviar e remover a foto de **qualquer** usuário. A tela `/admin/perfil` daquela change não cobre isso, porque lá cada um edita somente a si mesmo.

## Capabilities

### New Capabilities

- `painel-do-super-admin`: como o super admin enxerga a rede em `/superadmin` — ordem, busca, agrupamento por NEPE, identificação visual (logo do NEPE e foto do usuário) e o rótulo do papel de cada usuário.
- `exportacao-de-nepes`: a saída dos NEPEs e administradores em CSV, o formato desse arquivo e a garantia de que ele volta pela importação.

### Modified Capabilities

- `cadastro-de-nepes`: a logo deixa de ser baixada sem limite e passa a ter o destino restrito; o lote passa a ser gravado em transação, substituindo o requisito atual de interromper as linhas seguintes deixando as anteriores gravadas.

## Impact

- **Banco**: nenhuma coluna nova. `photo` e `photo_updated_at` já vêm da migration `Version20261117211500`, do change `perfil-do-usuario-no-admin`.
- **Código**: `src/Controller/superadmin/SuperAdminController.php` (dashboard, edição de usuário), `src/Controller/superadmin/TenantImportController.php` (rota da exportação), `src/Service/TenantImport/TenantImporter.php` (download da logo e transação), `src/Service/TenantImport/TenantImportParser.php` (destino da URL), novo serviço de exportação, `src/Repository/{TenantRepository,UserRepository}.php` (consultas ordenadas e agrupadas), `templates/superadmin/**`.
- **Arquivos**: `public/uploads/user_photo` já é criada pelo change de perfil; esta change só lê de lá.
- **Changes abertas**: esta change **depende** de `perfil-do-usuario-no-admin` estar implementada. As duas tocam `SuperAdminController` e os templates de usuário do `/superadmin`, e a tarefa 6.2 daquela change (política de senha nessas telas) ainda está pendente — por isso esta entra depois dela, não em paralelo.
- **Produção**: deploy sem migration (código e templates). Sem efeito na API `/api/biblia`, logo sem efeito no nepe-search.
- **LGPD**: o CSV exportado contém nome e e-mail dos administradores; o acesso continua restrito ao super admin e o arquivo não fica guardado no servidor.
