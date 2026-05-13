utilize as skills em skills para criar este novo projeto em Symfony, aproveitando a esttrutura existente.


## **Arquitetura do Sistema de Inscrições**

O sistema será dividido em um painel administrativo  e uma área pública customizada com Symfony  e Twig.

## **1\. Entidades de Dados (Banco de Dados)**

As tabelas serão relacionadas para permitir itens opcionais e múltiplos inscritos em uma única sessão de pagamento.

* **Evento:** Armazena a chave Pix, a descrição do evento e a mensagem de sucesso, banner de imagem, logo do evento.  
* **TipoInscricao:** Armazena o nome (Adulto, Jovem) e o valor base. Deve ser relacionado ao evento.  
* **ItemAdicional:** Relacionado ao TipoInscricao, contém a descrição e o valor extra, é relacionado a um tipo de inscrição. 
* **Inscricao:** Representa o grupo de pessoas cadastradas por um responsável. Armazena o nome do cadastrante e a data.  Relacionado ao evento.
* **Inscrito:** Cada pessoa individual dentro de uma Inscrição, com seu respectivo Tipo e Itens Adicionais. Relacionado ao evento.

## **2\. Painel Administrativo**

Utilizaremos o padrão de controladores do Symfony para gerenciar as configurações.


* **CRUD de Tipos e Itens:** Interface para criar o tipo "Adulto" e pendurar nele os itens "Com alimentação" ou "Sem alimentação" com seus respectivos valores.  
* **Exportação de Dados:** Um serviço que utiliza a biblioteca `League\Csv` para gerar o arquivo solicitado. O sistema buscará os dados de `Inscrito` e cruzará com o `Cadastrante` e a `DataInscricao`.

As listagens na área administrativa devem fazer uso do DataTable configurado para exbibir por padrão 100 registros, tendos as opções de exibir 100 em 100, 1000 em 1000 e todos.

### CRUD De eventos
Responsável por listar os dados dos eventos cadaastrados, com:
 - Nome do evento
 - Descrição do evento (com TinyMCE)
 - Chave Pix
 - Mensagem de sucesso
 - Banner de imagem
 - Logo do evento
 - Data de início
 - Data de fim
 - Status (ativo/inativo)
 - Token do evento, gerado com a concatenação de 2 strings de uniqueid
 - Cor do background
 - Cor primária do texto
 - Cor secundária do texto
 - Cor dos botões primários
 - Cor dos botões secundários

### CRUD de Tipos de Inscrição
Responsável por listar os dados dos tipos de inscrição cadastrados, com:
 - Nome do eventos a qual está associado (com a imagem do logo)
 - Nome do tipo de inscrição (Na listagem, na mesma célula no nome do tipo, devem ter , com a fonte menor, a lista de todos os itens adicionais com seus respectivos valores)
 - Valor base
 - Status (ativo/inativo)

### CRUD de Itens Adicionais
Responsável por listar os dados dos itens adicionais cadastrados, com:
 - Nome do evento cujo tipo de inscrição estiver associado (com a imagem do logo)
 - Nome do tipo de inscrição a qual está associado
 - Nome do item adicional
 - Valor do item adicional
 - Status (ativo/inativo)

### CRUD de Inscritos
Responsável por listar os dados dos inscritos cadastrados, com:
 - Nome do evento a qual está associado (com a imagem do logo)
 - Nome do tipo de inscrição a qual está associado
 - Nome do item adicional escolhidos no ato do cadastrasto com seu valor
 - Data da inscrição
 - Status (ativo/inativo)
 Na listagem deve ter um botão para exportar para CSV que exporta todos os dados dos inscritos, dos itens adicionar escolhidos no ato da inscrição e do tipo de inscrição escolhido no ato da inscrição.


## **3\. Área Pública e Fluxo de Inscrição**
A área pública deve seguir as cores definidas no CRUD de eventos.


O fluxo será controlado por uma **Session** (sessão) do Symfony para manter os inscritos temporários antes da finalização.

Haverá uma rota pública que receberá o token do evento e exibirá o formulário de inscrição.
Nesta rota será apresentado todos os dados do evento, com a imagem do banner e do logo e a descrição com raw, já que foi cadastrado com TinyMCE.

