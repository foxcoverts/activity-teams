{!! '<'.'?xml version="1.0" encoding="utf-8"?>' !!}
<feed xmlns="http://www.w3.org/2005/Atom">
    <title>{{ $title }}</title>
    <id>{{ $selfUrl }}</id>
    <updated>{{ $updated->toAtomString() }}</updated>
    <link rel="self" href="{{ $selfUrl }}" />
    @foreach ($posts as $post)
        <entry>
            <title>{{ $post->title }}</title>
            <id>{{ $post->memberUrl() }}</id>
            <link href="{{ $post->memberUrl() }}" />
            <updated>{{ $post->published_at?->toAtomString() }}</updated>
            @if ($post->author)
                <author>
                    <name>{{ $post->author->name }}</name>
                </author>
            @endif
            <summary>{{ $post->excerpt() }}</summary>
        </entry>
    @endforeach
</feed>
