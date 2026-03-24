@php($accessNotice = $accessNotice ?? null)
@php($showGuestAuthActions = $showGuestAuthActions ?? false)

<article class="glass-panel preview-card" id="guest-preview" data-reveal>
    <div class="preview-header">
        <div class="preview-copy">
            <span class="eyebrow">{{ $eyebrow ?? 'Guest draft preview' }}</span>
            <h2>{{ $guestPreview['title'] }}</h2>
            <p>{{ $guestPreview['excerpt'] }}</p>
        </div>

        <div class="meta-row preview-meta">
            <span>{{ $guestPreview['reading_time'] }} min</span>
            <span>{{ config("blog.tones.{$guestPreview['tone']}.label", \Illuminate\Support\Str::headline($guestPreview['tone'])) }}</span>
            <span>{{ config("blog.audiences.{$guestPreview['audience']}.label", \Illuminate\Support\Str::headline($guestPreview['audience'])) }}</span>
        </div>
    </div>

    <div class="preview-topic">
        <span>Prompt brief</span>
        <strong>{{ $guestPreview['topic'] }}</strong>
    </div>

    <div class="preview-intro-card">
        <p class="preview-intro">{{ $guestPreview['intro'] }}</p>
    </div>

    @if (! empty($guestPreview['sections']))
        <div class="preview-sections">
            @foreach ($guestPreview['sections'] as $index => $section)
                @php($paragraphs = preg_split('/\n{2,}/', $section['body']) ?: [$section['body']])

                <section class="preview-section">
                    <div class="section-index">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</div>

                    <div class="article-section-copy">
                        <h3>{{ $section['heading'] }}</h3>

                        @foreach ($paragraphs as $paragraph)
                            @if (trim($paragraph) !== '')
                                <p>{{ trim($paragraph) }}</p>
                            @endif
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    @endif

    <div class="preview-footer">
        @if (! empty($guestPreview['tags']))
            <div class="tag-row">
                @foreach ($guestPreview['tags'] as $tag)
                    <span class="tag-chip">{{ $tag }}</span>
                @endforeach
            </div>
        @endif

        <div class="preview-takeaway">
            <span>Closing takeaway</span>
            <p>{{ $guestPreview['takeaway'] }}</p>
        </div>

        @if ($accessNotice !== null)
            <div class="preview-lock-note @if ($showGuestAuthActions) has-actions @endif">
                <div class="preview-lock-copy">
                    <span class="eyebrow">Before you continue</span>
                    <p>{{ $accessNotice }}</p>
                </div>

                @if ($showGuestAuthActions)
                    <div class="preview-lock-actions">
                        <a class="button button-ember" href="{{ route('login') }}">Sign in</a>
                        <a class="button button-gradient-fire button-gradient-fire-light" href="{{ route('register') }}">Register</a>
                    </div>
                @endif
            </div>
        @endif
    </div>
</article>
