<?php

namespace App\Services;

use App\Models\Architecture;
use App\Models\Framework;
use App\Models\Language;

/**
 * Deduz language_id / framework_id / architecture_id quando o nome do
 * catálogo aparece na intenção. A escolha do formulário sempre vence.
 */
class CatalogHintResolver
{
    /**
     * @param  array{language_id?: int|null, framework_id?: int|null, architecture_id?: int|null}  $form
     * @param  array<string, mixed>  $intent
     * @return array{language_id: int|null, framework_id: int|null, architecture_id: int|null}
     */
    public function resolve(array $form, array $intent, string $input): array
    {
        $haystack = $this->haystack($intent, $input);

        $languageId = $this->positiveId($form['language_id'] ?? null)
            ?? $this->matchLanguage($haystack);

        $frameworkId = $this->positiveId($form['framework_id'] ?? null)
            ?? $this->matchFramework($haystack, $languageId);

        $architectureId = $this->positiveId($form['architecture_id'] ?? null)
            ?? $this->matchArchitecture($haystack);

        return [
            'language_id' => $languageId,
            'framework_id' => $frameworkId,
            'architecture_id' => $architectureId,
        ];
    }

    /**
     * @param  array<string, mixed>  $intent
     */
    private function haystack(array $intent, string $input): string
    {
        $parts = [$input];

        if (is_string($intent['architecture'] ?? null)) {
            $parts[] = $intent['architecture'];
        }

        foreach ($intent['technologies'] ?? [] as $tech) {
            if (is_string($tech) && $tech !== '') {
                $parts[] = $tech;
            }
        }

        return mb_strtolower(implode(' ', $parts));
    }

    private function matchLanguage(string $haystack): ?int
    {
        return $this->firstMatchingId(Language::query()->get(['id', 'nome', 'slug']), $haystack);
    }

    private function matchFramework(string $haystack, ?int $languageId): ?int
    {
        $query = Framework::query()->select(['id', 'nome', 'slug', 'language_id']);

        if ($languageId !== null) {
            $query->where('language_id', $languageId);
        }

        return $this->firstMatchingId($query->get(), $haystack);
    }

    private function matchArchitecture(string $haystack): ?int
    {
        return $this->firstMatchingId(Architecture::query()->get(['id', 'nome']), $haystack);
    }

    /**
     * @param  iterable<int, object>  $items
     */
    private function firstMatchingId(iterable $items, string $haystack): ?int
    {
        $best = null;
        $bestLen = 0;

        foreach ($items as $item) {
            foreach (['nome', 'slug'] as $field) {
                $label = mb_strtolower(trim((string) ($item->{$field} ?? '')));

                if ($label === '' || mb_strlen($label) < 2) {
                    continue;
                }

                if (! $this->containsLabel($haystack, $label)) {
                    continue;
                }

                if (mb_strlen($label) > $bestLen) {
                    $best = (int) $item->id;
                    $bestLen = mb_strlen($label);
                }
            }
        }

        return $best;
    }

    private function containsLabel(string $haystack, string $label): bool
    {
        if (str_contains($haystack, $label)) {
            return true;
        }

        $pattern = '/(?<![\p{L}\p{N}])'.preg_quote($label, '/').'(?![\p{L}\p{N}])/u';

        return preg_match($pattern, $haystack) === 1;
    }

    private function positiveId(mixed $id): ?int
    {
        if (! is_numeric($id) || (int) $id <= 0) {
            return null;
        }

        return (int) $id;
    }
}
