# Indexação com o texto bíblico
Agora, neste projeto, cada artigo, vídeo, material e página vai poder (ou não) ser associado a um versículo biblico ou a uma perícope.

## Bíblia
Neste computador temos um outro projeto especializado em biblia:
 /Volumes/Dados/work/nepe-serach-github
 então, vasculhe por lá, entenda como funciona o sistema de testamentos, livros, capítulos e versículos. Repare que eles possuem ids fixas. Se precisar acesse o banco do sistema para entender.
 Veja se é mais eficiente montar um json com este conteúdo para usar nos scripts dos selects ou se é melhor pegar via uma API a ser construida
 Você vai precisar criar aqui as arrays dos conteúdos de bíblia, para saber quantos capítulos cada livro possui, quantos versículos cada capítulo possui. etc.
 Copie para este sistema aqui a estrutura e conteudo de biblia_version, biblia_testament, biblia_book, biblia_verse e biblia_verse_ext. 
Você só vai pegar os versículos em biblia_verse da versão ARC.

 2	(ARC) - 1969 - Almeida Revisada e Corrigida	/pt/bible/212/GEN.1.arc	ARC

 Este sistema fica em várias máquinas, inclusive nesta e no servidor da produção.
 Você vai precisar criar um command para popular com os dados bíblicos os sistema, na base do se não tem cria, se tem atualiza, para evitar estragar caso rodado duas vezes.
 Acho que este arquivo de dados grande que será usado pelo command pode ficar zipado no sistema, dezipado quando for ser importado.
 Veja se não vai ficar muito grande para o github
 
## Escolha do versículo
No painel administrativo deste sistema aqui, quando estiver sendo editado ou inserido a artigo, vídeo, material ou página, deve ter um checkbox para escolher se deseja associar um versículo biblico ou a uma perícope. 

Se o uysuário marcar, o sistema deve apresentar um select para o usuário indicar o testamento (primeiro ou segundo - AT ou NT)
Quando escolher, no select do livro, aparecem os livros relacionados ao testamento escolhido.
Será um select com seach com os nomes do livro.
Quando escolher um livro, o sistema ajusta o select do capítulo, apresentando apenas os números de capítulos do livro escolhido, impedindo assim que o usuário escolha um capítulo que não existe.
Quando escolher o capítulo, o sistema deve apresentar os versículos em selects exibindo apenas os versículos desse capítulo.

Depois, o usuário poderá selecionar em outro select o versículo inicial e o versículo final.
Se já tiver tudo preenchido e o usuário mudar o capítulo, o sistema limpa os dados dos versículos
Se já tiver tudo preenchido e o usuário mudar o liveo, o sistema limpa os dados dos capítuolos e dos versículos e assim por diante.
Este sistema de associar um texto bíblico ao conteúdo deve ser bem bonito, funcional, elegante.

Quando o usuário tiver escolhido um versículo ou um trecho (quando escolher o versiculo final)
o sistema deve recuperar o texto bíblico referente a estes versículos e apresentar de forma elegante no painel administrativo.

## API
Você deve criar uma API que receba como parametros o livro, o capítulo e o versículo inicial e final e retorne a lista de conteúdos associados a este trecho.
Como este sistema é multi-tenant, este resultado deve ser fornecido com a url full do conteúdo no tenant que o criou. Deve ter a URL full da imagem. Deve ter a url full do thumb do vídeo (seo for um video), deve ter o logo do tenant. 
Futuramente, o serviço existente em  /Volumes/Dados/work/nepe-serach-github vai consumir estes dados exibindo o conteúdo produzidos pelos tenants deste serviço aqui lá em  /Volumes/Dados/work/nepe-serach-github

# Estudo
Antes de desenvolver isso, faça um estudo da situação e pense na melhor forma possível de concretizar estes desejos depois implante

