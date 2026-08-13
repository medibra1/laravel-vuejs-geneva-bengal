<?php

namespace App\Enums;

enum GalleryType: string
{
    case Gallery = 'gallery';
    case HeroSlide = 'hero_slide';
    case SocialTile = 'social_tile';
}
