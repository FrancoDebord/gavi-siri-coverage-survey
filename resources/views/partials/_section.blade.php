<section class="mb-5">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h4>{{ $title }}</h4>
    </div>
    <div class="row">
        <div class="col-lg-6 mb-3">
            <div class="card shadow-sm">
                <div class="card-body p-2">
                    <div class="table-responsive" style="max-height:420px; overflow:auto;">
                        <table id="tbl{{ ucfirst($id) }}" class="table table-sm table-striped table-hover mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Zone sanitaire</th>
                                    <th>Village</th>
                                    <th>Total</th>
                                    <th>Éligibles</th>
                                    <th>%</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($data as $r)
                                    <tr>
                                        <td>{{ $r['ZoneSanitaire'] ?? 'N/A' }}</td>
                                        <td>{{ $r['Village'] ?? 'N/A' }}</td>
                                        <td class="text-end">{{ $r['Total'] }}</td>
                                        <td class="text-end">{{ $r['Eligibles'] }}</td>
                                        <td class="text-end">
                                            {{ $r['Total'] ? round(($r['Eligibles'] / $r['Total']) * 100, 1) : 0 }}%
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- 🌍 Map --}}
        <div class="col-lg-6 mb-3">
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div id="map{{ ucfirst($id) }}" style="height:420px;"></div>
                </div>
            </div>
        </div>
    </div>
</section>
