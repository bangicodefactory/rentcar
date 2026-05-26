@extends('layouts.app')

@section('page-title')
    {{ __('Edit TVA') }}
@endsection

@section('breadcrumb')
    <ul class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><h1>{{ __('Dashboard') }}</h1></a></li>
        <li class="breadcrumb-item"><a href="{{ route('tva.index') }}">{{ __('TVA List') }}</a></li>
        <li class="breadcrumb-item active">{{ __('Edit TVA') }}</li>
    </ul>
@endsection

@section('content')
    <form action="{{ route('tva.update', $tva->id) }}" method="POST">
    @csrf
    @method('PUT')
    <div class="row">
        {{-- Booking select --}}
        <div class="form-group col-md-6">
            <label for="booking_id_display" class="form-label">{{ __('Booking') }}</label>
            <input type="text" id="booking_id_display" class="form-control" value="{{ $tva->booking_id ?? 'N/A' }}" readonly>
            <input type="hidden" name="booking_id" value="{{ $tva->booking_id }}">
        </div>
        <div class="form-group col-md-6 col-lg-6">
            <label for="designation" class="form-label">{{ __('Vehicle') }}</label>
            <input type="text" name="designation" id="designation" class="form-control" value="{{ old('designation', $tva->designation) }}" readonly>
        </div>

        {{-- @php
        // $prefix = bookingPrefix();
        // $factureNumber = isset($tva->facture_number)
        // ? (Str::startsWith($tva->facture_number, $prefix) ? $tva->facture_number : $prefix . $tva->facture_number)
        // : '';
        $factureNumber = isset($tva->facture_number)
        @endphp  --}}
        <!-- To avoid #BOK-000#BOK-0002 duplication in case of editing -->

        <div class="form-group col-md-6 col-lg-6">
        <label for="facture_number" class="form-label">{{ __('Facture Number') }}</label>
        <input type="text" name="facture_number" id="facture_number" class="form-control" value="{{ old('facture_number', $tva->facture_number) }}" required>
        </div>

        {{-- Facture Date --}}
        <div class="form-group col-md-6 col-lg-6">
            <label for="facture_date" class="form-label">{{ __('Facture Date') }}</label>
            <input type="date" name="facture_date" id="facture_date" class="form-control" value="{{ old('facture_date', $tva->facture_date ? \Carbon\Carbon::parse($tva->facture_date)->format('Y-m-d') : '') }}" required>
        </div>
        {{-- Quantity --}}
        <div class="form-group col-md-6 col-lg-6">
            <label for="quantity" class="form-label">{{ __('Quantity') }}</label>
            <input type="number" name="quantity" id="quantity" class="form-control" step="1" value="{{ old('quantity', $tva->quantity) }}" readonly>
        </div>

        {{-- Total HT --}}
        <div class="form-group col-md-6 col-lg-6">
            <label for="total_ht" class="form-label">{{ __('Total HT') }}</label>
            <input type="number" name="total_ht" id="total_ht" class="form-control" step="0.01" value="{{ old('total_ht', $tva->total_ht) }}" readonly>
        </div>

        {{-- TVA --}}
        <div class="form-group col-md-6 col-lg-6">
            <label for="tva" class="form-label">{{ __('TVA') }}</label>
            <input type="number" name="tva" id="tva" class="form-control" step="0.01" value="{{ old('tva', $tva->tva) }}" readonly>
        </div>

        {{-- Unit Price HT --}}
        <div class="form-group col-md-6 col-lg-6">
            <label for="unit_price_ht" class="form-label">{{ __('Unit Price HT (P.U.H.T)') }}</label>
            <input type="number" name="unit_price_ht" id="unit_price_ht" class="form-control" step="0.01" value="{{ old('unit_price_ht', $tva->unit_price_ht) }}">
        </div>


        {{-- Montant TTC --}}
        <div class="form-group col-md-6 col-lg-6">
            <label for="montant_ttc" class="form-label">{{ __('Montant TTC') }}</label>
            <input type="number" name="montant_ttc" id="montant_ttc" class="form-control" step="0.01" value="{{ old('montant_ttc', $tva->montant_ttc) }}">
        </div>

       @push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const puht = document.getElementById('unit_price_ht');   // PUHT
    const qte = document.getElementById('quantity');         // QTE
    const htField = document.getElementById('total_ht');     // HT
    const tvaField = document.getElementById('tva');         // TVA
    const ttcField = document.getElementById('montant_ttc'); // TTC
    const tvaRate = 0.2; // 20%

    puht.addEventListener('input', function () {
        const puhtVal = parseFloat(puht.value);
        const qteVal = parseFloat(qte.value);

        if (isNaN(puhtVal) || isNaN(qteVal)) {
            htField.value = '';
            tvaField.value = '';
            ttcField.value = '';
            return;
        }

        const ht = +(puhtVal * qteVal).toFixed(2);          // HT = PUHT * QTE
        const tva = +(ht * tvaRate).toFixed(2);              // TVA = HT * 0.2
        const ttc = +(ht + tva).toFixed(2);                  // TTC = HT + TVA

        htField.value = ht;
        tvaField.value = tva;
        ttcField.value = ttc;
    });
});
</script>
@endpush

    </div>
    <div class="mt-4 text-end">
        <button type="submit" class="btn btn-primary">{{__('Update TVA')}}</button>
    </div>
    </form>
@endsection