Abaixo da apresentação do evento teremos um botão para "Adicionar inscrito"
Quando o visitante clicar para adicionar inscrito, o sistema deve exibir um formulário com os seguintes campos:
 
 - Nome completo
 - Nome para o crachá
 - Data de nascimento
 - e-mail
 - CPF
 - Whatsapp
 - Nome e telefone de contato de emergência
 - Cidade
 - Estado
 - Possui restrição alimentar? (campo texto)
 - Possui alguma alergia? (campor texto)
 - Estou ciente que a minha participação no evento está de acordo com as diretrizes da Lei Geral de Proteção de Dados informada no início desse formulário. (checkbox de preenchimento obrigatório)
 - Autorizo, que a Comissão organizadora do evento, utilize gratuitamente - pelas redes sociais ou site - imagens, sons, falas dos participantes com o objetivo de divulgação e de arquivamento histórico das memórias do evento. Comprometo-me a não gravar, copiar, compartilhar, publicar ou utilizar de qualquer forma as imagens, sons ou quaisquer outros dados dos demais participantes do evento, nos termos da legislação em vigor. (checkbox de preenchimento obrigatório)
 - Tipo de inscrição (select com os tipos de inscrição cadastrados no CRUD de Tipos de Inscrição)
 - Itens adicionais (select com os itens adicionais cadastrados no CRUD de Itens Adicionais de acordo com o tipo de inscrição escolhido) - Leve em consideração que podem ter vários itens adicionais.

Em algum lugar, o local mais apropriado, deve ter bem visível o valor total dessa inscrição atual, com a soma de todos os itens opçionais escolhidos.

Deve ter um botão para "Cadastrar inscrito" que deve voltar para a página com os dados do evento com o  "Adicionar inscrito" para que o usuário possa cadastrar outro inscrito.
Junto com o botão, deve ter a lista dos inscritos já cadastrados contendo nome, tipo de inscrição e itens adicionais escolhidos e valor da inscrição.
Em algum lugar dessa tela , o local mais apropriado, deve ter bem visível o valor total da soma de todos os incritos.
Quando a listagen de inscritos for maior do que zero, o sistema mostra 2 botões: Adicionar inscrito e Finalizar inscrições.

Quando resolver finalizar, o sistema deve levar para uma página de resumo com todos os dados do inscrito e o valor total da inscrição, com a soma de todos os itens opçionais escolhidos.
Deve ter o valor total bem grande.
Deve ter a chave pix e o QR code para pagamento.
A chave pix deve ter um botão "copiar" que copia o valor da chave pix para a área de transferência, quando fizer isso aparece uma mensagem "Chave pix copiada"
A chave pix deve ser gerada seguindo o roteiro abaixo.
A função de gerar chave pix deve estar separada num service específico.


---

## **Estrutura de Pastas Sugerida**

* `src/Entity/`: Definição das tabelas com Annotations/Attributes.  
* `src/Controller/Admin/`: Controladores para gestão e exportação CSV.  
* `src/Controller/Pub/`: Controladores para gestão das rotas da área pública
* `src/Service/PixService.php`: Serviço para calcular o valor total e gerar o código de pagamento.  
* `templates/`: Arquivos Twig para a interface visual.




```php
<?php

// 1. CONFIGURAÇÕES DO BANCO DE DADOS
$host = 'localhost';
$db   = 'nome_do_seu_banco';
$user = 'usuario_mysql';
$pass = 'senha_mysql';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
} catch (PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}

// 2. CLASSE GERADORA PIX
class PixGenerator {
    
    private function crc16($data) {
        $res = 0xFFFF;
        for ($i = 0; $i < strlen($data); $i++) {
            $res ^= (ord($data[$i]) << 8);
            for ($j = 0; $j < 8; $j++) {
                if ($res & 0x8000) {
                    $res = ($res << 1) ^ 0x1021;
                } else {
                    $res <<= 1;
                }
                $res &= 0xFFFF;
            }
        }
        return strtoupper(str_pad(dechex($res), 4, '0', STR_PAD_LEFT));
    }

    private function formatField($id, $value) {
        $len = str_pad(strlen($value), 2, '0', STR_PAD_LEFT);
        return $id . $len . $value;
    }

    public function generate($chave, $beneficiario, $cidade, $valor, $txid) {
        // Limpeza básica: remove acentos e caracteres especiais do nome/cidade
        $beneficiario = iconv('UTF-8', 'ASCII//TRANSLIT', $beneficiario);
        $cidade = iconv('UTF-8', 'ASCII//TRANSLIT', $cidade);
        
        $gui = $this->formatField('00', 'BR.GOV.BCB.PIX');
        $key = $this->formatField('01', $chave);
        $merchantAccount = $this->formatField('26', $gui . $key);

        $payload = [
            '00' => '01',
            '26' => $merchantAccount,
            '52' => '0000',
            '53' => '986',
            '54' => number_format($valor, 2, '.', ''),
            '58' => 'BR',
            '59' => substr($beneficiario, 0, 25),
            '60' => substr($cidade, 0, 15),
            '62' => $this->formatField('05', $txid),
        ];

        $result = '';
        foreach ($payload as $id => $value) {
            $result .= $this->formatField($id, $value);
        }

        $result .= '6304';
        $result .= $this->crc16($result);

        return $result;
    }
}

// 3. BUSCA DOS DADOS DO PEDIDO NO MYSQL
$id_do_pedido_venda = 123; // Exemplo de ID vindo de um $_GET['id'] ou variável

$stmt = $pdo->prepare("SELECT id, total FROM pedidos WHERE id = ?");
$stmt->execute([$id_do_pedido_venda]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if ($pedido) {
    // 4. GERAÇÃO DO CÓDIGO
    $pix = new PixGenerator();
    
    // Identificador único (TXID): "PED" + número do pedido
    $txid = "PED" . $pedido['id']; 
    
    $linhaPix = $pix->generate(
        'suachave@email.com',   // <-- SUA CHAVE PIX AQUI
        'MINHA LOJA LTDA',      // Nome do beneficiário (sem acentos)
        'SAO PAULO',            // Cidade
        $pedido['total'],       // Valor vindo do Banco de Dados
        $txid                   // ID do pedido como identificador
    );

    // 5. EXIBIÇÃO PARA O CLIENTE
    echo "<h3>Pagamento via Pix</h3>";
    echo "<p>Valor: R$ " . number_format($pedido['total'], 2, ',', '.') . "</p>";
    echo "<p>Copie o código abaixo e cole no seu aplicativo bancário:</p>";
    echo "<textarea style='width:100%; height:100px; word-break: break-all;' readonly id='codPix'>$linhaPix</textarea>";
    echo "<br><button onclick=\"document.getElementById('codPix').select(); document.execCommand('copy'); alert('Copiado!');\">Copiar Código Pix</button>";

} else {
    echo "Pedido não encontrado.";
}
```

