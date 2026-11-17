<?php

namespace App\Service\TenantImport;

/**
 * O arquivo passou do limite enquanto estava sendo recebido.
 *
 * Interna ao download da logo: serve para interromper a leitura em pedaços sem
 * confundir o motivo com uma falha de rede.
 */
class TooLargeException extends \RuntimeException
{
}
