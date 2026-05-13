<?php

namespace App\Service;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRMarkupSVG;

class PixService
{
    private function crc16(string $data): string
    {
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

    private function formatField(string $id, string $value): string
    {
        $len = str_pad(strlen($value), 2, '0', STR_PAD_LEFT);
        return $id . $len . $value;
    }

    /**
     * Generates the PIX copy-paste code (Copia e Cola)
     */
    public function generatePixCode(
        string $chave,
        string $beneficiario,
        string $cidade,
        float $valor,
        string $txid
    ): string {
        // Remove accents and special characters
        $beneficiario = iconv('UTF-8', 'ASCII//TRANSLIT', $beneficiario);
        $cidade = iconv('UTF-8', 'ASCII//TRANSLIT', $cidade);

        $gui = $this->formatField('00', 'BR.GOV.BCB.PIX');

        // Sanitize key: if it's potentially a CPF, CNPJ or Phone, remove non-alphanumeric
        // For email and random keys, keep as is.
        // Simple heuristic: if it contains '@', it's an email. If it looks like a UUID, it's a random key.
        if (strpos($chave, '@') === false && !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}/i', $chave)) {
            // Remove everything except numbers and letters
            $chave = preg_replace('/[^a-zA-Z0-9+]/', '', $chave);
        }

        $key = $this->formatField('01', $chave);
        $merchantAccount = $gui . $key;

        $payload = [
            '00' => '01',
            '26' => $merchantAccount,
            '52' => '0000',
            '53' => '986',
            '54' => number_format($valor, 2, '.', ''),
            '58' => 'BR',
            '59' => substr($beneficiario, 0, 25),
            '60' => substr($cidade, 0, 15),
            '62' => $this->formatField('05', substr($txid, 0, 25)),
        ];

        $result = '';
        foreach ($payload as $id => $value) {
            $result .= $this->formatField($id, $value);
        }

        $result .= '6304';
        $result .= $this->crc16($result);

        return $result;
    }

    /**
     * Generates a base64 data URI (SVG) QR Code from a PIX code string.
     * SVG works universally without ext-gd.
     */
    public function generateQrCode(string $pixCode): string
    {
        $options = new QROptions();
        $options->eccLevel        = EccLevel::L;
        $options->scale           = 6;
        $options->outputInterface = QRMarkupSVG::class;
        $options->outputBase64    = true;

        // Return as inline base64 data URI for use in <img src="..."/>
        return (new QRCode($options))->render($pixCode);
    }
}
