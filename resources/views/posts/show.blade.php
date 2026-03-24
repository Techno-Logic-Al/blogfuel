@extends('layouts.app')

@section('title', $post->title.' | '.config('app.name'))
@section('meta_title', $post->title)
@section('meta_description', $post->excerpt)
@section('meta_type', 'article')
@section('meta_url', route('posts.show', $post))
@section('meta_image', asset('images/BlogFuel round totally transparent icon.png'))

@section('content')
    @php
        $shareUrl = route('posts.show', $post);
        $facebookShareUrl = 'https://www.facebook.com/sharer/sharer.php?u='.urlencode($shareUrl);
        $xShareUrl = 'https://twitter.com/intent/tweet?text='.urlencode($post->title).'&url='.urlencode($shareUrl);
        $redditShareUrl = 'https://www.reddit.com/submit?url='.urlencode($shareUrl).'&title='.urlencode($post->title);
        $linkedInShareUrl = 'https://www.linkedin.com/sharing/share-offsite/?url='.urlencode($shareUrl);
        $emailShareUrl = 'mailto:?subject='.rawurlencode($post->title).'&body='.rawurlencode($post->title."\n\n".$post->excerpt."\n\n".$shareUrl);
        $canSharePost = auth()->check()
            && (
                auth()->id() === $post->user_id
                || auth()->user()?->hasUnlimitedGenerationAccess()
            );
    @endphp

    <article class="article-shell">
        <header class="glass-panel article-hero" data-reveal>
            <div class="article-hero-copy">
                <a class="text-link" href="{{ route('posts.index') }}">Back to home</a>
                <span class="eyebrow">{{ config("blog.tones.{$post->tone}.label", \Illuminate\Support\Str::headline($post->tone)) }}</span>
                <h1>{{ $post->title }}</h1>
                <p class="article-lead">{{ $post->excerpt }}</p>

                <div class="meta-row wrap">
                    <span>{{ $post->created_at->format('d M Y') }}</span>
                    <span>{{ $post->reading_time }} min read</span>
                    <span>{{ config("blog.audiences.{$post->audience}.label", \Illuminate\Support\Str::headline($post->audience)) }}</span>
                </div>

                @if (! empty($post->tags))
                    <div class="tag-row">
                        @foreach ($post->tags as $tag)
                            <span class="tag-chip">{{ $tag }}</span>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="article-side">
                <div class="quote-card">
                    <span>Prompt brief</span>
                    <p>{{ $post->topic }}</p>
                </div>

                <div class="quote-card">
                    <span>Closing takeaway</span>
                    <p>{{ $post->takeaway }}</p>
                </div>

                @auth
                    @if ($canSharePost)
                        <div
                            class="quote-card share-card"
                            data-share-card
                            data-share-title="{{ $post->title }}"
                            data-share-url="{{ $shareUrl }}"
                            data-share-excerpt="{{ $post->excerpt }}"
                        >
                            <span>Share this post</span>
                            <p class="share-helper">Open quick share links, email the article, or copy the article link.</p>

                            <div class="share-grid">
                                <a class="share-chip share-chip-icon" href="{{ $facebookShareUrl }}" target="_blank" rel="noopener noreferrer" aria-label="Facebook" title="Facebook">
                                    <svg viewBox="0 0 24 24" role="presentation" aria-hidden="true">
                                        <path d="M13.5 21v-7h2.6l.4-3h-3V9.1c0-.9.3-1.6 1.7-1.6H16.7V4.8c-.3 0-1.2-.1-2.3-.1-2.3 0-3.9 1.4-3.9 4v2.3H8v3h2.5v7h3z" fill="currentColor"/>
                                    </svg>
                                    <span class="sr-only">Facebook</span>
                                </a>
                                <a class="share-chip share-chip-icon" href="{{ $xShareUrl }}" target="_blank" rel="noopener noreferrer" aria-label="X" title="X">
                                    <svg viewBox="0 0 24 24" role="presentation" aria-hidden="true">
                                        <path d="M4 4l6.5 8.3L4.4 20H7l4.7-5.7 4.4 5.7H20l-6.8-8.8L19.2 4h-2.5l-4.3 5.2L8.3 4H4z" fill="currentColor"/>
                                    </svg>
                                    <span class="sr-only">X</span>
                                </a>
                                <a class="share-chip share-chip-icon" href="{{ $redditShareUrl }}" target="_blank" rel="noopener noreferrer" aria-label="Reddit" title="Reddit">
                                    <svg viewBox="0 0 24 24" role="presentation" aria-hidden="true">
                                        <circle cx="12" cy="13.2" r="5.9" fill="none" stroke="currentColor" stroke-width="1.8"/>
                                        <circle cx="9.6" cy="12.4" r="1" fill="currentColor"/>
                                        <circle cx="14.4" cy="12.4" r="1" fill="currentColor"/>
                                        <path d="M9.2 15.2c.8.7 1.8 1 2.8 1s2-.3 2.8-1" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.8"/>
                                        <path d="M13 7.1l.8-3 2.5.6" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.8"/>
                                        <circle cx="17.2" cy="5" r="1.2" fill="none" stroke="currentColor" stroke-width="1.6"/>
                                        <circle cx="6.2" cy="11.2" r="1.3" fill="none" stroke="currentColor" stroke-width="1.6"/>
                                        <circle cx="17.8" cy="11.2" r="1.3" fill="none" stroke="currentColor" stroke-width="1.6"/>
                                    </svg>
                                    <span class="sr-only">Reddit</span>
                                </a>
                                <a class="share-chip share-chip-icon" href="{{ $linkedInShareUrl }}" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn" title="LinkedIn">
                                    <svg viewBox="0 0 24 24" role="presentation" aria-hidden="true">
                                        <path d="M6.2 8.3a1.8 1.8 0 110-3.6 1.8 1.8 0 010 3.6zm-1.5 2h3V20h-3v-9.7zm4.9 0h2.9v1.3h.1c.4-.8 1.4-1.7 3-1.7 3.2 0 3.8 2.1 3.8 4.8V20h-3v-4.7c0-1.1 0-2.6-1.6-2.6s-1.8 1.2-1.8 2.5V20h-3v-9.7z" fill="currentColor"/>
                                    </svg>
                                    <span class="sr-only">LinkedIn</span>
                                </a>
                                <a class="share-chip share-chip-icon" href="{{ $emailShareUrl }}" aria-label="Email article" title="Email article">
                                    <svg viewBox="0 0 24 24" role="presentation" aria-hidden="true">
                                        <path d="M4 6.5h16v11H4z" fill="none" stroke="currentColor" stroke-width="1.8"/>
                                        <path d="M4.8 7.2L12 13l7.2-5.8" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"/>
                                    </svg>
                                    <span class="sr-only">Email article</span>
                                </a>
                                <button class="share-chip share-chip-icon" type="button" data-share-copy="link" aria-label="Copy link" title="Copy link">
                                    <span class="share-chip-image share-chip-image-link" aria-hidden="true"></span>
                                    <span class="sr-only">Copy link</span>
                                </button>
                            </div>

                            <small class="share-status" data-share-status aria-live="polite">
                                Email opens your mail app. Copy link saves the article URL to your clipboard.
                            </small>
                        </div>
                    @endif
                @endauth
            </div>
        </header>

        <section class="article-body">
            <div class="glass-panel article-intro" data-reveal>
                <p>{{ $post->intro }}</p>
            </div>

            @foreach ($post->sections as $index => $section)
                @php($paragraphs = preg_split('/\n{2,}/', $section['body']) ?: [$section['body']])

                <section class="glass-panel article-section" data-reveal>
                    <div class="section-index">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</div>

                    <div class="article-section-copy">
                        <h2>{{ $section['heading'] }}</h2>

                        @foreach ($paragraphs as $paragraph)
                            @if (trim($paragraph) !== '')
                                <p>{{ trim($paragraph) }}</p>
                            @endif
                        @endforeach
                    </div>
                </section>
            @endforeach
        </section>

        <section class="section-block" data-reveal>
            <div class="section-heading">
                <div>
                    <span class="eyebrow">More BlogFuel-generated articles</span>
                    <h2>Other blog posts to explore:</h2>
                </div>
                <a class="text-link" href="{{ route('posts.index') }}#prompt-form">Generate another article</a>
            </div>

            @if ($relatedPosts->isNotEmpty())
                <div class="post-grid" data-related-post-grid>
                    @include('posts.partials.related-post-cards', [
                        'relatedPosts' => $relatedPosts,
                    ])
                </div>

                @if ($relatedPostsHasMore)
                    <div class="load-more-panel">
                        <button
                            class="button load-more-button"
                            type="button"
                            data-related-load-more
                            data-related-url="{{ route('posts.related', $post) }}"
                            data-next-page="{{ $relatedPostsNextPage }}"
                        >
                            Load more
                        </button>
                        <small class="load-more-status" data-related-status aria-live="polite"></small>
                    </div>
                @endif
            @else
                <div class="glass-panel solo-state">
                    <p>This is the only saved article so far. Generate another one from the homepage.</p>
                    <a class="button button-primary" href="{{ route('posts.index') }}#prompt-form">Generate another article</a>
                </div>
            @endif
        </section>
    </article>
@endsection
