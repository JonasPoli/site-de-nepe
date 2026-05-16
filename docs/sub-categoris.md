# Sub-Categorias
Agora as categorias poderão ser aninhadas em subcategorias.
Agora as categorias e as subcategorias poderão ter sessões, assim como as páginas.
Então, na lista de categoria/subcategoria, deve ter um botão com a ação de listar "Sessões" da categoria/subcategoria.

As categorias e as subcategorias poderão na prática serão como as páginas

Na lista de sessões, deve prever que no topo poderá ter as informações sobre uma página, categoria ou subcategoria (dependendo de onde o usuário clicou para chegar na lista de sessões).

Na lista de categorias, /admin/category, deve ter um botão de ação para listar as sub-categorias de uma categoria.

## Novos campos
Categoria pai
Aparece no menu do topo? (sim/não)
Aparece no menu do rodapé? (sim/não)


##  Form
Quando uma categoria for criada ou editatada, deve ser possível informar quql a categoria pai (relacionamento OneToMany com a mesma entidade).
Deve ser possível listar as subcategorias de uma categoria.

## Menu
Na área pública do site, no menu superior e menu no rodapé deverá aparecer as categorias e subcategorias marcadas para aparecer.


## Sub-sub
As subcategorias não poderão ter subcategorias (aninhamento apenas de um nível).
Deve ter esse controle pra só poder escolher como "Categoria Pai" uma Categoria que não seja subcategoria.


## Campos de identificação
Para cada categoria/subcategoria deverá ter os seguintes campos de identificação:
pré-título, título (que já existe) e descrição .
Sero usado da seguinte forma:
```html
<div class="container vl-hero-inner reveal-up active">
        <div class="vl-hero-text">
            <span class="vl-badge">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"></path></svg>
                <pré-titulo>
            </span>
            <h1><titulo></h1>
            <p><descrição></p>
        </div>
            </div>
```

# Elegancia
Econtrole uma  maneira elegante de organizar a existência de categorias e sub-categorias.
Encontre uma maneira elegante, bonita e bem organizada para exibir as subcategorias de uma categoria.