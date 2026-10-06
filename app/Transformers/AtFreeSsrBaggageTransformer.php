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

                        $description = trim((string) (
                            $ssr['Description']
                            ?? $ssr['PieceDescription']
                            ?? $ssr['Code']
                            ?? 'Included checked baggage'
                        ));
                        $weights = $this->weights($ssr);
                        [$pieces, $weight] = $this->allowance($ssr);

                        $policies[] = [
                            'type' => 'checkIn',
                            'pieces' => $pieces,
                            'weight' => $weights[0] ?? $weight,
                            // AT returns combined free allowance as e.g.
                            // "15 Kg, 07 Kg": checked first, cabin second.
                            'description' => isset($weights[1])
                                ? $this->weightDescription($weights[0])
                                : $description,
                            'traveler_type' => $this->travelerType($ssr['PTC'] ?? 'ADT'),
                            'segment_ref_id' => $segmentRefId,
                            'source' => 'free_ssr',
                        ];

                        if (isset($weights[1])) {
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

    private function allowance(array $ssr): array
    {
        $text = implode(' ', array_filter([
            $ssr['Description'] ?? null,
            $ssr['PieceDescription'] ?? null,
            $ssr['Code'] ?? null,
        ]));

        if (preg_match('/(\d+(?:\.\d+)?)\s*(?:kg|kgs|kilogram)/i', $text, $match)) {
            return [0, (float) $match[1]];
        }

        if (preg_match('/(\d+)\s*(?:pc|pcs|piece)/i', $text, $match)) {
            return [(int) $match[1], null];
        }

        return [0, null];
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
