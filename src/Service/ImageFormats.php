<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Formatos de imagem do portal de notícias.
 *  - Envio (capa da notícia, foto do jornalista): só JPG e PNG, por decisão do Jonas (10/11/2026),
 *    conferindo a extensão do nome e o conteúdo do arquivo.
 *  - Miniatura: só gera pelo LiipImagine o que o GD do servidor abre (o servidor não lê AVIF);
 *    o resto é mostrado pelo arquivo original (filtro Twig news_image).
 */
final class ImageFormats
{
    public const UPLOAD_MESSAGE = 'A imagem precisa ser JPG ou PNG.';
    public const ACCEPT_ATTRIBUTE = '.jpg,.jpeg,.png,image/jpeg,image/png';

    private const UPLOAD_EXTENSIONS = ['jpg', 'jpeg', 'png'];
    private const UPLOAD_MIMES = ['image/jpeg', 'image/png'];

    /** Tipo MIME => [bit do imagetypes(), nome usado em IMAGE_FORMATS_DISABLED] */
    private const GD_TYPES = [
        'image/jpeg' => [IMG_JPG, 'jpeg'],
        'image/png'  => [IMG_PNG, 'png'],
        'image/webp' => [IMG_WEBP, 'webp'],
        'image/gif'  => [IMG_GIF, 'gif'],
        'image/avif' => [IMG_AVIF, 'avif'],
    ];

    /** @var list<string> */
    private readonly array $disabled;

    public function __construct(
        #[Autowire('%app.image_formats.disabled%')]
        ?string $disabled = null,
    ) {
        $this->disabled = array_values(array_filter(array_map('trim', explode(',', strtolower((string) $disabled)))));
    }

    /** Extensão .jpg/.jpeg/.png (sem diferenciar maiúsculas) e conteúdo JPEG ou PNG. */
    public function acceptsUpload(UploadedFile $file): bool
    {
        $ext = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
        return in_array($ext, self::UPLOAD_EXTENSIONS, true)
            && in_array((string) $file->getMimeType(), self::UPLOAD_MIMES, true);
    }

    /** O GD do servidor abre este arquivo gravado (para gerar a miniatura)? */
    public function canThumbnail(string $path): bool
    {
        if (!is_file($path)) {
            return false;
        }
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!isset(self::GD_TYPES[$mime])) {
            return false;
        }
        [$bit, $name] = self::GD_TYPES[$mime];
        return (imagetypes() & $bit) !== 0 && !in_array($name, $this->disabled, true);
    }
}
