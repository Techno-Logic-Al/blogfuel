@foreach ($relatedPosts as $relatedPost)
    <article class="glass-panel post-card">
        <div class="meta-row">
            <span>{{ $relatedPost->created_at->format('d M Y') }}</span>
            <span>{{ $relatedPost->reading_time }} min read</span>
        </div>
        <h3>{{ $relatedPost->title }}</h3>
        <p>{{ $relatedPost->excerpt }}</p>
        <div class="post-card-footer">
            <span class="post-topic">{{ $relatedPost->topic }}</span>
            <a class="text-link" href="{{ route('posts.show', $relatedPost) }}">Read more</a>
        </div>
    </article>
@endforeach
