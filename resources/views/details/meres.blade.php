@extends('layouts.app')

@section('content')
<div class="container-fluid py-3">
    <h4 class="mb-3 fw-bold text-primary">Détails - Mères</h4>

    {{-- Filtres --}}
    <div class="card mb-3 p-3 bg-light shadow-sm">
        <div class="row g-2">
            <div class="col-md-2">
                <label class="form-label">Zone Sanitaire</label>
                <select id="filterZone" class="form-select">
                    <option value="">Toutes</option>
                    @foreach($zones as $z)
                        <option value="{{ $z }}">{{ $z }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Commune</label>
                <select id="filterCommune" class="form-select">
                    <option value="">Toutes</option>
                    @foreach($communes as $c)
                        <option value="{{ $c }}">{{ $c }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Arrondissement</label>
                <select id="filterArr" class="form-select">
                    <option value="">Tous</option>
                    @foreach($arrondissements as $a)
                        <option value="{{ $a }}">{{ $a }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Village</label>
                <select id="filterVillage" class="form-select">
                    <option value="">Tous</option>
                    @foreach($villages as $v)
                        <option value="{{ $v }}">{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Agent</label>
                <select id="filterAgent" class="form-select">
                    <option value="">Tous</option>
                    @foreach($agents as $ag)
                        <option value="{{ $ag }}">{{ $ag }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="row mt-2">
            <div class="col-md-2">
                <button id="clearFilters" class="btn btn-outline-secondary w-100">Réinitialiser</button>
            </div>
        </div>
    </div>

    {{-- Résumé --}}
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-primary text-white fw-bold">Résumé par Zone / Commune / Arrondissement / Village</div>
        <div class="card-body">
            <table id="tblSummary" class="table table-striped table-bordered w-100">
                <thead class="table-light">
                    <tr>
                        <th>Zone</th><th>Commune</th><th>Arrondissement</th><th>Village</th>
                        <th>Total mères</th><th>Éligibles</th><th>Taux (%)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($summary as $r)
                        <tr>
                            <td>{{ $r['ZoneSanitaire'] }}</td>
                            <td>{{ $r['Commune'] }}</td>
                            <td>{{ $r['Arrondissement'] }}</td>
                            <td>{{ $r['Village'] }}</td>
                            <td class="text-end">{{ $r['TotalMeres'] }}</td>
                            <td class="text-end">{{ $r['Eligibles'] }}</td>
                            <td class="text-end">{{ $r['Taux'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Individuels --}}
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-success text-white fw-bold">Mères individuelles</div>
        <div class="card-body">
            <table id="tblIndividuals" class="table table-hover table-bordered w-100">
                <thead class="table-light">
                    <tr>
                        <th>Zone</th><th>Commune</th><th>Arrondissement</th><th>Village</th>
                        <th>Nom</th><th>Âge</th><th>Éligible</th><th>Agent</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($individuals as $r)
                        <tr class="{{ $r['Eligible'] === 'Oui' ? 'table-success' : 'table-danger' }}">
                            <td>{{ $r['ZoneSanitaire'] }}</td>
                            <td>{{ $r['Commune'] }}</td>
                            <td>{{ $r['Arrondissement'] }}</td>
                            <td>{{ $r['Village'] }}</td>
                            <td>{{ $r['Nom'] }}</td>
                            <td>{{ $r['Age'] }}</td>
                            <td>{{ $r['Eligible'] }}</td>
                            <td>{{ $r['Agent'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Carte --}}
    <div class="card shadow-sm">
        <div class="card-header bg-info text-white fw-bold">Carte des mères</div>
        <div class="card-body p-0">
            <div id="mapMeres" style="height:600px;width:100%"></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function(){
    // DataTables init
    const summaryDT = $('#tblSummary').DataTable({
        paging: true, pageLength: 10, lengthChange: false, info: false, responsive: true,
        order: [[0,'asc'],[1,'asc'],[2,'asc'],[3,'asc']]
    });
    const indDT = $('#tblIndividuals').DataTable({
        paging: true, pageLength: 10, lengthChange: false, info: false, responsive: true,
        order: [[0,'asc'],[1,'asc'],[2,'asc'],[3,'asc']]
    });

    // markers (from backend)
    const markers = {!! json_encode($markers) !!};

    // Build map (recreated on filter to avoid stale state)
    function buildMap(domId, points) {
        const container = document.getElementById(domId);
        container._leaflet_id = null;
        container.innerHTML = '';

        const map = L.map(domId, { scrollWheelZoom: true }).setView([9.5,2.5], 7);
        const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
        const topo = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png');
        const sat = L.tileLayer('https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png');
        L.control.layers({ 'OSM': osm, 'Topo': topo, 'Satellite-like': sat }).addTo(map);

        const group = L.featureGroup();
        points.forEach(p => {
            if(p.lat && p.lng) {
                const color = p.eligible ? 'green' : 'red';
                const mk = L.circleMarker([p.lat, p.lng], { color, radius:6, fillOpacity:0.85 })
                    .bindPopup(`<b>${p.name ?? 'Mère'}</b><br>${p.village}<br>Agent: ${p.agent ?? '—'}<br>Éligible: ${p.eligible ? 'Oui' : 'Non'}`);
                group.addLayer(mk);
            }
        });

        group.addTo(map);
        if (group.getLayers().length) map.fitBounds(group.getBounds(), { padding:[40,40], maxZoom: 13 });

        // legend
        const legend = L.control({ position: 'bottomright' });
        legend.onAdd = function(){
            const div = L.DomUtil.create('div', 'info legend p-2 bg-white rounded shadow-sm');
            div.innerHTML = '<i style="background:green;width:12px;height:12px;display:inline-block;border-radius:6px;margin-right:6px"></i> Éligible<br>' +
                            '<i style="background:red;width:12px;height:12px;display:inline-block;border-radius:6px;margin-right:6px"></i> Non éligible';
            return div;
        };
        legend.addTo(map);

        return map;
    }

    // initial map
    let map = buildMap('mapMeres', markers);

    // apply filters function
    function applyFilters() {
        const zone = $('#filterZone').val();
        const commune = $('#filterCommune').val();
        const arr = $('#filterArr').val();
        const village = $('#filterVillage').val();
        const agent = $('#filterAgent').val();

        // reset search
        summaryDT.columns().search('');
        indDT.columns().search('');

        if (zone) { summaryDT.column(0).search(zone, true, false); indDT.column(0).search(zone, true, false); }
        if (commune) { summaryDT.column(1).search(commune, true, false); indDT.column(1).search(commune, true, false); }
        if (arr) { summaryDT.column(2).search(arr, true, false); indDT.column(2).search(arr, true, false); }
        if (village) { summaryDT.column(3).search(village, true, false); indDT.column(3).search(village, true, false); }
        if (agent) { indDT.column(7).search(agent, true, false); } // agent column in individuals is index 7

        summaryDT.draw();
        indDT.draw();

        // filter markers for map
        const filtered = markers.filter(m => {
            if (zone && m.ZoneSanitaire !== zone) return false;
            if (commune && m.Commune !== commune) return false;
            if (arr && m.Arrondissement !== arr) return false;
            if (village && m.village !== village) return false;
            if (agent && m.agent !== agent) return false;
            return true;
        });

        map = buildMap('mapMeres', filtered);
    }

    $('#filterZone,#filterCommune,#filterArr,#filterVillage,#filterAgent').on('change', applyFilters);
    $('#clearFilters').on('click', function(){
        $('#filterZone,#filterCommune,#filterArr,#filterVillage,#filterAgent').val('');
        applyFilters();
    });
});
</script>
@endpush
