<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flight Itinerary</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }

        .container {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
        }

        .header {
            background-color: #f4f4f4;
            padding: 20px;
            text-align: center;
        }

        .content {
            padding: 20px;
        }

        .flight-info {
            margin-bottom: 20px;
        }

        .flight-info-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .segment {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 15px;
        }

        .airline-logo {
            width: 40px;
            height: 40px;
            border-radius: 50%;
        }

        .layover {
            background-color: #ffe4e1;
            padding: 10px;
            margin: 10px 0;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- User Information -->
        <div class="user-info">
            <p><strong>Name:</strong> {{ $agent->details->first_name . ' ' . $agent->details->last_name }}</p>
            <p><strong>Email:</strong> {{ $agent->email }}</p>
            <p><strong>Phone:</strong> {{ $agent->details->phone ?? 'N/A' }}</p>
        </div>

        <div class="content">
            <div class="flight-info">
                <div class="flight-info-header">
                    <h2>
                        {{ $flight['slices'][0]['segments'][0]['origin']['airport']['city_name'] }} to
                        {{ $flight['slices'][0]['segments'][count($flight['slices'][0]['segments']) - 1]['destination']['airport']['city_name'] }}
                    </h2>

                    <h2>
                        {{ $flight['price']['total'] }}
                    </h2>
                </div>
                <p>
                    Travel time: {{ $flight['slices'][0]['duration'] }}
                    {{ count($flight['slices'][0]['segments']) - 1 }}
                    stop{{ count($flight['slices'][0]['segments']) - 1 !== 1 ? 's' : '' }}
                </p>
                <p>
                    {{ \Carbon\Carbon::parse($flight['slices'][0]['segments'][0]['departure_at'])->format('M d, Y h:i A') }}
                    -
                    {{ \Carbon\Carbon::parse($flight['slices'][0]['segments'][count($flight['slices'][0]['segments']) - 1]['arriving_at'])->format('M d, Y h:i A') }}
                </p>
                <p>
                    {{ $flight['slices'][0]['segments'][0]['origin']['airport']['city_name'] }}
                    ({{ $flight['slices'][0]['segments'][0]['origin']['airport']['iata_code'] }}) -
                    {{ $flight['slices'][0]['segments'][count($flight['slices'][0]['segments']) - 1]['destination']['airport']['city_name'] }}
                    ({{ $flight['slices'][0]['segments'][count($flight['slices'][0]['segments']) - 1]['destination']['airport']['iata_code'] }})
                </p>
            </div>

            @foreach ($flight['slices'][0]['segments'] as $index => $segment)
                <div class="segment">
                    <h3>{{ $segment['airlines']['name'] }}</h3>
                    <p>
                        <strong>From:</strong> {{ $segment['origin']['airport']['city_name'] }}
                        ({{ $segment['origin']['airport']['iata_code'] }})
                    </p>
                    <p>
                        <strong>To:</strong> {{ $segment['destination']['airport']['city_name'] }}
                        ({{ $segment['destination']['airport']['iata_code'] }})
                    </p>
                    <p><strong>Aircraft:</strong> {{ $segment['aircraft']['name'] ?? 'N/A' }}</p>
                    <p><strong>Departure Terminal:</strong> {{ $segment['origin_terminal'] ?? 'N/A' }}</p>
                    <p><strong>Arrival Terminal:</strong> {{ $segment['destination_terminal'] ?? 'N/A' }}</p>
                    <p><strong>Departure:</strong>
                        {{ \Carbon\Carbon::parse($segment['departure_at'])->format('M d, Y h:i A') }}</p>
                    <p><strong>Arrival:</strong>
                        {{ \Carbon\Carbon::parse($segment['arriving_at'])->format('M d, Y h:i A') }}</p>
                    <p><strong>Duration:</strong>
                        {{ gmdate('H:i', convertDurationToSeconds($segment['duration'] ?? 'PT0S')) }}</p>
                </div>

                @if ($index < count($flight['slices'][0]['segments']) - 1)
                    <div class="layover">
                        <strong>Layover:</strong>
                        {{ \Carbon\Carbon::parse($flight['slices'][0]['segments'][$index + 1]['departure_at'])->format('M d, Y h:i A') }}
                    </div>
                @endif
            @endforeach
        </div>
    </div>
</body>

</html>