Pedindo ajuda ao Gemini de como gerar o QRCODE em PHP.
Para gerar a imagem do QR Code a partir da linha "Copia e Cola" que criamos, a melhor abordagem em PHP é utilizar uma biblioteca robusta. A biblioteca padrão do mercado para isso é a chillerlan/php-qrcode, que é moderna e fácil de usar.

Vou dividir em dois passos: como instalar a biblioteca e o código PHP completo e atualizado para exibir o valor, o texto para copiar e o QR Code.

Passo 1: Instalar a Biblioteca (via Composer)
Se você não usa o Composer (gerenciador de dependências do PHP), recomendo fortemente começar a usar. É o padrão para projetos PHP modernos.

Na pasta do seu projeto, execute este comando no terminal:
composer require chillerlan/php-qrcode

Passo 2: O Código PHP Completo (Unindo Tudo)
Este script é uma evolução do anterior. Ele conecta ao banco, busca o pedido, gera o código Pix usando a nossa classe e, no final, usa a biblioteca php-qrcode para gerar a imagem.

```php
<?php

// 1. CARREGAR DEPENDÊNCIAS DO COMPOSER
// Certifique-se de que o caminho para o autoload.php está correto.
// Geralmente fica na pasta 'vendor' na raiz do seu projeto.
require_once _DIR_ . '/vendor/autoload.php';

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

// 2. CONFIGURAÇÕES DO BANCO DE DADOS
$host = 'localhost';
$db   = 'nome_do_seu_banco';
$user = 'usuario_mysql';
$pass = 'senha_mysql';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
} catch (PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}

// 3. CLASSE GERADORA PIX (A mesma do exemplo anterior)
class PixGenerator {
    
    private function crc16($data) {
        $res = 0xFFFF;
        for ($i = 0; $i < strlen($data); $i++) {
            $res ^= (ord($data[$i]) << 8);
            for ($j = 0; $j < 8; $j++) {
                if ($res & 0x8000) {
                    $res = ($res << 1) ^ 0x1021;
                } else {
                    $res <<= 1;
                }
                $res &= 0xFFFF;
            }
        }
        return strtoupper(str_pad(dechex($res), 4, '0', STR_PAD_LEFT));
    }

    private function formatField($id, $value) {
        $len = str_pad(strlen($value), 2, '0', STR_PAD_LEFT);
        return $id . $len . $value;
    }

    public function generate($chave, $beneficiario, $cidade, $valor, $txid) {
        // Limpeza básica: remove acentos e caracteres especiais do nome/cidade
        $beneficiario = iconv('UTF-8', 'ASCII//TRANSLIT', $beneficiario);
        $cidade = iconv('UTF-8', 'ASCII//TRANSLIT', $cidade);
        
        $gui = $this->formatField('00', 'BR.GOV.BCB.PIX');
        $key = $this->formatField('01', $chave);
        $merchantAccount = $this->formatField('26', $gui . $key);

        $payload = [
            '00' => '01',
            '26' => $merchantAccount,
            '52' => '0000',
            '53' => '986',
            '54' => number_format($valor, 2, '.', ''),
            '58' => 'BR',
            '59' => substr($beneficiario, 0, 25),
            '60' => substr($cidade, 0, 15),
            '62' => $this->formatField('05', $txid),
        ];

        $result = '';
        foreach ($payload as $id => $value) {
            $result .= $this->formatField($id, $value);
        }

        $result .= '6304';
        $result .= $this->crc16($result);

        return $result;
    }
}

// 4. BUSCA DOS DADOS DO PEDIDO NO MYSQL
$id_do_pedido_venda = 123; // Exemplo de ID vindo de um $_GET['id'] ou variável

$stmt = $pdo->prepare("SELECT id, total FROM pedidos WHERE id = ?");
$stmt->execute([$id_do_pedido_venda]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if ($pedido) {
    // 5. GERAÇÃO DO CÓDIGO PIX (A STRING)
    $pix = new PixGenerator();
    $txid = "PED" . $pedido['id']; // Identificador único do pedido
    
    $linhaPix = $pix->generate(
        'suachave@email.com',   // <-- SUA CHAVE PIX AQUI
        'MINHA LOJA LTDA',      // Nome do beneficiário (sem acentos)
        'SAO PAULO',            // Cidade
        $pedido['total'],       // Valor vindo do Banco de Dados
        $txid                   // ID do pedido como identificador
    );

    // 6. GERAÇÃO DO QR CODE (A IMAGEM)
    $options = new QROptions([
        'version'    => 5,
        'outputType' => QRCode::OUTPUT_IMAGE_PNG,
        'eccLevel'   => QRCode::ECC_L, // Nível de correção de erro (Baixo, suficiente para Pix)
        'scale'      => 5, // Tamanho do QR Code
        'imageBase64' => true, // Importante para embutir na tag img
    ]);

    $qrcode = new QRCode($options);
    $qrcodeImage = $qrcode->render($linhaPix);

    // 7. EXIBIÇÃO PARA O CLIENTE (HTML)
    ?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Pagamento via Pix - Pedido #
        <?php echo $pedido['id']; ?>
    </title>
    <style>
        body {
            font-family: sans-serif;
            text-align: center;
            margin-top: 50px;
        }

        .pix-container {
            max-width: 400px;
            margin: 0 auto;
            border: 1px solid #ccc;
            padding: 20px;
            border-radius: 8px;
        }

        #codPix {
            width: 100%;
            height: 80px;
            word-break: break-all;
            margin-top: 15px;
            border-color: #ddd;
        }

        button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 10px;
        }

        button:hover {
            background-color: #0056b3;
        }
    </style>
</head>

<body>

    <div class="pix-container">
        <h3>Pagamento via Pix</h3>
        <p><strong>Pedido #
                <?php echo $pedido['id']; ?>
            </strong></p>
        <p>Valor: <strong>R$
                <?php echo number_format($pedido['total'], 2, ',', '.'); ?>
            </strong></p>

        <img src="<?php echo $qrcodeImage; ?>" alt="QR Code Pix" />

        <p style="font-size: 0.9em; color: #666;">Ou, se preferir, copie o código abaixo:</p>
        <textarea readonly id="codPix"><?php echo $linhaPix; ?></textarea>
        <br>
        <button onclick="copiarPix()">Copiar Código Pix</button>
    </div>

    <script>
        function copiarPix() {
            var copyText = document.getElementById("codPix");
            copyText.select();
            copyText.setSelectionRange(0, 99999); // Para dispositivos móveis
            document.execCommand("copy");
            alert("Código Pix copiado!");
        }
    </script>

</body>

</html>
<?php

} else {
    echo "Pedido não encontrado.";
}
?>
```

Explicação das Novidades:
require_once _DIR_ . '/vendor/autoload.php';: Esta é a linha mágica do Composer que carrega todas as bibliotecas
instaladas (incluindo a do QR Code).

use chillerlan\QRCode\QRCode; e use chillerlan\QRCode\QROptions;: Estamos importando as classes necessárias da
biblioteca.

QROptions: Configuramos como queremos o QR Code. O ponto mais importante é 'imageBase64' => true, que faz a biblioteca
retornar uma string Base64 da imagem, permitindo que a embutamos diretamente na tag <img> no HTML, sem precisar salvar
um arquivo no servidor.

$qrcode->render($linhaPix): Esta linha pega a string do Pix e a transforma na imagem do QR Code.

HTML/CSS/JS: Adicionei um design básico e o JavaScript necessário para que o botão "Copiar Código Pix" funcione em
computadores e celulares.

Agora você tem uma solução completa que gera tanto a imagem para escanear quanto o texto para copiar, identificados pelo
número do pedido.