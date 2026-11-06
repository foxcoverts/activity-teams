{!! '<' . '?xml version="1.0" encoding="utf-8"?>' !!}
<rss version="2.0">
    <channel>
        <title>{{ $title }}</title>
        <link>{{ $selfUrl }}</link>
        <description>{{ $title }}</description>
        <lastBuildDate>{{ $updated->toRssString() }}</lastBuildDate>
        @foreach ($posts as $post)
            <item>
                <title>{{ $post->title }}</title>
                <link>{{ $post->memberUrl() }}</link>
                <guid isPermaLink="true">{{ $post->memberUrl() }}</guid>
                @if ($post->published_at)
                    <pubDate>{{ $post->published_at->toRssString() }}</pubDate>
                @endif
                <description>{{ $post->excerpt() }}</description>
            </item>
        @endforeach
    </channel>
</rss>
