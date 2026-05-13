<?php
require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/config/bootstrap.php';

use App\Kernel;
use App\Entity\Evento;
use App\Entity\TipoInscricao;
use App\Entity\ItemAdicional;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;

$kernel = new Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();
$em = $container->get('doctrine')->getManager();

// Wipe existing
$em->createQuery('DELETE FROM App\Entity\ItemAdicional')->execute();
$em->createQuery('DELETE FROM App\Entity\TipoInscricao')->execute();
$em->createQuery('DELETE FROM App\Entity\Inscrito')->execute();
$em->createQuery('DELETE FROM App\Entity\Inscricao')->execute();
$em->createQuery('DELETE FROM App\Entity\Evento')->execute();

$evento = new Evento();
$evento->setNome('DevFest 2026');
$evento->setDescricao('<p>Maior evento de dev!</p>');
$evento->setChavePix('12345678900');
$evento->setToken('test-token-123');
$evento->setStatus('ativo');
$evento->setCorBackground('#ffffff');
$evento->setCorTexto('#000000');
$evento->setCorBotaoPrimario('#0000ff');
$evento->setDataInicio(new \DateTime());
$em->persist($evento);

$tipo = new TipoInscricao();
$tipo->setNome('VIP');
$tipo->setValorBase('100.00');
$tipo->setStatus('ativo');
$tipo->setEvento($evento);
$em->persist($tipo);

$item = new ItemAdicional();
$item->setDescricao('Camiseta');
$item->setValor('50.00');
$item->setStatus('ativo');
$item->setTipoInscricao($tipo);
$em->persist($item);

$em->flush();
echo "Generated event with token: " . $evento->getToken() . "\n";
