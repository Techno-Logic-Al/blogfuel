<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAiPostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'topic' => trim((string) $this->input('topic')),
            'keywords' => trim((string) $this->input('keywords')),
            'model' => trim((string) ($this->input('model') ?: $this->defaultModel())),
            'enhance_seo' => $this->boolean('enhance_seo'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'topic' => ['required', 'string', 'min:6', 'max:140'],
            'keywords' => ['nullable', 'string', 'max:180'],
            'tone' => ['required', Rule::in(array_keys(config('blog.tones', [])))],
            'audience' => ['required', Rule::in(array_keys(config('blog.audiences', [])))],
            'depth' => ['required', Rule::in(array_keys(config('blog.depths', [])))],
            'model' => ['required', Rule::in($this->allowedModels())],
            'enhance_seo' => ['required', 'boolean'],
        ];
    }

    /**
     * Return the default model when the form omits one.
     */
    protected function defaultModel(): string
    {
        $configuredModel = (string) config('services.openai.model');
        $availableModels = $this->allowedModels();

        if (in_array($configuredModel, $availableModels, true)) {
            return $configuredModel;
        }

        return in_array('gpt-5-mini', $availableModels, true)
            ? 'gpt-5-mini'
            : (string) ($availableModels[0] ?? 'gpt-5-mini');
    }

    /**
     * Return the model keys the current route/user should be allowed to submit.
     *
     * @return array<int, string>
     */
    protected function allowedModels(): array
    {
        $availableModels = config('blog.models', []);

        if ($this->routeIs('posts.preview')) {
            return array_key_exists('gpt-5-mini', $availableModels)
                ? ['gpt-5-mini']
                : [(string) (array_key_first($availableModels) ?? 'gpt-5-mini')];
        }

        $user = $this->user();

        if ($user === null) {
            return array_keys($availableModels);
        }

        return array_keys($user->availableModels());
    }
}
