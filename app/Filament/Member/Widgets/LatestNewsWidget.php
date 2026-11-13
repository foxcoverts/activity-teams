<?php

namespace App\Filament\Member\Widgets;

use App\Models\NewsPost;

class LatestNewsWidget extends DashboardSection
{
    protected static ?int $sort = 9;

    protected string $view = 'filament.member.widgets.latest-news';

    protected function sectionData(): array
    {
        $post = $this->dashboard()->latestNews();

        return [
            'post' => $post,
            'isNew' => $post instanceof NewsPost && $this->dashboard()->newsIsUnread($post),
        ];
    }
}
