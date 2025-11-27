@extends('layouts.app')

@section('content')
    <div class="container-fluid">

        {{-- 🔍 Filtres --}}
        <div class="row mb-3">
            <div class="col-md-2">
                <label class="form-label small">Zone sanitaire</label>
                <select id="filterZone" class="form-select form-select-sm">
                    <option value="">Toutes</option>
                    @foreach ($mapZone as $key => $zone)
                        <option value="{{ $zone }}">{{ $zone }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Commune</label>
                <select id="filterCommune" class="form-select form-select-sm">
                    <option value="">Toutes</option>
                    @foreach ($mapCommune as $key => $commune)
                        <option value="{{ $commune }}">{{ $commune }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">Village</label>
                <select id="filterVillage" class="form-select form-select-sm">
                    <option value="">Tous</option>
                    @foreach ($villages as $village)
                        <option value="{{ $village }}">{{ $village }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">Agent (binôme)</label>
                <select id="filterAgent" class="form-select form-select-sm">
                    <option value="">Tous</option>
                    @foreach ($agents as $agent)
                        <option value="{{ $agent }}">{{ $agent }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button id="clearFilters" class="btn btn-outline-secondary btn-sm w-100">Effacer filtres</button>
            </div>
        </div>

        {{-- 🧭 En-tête --}}
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h4">Tableau de bord - Indicateurs principaux</h1>
            <a href="{{ route('data.errors') }}" class="btn btn-outline-danger">Voir les erreurs sur les données</a>
        </div>

        {{-- 📊 KPI Row --}}
        <div class="row mb-5">
            {{-- Ménages --}}
            <div class="col-md-4">
                <div class="card shadow-sm text-white" style="background-color:#28a745;">
                    <div class="card-body text-center">
                        <h6>Ménages</h6>
                        <h3>{{ $kpiData['menage']['total'] ?? 0 }}</h3>
                        <p>Éligibles : <strong>{{ $kpiData['menage']['eligible'] ?? 0 }}</strong></p>
                        <p>Taux : <strong>{{ $kpiData['menage']['pourcentage'] ?? 0 }}%</strong></p>
                        <a href="{{ route('details.menages') }}" class="btn btn-light btn-sm">Détails</a>
                    </div>
                </div>
            </div>

            {{-- Mères --}}
            <div class="col-md-4">
                <div class="card shadow-sm text-dark" style="background-color:#ffc107;">
                    <div class="card-body text-center">
                        <h6>Mères d’enfant</h6>
                        <h3>{{ $kpiData['mere']['total'] ?? 0 }}</h3>
                        <p>Éligibles : <strong>{{ $kpiData['mere']['eligible'] ?? 0 }}</strong></p>
                        <p>Taux : <strong>{{ $kpiData['mere']['pourcentage'] ?? 0 }}%</strong></p>
                        <a href="{{ route('meres') }}" class="btn btn-dark btn-sm">Détails</a>
                    </div>
                </div>
            </div>

            {{-- Enfants --}}
            <div class="col-md-4">
                <div class="card shadow-sm text-white" style="background-color:#007bff;">
                    <div class="card-body text-center">
                        <h6>Enfants</h6>
                        <h3>{{ $kpiData['enfant']['total'] ?? 0 }}</h3>
                        <p>Éligibles : <strong>{{ $kpiData['enfant']['eligible'] ?? 0 }}</strong></p>
                        <p>Taux : <strong>{{ $kpiData['enfant']['pourcentage'] ?? 0 }}%</strong></p>
                        <a href="{{ route('details.enfants') }}" class="btn btn-light btn-sm">Détails</a>
                    </div>
                </div>
            </div>
        </div>

        {{-- 🏠 MENAGES --}}
        @include('partials._section', [
            'id' => 'menages',
            'title' => 'Ménages - récapitulatif par Zone sanitaire et village',
            'data' => $menages_table,
        ])

        {{-- 👩‍🍼 MERES --}}
        @include('partials._section', [
            'id' => 'meres',
            'title' => 'Mères - récapitulatif par Zone sanitaire et village',
            'data' => $meres_table,
        ])

        {{-- 👶 ENFANTS --}}
        @include('partials._section', [
            'id' => 'enfants',
            'title' => 'Enfants - récapitulatif par Zone sanitaire et village',
            'data' => $enfants_table,
        ])
    </div>



    {{-- Scripts: jQuery, DataTables, Leaflet --}}
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    {{-- <script>
        $(document).ready(function() {
            $('#tblMenages').DataTable({
                paging: true,
                pageLength: 10,
                lengthChange: false,
                searching: true,
                info: false,
                responsive: true,
                order: [
                    [0, 'asc']
                ]
            });
            $('#tblMeres').DataTable({
                paging: true,
                pageLength: 10,
                lengthChange: false,
                searching: true,
                info: false,
                responsive: true,
                order: [
                    [0, 'asc']
                ]
            });
            $('#tblEnfants').DataTable({
                paging: true,
                pageLength: 10,
                lengthChange: false,
                searching: true,
                info: false,
                responsive: true,
                order: [
                    [0, 'asc']
                ]
            });
        });

        // Utility to create a map with multiple tile layers and markers passed from backend
        function initMap(domId, markers, defaultCenter = [9.5, 2.5], defaultZoom = 7) {
            const map = L.map(domId).setView(defaultCenter, defaultZoom);

            // Tile layers
            const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OSM'
            });
            const topo = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenTopoMap'
            });
            const satellite = L.tileLayer('https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png', {
                attribution: '&copy; OSM HOT'
            });

            osm.addTo(map);

            const baseMaps = {
                "OSM": osm,
                "Topo": topo,
                "Satellite-like": satellite
            };
            L.control.layers(baseMaps).addTo(map);

            // Markers
            const eligibleIcon = L.circleMarker([0, 0], {
                radius: 6,
                color: 'green'
            });
            const nonIcon = L.circleMarker([0, 0], {
                radius: 6,
                color: 'red'
            });

            markers.forEach(function(m) {
                try {
                    const color = m.eligible ? 'green' : 'red';
                    const marker = L.circleMarker([m.lat, m.lng], {
                        color: color,
                        radius: 6,
                        fillOpacity: 0.8
                    });
                    const popup =
                        `<b>${m.village}</b><br/>Agent: ${m.agent}<br/>ID: ${m.id}<br/>Eligible: ${m.eligible ? 'Oui' : 'Non'}`;
                    marker.bindPopup(popup);
                    marker.addTo(map);
                } catch (e) {
                    /* ignore invalid points */
                }
            });

            // Fit bounds if markers exist
            if (markers.length) {
                const group = markers.filter(m => m.lat && m.lng).map(m => [m.lat, m.lng]);
                map.fitBounds(group, {
                    maxZoom: 14,
                    padding: [30, 30]
                });
            }

            // Add legend
            const legend = L.control({
                position: 'bottomright'
            });
            legend.onAdd = function() {
                const div = L.DomUtil.create('div', 'info legend p-2 bg-white rounded shadow-sm');
                div.innerHTML +=
                    '<i style="background:green; width:12px; height:12px; display:inline-block; border-radius:6px; margin-right:6px"></i> Éligible<br/>';
                div.innerHTML +=
                    '<i style="background:red; width:12px; height:12px; display:inline-block; border-radius:6px; margin-right:6px"></i> Non éligible<br/>';
                return div;
            };
            legend.addTo(map);
        }

        // Data passed from backend
        const menagesMarkers = {!! json_encode($menages_markers) !!};
        const meresMarkers = {!! json_encode($meres_markers) !!};
        const enfantsMarkers = {!! json_encode($enfants_markers) !!};

        // Initialize maps
        initMap('mapMenages', menagesMarkers);
        initMap('mapMeres', meresMarkers);
        initMap('mapEnfants', enfantsMarkers);



        // after DataTables init and marker arrays available
        function applyFilters() {
            const zone = $('#filterZone').val();
            const commune = $('#filterCommune').val();
            const village = $('#filterVillage').val();
            const agent = $('#filterAgent').val();

            // Filter DataTables by columns: assume table columns have zone -> col 0, village col 1, etc.
            const tables = [{
                    dt: $('#tblMenages').DataTable(),
                    cols: {
                        zone: 0,
                        village: 1
                    }
                },
                {
                    dt: $('#tblMeres').DataTable(),
                    cols: {
                        zone: 0,
                        village: 1
                    }
                },
                {
                    dt: $('#tblEnfants').DataTable(),
                    cols: {
                        zone: 0,
                        village: 1
                    }
                }
            ];
            tables.forEach(function(t) {
                t.dt.columns().search('');
                if (zone) t.dt.column(t.cols.zone).search('^' + zone + '$', true, false);
                if (village) t.dt.column(t.cols.village).search('^' + village + '$', true, false);
                t.dt.draw();
            });

            // Re-draw maps filtering markers arrays then re-init maps (or toggle visibility)
            filterAndRedrawMap('mapMenages', menagesMarkers, zone, commune, village, agent);
            filterAndRedrawMap('mapMeres', meresMarkers, zone, commune, village, agent);
            filterAndRedrawMap('mapEnfants', enfantsMarkers, zone, commune, village, agent);
        }

        $('#filterZone, #filterCommune, #filterVillage, #filterAgent').on('change', applyFilters);
        $('#clearFilters').on('click', function() {
            $('#filterZone,#filterCommune,#filterVillage,#filterAgent').val('');
            applyFilters();
        });

        // helper to filter markers and redraw a single map container (simple approach: remove map and re-create)
        function filterAndRedrawMap(domId, markers, zone, commune, village, agent) {
            // Filter markers array by properties: we used 'village' and 'agent' and store l1/l2 in marker if needed.
            const filtered = markers.filter(function(m) {
                if (zone && (m.l1 == undefined || m.l1 != zone)) return false;
                if (commune && (m.l2 == undefined || m.l2 != commune)) return false;
                if (village && m.village != village) return false;
                if (agent && m.agent != agent) return false;
                return true;
            });
            // Remove existing map _and_ recreate (safe simpler method)
            const container = document.getElementById(domId);
            if (!container) return;
            container._leaflet_id = null;
            container.innerHTML = "";
            initMap(domId, filtered);
        }
    </script>  --}}

    {{-- @push('scripts') --}}
    <script>
        $(document).ready(function() {
            // Initialisation DataTables
            ['Menages', 'Meres', 'Enfants'].forEach(id => {
                $('#tbl' + id).DataTable({
                    paging: true,
                    pageLength: 10,
                    lengthChange: false,
                    searching: true,
                    info: false,
                    responsive: true,
                    order: [
                        [0, 'asc']
                    ]
                });
            });

            // Fonction de création des cartes Leaflet
            function initMap(domId, markers, defaultCenter = [9.5, 2.5], defaultZoom = 7) {
                // const map = L.map(domId).setView(defaultCenter, defaultZoom);

                const map = L.map(domId, {
                    scrollWheelZoom: false
                }).setView(defaultCenter, defaultZoom);

                map.on('click', function() {
                    if (!map.scrollWheelZoom.enabled()) {
                        map.scrollWheelZoom.enable();
                    }
                });
                map.on('mouseout', function() {
                    map.scrollWheelZoom.disable();
                });

                const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap'
                }).addTo(map);
                const topo = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenTopoMap'
                });
                const hot = L.tileLayer('https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png', {
                    attribution: '&copy; OSM HOT'
                });

                L.control.layers({
                    "OSM": osm,
                    "Topo": topo,
                    "Humanitarian": hot
                }).addTo(map);

                markers.forEach(m => {
                    if (m.lat && m.lng) {
                        const color = m.eligible ? 'green' : 'red';
                        const marker = L.circleMarker([m.lat, m.lng], {
                            color,
                            radius: 6,
                            fillOpacity: 0.8
                        }).bindPopup(
                            `<b>${m.village ?? 'N/A'}</b><br>Agent: ${m.agent ?? 'N/A'}<br>Nom du village: ${m.village ?? ''}<br>Éligible: ${m.eligible ? 'Oui' : 'Non'}`
                        );
                        marker.addTo(map);
                    }
                });

                if (markers.length > 0) {
                    const validPoints = markers.filter(m => m.lat && m.lng).map(m => [m.lat, m.lng]);
                    if (validPoints.length) map.fitBounds(validPoints, {
                        padding: [40, 40]
                    });
                }

                // Légende
                const legend = L.control({
                    position: 'bottomright'
                });
                legend.onAdd = () => {
                    const div = L.DomUtil.create('div', 'info legend p-2 bg-white rounded shadow-sm');
                    div.innerHTML = `
                <i style="background:green; width:12px; height:12px; display:inline-block; border-radius:6px; margin-right:6px"></i> Éligible<br/>
                <i style="background:red; width:12px; height:12px; display:inline-block; border-radius:6px; margin-right:6px"></i> Non éligible<br/>
            `;
                    return div;
                };
                legend.addTo(map);
            }

            // Données passées depuis Laravel
            const menagesMarkers = {!! json_encode($menages_markers) !!};
            const meresMarkers = {!! json_encode($meres_markers) !!};
            const enfantsMarkers = {!! json_encode($enfants_markers) !!};

            // Initialiser les 3 cartes
            initMap('mapMenages', menagesMarkers);
            initMap('mapMeres', meresMarkers);
            initMap('mapEnfants', enfantsMarkers);

            // Fonction de filtrage global
            function applyFilters() {
                const zone = $('#filterZone').val();
                const commune = $('#filterCommune').val();
                const village = $('#filterVillage').val();
                const agent = $('#filterAgent').val();

                const tables = [{
                        id: 'Menages',
                        markers: menagesMarkers
                    },
                    {
                        id: 'Meres',
                        markers: meresMarkers
                    },
                    {
                        id: 'Enfants',
                        markers: enfantsMarkers
                    }
                ];

                tables.forEach(t => {
                    const dt = $('#tbl' + t.id).DataTable();
                    dt.columns().search('');
                    if (zone) dt.column(0).search(zone, true, false);
                    if (village) dt.column(1).search(village, true, false);
                    dt.draw();

                    const filtered = t.markers.filter(m => {
                        if (zone && m.l1 !== zone) return false;
                        if (commune && m.l2 !== commune) return false;
                        if (village && m.village !== village) return false;
                        if (agent && m.agent !== agent) return false;
                        return true;
                    });

                    const container = document.getElementById('map' + t.id);
                    if (container) {
                        container._leaflet_id = null;
                        container.innerHTML = '';
                        initMap('map' + t.id, filtered);
                    }
                });
            }

            $('#filterZone, #filterCommune, #filterVillage, #filterAgent').on('change', applyFilters);
            $('#clearFilters').on('click', function() {
                $('#filterZone, #filterCommune, #filterVillage, #filterAgent').val('');
                applyFilters();
            });
        });
    </script>
    {{-- @endpush --}}

    {{-- <script>
        $(document).ready(function() {
            ['Menages', 'Meres', 'Enfants'].forEach(id => {
                $('#tbl' + id).DataTable({
                    paging: false,
                    searching: false,
                    info: false,
                    scrollY: 300,
                    scrollCollapse: true,
                    ordering: true,
                    responsive: true
                });
            });

            // Filtres simples (à adapter plus tard avec Ajax si besoin)
            $('#clearFilters').click(function() {
                $('#filterZone, #filterCommune, #filterVillage, #filterAgent').val('');
            });

            // Initialisation de Leaflet
            ['Menages', 'Meres', 'Enfants'].forEach(id => {
                let map = L.map('map' + id).setView([9.3, 2.3], 8);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap'
                }).addTo(map);

                // TODO : ajouter markers (avec distinction eligible / non-eligible)
            });
        });
    </script> --}}

    <style>
        /* Keep DataTables inside their cards and avoid overflow of page */
        .table-responsive {
            min-height: 150px;
        }

        .dataTables_wrapper .dataTables_paginate {
            float: right;
        }

        .card {
            overflow: visible;
        }

        .sticky-top {
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .leaflet-container {
            cursor: grab;
        }

        .leaflet-container:focus {
            outline: 2px solid #4CAF50;
        }
    </style>
@endsection
