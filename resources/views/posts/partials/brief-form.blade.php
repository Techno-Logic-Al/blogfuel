@inject('recaptcha', 'App\Services\RecaptchaEnterpriseVerifier')
@php($inlineSubmitWithSeo = $inlineSubmitWithSeo ?? false)
@php($submitIcon = $submitIcon ?? null)
@php($lockedModel = $lockedModel ?? null)
@php($lockedModelConfig = $lockedModel !== null ? ($models[$lockedModel] ?? null) : null)
@php($modelHelperCopy = $modelHelperCopy ?? null)

<form
    class="studio-form"
    method="POST"
    action="{{ $action }}"
    data-generate-form
    @if ($recaptcha->enabled())
        data-recaptcha-form
        data-recaptcha-action="GENERATE_POST"
    @endif
>
    @csrf

    <label class="field field-span-2">
        <span>Blog topic</span>
        <input
            name="topic"
            type="text"
            value="{{ old('topic') }}"
            maxlength="140"
            placeholder="Use a clear topic and a few relevant keywords for stronger results."
            required
        >
        @error('topic')
            <small class="field-error">{{ $message }}</small>
        @enderror
    </label>

    <label class="field field-span-2">
        <span>Keywords</span>
        <input
            name="keywords"
            type="text"
            value="{{ old('keywords') }}"
            maxlength="180"
            placeholder="Laravel, OpenAI, automation, blog workflow"
        >
        @error('keywords')
            <small class="field-error">{{ $message }}</small>
        @enderror
    </label>

    <label class="field">
        <span>Tone</span>
        <select name="tone" required>
            @foreach ($tones as $value => $tone)
                <option value="{{ $value }}" @selected(old('tone', 'insightful') === $value)>{{ $tone['label'] }}</option>
            @endforeach
        </select>
        @error('tone')
            <small class="field-error">{{ $message }}</small>
        @enderror
    </label>

    <label class="field">
        <span>Audience</span>
        <select name="audience" required>
            @foreach ($audiences as $value => $audience)
                <option value="{{ $value }}" @selected(old('audience', 'general') === $value)>{{ $audience['label'] }}</option>
            @endforeach
        </select>
        @error('audience')
            <small class="field-error">{{ $message }}</small>
        @enderror
    </label>

    <label class="field">
        <span>Depth</span>
        <select name="depth" required>
            @foreach ($depths as $value => $depth)
                <option value="{{ $value }}" @selected(old('depth', 'balanced') === $value)>{{ $depth['label'] }}</option>
            @endforeach
        </select>
        @error('depth')
            <small class="field-error">{{ $message }}</small>
        @enderror
    </label>

    <label class="field">
        <span>AI model</span>
        @if ($lockedModelConfig !== null)
            <input type="hidden" name="model" value="{{ $lockedModel }}">
            <div class="field-static">{{ $lockedModelConfig['label'] }}</div>
            <small class="field-note">Guest trials use GPT-5 mini for the most reliable response time.</small>
        @else
            <select name="model" required>
                @foreach ($models as $value => $model)
                    <option value="{{ $value }}" @selected(old('model', $defaultModel) === $value)>
                        {{ $model['label'] }}{{ ! empty($model['description']) ? ' - '.$model['description'] : '' }}
                    </option>
                @endforeach
            </select>
            @if (! empty($modelHelperCopy))
                <small class="field-note">{{ $modelHelperCopy }}</small>
            @endif
        @endif
        @error('model')
            <small class="field-error">{{ $message }}</small>
        @enderror
    </label>

    <div class="field-span-2 toggle-wrap @if ($inlineSubmitWithSeo) toggle-wrap-inline @endif">
        <div class="toggle-main">
            <input type="hidden" name="enhance_seo" value="0">
            <input
                class="toggle-input"
                id="enhance_seo"
                name="enhance_seo"
                type="checkbox"
                value="1"
                @checked((bool) old('enhance_seo', false))
            >
            <label class="toggle-field" for="enhance_seo">
                <span class="toggle-switch" aria-hidden="true"></span>
                <span class="toggle-copy">
                    <span class="toggle-title">Enhance SEO</span>
                    <small>Optimise the title, headings, intro, excerpt, and keyword placement for search engines.</small>
                </span>
            </label>
        </div>

        @if ($inlineSubmitWithSeo)
            <div class="toggle-submit">
                <button class="button {{ $submitButtonClass ?? 'button-primary' }} @if ($submitIcon !== null) button-with-mark @endif" type="submit" data-submit-button>
                    @if ($submitIcon !== null)
                        <span class="button-content-with-icon">
                            <img class="button-mark" src="{{ asset($submitIcon) }}" alt="" aria-hidden="true">
                            <span data-submit-label>{{ $submitLabel }}</span>
                        </span>
                    @else
                        <span data-submit-label>{{ $submitLabel }}</span>
                    @endif
                </button>
            </div>
        @endif

        @error('enhance_seo')
            <small class="field-error">{{ $message }}</small>
        @enderror

        @if ($inlineSubmitWithSeo && ! empty($submitCopy))
            <p class="form-note toggle-note">{{ $submitCopy }}</p>
        @endif
    </div>

    @if (! $inlineSubmitWithSeo)
        <div class="form-actions field-span-2">
            <p>{{ $submitCopy }}</p>
            <button class="button {{ $submitButtonClass ?? 'button-primary' }} @if ($submitIcon !== null) button-with-mark @endif" type="submit" data-submit-button>
                @if ($submitIcon !== null)
                    <span class="button-content-with-icon">
                        <img class="button-mark" src="{{ asset($submitIcon) }}" alt="" aria-hidden="true">
                        <span data-submit-label>{{ $submitLabel }}</span>
                    </span>
                @else
                    <span data-submit-label>{{ $submitLabel }}</span>
                @endif
            </button>
        </div>
    @endif

    @if ($recaptcha->enabled())
        @include('partials.recaptcha-fields')
    @endif
</form>
