<?php

return [

    'sign_base_url' => env('AT_SIGN_BASE_URL'),
    'flight_base_url' => env('AT_FLIGHT_BASE_URL'),
    'fare_rule_path' => env('AT_FARE_RULE_PATH', '/Flights/FareRule'),
    'merchant_id' => env('AT_MERCHANT_ID'),
    'api_key'     => env('AT_API_KEY'),
    'client_id'   => env('AT_CLIENT_ID'),
    'password'    => env('AT_PASSWORD'),
    'agent_code'  => env('AT_AGENT_CODE'),
    'browser_key' => env('AT_BROWSER_KEY'),
    'ca_bundle'   => env('AT_CA_BUNDLE', storage_path('certs/server_chain.pem')),

    'pre_booking_ssr_limits' => [
        'domestic' => [
            'ssr' => 'TR,FD,QZ,D7,PQ,JW,OZ,SG,6E,AK,I5,CI5,EI5,FZ,G8,Z2,XJ,XT,PSG,2T,S6E,C6E,E6E,SSG,CSG,ESG,SG8,CG8,EG8,FSG,FG8,SI5,PG8,SF,IX,QP,EQP,IC,R6E,AMN,N6E,EIX,RIY,AI,JCL,S51,SB,AB',
            'baggage' => 'SG,6E,FZ,G8,FD,QZ,D7,PQ,Z2,XJ,XT,PSG,2T,C6E,S6E,ESG,SSG,CSG,EG8,SG8,CG8,I5,AK,E6E,FSG,CI5,EI5,FG8,SI5,PG8,SF,IX,QP,EQP,IC,R6E,AMN,N6E,EIX,RIY,AI,JCL,S51,AB,SB',
            'meal' => '6E,AK,I5,G8,FD,QZ,D7,PQ,Z2,XJ,XT,PSG,ESG,CSG,S6E,C6E,EG8,SG8,CG8,SSG,E6E,FSG,CI5,EI5,SI5,SG,PG8,SF,IX,QP,EQP,AMN,R6E,N6E,EIX,RIY,AI,JCL,S51,AB,SB',
            'seat' => '6E,SG,ESG,CSG,S6E,E6E,C6E,G8,SG8,EG8,CG8,AK,I5,EI5,CI5,SSG,IX,SI5,SB,AB,QP,EQP,IC,R6E,AM,AMN,N6E,EIX,RIY,AI,S51',
        ],
        'international' => [
            'ssr' => 'TR,AK,FD,QZ,D7,PQ,JW,OZ,SG,6E,G9,Z2,XJ,FZ,G8,TZ,3L,XT,PSG,2T,S6E,C6E,E6E,SSG,CSG,ESG,SG8,CG8,EG8,SQ,WY,OV,FSG,OD,IX,PG8,LH,KQ,J9,EY,SF,I5,AMN,QP,EQP,GF,DD,G9A,JI,XY,TZN,EIX,OD1,RIY,VJ,AI,JCL,SB,AB,1SN,PKF,W5,ACH,F3,A3,RX',
            'baggage' => 'G9,SG,6E,I5,TR,QZ,D7,PQ,Z2,XJ,FD,FZ,G8,TZ,XT,PSG,C6E,S6E,ESG,SSG,CSG,EG8,SG8,LH,CG8,WY,E6E,OV,FSG,OD,3L,AK,IX,PG8,SQ,KQ,J9,EY,SF,AMN,QP,EQP,GF,DD,G9A,JI,XY,TZN,EIX,OD1,RIY,VJ,AI,JCL,AB,SB,PKF,W5,ACH,F3,A3,RX',
            'meal' => 'G9,6E,AK,I5,TR,QZ,D7,PQ,Z2,XJ,FD,SG,G8,TZ,XT,PSG,ESG,CSG,EG8,SG8,CG8,SSG,E6E,LH,FSG,S6E,3L,IX,PG8,J9,SF,SQ,AMN,QP,EQP,FZ,WY,G9A,XY,TZN,EIX,OD,RIY,VJ,AI,C6E,JCL,OD1,PKF,ACH,F3,OV,AB,SB',
            'seat' => '6E,ESG,SG,CSG,S6E,E6E,C6E,G8,SG8,EG8,CG8,SSG,IX,G9,SQ,LH,KQ,TZ,SB,AB,I5,GF,3L,QP,EQP,FZ,WY,AM,G9A,JI,AMN,XY,TZN,AK,EIX,OD,RIY,VJ,OD1,AI,1SN,PKF,EY,ACH,F3,A3',
        ],
    ],

];
