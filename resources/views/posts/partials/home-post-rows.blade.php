@foreach ($posts as $post)
    <article
        class="studio-post-row is-clickable"
        data-row-link="{{ route('posts.show', $post) }}"
        tabindex="0"
        role="link"
        aria-label="Open {{ $post->title }}"
    >
        <div class="studio-post-copy">
            <div class="meta-row">
                <span>{{ $post->created_at->format('d M Y') }}</span>
                <span>{{ $post->reading_time }} min</span>
            </div>
            <h3>{{ $post->title }}</h3>
            <p>{{ $post->excerpt }}</p>
            <div class="studio-post-actions">
                <a class="capsule-link" href="{{ route('posts.show', $post) }}" data-row-link-ignore>Open article</a>
                <span class="post-topic">{{ $post->topic }}</span>
            </div>
        </div>

        @auth
            @if (auth()->id() === $post->user_id)
                <form action="{{ route('posts.destroy', $post) }}" method="POST" onsubmit="return confirm('Delete this saved post?');">
                    @csrf
                    @method('DELETE')
                    <button class="icon-button" type="submit" aria-label="Delete {{ $post->title }}" data-row-link-ignore>
                        <svg viewBox="0 0 24 24" role="presentation" aria-hidden="true">
                            <path d="M3.75 6.75h16.5" />
                            <path d="M9 6.75v-1.5A2.25 2.25 0 0 1 11.25 3h1.5A2.25 2.25 0 0 1 15 5.25v1.5" />
                            <path d="M18 6.75l-.76 11.23A2.25 2.25 0 0 1 14.99 20H9.01a2.25 2.25 0 0 1-2.25-2.02L6 6.75" />
                            <path d="M10 10.5v5.5" />
                            <path d="M14 10.5v5.5" />
                        </svg>
                    </button>
                </form>
            @endif
        @endauth
    </article>
@endforeach
