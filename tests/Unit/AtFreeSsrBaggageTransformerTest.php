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

it('splits a combined piece description into checked and cabin baggage', function () {
    // Case from search: checked and cabin allowance arrive in one string.
    $ssr = [
        'Type' => '2',
        'Description' => '2 PC',
        'PieceDescription' => '1 Piece equal 23KG,1 Piece equal 7 Kg',
        'PTC' => 'ADT',
    ];
    $response = ['Trips' => [['Journey' => [['Segments' => [['SSR' => [$ssr]]]]]]]];
    $flight = ['leg' => ['flights' => [['segments' => [['ref_id' => 'segment-1']]]]]];

    $policies = (new AtFreeSsrBaggageTransformer())->transform($response, $flight)['baggage_policies'];

    expect($policies)->toHaveCount(2);

    $checked = collect($policies)->firstWhere('type', 'checkIn');
    $cabin = collect($policies)->firstWhere('type', 'carry');

    expect($checked['description'])->toBe('1 Piece equal 23KG')
        ->and($checked['detail_description'])->toBeNull()
        ->and($checked['pieces'])->toBe(1)
        ->and($checked['weight'])->toBe(23.0)
        ->and($checked['segment_ref_id'])->toBe('segment-1')
        ->and($cabin['description'])->toBe('1 Piece equal 7 Kg')
        ->and($cabin['pieces'])->toBe(1)
        ->and($cabin['weight'])->toBe(7.0)
        ->and($cabin['segment_ref_id'])->toBe('segment-1');
});

it('uses the last piece description part as cabin when there are more than two parts', function () {
    $ssr = ['Type' => '2', 'Description' => '', 'PieceDescription' => '1 Piece equal 23KG, 1 Piece equal 23KG, 1 Piece equal 7 Kg'];
    $response = ['Trips' => [['Journey' => [['Segments' => [['SSR' => [$ssr]]]]]]]];
    $flight = ['leg' => ['flights' => [['segments' => [['ref_id' => 'segment-1']]]]]];

    $policies = (new AtFreeSsrBaggageTransformer())->transform($response, $flight)['baggage_policies'];

    expect(collect($policies)->firstWhere('type', 'checkIn')['description'])->toBe('1 Piece equal 23KG')
        ->and(collect($policies)->firstWhere('type', 'carry')['description'])->toBe('1 Piece equal 7 Kg');
});

it('keeps the description based checked and cabin split when the description has a comma', function () {
    $ssr = ['Type' => '2', 'Description' => '25 Kg, 7 Kg', 'PieceDescription' => ''];
    $response = ['Trips' => [['Journey' => [['Segments' => [['SSR' => [$ssr]]]]]]]];
    $flight = ['leg' => ['flights' => [['segments' => [['ref_id' => 'segment-1']]]]]];

    $policies = (new AtFreeSsrBaggageTransformer())->transform($response, $flight)['baggage_policies'];

    expect(collect($policies)->firstWhere('type', 'checkIn')['description'])->toBe('25 Kg')
        ->and(collect($policies)->firstWhere('type', 'carry')['description'])->toBe('7 Kg');
});
