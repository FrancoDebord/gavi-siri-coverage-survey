@extends('layouts.app')

@section('content')
<div class="container-fluid py-3">
    <h4 class="mb-3 fw-bold text-primary">Détails - Enfants</h4>

    {{-- Filtres --}}
    <div class="card mb-3 p-3 bg-light shadow-sm">
        <div class="row g-2">
            <div class="col-md-2">
                <label class="form-label">Zone Sanitaire</label>
                <select id="filterZone" class="form-select"><option value="">Toutes</option>@foreach($zones as $z)<option>{{ $z }}</option>@endforeach</select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Commune</label>
                <select id="filterCommune" class="form-select"><option value="">Toutes</option>@foreach($communes as $c)<option>{{ $c }}</option>@endforeach</select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Arrondissement</label>
                <select id="filterArr" class="form-select"><option value="">Tous</option>@foreach($arrondissements as $a)<option>{{ $a }}</option>@endforeach</select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Village</label>
                <select id="filterVillage" class="form-select"><option value="">Tous</option>@foreach($villages as $v)<option>{{ $v }}</option>@endforeach</select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Agent</label>
                <select id="filterAgent" class="form-select"><option value="">Tous</option>@foreach($agents as $ag)<option>{{ $ag }}</option>@endforeach</select>
            </div>
        </div>
        <div class="row mt-2"><div class="col-md-2"><button id="clearFilters" class="btn btn-outline-secondary w-100">Réinitialiser</button></div></div>
    </div>

    {{-- Récapitulatif --}}
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-primary text-white fw-bold">Répartition par groupe d'âge (par zone/commune/arrondissement/village)</div>
        <div class="card-body">
            <table id="tblSummary" class="table table-striped table-bordered w-100">
                <thead class="table-light">
                    <tr>
                        <th>Zone</th><th>Commune</th><th>Arrondissement</th><th>Village</th>
                        <th>Total</th><th>Passé la nuit</th><th>Éligibles</th>
                        <th>6-10m</th><th>11-29m</th><th>11-30m</th><th>30m+</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($summary as $r)
                    <tr>
                        <td>{{ $r['ZoneSanitaire'] }}</td>
                        <td>{{ $r['Commune'] }}</td>
                        <td>{{ $r['Arrondissement'] }}</td>
                        <td>{{ $r['Village'] }}</td>
                        <td class="text-end">{{ $r['TotalEnfants'] }}</td>
                        <td class="text-end">{{ $r['NuitDerniere'] }}</td>
                        <td class="text-end">{{ $r['Eligibles'] }}</td>
                        <td class="text-end">{{ $r['G_6_10'] }}</td>
                        <td class="text-end">{{ $r['G_11_29'] }}</td>
                        <td class="text-end">{{ $r['G_11_30'] }}</td>
                        <td class="text-end">{{ $r['G_30_PLUS'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Individuels --}}
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-success text-white fw-bold">Enfants individuels</div>
        <div class="card-body">
            <table id="tblIndividuals" class="table table-hover table-bordered w-100">
                <thead class="table-light">
                    <tr><th>Zone</th><th>Commune</th><th>Arrondissement</th><th>Village</th>
                        {{-- <th>ID</th> --}}
                        <th>Âge (mois)</th><th>Dormi nuit dernière</th><th>Éligible</th><th>Agent</th></tr>
                </thead>
                <tbody>
                @foreach($individuals as $r)
                    <tr class="{{ $r['Eligible'] === 'Oui' ? 'table-success' : 'table-danger' }}">
                        <td>{{ $r['ZoneSanitaire'] }}</td>
                        <td>{{ $r['Commune'] }}</td>
                        <td>{{ $r['Arrondissement'] }}</td>
                        <td>{{ $r['Village'] }}</td>
                        {{-- <td>{{ $r['ChildId'] }}</td> --}}
                        <td class="text-end">{{ $r['AgeMonths'] }}</td>
                        <td>{{ $r['SleepLastNight'] }}</td>
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
        <div class="card-header bg-info text-white fw-bold">Carte des enfants</div>
        <div class="card-body p-0">
            <div id="mapEnfants" style="height:600px;width:100%"></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function(){
    const summaryDT = $('#tblSummary').DataTable({ paging:true, pageLength:10, responsive:true, order:[[0,'asc'],[1,'asc'],[2,'asc'],[3,'asc']] });
    const indDT = $('#tblIndividuals').DataTable({ paging:true, pageLength:10, responsive:true, order:[[0,'asc'],[1,'asc'],[2,'asc'],[3,'asc']] });

    const markers = {!! json_encode($markers) !!};

    function buildMap(domId, points) {
        const container = document.getElementById(domId);
        container._leaflet_id = null;
        container.innerHTML = '';
        const map = L.map(domId, { scrollWheelZoom: true }).setView([9.5,2.5], 7);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
        const layer = L.featureGroup();
        points.forEach(p=>{
            if(p.lat && p.lng){
                const color = p.eligible ? 'green' : 'red';
                const mk = L.circleMarker([p.lat,p.lng], { color, radius:6, fillOpacity:0.8 }).bindPopup(`<b>${p.village}</b><br>ID:${p.id}<br>Éligible:${p.eligible?'Oui':'Non'}<br>Agent:${p.agent}`);
                layer.addLayer(mk);
            }
        });
        layer.addTo(map);
        if (layer.getLayers().length) map.fitBounds(layer.getBounds(), {padding:[40,40], maxZoom:14});
        return map;
    }

    let map = buildMap('mapEnfants', markers);

    function applyFilters(){
        const zone = $('#filterZone').val();
        const commune = $('#filterCommune').val();
        const arr = $('#filterArr').val();
        const village = $('#filterVillage').val();
        const agent = $('#filterAgent').val();

        summaryDT.columns().search('');
        indDT.columns().search('');

        if(zone) { summaryDT.column(0).search(zone, true, false); indDT.column(0).search(zone, true, false); }
        if(commune) { summaryDT.column(1).search(commune, true, false); indDT.column(1).search(commune, true, false); }
        if(arr) { summaryDT.column(2).search(arr, true, false); indDT.column(2).search(arr, true, false); }
        if(village) { summaryDT.column(3).search(village, true, false); indDT.column(3).search(village, true, false); }
        if(agent) { indDT.column(8).search(agent, true, false); } // agent col in individuals

        summaryDT.draw(); indDT.draw();

        const filtered = markers.filter(m=>{
            if(zone && m.ZoneSanitaire !== zone) return false;
            if(commune && m.Commune !== commune) return false;
            if(arr && m.Arrondissement !== arr) return false;
            if(village && m.village !== village) return false;
            if(agent && m.agent !== agent) return false;
            return true;
        });

        map = buildMap('mapEnfants', filtered);
    }

    $('#filterZone,#filterCommune,#filterArr,#filterVillage,#filterAgent').on('change', applyFilters);
    $('#clearFilters').on('click', function(){ $('#filterZone,#filterCommune,#filterArr,#filterVillage,#filterAgent').val(''); applyFilters(); });
});
</script>
@endpush
