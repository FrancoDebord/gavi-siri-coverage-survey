@extends('layouts.app')

@section('content')
    <div class="container">

        <div class="">
            {{ $summary['total_errors'] }} erreurs trouvées
        </div>
        <div class="d-flex justify-content-between align-items-center">
            <h1>Erreurs de données</h1>
            <a href="{{ route('dashboard.index') }}" class="btn btn-secondary">Retour</a>
        </div>

        <div class="mt-3">
            <table id="tblErrors" class="table table-sm table-bordered table-hover">
                <thead>
                    <tr>
                        <th>Fichier</th>
                        <th>Row KEY</th>
                        <th>Champ</th>
                        <th>Problème</th>
                        <th>Valeur</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($errors as $e)
                        <tr>
                            <td>{{ $e['file'] }}</td>
                            <td>{{ $e['row_key'] }}</td>
                            <td>{{ $e['field'] }}</td>
                            <td>{{ $e['issue'] ?? '' }} {{ $e['constraint'] ?? '' }}</td>
                            <td style="max-width:200px; overflow-wrap:anywhere;">{{ $e['value'] ?? '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#tblErrors').DataTable({
                pageLength: 25,
                lengthChange: false,
                responsive: true,
                order: [
                    [0, 'asc']
                ]
            });
        });
    </script>
@endsection
