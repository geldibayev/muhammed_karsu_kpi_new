<?php

namespace App\Services;

use App\Models\Datum;
use App\Support\ScopusCriterionRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ScopusPublicationTierResolver
{
    public function resolve(Datum $datum): ?string
    {
        $candidates = collect();
        $this->addStructuredCandidate($candidates, $datum->publication_tier);

        foreach ([
            'publication_tier',
            'quartile',
            'article.publication_tier',
            'article.quartile',
            'data.publication_tier',
            'data.quartile',
        ] as $key) {
            $this->addStructuredCandidate($candidates, data_get($datum->material, $key));
        }

        $texts = collect([$datum->reason])
            ->merge($datum->histories
                ->whereIn('message_type', ['ai_evaluation', 'manual_review_approved'])
                ->pluck('message'));

        foreach ($texts as $text) {
            if (! is_string($text) || trim($text) === '') {
                continue;
            }

            preg_match_all('/(?<![\pL\pN])Q([1-4])(?![\pL\pN])/iu', $text, $matches);

            foreach ($matches[1] ?? [] as $quartile) {
                $candidates->push('q'.$quartile);
            }

            if ($this->isIndexedConferenceText($text)) {
                $candidates->push('conference');
            }
        }

        $uniqueCandidates = $candidates->unique()->values();

        return $uniqueCandidates->count() === 1 ? $uniqueCandidates->first() : null;
    }

    private function addStructuredCandidate(Collection $candidates, mixed $value): void
    {
        if (! is_string($value)) {
            return;
        }

        $normalized = Str::lower(trim($value));

        if (array_key_exists($normalized, ScopusCriterionRule::PUBLICATION_TIER_POINTS)) {
            $candidates->push($normalized);
        }
    }

    private function isIndexedConferenceText(string $text): bool
    {
        $mentionsConference = preg_match('/conference|proceedings|konferens|конференц/iu', $text) === 1;
        $mentionsIndex = preg_match('/scopus|web\s+of\s+science|(?<![\pL\pN])wos(?![\pL\pN])/iu', $text) === 1;

        return $mentionsConference && $mentionsIndex;
    }
}
