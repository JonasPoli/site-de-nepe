<?php

namespace App\Twig;

/**
 * Contraste entre duas cores pela fórmula do WCAG 2.x (luminância relativa).
 *
 * O tema noticias usa para decidir a cor dos títulos no modo escuro; o
 * `tenant_css_vars()` usa para gerar a variante legível da cor do cliente
 * (ver `legivelSobre()`).
 */
final class ColorContrast
{
    /** Razão de contraste de 1 a 21. Cor inválida ou vazia dá 1 (sem contraste). */
    public static function ratio(?string $a, ?string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);
        if ($la === null || $lb === null) {
            return 1.0;
        }
        [$hi, $lo] = $la >= $lb ? [$la, $lb] : [$lb, $la];
        return ($hi + 0.05) / ($lo + 0.05);
    }

    /** Luminância relativa de 0 a 1, ou null para cor que não é #rgb ou #rrggbb. */
    public static function luminance(?string $hex): ?float
    {
        $hex = ltrim(trim((string) $hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return null;
        }
        $channel = function (string $h): float {
            $c = hexdec($h) / 255;
            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };
        return 0.2126 * $channel(substr($hex, 0, 2))
             + 0.7152 * $channel(substr($hex, 2, 2))
             + 0.0722 * $channel(substr($hex, 4, 2));
    }

    /**
     * A mesma cor, escurecida ou clareada só o quanto baste para chegar ao contraste
     * mínimo sobre o fundo informado. O matiz e a saturação não mudam: a cor da marca
     * continua reconhecível, só fica legível.
     *
     * Usada no texto (títulos, links, rótulos). Preenchimento sólido continua com a cor
     * exata que o cliente escolheu — ali quem precisa de contraste é o texto por cima.
     *
     * Devolve a cor original quando ela já tem contraste, e null para cor inválida.
     */
    public static function legivelSobre(?string $cor, ?string $fundo, float $minimo = 4.5): ?string
    {
        $rgb = self::toRgb($cor);
        $luzFundo = self::luminance($fundo);
        if ($rgb === null || $luzFundo === null) {
            return self::toHex($rgb);
        }

        if (self::ratio(self::toHex($rgb), $fundo) >= $minimo) {
            return self::toHex($rgb);
        }

        // Fundo claro pede cor mais escura; fundo escuro, mais clara.
        $paraEscuro = $luzFundo > 0.18;
        [$h, $s, $l] = self::rgbToHsl($rgb);

        // 100 passos de 1% de luminosidade: o bastante para qualquer cor do catálogo
        for ($i = 1; $i <= 100; ++$i) {
            $novoL = $paraEscuro ? $l - $i / 100 : $l + $i / 100;
            if ($novoL < 0 || $novoL > 1) {
                break;
            }
            $candidata = self::toHex(self::hslToRgb($h, $s, $novoL));
            if ($candidata !== null && self::ratio($candidata, $fundo) >= $minimo) {
                return $candidata;
            }
        }

        // Não deu com o mesmo matiz: preto ou branco, o que contrastar mais
        return $paraEscuro ? '#000000' : '#ffffff';
    }

    /** Preto ou branco, o que for mais legível sobre a cor informada. */
    public static function tintaSobre(?string $fundo): string
    {
        return self::ratio('#000000', $fundo) >= self::ratio('#ffffff', $fundo) ? '#1a1a1a' : '#ffffff';
    }

    /** @return array{0: int, 1: int, 2: int}|null */
    private static function toRgb(?string $hex): ?array
    {
        $hex = ltrim(trim((string) $hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return null;
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    /** @param array{0: int, 1: int, 2: int}|null $rgb */
    private static function toHex(?array $rgb): ?string
    {
        if ($rgb === null) {
            return null;
        }

        return sprintf('#%02x%02x%02x', max(0, min(255, $rgb[0])), max(0, min(255, $rgb[1])), max(0, min(255, $rgb[2])));
    }

    /**
     * @param array{0: int, 1: int, 2: int} $rgb
     *
     * @return array{0: float, 1: float, 2: float} matiz (0-1), saturação (0-1), luminosidade (0-1)
     */
    private static function rgbToHsl(array $rgb): array
    {
        [$r, $g, $b] = [$rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255];
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;

        if ($max === $min) {
            return [0.0, 0.0, $l];
        }

        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

        $h = match ($max) {
            $r => (($g - $b) / $d) + ($g < $b ? 6 : 0),
            $g => (($b - $r) / $d) + 2,
            default => (($r - $g) / $d) + 4,
        };

        return [$h / 6, $s, $l];
    }

    /** @return array{0: int, 1: int, 2: int} */
    private static function hslToRgb(float $h, float $s, float $l): array
    {
        if ($s === 0.0) {
            $v = (int) round($l * 255);

            return [$v, $v, $v];
        }

        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;

        $canal = static function (float $t) use ($p, $q): int {
            $t = fmod($t + 1, 1);
            if ($t < 1 / 6) {
                $v = $p + ($q - $p) * 6 * $t;
            } elseif ($t < 1 / 2) {
                $v = $q;
            } elseif ($t < 2 / 3) {
                $v = $p + ($q - $p) * (2 / 3 - $t) * 6;
            } else {
                $v = $p;
            }

            return (int) round($v * 255);
        };

        return [$canal($h + 1 / 3), $canal($h), $canal($h - 1 / 3)];
    }

    /**
     * A cor ajustada até ficar legível sobre TODOS os fundos informados.
     *
     * Existe porque o tema usa a cor da marca como texto em mais de um fundo: a página
     * branca, a faixa alternada e as "pílulas" com 30% da cor sobre o fundo. Ajustar só
     * para o branco deixava a pílula reprovando no contraste.
     *
     * @param list<string|null> $fundos
     */
    public static function legivelSobreVarios(?string $cor, array $fundos, float $minimo = 4.5): ?string
    {
        $resultado = self::toHex(self::toRgb($cor));
        if ($resultado === null) {
            return null;
        }

        foreach (array_filter($fundos) as $fundo) {
            if (self::ratio($resultado, $fundo) >= $minimo) {
                continue;
            }
            $ajustada = self::legivelSobre($resultado, $fundo, $minimo);
            if ($ajustada !== null) {
                $resultado = $ajustada;
            }
        }

        return $resultado;
    }

    /**
     * A cor que aparece quando `$frente` é desenhada com `$alfa` de opacidade sobre `$fundo`.
     * É o que o navegador mostra numa classe como `bg-cetec-secondary/30`.
     */
    public static function mistura(?string $frente, ?string $fundo, float $alfa): ?string
    {
        $f = self::toRgb($frente);
        $b = self::toRgb($fundo);
        if ($f === null || $b === null) {
            return null;
        }

        $alfa = max(0.0, min(1.0, $alfa));

        return self::toHex([
            (int) round($f[0] * $alfa + $b[0] * (1 - $alfa)),
            (int) round($f[1] * $alfa + $b[1] * (1 - $alfa)),
            (int) round($f[2] * $alfa + $b[2] * (1 - $alfa)),
        ]);
    }

    /**
     * Par fundo + tinta para um preenchimento sólido (botão, pílula, etiqueta).
     *
     * Existe porque há cor em que nem preto nem branco chegam ao mínimo: o rosa #cb4e9a
     * dá 4,22:1 com preto e 4,12:1 com branco, os dois abaixo dos 4,5:1 do WCAG AA.
     * Nesse caso o fundo também cede — clareia quando a tinta é escura, escurece quando é
     * clara —, até o par passar. Matiz e saturação não mudam: a cor do cliente continua
     * reconhecível.
     *
     * @return array{0: string, 1: string} [fundo, tinta]
     */
    public static function parFundoTinta(?string $cor, float $minimo = 4.6): array
    {
        $fundo = self::toHex(self::toRgb($cor));
        if ($fundo === null) {
            return ['#1a1a1a', '#ffffff'];
        }

        $tinta = self::tintaSobre($fundo);
        if (self::ratio($tinta, $fundo) >= $minimo) {
            return [$fundo, $tinta];
        }

        $tintaEscura = self::luminance($tinta) !== null && self::luminance($tinta) < 0.5;
        [$h, $s, $l] = self::rgbToHsl(self::toRgb($fundo));

        for ($i = 1; $i <= 100; ++$i) {
            // Tinta escura pede fundo mais claro, e vice-versa
            $novoL = $tintaEscura ? $l + $i / 100 : $l - $i / 100;
            if ($novoL < 0 || $novoL > 1) {
                break;
            }
            $candidato = self::toHex(self::hslToRgb($h, $s, $novoL));
            if ($candidato !== null && self::ratio($tinta, $candidato) >= $minimo) {
                return [$candidato, $tinta];
            }
        }

        return [$tintaEscura ? '#ffffff' : '#000000', $tinta];
    }
}
