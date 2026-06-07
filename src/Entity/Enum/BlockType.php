<?php

namespace App\Entity\Enum;

enum BlockType: string
{
    case TextImage     = 'text_image';
    case Gallery       = 'gallery';
    case Newsletter    = 'newsletter';
    case Stats         = 'stats';
    case NewsCall      = 'news_call';
    case Map           = 'map';
    case SubCategories = 'sub_categories';
    case PageList      = 'page_list';
    case Blurbs4       = 'blurbs4';
    case Testimonials  = 'testimonials';
    case PartnerLogos  = 'partner_logos';
    case Banner        = 'banner';
    case Team          = 'team';
    case Contact       = 'contact';

    public function label(): string
    {
        return match($this) {
            self::TextImage     => 'Imagem + Texto',
            self::Gallery       => 'Galeria de Imagens',
            self::Newsletter    => 'Newsletter / Captura de E-mail',
            self::Stats         => 'Estatísticas',
            self::NewsCall      => 'Chamada para Notícia',
            self::Map           => 'Mapa',
            self::SubCategories => 'Listar Subcategorias',
            self::PageList      => 'Listar Páginas',
            self::Blurbs4       => 'Texto com 4 Blocos',
            self::Testimonials  => 'Depoimentos',
            self::PartnerLogos  => 'Logos de Parceiros',
            self::Banner        => 'Banner',
            self::Team          => 'Membros da Equipe',
            self::Contact       => 'Formulário de Contato',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::TextImage     => 'layout-split',
            self::Gallery       => 'images',
            self::Newsletter    => 'envelope-open',
            self::Stats         => 'bar-chart-line',
            self::NewsCall      => 'newspaper',
            self::Map           => 'geo-alt',
            self::SubCategories => 'diagram-3',
            self::PageList      => 'card-list',
            self::Blurbs4       => 'grid',
            self::Testimonials  => 'chat-quote',
            self::PartnerLogos  => 'hand-thumbs-up',
            self::Banner        => 'image',
            self::Team          => 'people',
            self::Contact       => 'envelope-at',
        };
    }
}
