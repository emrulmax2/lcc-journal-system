<?php

declare(strict_types=1);

namespace App\Enums;

enum ArticleFileType: string
{
    case Pdf = 'pdf';
    case Html = 'html';
    case Xml = 'xml';
    case Supplementary = 'supplementary';
    case Dataset = 'dataset';

    public function label(): string
    {
        return match ($this) {
            self::Pdf => 'PDF',
            self::Html => 'Full-text HTML',
            self::Xml => 'Full-text XML',
            self::Supplementary => 'Supplementary material',
            self::Dataset => 'Dataset',
        };
    }
}
