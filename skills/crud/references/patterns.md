# CRUD Patterns

Common patterns for building and customizing Symfony CRUDs.

---

## Standard Controller with MapEntity

Use `#[MapEntity(id: 'id')]` to bind specific record IDs in edit and delete routes.

```php
#[Route('/admin/news')]
#[IsGranted('ROLE_ADMIN')]
final class NewsController extends AbstractController
{
    #[Route(name: 'admin_news_index', methods: ['GET'])]
    public function index(NewsRepository $newsRepository): Response
    {
        return $this->render('admin/news/index.html.twig', [
            'news' => $newsRepository->findBy([], ['publishedAt' => 'DESC']),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_news_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, #[MapEntity(id: 'id')] News $news, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(NewsType::class, $news);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Registro atualizado!');
            return $this->redirectToRoute('admin_news_index');
        }

        return $this->render('admin/news/edit.html.twig', [
            'news' => $news,
            'form' => $form->createView(),
        ]);
    }
}
```

---

## Index Template with DataTable (Shoelace)

The listing should use a `sl-card` wrapper and a `#crud-table` with DataTables initialization.

```twig
{% block body %}
    <sl-card>
        <div slot="header" class="flex justify-between items-center">
            <h1 class="text-xl font-bold">Listagem de Notícias</h1>
            <sl-button variant="primary" outline href="{{ path('admin_news_new') }}">
                <sl-icon slot="prefix" name="plus-lg"></sl-icon>
                Novo Registro
            </sl-button>
        </div>

        <table id="crud-table" class="display w-full">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Título</th>
                    <th>Data</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                {% for item in news %}
                    <tr>
                        <td>{{ item.id }}</td>
                        <td>{{ item.title }}</td>
                        <td>{{ item.publishedAt ? item.publishedAt|date('d/m/Y') : '' }}</td>
                        <td>
                            <div class="flex gap-2">
                                <sl-button size="small" variant="neutral" outline href="{{ path('admin_news_edit', {id: item.id}) }}">
                                    <sl-icon name="pencil-square"></sl-icon>
                                </sl-button>
                            </div>
                        </td>
                    </tr>
                {% endfor %}
            </tbody>
        </table>
    </sl-card>
{% endblock %}

{% block javascripts %}
    {{ parent() }}
    <script src="https://cdn.datatables.net/2.1.3/js/dataTables.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            new DataTable('#crud-table', {
                responsive: true,
                language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json' }
            });
        });
    </script>
{% endblock %}
```

---

## Form Template (Two-Column Grid)

Standardize the form layout with a responsive grid and Shoelace buttons.

```twig
{# templates/admin/news/_form.html.twig #}
{{ form_start(form) }}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="md:col-span-2">
            {{ form_row(form.title) }}
        </div>
        <div>
            {{ form_row(form.category) }}
        </div>
        <div>
            {{ form_row(form.publishedAt) }}
        </div>
        <div class="md:col-span-2">
            {{ form_row(form.content, {attr: {class: 'editor-html'}}) }}
        </div>
    </div>

    <div class="flex justify-between items-center mt-8 border-t pt-6">
        <sl-button type="submit" variant="primary" size="large">
            <sl-icon slot="prefix" name="check-lg"></sl-icon>
            Salvar Registro
        </sl-button>
        
        {% if news.id %}
            {{ include('admin/news/_delete_form.html.twig') }}
        {% endif %}
    </div>
{{ form_end(form) }}
```
