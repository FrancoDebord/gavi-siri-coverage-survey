@extends('layouts.app')

@section('content')
    <h2 class="fw-bold mb-4">Recrutement des Mères</h2>

    <div class="table-responsive mb-5">
        <table class="table table-striped table-hover align-middle">
            <thead class="table-primary">
                <tr>
                    <th>Village</th>
                    <th>Binôme</th>
                    <th>Total Ménages</th>
                    <th>Ménages avec Enfant Éligible</th>
                </tr>
            </thead>
            <tbody>
                @php $grouped = collect($data)->groupBy('village_name'); @endphp
                @foreach ($grouped as $village => $records)
                    @php
                        $binome = $records->first()['fieldworker_name'] ?? '-';
                        $total = $records->count();
                        $eligibles = $records->where('Eligible', true)->count();
                    @endphp
                    <tr>
                        <td>{{ $village }}</td>
                        <td>{{ $binome }}</td>
                        <td>{{ $total }}</td>
                        <td>{{ $eligibles }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <h4 class="fw-semibold mb-3">Carte des Mères</h4>
    <div id="map" style="height: 500px;" class="border rounded shadow-sm"></div>

    <script>
        const map = L.map('map').setView([9.5, 2.3], 7);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);

        const data = @json($data);

        data.forEach(item => {
            if (item['current_location-Latitude'] && item['current_location-Longitude']) {
                const color = item['Eligible'] ? 'green' : 'red';
                L.circleMarker(
                    [item['current_location-Latitude'], item['current_location-Longitude']], {
                        color,
                        radius: 6
                    }
                ).bindPopup(`${item['village_name']}<br>${item['fieldworker_name']}`).addTo(map);
            }
        });
    </script>
@endsection
