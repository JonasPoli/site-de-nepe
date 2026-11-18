# PageSpeed dos sites dos NEPEs — 17/11/2026

Aplicação da skill `pagespeed-100` (escrita a partir do trabalho no WAB Sites).

## Antes e depois

Medido em `renovandoconsciencias.nepebrasil.org`, no PageSpeed Insights oficial.
Ordem: desempenho / acessibilidade / práticas recomendadas / SEO.

| | Antes | Depois |
|---|---|---|
| Celular | 73 / 94 / 100 / 100 | **99 / 100 / 100 / 100** |
| Computador | 85 / 94 / 100 / 100 | **96 / 100 / 100 / 100** |

Peso de imagem da home: **9,7 MB → 316 KB** (um PNG de 7,8 MB virou 83 KB).

Como é uma instalação só servindo todos os NEPEs, as correções valem para os seis sites.

## Bloqueio que não é de código: o Cloudflare na frente

Cinco dos seis domínios — `nepebrasilia`, `ciscodedeus`, `rodrigolobato`, `nepealcione` e
`ueebp` — respondem **403 com a tela "Executando verificação de segurança"** a qualquer
robô. O PageSpeed mede essa tela, não o site: ao medir `ciscodedeus` ele deu **SEO 40** e
apontou exatamente as marcas da tela de desafio.

```
O documento usa <meta http-equiv="refresh">
A página está bloqueada para indexação
A página tem uma falha no código de status HTTP
```

**O Google também não indexa esses cinco sites.** Isso é mais grave que qualquer nota e
nenhuma mudança no PHP resolve: é o Security Level em "I'm Under Attack" ou o Bot Fight
Mode no Cloudflare do `nepebrasil.org`. Conferir em Security → Settings: Security Level em
Medium e Bot Fight Mode desligado.

Confira com:

```bash
curl -s -o /dev/null -w "%{http_code}\n" https://ciscodedeus.nepebrasil.org/
```

## O que foi feito

### Imagens

- Filtro Twig `thumb` / `thumb_attrs` / `thumb_srcset` (`src/Twig/ThumbExtension.php`):
  devolve o caminho pronto da miniatura. Antes o `imagine_filter()` gerava
  `/media/cache/resolve/…`, que responde **302** antes de servir a imagem.
- `FormatExtensionResolver` registrado pelo mesmo motivo.
- Todas as imagens dos templates passam por filtro, com `width` e `height` no HTML.
- O banner da home e a capa do artigo deixaram de ser `background-image` em CSS e viraram
  `<img>`: imagem em CSS não é descoberta cedo pelo navegador e não aceita `srcset`.
- Filtros novos: `hero_desktop`, `hero_mobile`, `bloco_imagem`, `galeria`,
  `conteudo_largo`, `capa_pagina`, `avatar`.
- Comando `app:miniaturas:aquecer`, que precisa rodar depois de todo deploy.
- Pré-carga da imagem do maior elemento visível, no começo do `<head>`.

### Nada de terceiro no caminho de renderização

| Saiu | Como |
|---|---|
| FontAwesome (cdnjs) | SVG embutido, `symfony/ux-icons` |
| Shoelace (jsdelivr) | os cinco `<sl-icon>` viraram `ux_icon('bi:…')` — a biblioteca padrão do Shoelace é o Bootstrap Icons |
| Google Fonts | a Inter passou a ser servida pelo próprio domínio (`templates/pub/_fontes.html.twig`) |
| Swiper (jsdelivr) | do próprio domínio, só na página com carrossel e carregado depois do `load` |
| GLightbox (jsdelivr) | do próprio domínio e carregado depois do `load` |

### Acessibilidade

- A cor do NEPE ganhou variante legível para texto (`--color-*-text`) e par fundo+tinta
  para preenchimento sólido (`--color-*-solid` e `--color-on-*`): no rosa `#cb4e9a` nem
  preto nem branco chegavam a 4,5:1.
- Títulos do rodapé de `h4` para `h3` (pulavam o `h3`).
- Marcadores do banner com alvo de toque de 24 px, sem mudar o desenho.

## Deploy

```bash
ssh -p 22022 root@162.240.232.248
cd /home/projetomaster/repositories/site-de-nepe
sudo -u projetomaster git pull
sudo -u projetomaster /opt/cpanel/ea-php83/root/usr/bin/php composer.phar install --no-interaction --optimize-autoloader
P=/opt/cpanel/ea-php83/root/usr/bin/php
sudo -u projetomaster $P bin/console cache:clear --env=prod
sudo -u projetomaster $P bin/console asset-map:compile --env=prod
sudo -u projetomaster $P bin/console app:videos:capas --env=prod
sudo -u projetomaster $P bin/console app:miniaturas:aquecer --env=prod
```

> O `public/.htaccess` tem alterações do cPanel que **não** estão no git. Nunca rodar
> `git checkout`, `stash` ou `reset` nesse arquivo. O `git pull` o preserva.

## O que ficou de fora

- O cache de longa duração dos estáticos: a auditoria `uses-long-cache-ttl` tem **peso 0**
  na nota, e mexer no `public/.htaccess` conflitaria com a alteração do cPanel que está no
  servidor fora do git. Melhora quem volta ao site, não a nota.
- Os outros 5 domínios só poderão ser medidos (e indexados) depois que o Cloudflare sair
  da frente.
