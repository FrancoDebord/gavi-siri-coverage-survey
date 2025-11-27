@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold">Tableau de bord ODK - Recrutement</h3>
        <a href="{{ url('/errors') }}" class="btn btn-danger">⚠️ Données erronées</a>
    </div>

    {{-- Filtres globaux --}}
    <div class="row mb-4">
        <div class="col-md-3">
            <label class="form-label">Zone Sanitaire</label>
            <select id="filter-zone" class="form-select">
                <option value="">Toutes</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Commune</label>
            <select id="filter-commune" class="form-select">
                <option value="">Toutes</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Village</label>
            <select id="filter-village" class="form-select">
                <option value="">Tous</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Agent</label>
            <select id="filter-agent" class="form-select">
                <option value="">Tous</option>
            </select>
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="row g-3 mb-5">
        <div class="col-md-4">
            <div class="card bg-primary-subtle shadow-sm">
                <div class="card-body text-center">
                    <h5 class="fw-bold">🏠 Ménages</h5>
                    <h2>{{ $kpiData['menage']['total'] }}</h2>
                    <p>Éligibles : <strong>{{ $kpiData['menage']['eligible'] }}</strong> ({{ $kpiData['menage']['pourcentage'] }}%)</p>
                    <a href="{{ url('/menages') }}" class="btn btn-outline-primary btn-sm">Détails</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success-subtle shadow-sm">
                <div class="card-body text-center">
                    <h5 class="fw-bold">👩‍👧 Mères</h5>
                    <h2>{{ $kpiData['mere']['total'] }}</h2>
                    <p>Éligibles : <strong>{{ $kpiData['mere']['eligible'] }}</strong> ({{ $kpiData['mere']['pourcentage'] }}%)</p>
                    <a href="{{ url('/meres') }}" class="btn btn-outline-success btn-sm">Détails</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-warning-subtle shadow-sm">
                <div class="card-body text-center">
                    <h5 class="fw-bold">👶 Enfants</h5>
                    <h2>{{ $kpiData['enfant']['total'] }}</h2>
                    <p>Éligibles : <strong>{{ $kpiData['enfant']['eligible'] }}</strong> ({{ $kpiData['enfant']['pourcentage'] }}%)</p>
                    <a href="{{ url('/enfants') }}" class="btn btn-outline-warning btn-sm">Détails</a>
                </div>
            </div>
        </div>
    </div>

    {{-- Section tableaux et cartes --}}
    @php
        $sections = [
            ['title' => 'Ménages', 'id' => 'menages', 'table' => $menages_table, 'color' => 'primary'],
            ['title' => 'Mères', 'id' => 'meres', 'table' => $meres_table, 'color' => 'success'],
            ['title' => 'Enfants', 'id' => 'enfants', 'table' => $enfants_table, 'color' => 'warning']
        ];
    @endphp

    @foreach($sections as $s)
        <div class="mb-5">
            <h4 class="fw-bold text-{{ $s['color'] }}">{{ $s['title'] }}</h4>

            <div class="table-responsive mb-3">
                <table id="table-{{ $s['id'] }}" class="table table-bordered table-striped" style="width:100%">
                    <thead class="table-{{ $s['color'] }}">
                        <tr>
                            <th>Zone Sanitaire</th>
                            <th>Village</th>
                            <th>Total</th>
                            <th>Éligibles</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($s['table'] as $row)
                            <tr>
                                <td>{{ $row['ZoneSanitaire'] }}</td>
                                <td>{{ $row['Village'] }}</td>
                                <td>{{ $row['Total'] }}</td>
                                <td>{{ $row['Eligibles'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div id="map-{{ $s['id'] }}" style="height: 400px; border-radius: 10px;"></div>
        </div>
    @endforeach
</div>
@endsection

@section('scripts')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
$(document).ready(function() {
    // Init DataTables
    ['menages','meres','enfants'].forEach(id => {
        $('#table-' + id).DataTable({
            pageLength: 5,
            lengthChange: false,
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json' }
        });
    });

    // Init Maps
    const initMap = (id) => {
        const map = L.map('map-' + id).setView([9.5, 2.5], 8);

        const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap'
        }).addTo(map);

        const sat = L.tileLayer('https://{s}.google.com/vt/lyrs=s&x={x}&y={y}&z={z}', {
            maxZoom: 20,
            subdomains:['mt0','mt1','mt2','mt3'],
            attribution: 'Google Satellite'
        });

        const topo = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
            maxZoom: 17,
            attribution: '© OpenTopoMap'
        });

        L.control.layers({ 'OSM': osm, 'Satellite': sat, 'Topo': topo }).addTo(map);

        const legend = L.control({position: 'bottomright'});
        legend.onAdd = function () {
            const div = L.DomUtil.create('div', 'info legend bg-white p-2 rounded shadow');
            div.innerHTML = '<i style="background:green;width:15px;height:15px;display:inline-block;margin-right:5px;"></i> Éligible<br>' +
                            '<i style="background:red;width:15px;height:15px;display:inline-block;margin-right:5px;"></i> Non Éligible';
            return div;
        };
        legend.addTo(map);
    }

    initMap('menages');
    initMap('meres');
    initMap('enfants');
});
</script>
@endsection
