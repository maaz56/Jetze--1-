<?php

namespace Database\Seeders;

use App\Models\Airline;
use App\Models\PopularRoute;
use App\Models\SeoMeta;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PopularRouteSeeder extends Seeder
{
    /**
     * Run the database seeds for popular flight routes in Jetze.
     */
    public function run(): void
    {
        $routes = [
            [
                'from_airport' => 'LHE',
                'to_airport' => 'DXB',
                'type' => 'international',
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 1,
                'airline_iata' => 'EK',
                'journey_type' => 'round',
                'travel_class' => 'economy',
                'departure_plus_days' => 7,
                'stay_duration_days' => 7,
                'price_type' => 'dynamic',
                'dynamic_refresh_hours' => 4,
                'dynamic_price' => 950.00,
                'dynamic_price_currency' => 'AED',
                'image' => 'https://images.unsplash.com/photo-1512453979798-5ea266f8880c?w=1200&auto=format&fit=crop&q=80',
                'destination_name_en' => 'Dubai',
                'destination_name_ar' => 'دبي',
                'meta' => [
                    'stops' => 0,
                    'duration_minutes' => 215,
                    'airline_iata' => 'EK',
                    'cabin' => 'Y',
                ],
                'blogs' => [
                    [
                        'title' => 'Top Travel Tips for Flying from Lahore to Dubai',
                        'content' => '<p>Dubai remains one of the top global destinations for travelers departing from Allama Iqbal International Airport (LHE). With convenient non-stop flights taking just under 3.5 hours, you can experience world-class shopping, desert safaris, and iconic architecture like Burj Khalifa.</p><p>We recommend booking at least 2 to 3 weeks ahead for the best economy fares, particularly during the winter months from November to March when Dubai weather is ideal.</p>',
                    ],
                    [
                        'title' => 'Baggage Allowances & Airport Procedures',
                        'content' => '<p>Most carriers operating from Lahore to Dubai International Airport (DXB) allow between 30kg and 40kg of checked baggage for economy travelers. Be sure to arrive at LHE at least 3 hours before scheduled departure.</p>',
                    ]
                ],
                'faqs' => [
                    [
                        'question' => 'How long is the flight from Lahore to Dubai?',
                        'answer' => '<p>Direct non-stop flights from Lahore (LHE) to Dubai (DXB) typically take approximately 3 hours and 35 minutes.</p>',
                    ],
                    [
                        'question' => 'Which airlines fly directly between Lahore and Dubai?',
                        'answer' => '<p>Emirates, Pakistan International Airlines (PIA), flydubai, and Airblue operate regular non-stop flights between Lahore and Dubai.</p>',
                    ],
                    [
                        'question' => 'What is the cheapest time to book flights to Dubai?',
                        'answer' => '<p>Fares are generally lowest between April and August, though promotions and discount deals frequently pop up for mid-week travel year-round.</p>',
                    ],
                ],
                'seo' => [
                    'meta_title' => 'Cheap Flights from Lahore to Dubai (LHE to DXB) | Jetze',
                    'meta_description' => 'Find the cheapest flights from Lahore to Dubai. Compare live fares, flight schedules, and book discounted flight tickets online with Jetze.',
                    'meta_keywords' => 'Lahore to Dubai flights, LHE to DXB, cheap flights to Dubai, Dubai flight tickets',
                ]
            ],
            [
                'from_airport' => 'KHI',
                'to_airport' => 'ISB',
                'type' => 'domestic',
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 2,
                'airline_iata' => 'PK',
                'journey_type' => 'round',
                'travel_class' => 'economy',
                'departure_plus_days' => 5,
                'stay_duration_days' => 4,
                'price_type' => 'dynamic',
                'dynamic_refresh_hours' => 4,
                'dynamic_price' => 450.00,
                'dynamic_price_currency' => 'AED',
                'image' => 'https://images.unsplash.com/photo-1598894109724-4f40f09a5601?w=1200&auto=format&fit=crop&q=80',
                'destination_name_en' => 'Islamabad',
                'destination_name_ar' => 'إسلام آباد',
                'meta' => [
                    'stops' => 0,
                    'duration_minutes' => 110,
                    'airline_iata' => 'PK',
                    'cabin' => 'Y',
                ],
                'blogs' => [
                    [
                        'title' => 'Traveling from Karachi to the Capital',
                        'content' => '<p>Whether you are traveling for government business, corporate conferences, or a gateway to the northern areas of Pakistan, Karachi to Islamabad is Pakistan\'s busiest domestic air route. Multiple daily flights connect Jinnah International Airport (KHI) with Islamabad International Airport (ISB).</p>',
                    ]
                ],
                'faqs' => [
                    [
                        'question' => 'How long does a flight from Karachi to Islamabad take?',
                        'answer' => '<p>Non-stop flights take roughly 1 hour and 50 minutes.</p>',
                    ],
                    [
                        'question' => 'Which domestic airlines operate between Karachi and Islamabad?',
                        'answer' => '<p>PIA, Airblue, SereneAir, AirSial, and Fly Jinnah all offer daily flights connecting both cities.</p>',
                    ],
                ],
                'seo' => [
                    'meta_title' => 'Cheap Flights from Karachi to Islamabad (KHI to ISB) | Jetze',
                    'meta_description' => 'Book affordable domestic flights from Karachi to Islamabad. Compare daily schedules and airlines with instant e-ticketing on Jetze.',
                    'meta_keywords' => 'Karachi to Islamabad flights, KHI to ISB air tickets, domestic flights Pakistan',
                ]
            ],
            [
                'from_airport' => 'ISB',
                'to_airport' => 'LHR',
                'type' => 'international',
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 3,
                'airline_iata' => 'PK',
                'journey_type' => 'round',
                'travel_class' => 'economy',
                'departure_plus_days' => 14,
                'stay_duration_days' => 14,
                'price_type' => 'dynamic',
                'dynamic_refresh_hours' => 6,
                'dynamic_price' => 2200.00,
                'dynamic_price_currency' => 'AED',
                'image' => 'https://images.unsplash.com/photo-1513635269975-59663e0ac1ad?w=1200&auto=format&fit=crop&q=80',
                'destination_name_en' => 'London',
                'destination_name_ar' => 'لندن',
                'meta' => [
                    'stops' => 1,
                    'duration_minutes' => 540,
                    'airline_iata' => 'PK',
                    'cabin' => 'Y',
                ],
                'blogs' => [
                    [
                        'title' => 'Complete Guide for Flights from Islamabad to London',
                        'content' => '<p>Flying from Islamabad to London Heathrow (LHR) or Gatwick (LGW) is one of the most traveled long-haul routes connecting Pakistan to Europe. Renowned connecting airlines such as Qatar Airways, Emirates, and Turkish Airlines offer short layover itineraries.</p>',
                    ]
                ],
                'faqs' => [
                    [
                        'question' => 'What is the average flight duration from Islamabad to London?',
                        'answer' => '<p>A direct flight takes around 8.5 to 9 hours, while 1-stop flights via Middle Eastern hubs usually take between 11 and 14 hours total duration.</p>',
                    ],
                ],
                'seo' => [
                    'meta_title' => 'Flights from Islamabad to London (ISB to LHR) | Jetze',
                    'meta_description' => 'Find the best deals on flights from Islamabad to London Heathrow. Book one-way and return tickets with Jetze.',
                    'meta_keywords' => 'Islamabad to London flights, ISB to LHR, London flight tickets',
                ]
            ],
            [
                'from_airport' => 'KHI',
                'to_airport' => 'JED',
                'type' => 'international',
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 4,
                'airline_iata' => 'SV',
                'journey_type' => 'round',
                'travel_class' => 'economy',
                'departure_plus_days' => 10,
                'stay_duration_days' => 10,
                'price_type' => 'dynamic',
                'dynamic_refresh_hours' => 4,
                'dynamic_price' => 1250.00,
                'dynamic_price_currency' => 'AED',
                'image' => 'https://images.unsplash.com/photo-1591604129939-f1efa4d9f7fa?w=1200&auto=format&fit=crop&q=80',
                'destination_name_en' => 'Jeddah',
                'destination_name_ar' => 'جدة',
                'meta' => [
                    'stops' => 0,
                    'duration_minutes' => 260,
                    'airline_iata' => 'SV',
                    'cabin' => 'Y',
                ],
                'blogs' => [
                    [
                        'title' => 'Umrah & Business Flights from Karachi to Jeddah',
                        'content' => '<p>King Abdulaziz International Airport (JED) is the gateway for pilgrimage to the Holy City of Makkah as well as Saudi Arabia\'s commercial hub. Both Saudia and Pakistani carriers operate multiple daily non-stop flights from Karachi.</p>',
                    ]
                ],
                'faqs' => [
                    [
                        'question' => 'How early should pilgrims arrive at Karachi airport for Jeddah flights?',
                        'answer' => '<p>Due to specialized pilgrimage screening and Zamzam baggage protocols, it is strongly advised to arrive at least 4 hours before departure.</p>',
                    ],
                ],
                'seo' => [
                    'meta_title' => 'Karachi to Jeddah Flights (KHI to JED) - Umrah Special | Jetze',
                    'meta_description' => 'Book cheap flights from Karachi to Jeddah on Saudia and PIA. Lowest fares for Umrah and family visits on Jetze.',
                    'meta_keywords' => 'Karachi to Jeddah flights, KHI to JED, Umrah flights, Jeddah flight tickets',
                ]
            ],
            [
                'from_airport' => 'LHE',
                'to_airport' => 'IST',
                'type' => 'international',
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 5,
                'airline_iata' => 'TK',
                'journey_type' => 'round',
                'travel_class' => 'economy',
                'departure_plus_days' => 12,
                'stay_duration_days' => 7,
                'price_type' => 'dynamic',
                'dynamic_refresh_hours' => 6,
                'dynamic_price' => 1420.00,
                'dynamic_price_currency' => 'AED',
                'image' => 'https://images.unsplash.com/photo-1524231757912-21f4fe3a7200?w=1200&auto=format&fit=crop&q=80',
                'destination_name_en' => 'Istanbul',
                'destination_name_ar' => 'إسطنبول',
                'meta' => [
                    'stops' => 0,
                    'duration_minutes' => 370,
                    'airline_iata' => 'TK',
                    'cabin' => 'Y',
                ],
                'blogs' => [
                    [
                        'title' => 'Discover Istanbul: Direct from Lahore',
                        'content' => '<p>Explore the historic crossroads of Europe and Asia with non-stop flights connecting Lahore with Istanbul Airport (IST). Enjoy Turkish hospitality, majestic mosques, and vibrant Grand Bazaar shopping.</p>',
                    ]
                ],
                'faqs' => [
                    [
                        'question' => 'Do Pakistani citizens need a visa to visit Istanbul?',
                        'answer' => '<p>Yes, Pakistani passport holders need a visa. Those holding valid US, UK, Schengen, or Ireland visas can conveniently apply for an official Turkish e-Visa online.</p>',
                    ],
                ],
                'seo' => [
                    'meta_title' => 'Lahore to Istanbul Flights (LHE to IST) | Jetze',
                    'meta_description' => 'Compare and book cheap flights from Lahore to Istanbul with Turkish Airlines and regional partners on Jetze.',
                    'meta_keywords' => 'Lahore to Istanbul flights, LHE to IST, cheap flights to Turkey',
                ]
            ],
            [
                'from_airport' => 'ISB',
                'to_airport' => 'DOH',
                'type' => 'international',
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 6,
                'airline_iata' => 'QR',
                'journey_type' => 'round',
                'travel_class' => 'economy',
                'departure_plus_days' => 8,
                'stay_duration_days' => 6,
                'price_type' => 'dynamic',
                'dynamic_refresh_hours' => 4,
                'dynamic_price' => 1100.00,
                'dynamic_price_currency' => 'AED',
                'image' => 'https://images.unsplash.com/photo-1579606032836-db40fe5a2497?w=1200&auto=format&fit=crop&q=80',
                'destination_name_en' => 'Doha',
                'destination_name_ar' => 'الدوحة',
                'meta' => [
                    'stops' => 0,
                    'duration_minutes' => 220,
                    'airline_iata' => 'QR',
                    'cabin' => 'Y',
                ],
                'blogs' => [
                    [
                        'title' => 'Flight Guide: Islamabad to Hamad International, Doha',
                        'content' => '<p>Hamad International Airport in Doha consistently ranks as one of the world\'s best airports. Enjoy direct connectivity from Islamabad with Qatar Airways.</p>',
                    ]
                ],
                'faqs' => [
                    [
                        'question' => 'How long is the non-stop flight from Islamabad to Doha?',
                        'answer' => '<p>Direct flights take approximately 3 hours and 40 minutes.</p>',
                    ],
                ],
                'seo' => [
                    'meta_title' => 'Cheap Flights from Islamabad to Doha (ISB to DOH) | Jetze',
                    'meta_description' => 'Find the lowest fares for flights from Islamabad to Doha with Qatar Airways and PIA on Jetze.',
                    'meta_keywords' => 'Islamabad to Doha flights, ISB to DOH, Qatar Airways flights',
                ]
            ],
            [
                'from_airport' => 'KHI',
                'to_airport' => 'DXB',
                'type' => 'international',
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 7,
                'airline_iata' => 'FZ',
                'journey_type' => 'round',
                'travel_class' => 'economy',
                'departure_plus_days' => 6,
                'stay_duration_days' => 5,
                'price_type' => 'dynamic',
                'dynamic_refresh_hours' => 4,
                'dynamic_price' => 890.00,
                'dynamic_price_currency' => 'AED',
                'image' => 'https://images.unsplash.com/photo-1546412414-e1885259563a?w=1200&auto=format&fit=crop&q=80',
                'destination_name_en' => 'Dubai',
                'destination_name_ar' => 'دبي',
                'meta' => [
                    'stops' => 0,
                    'duration_minutes' => 140,
                    'airline_iata' => 'FZ',
                    'cabin' => 'Y',
                ],
                'blogs' => [
                    [
                        'title' => 'Quick 2-Hour Hop from Karachi to Dubai',
                        'content' => '<p>Karachi to Dubai is the quickest international route from Pakistan, taking just over two hours. With multiple daily departures by Emirates, flydubai, and Airblue, you can easily plan weekend getaways.</p>',
                    ]
                ],
                'faqs' => [
                    [
                        'question' => 'What is the flight duration between Karachi and Dubai?',
                        'answer' => '<p>Non-stop flights typically take between 2 hours 10 minutes and 2 hours 25 minutes.</p>',
                    ],
                ],
                'seo' => [
                    'meta_title' => 'Karachi to Dubai Flights (KHI to DXB) | Jetze',
                    'meta_description' => 'Book cheap flight tickets from Karachi to Dubai. Daily flights on Emirates, flydubai, PIA and Airblue on Jetze.',
                    'meta_keywords' => 'Karachi to Dubai flights, KHI to DXB, cheap flights Karachi to Dubai',
                ]
            ],
            [
                'from_airport' => 'LHE',
                'to_airport' => 'KHI',
                'type' => 'domestic',
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 8,
                'airline_iata' => 'PK',
                'journey_type' => 'round',
                'travel_class' => 'economy',
                'departure_plus_days' => 4,
                'stay_duration_days' => 3,
                'price_type' => 'dynamic',
                'dynamic_refresh_hours' => 4,
                'dynamic_price' => 420.00,
                'dynamic_price_currency' => 'AED',
                'image' => 'https://images.unsplash.com/photo-1589182373726-e4f658ab50f0?w=1200&auto=format&fit=crop&q=80',
                'destination_name_en' => 'Karachi',
                'destination_name_ar' => 'كراتشي',
                'meta' => [
                    'stops' => 0,
                    'duration_minutes' => 105,
                    'airline_iata' => 'PK',
                    'cabin' => 'Y',
                ],
                'blogs' => [
                    [
                        'title' => 'Direct Flights from Lahore to Karachi',
                        'content' => '<p>Travel effortlessly between Pakistan\'s cultural capital and its bustling economic center on the Arabian Sea. Upwards of 8 daily flights operate between LHE and KHI.</p>',
                    ]
                ],
                'faqs' => [
                    [
                        'question' => 'How long does a flight from Lahore to Karachi take?',
                        'answer' => '<p>Direct non-stop flights take roughly 1 hour and 45 minutes.</p>',
                    ],
                ],
                'seo' => [
                    'meta_title' => 'Lahore to Karachi Flights (LHE to KHI) | Jetze',
                    'meta_description' => 'Book cheap domestic flights from Lahore to Karachi on PIA, Airblue, SereneAir, and AirSial via Jetze.',
                    'meta_keywords' => 'Lahore to Karachi flights, LHE to KHI tickets, Pakistan domestic airfares',
                ]
            ],
            [
                'from_airport' => 'PEW',
                'to_airport' => 'SHJ',
                'type' => 'international',
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 9,
                'airline_iata' => 'PA',
                'journey_type' => 'round',
                'travel_class' => 'economy',
                'departure_plus_days' => 9,
                'stay_duration_days' => 8,
                'price_type' => 'dynamic',
                'dynamic_refresh_hours' => 4,
                'dynamic_price' => 980.00,
                'dynamic_price_currency' => 'AED',
                'image' => 'https://images.unsplash.com/photo-1518684079-3c830dcef090?w=1200&auto=format&fit=crop&q=80',
                'destination_name_en' => 'Sharjah',
                'destination_name_ar' => 'الشارقة',
                'meta' => [
                    'stops' => 0,
                    'duration_minutes' => 210,
                    'airline_iata' => 'PA',
                    'cabin' => 'Y',
                ],
                'blogs' => [
                    [
                        'title' => 'Peshawar to Sharjah Direct Flights',
                        'content' => '<p>Connect directly from Bacha Khan International Airport in Peshawar to Sharjah International Airport, a popular gateway for the Northern Emirates and Dubai commuter region.</p>',
                    ]
                ],
                'faqs' => [
                    [
                        'question' => 'Is Sharjah airport close to Dubai?',
                        'answer' => '<p>Yes, Sharjah International Airport is only about 15-20 kilometers from central Dubai, with readily available taxis and buses.</p>',
                    ],
                ],
                'seo' => [
                    'meta_title' => 'Peshawar to Sharjah Flights (PEW to SHJ) | Jetze',
                    'meta_description' => 'Find the best airfare deals for flights from Peshawar to Sharjah with Airblue and regional carriers on Jetze.',
                    'meta_keywords' => 'Peshawar to Sharjah flights, PEW to SHJ, cheap flights to UAE',
                ]
            ],
            [
                'from_airport' => 'SKT',
                'to_airport' => 'RUH',
                'type' => 'international',
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 10,
                'airline_iata' => 'SV',
                'journey_type' => 'round',
                'travel_class' => 'economy',
                'departure_plus_days' => 11,
                'stay_duration_days' => 9,
                'price_type' => 'dynamic',
                'dynamic_refresh_hours' => 4,
                'dynamic_price' => 1200.00,
                'dynamic_price_currency' => 'AED',
                'image' => 'https://images.unsplash.com/photo-1586724237569-f3d0c1dee8c6?w=1200&auto=format&fit=crop&q=80',
                'destination_name_en' => 'Riyadh',
                'destination_name_ar' => 'الرياض',
                'meta' => [
                    'stops' => 0,
                    'duration_minutes' => 280,
                    'airline_iata' => 'SV',
                    'cabin' => 'Y',
                ],
                'blogs' => [
                    [
                        'title' => 'Flying from Sialkot to the Saudi Capital',
                        'content' => '<p>Direct flights from Sialkot International Airport (SKT) to King Khalid International Airport (RUH) in Riyadh offer tremendous convenience for Pakistani expatriates and business professionals.</p>',
                    ]
                ],
                'faqs' => [
                    [
                        'question' => 'Which airlines fly from Sialkot to Riyadh?',
                        'answer' => '<p>Saudia, PIA, and Airblue operate regular scheduled flights on the Sialkot to Riyadh route.</p>',
                    ],
                ],
                'seo' => [
                    'meta_title' => 'Sialkot to Riyadh Flights (SKT to RUH) | Jetze',
                    'meta_description' => 'Check flight schedules and low fares from Sialkot to Riyadh on Saudia and PIA with Jetze.',
                    'meta_keywords' => 'Sialkot to Riyadh flights, SKT to RUH, Riyadh flight tickets',
                ]
            ]
        ];

        foreach ($routes as $item) {
            $airline = !empty($item['airline_iata'])
                ? Airline::where('iata_code', $item['airline_iata'])->first()
                : null;

            $slugBase = Str::slug(($item['destination_name_en'] ?: 'flight') . '-from-' . $item['from_airport'] . '-to-' . $item['to_airport']);
            $slug = $slugBase;
            $counter = 1;
            while (PopularRoute::where('slug', $slug)->exists()) {
                $slug = "{$slugBase}-{$counter}";
                $counter++;
            }

            $route = PopularRoute::updateOrCreate(
                [
                    'from_airport' => $item['from_airport'],
                    'to_airport' => $item['to_airport'],
                ],
                [
                    'slug' => $slug,
                    'type' => $item['type'],
                    'is_active' => $item['is_active'],
                    'is_featured' => $item['is_featured'],
                    'sort_order' => $item['sort_order'],
                    'airline_id' => $airline?->id,
                    'journey_type' => $item['journey_type'],
                    'travel_class' => $item['travel_class'],
                    'departure_plus_days' => $item['departure_plus_days'],
                    'stay_duration_days' => $item['stay_duration_days'],
                    'price_type' => $item['price_type'],
                    'dynamic_refresh_hours' => $item['dynamic_refresh_hours'],
                    'dynamic_price' => $item['dynamic_price'],
                    'dynamic_price_currency' => $item['dynamic_price_currency'],
                    'dynamic_price_meta' => $item['meta'],
                    'price_checked_at' => now(),
                    'price_updated_at' => now(),
                    'next_price_refresh_at' => now()->addHours($item['dynamic_refresh_hours']),
                    'price_status' => 'fresh',
                    'price_failure_count' => 0,
                    'destination_name_en' => $item['destination_name_en'],
                    'destination_name_ar' => $item['destination_name_ar'],
                    'image' => $item['image'],
                    'blogs' => $item['blogs'],
                    'faqs' => $item['faqs'],
                ]
            );

            // Sync SEO Meta
            if (!empty($item['seo'])) {
                SeoMeta::updateOrCreate(
                    [
                        'seoable_type' => PopularRoute::class,
                        'seoable_id' => $route->id,
                    ],
                    [
                        'meta_title' => $item['seo']['meta_title'],
                        'meta_description' => $item['seo']['meta_description'],
                        'meta_keywords' => $item['seo']['meta_keywords'],
                        'og_title' => $item['seo']['meta_title'],
                        'og_description' => $item['seo']['meta_description'],
                        'og_image' => $item['image'],
                    ]
                );
            }
        }
    }
}
