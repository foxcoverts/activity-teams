<?php

namespace App\Filament\Teams\Widgets;

use App\Filament\Member\Widgets\LatestNewsWidget as MemberLatestNewsWidget;
use App\Filament\Teams\Resources\NewsPosts\NewsPostResource;
use App\Filament\Teams\Widgets\Concerns\ScopedToTeam;

class LatestNewsWidget extends MemberLatestNewsWidget
{
    use ScopedToTeam;

    protected function sectionData(): array
    {
        return [
            ...parent::sectionData(),
            'newsUrl' => NewsPostResource::getUrl('index'),
        ];
    }
}
