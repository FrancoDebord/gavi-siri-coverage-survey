@extends('layouts.app')

@section('content')
    <h2 class="fw-bold mb-4">Recrutement des Ménages</h2>
    <div class="row">
        <div class="col-md-4">
            <div class="alert alert-success d-flex align-items-center" role="alert">
                <svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Success:">
                    <use xlink:href="#check-circle-fill" />
                </svg>
                <div>
                    <strong>Total Ménages Recrutés :</strong> {{ count($data) }}
                </div>
            </div>
            <div class="alert alert-info d-flex align-items-center" role="alert">
                <svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Info:">
                    <use xlink:href="#info-fill" />
                </svg>
                <div>
                    <strong>Ménages Éligibles :</strong> {{ $eligibleCount }}
                    ({{ number_format(($eligibleCount / max(count($data), 1)) * 100, 2) }}%)
                </div>
            </div>
            <div class="alert alert-warning d-flex align-items-center" role="alert">
                <svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Warning:">
                    <use xlink:href="#exclamation-triangle-fill" />
                </svg>
                <div>
                    <strong>Ménages Non Éligibles :</strong> {{ $ineligibleCount }}
                    ({{ number_format(($ineligibleCount / max(count($data), 1)) * 100, 2) }}%)
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="table-responsive mb-5">
                <table class="table table-striped table-hover align-middle" id="summary">
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
        </div>

    </div>


    <h4 class="fw-semibold mb-3">Carte des Ménages</h4>
    <div id="map" style="height: 500px;" class="border rounded shadow-sm"></div>

    @php
        $grouped = collect($data)->groupBy('village_name');
    @endphp

