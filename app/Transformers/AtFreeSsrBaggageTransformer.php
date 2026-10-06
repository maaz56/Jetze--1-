<?php

namespace App\Transformers;

/**
 * Turns AT's free-SSR response into the same baggage-policy shape rendered by
 * the listing side sheet. AT does not return our generated segment_ref_id, so
 * the selected flight's trusted trip/journey/segment order is used to attach
 * each SSR item to its segment.
 */
class AtFreeSsrBaggageTransformer
{
    public function transform(array $response, array $flight): array
    {
        $policies = [];

        foreach ($response['Trips'] ?? [] as $tripIndex => $trip) {
            $flightLeg = $flight['leg']['flights'][$tripIndex] ?? [];
            $segments = $flightLeg['segments'] ?? [];

            foreach ($trip['Journey'] ?? [] as $journey) {
                foreach ($journey['Segments'] ?? [] as $segmentIndex => $segment) {
                    $segmentRefId = $segments[$segmentIndex]['ref_id'] ?? null;
                    if (!$segmentRefId) {
                        continue;
                    }

                    foreach ($segment['SSR'] ?? [] as $ssr) {
                        if ((string) ($ssr['Type'] ?? '') !== '2') {
                            continue;
                        }

                        $description = trim((string) ($ssr['Description'] ?? ''));
                        $pieceDescription = trim((string) ($ssr['PieceDescription'] ?? ''));
                        $weights = $this->weights($ssr);
                        $checkedText = $pieceDescription ?: ($description ?: (string) ($ssr['Code'] ?? 'Included checked baggage'));
                        [$pieces, $weight] = $this->allowanceFromText($checkedText);

                        if (!$pieceDescription && isset($weights[0])) {
                            $weight = $weights[0];
                        }

                        $policies[] = [
                            'type' => 'checkIn',
                            'pieces' => $pieces,
                            'weight' => $weight,
                            'description' => $pieceDescription ?: (
                                isset($weights[1]) ? $this->weightDescription($weights[0]) : $checkedText
                            ),
                            'traveler_type' => $this->travelerType($ssr['PTC'] ?? 'ADT'),
                            'segment_ref_id' => $segmentRefId,
                            'source' => 'free_ssr',
                        ];

                        if ($pieceDescription && $description) {
                            [$carryPieces, $carryWeight] = $this->allowanceFromText($description);
                            $policies[] = [
                                'type' => 'carry',
                                'pieces' => $carryPieces,
                                'weight' => $carryWeight,
                                'description' => $description,
                                'traveler_type' => $this->travelerType($ssr['PTC'] ?? 'ADT'),
                                'segment_ref_id' => $segmentRefId,
                                'source' => 'free_ssr',
                            ];
                        } elseif (isset($weights[1])) {
                            $policies[] = [
                                'type' => 'carry',
                                'pieces' => 0,
                                'weight' => $weights[1],
                                'description' => $this->weightDescription($weights[1]),
                                'traveler_type' => $this->travelerType($ssr['PTC'] ?? 'ADT'),
                                'segment_ref_id' => $segmentRefId,
                                'source' => 'free_ssr',
                            ];
                        }
                    }
                }
            }
        }

        return ['baggage_policies' => $policies];
    }

    private function allowanceFromText(string $text): array
    {
        $pieces = 0;
        $weight = null;

        if (preg_match('/(\d+)\s*(?:pc|pcs|piece)/i', $text, $match)) {
            $pieces = (int) $match[1];
        }

        if (preg_match('/(\d+(?:\.\d+)?)\s*(?:kg|kgs|kilogram)/i', $text, $match)) {
            $weight = (float) $match[1];
        }

        return [$pieces, $weight];
    }

    /** @return array<int, float> */
    private function weights(array $ssr): array
    {
        $text = implode(' ', array_filter([
            $ssr['Description'] ?? null,
            $ssr['PieceDescription'] ?? null,
        ]));

        preg_match_all('/(\d+(?:\.\d+)?)\s*(?:kg|kgs|kilogram)/i', $text, $matches);

        return array_map('floatval', $matches[1] ?? []);
    }

    private function weightDescription(float $weight): string
    {
        return rtrim(rtrim(number_format($weight, 2, '.', ''), '0'), '.') . ' Kg included';
    }

    private function travelerType(mixed $ptc): string
    {
        return match (strtoupper(trim((string) $ptc))) {
            'ADULT' => 'ADT',
            'CHILD' => 'CHD',
            'INFANT' => 'INF',
            default => strtoupper(trim((string) $ptc)) ?: 'ADT',
        };
    }
}
