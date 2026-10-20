<?php

use App\Transformers\AtFreeSsrBaggageTransformer;

it('uses the SSR description for checked baggage even when a piece description is present', function () {
    $ssr = [
        'Type' => '2',
        'Description' => '0KG,0kg',
        'PieceDescription' => ' , ',
        'PTC' => 'ADT',
    ];
    $response = ['Trips' => [['Journey' => [['Segments' => [['SSR' => [$ssr]]]]]]]];
    $flight = ['leg' => ['flights' => [['segments' => [['ref_id' => 'segment-1']]]]]];

    $policies = (new AtFreeSsrBaggageTransformer())->transform($response, $flight)['baggage_policies'];

    expect($policies)->toHaveCount(1)
        ->and($policies[0]['description'])->toBe('0KG')
        ->and($policies[0]['detail_description'])->toBeNull()
        ->and($policies[0]['type'])->toBe('checkIn')
        ->and($policies[0]['segment_ref_id'])->toBe('segment-1');
});

it('keeps only the checked allowance before the comma', function () {
    $ssr = ['Type' => '2', 'Description' => '15Kg,07 Kg', 'PieceDescription' => ''];
    $response = ['Trips' => [['Journey' => [['Segments' => [['SSR' => [$ssr]]]]]]]];
    $flight = ['leg' => ['flights' => [['segments' => [['ref_id' => 'segment-1']]]]]];

    $policies = (new AtFreeSsrBaggageTransformer())->transform($response, $flight)['baggage_policies'];

    expect($policies[0]['description'])->toBe('15Kg');
});
