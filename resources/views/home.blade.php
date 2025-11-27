@extends('layouts.app')

@section('content')
<h2 class="mb-4 fw-bold">Tableau de bord général</h2>

<div class="row g-4">
  <div class="col-md-3">
    <div class="card text-center border-0 shadow-sm">
      <div class="card-body">
        <h5 class="text-muted">Ménages</h5>
        <h2>{{ $totalMenages }}</h2>
      </div>
    </div>
  </div>

  <div class="col-md-3">
    <div class="card text-center border-0 shadow-sm">
      <div class="card-body">
        <h5 class="text-muted">Villages</h5>
        <h2>{{ $villages }}</h2>
      </div>
    </div>
  </div>

  <div class="col-md-3">
    <div class="card text-center border-0 shadow-sm">
      <div class="card-body">
        <h5 class="text-muted">Agents</h5>
        <h2>{{ $agents }}</h2>
      </div>
    </div>
  </div>

  <div class="col-md-3">
    <div class="card text-center border-0 shadow-sm">
      <div class="card-body">
        <h5 class="text-muted">Campagne</h5>
        <h2>SMC 2025</h2>
      </div>
    </div>
  </div>
</div>
@endsection
