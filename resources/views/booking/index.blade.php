@extends('layouts.app')
@section('page-title')
    {{__('Booking')}}
@endsection
@section('breadcrumb')
    <ul class="breadcrumb mb-0">
        <li class="breadcrumb-item">
            <a href="{{route('dashboard')}}"><h1>{{__('Dashboard')}}</h1></a>
        </li>
        <li class="breadcrumb-item active">
            <a href="#">
                {{__('Booking')}}
            </a>
        </li>
    </ul>
@endsection
@section('card-action-btn')
    @if(Gate::check('manage vehicle'))
        <a class="btn btn-primary btn-sm ml-20" href="{{ route('booking.create') }}"> <i
                class="ti-plus mr-5"></i>
            {{__('Create Booking')}}
        </a>
    @endif
    @can('create booking')
        <a class="btn btn-success btn-sm ml-10" href="{{ route('booking.template') }}">
            <i class="ti-download mr-5"></i>{{__('Template')}}
        </a>
        <button class="btn btn-warning btn-sm ml-10" data-bs-toggle="modal" data-bs-target="#importBookingModal">
            <i class="ti-upload mr-5"></i>{{__('Import Excel')}}
        </button>
    @endcan
    @can('delete booking')
        <button class="btn btn-danger btn-sm ml-10" id="bulkDeleteBtn" style="display:none;" onclick="confirmBulkDelete()">
            <i class="ti-trash mr-5"></i>{{__('Delete Selected')}}
        </button>
    @endcan
@endsection
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <table class="display dataTable cell-border datatbl-advance" id="bookingTable">
                        <thead>
                        <tr>
                            <th hidden>id</th>
                            @can('delete booking')
                            <th style="width:30px;"><input type="checkbox" id="selectAll" title="{{__('Select All')}}"></th>
                            @endcan
                            <th>{{__('ID')}}</th>
                            <th>{{__('Driver')}}</th>
                            <th>{{__('Vehicle')}}</th>
                            <th>{{__('Duration')}}</th>
                            <th data-column="status">{{__('Status')}}</th>
                            <th data-column="payment_status">{{__('Payment Status')}}</th>
                            @if(Gate::check('edit booking') || Gate::check('delete booking') || Gate::check('show booking'))
                                <th>{{__('Action')}}</th>
                            @endif
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($bookings as $booking)
                            <tr>
                                <td hidden>{{ $booking->id }}</td>
                                @can('delete booking')
                                <td><input type="checkbox" class="booking-checkbox" value="{{ $booking->id }}"></td>
                                @endcan
                                <td>{{ bookingPrefix().$booking->booking_id }}</td>
                                <td>{{ !empty($booking->drivers)?$booking->drivers->name:'-' }}</td>
                                <td>{{ !empty($booking->vehicleDetails())?$booking->vehicleDetails()->name:'-' }} - {{ !empty($booking->vehicleDetails())?$booking->vehicleDetails()->license_plate:'-' }}</td>
                                <td>
                                    {{ dateFormat($booking->start_date) .' / '. timeFormat($booking->start_time)}} <br>
                                    {{ dateFormat($booking->end_date) .' / '. timeFormat($booking->end_time)}}
                                </td>
                                <td data-status="{{ $booking->status }}">
                                    @if($booking->status=='yet_to_start')
                                        <span class="badge badge-primary">{{\App\Models\Booking::$status[$booking->status]}}</span>
                                    @elseif($booking->status=='completed')
                                        <span class="badge badge-success">{{\App\Models\Booking::$status[$booking->status]}}</span>
                                    @elseif($booking->status=='on_going')
                                        <span class="badge badge-warning">{{\App\Models\Booking::$status[$booking->status]}}</span>
                                    @elseif($booking->status=='cancelled')
                                        <span class="badge badge-danger">{{\App\Models\Booking::$status[$booking->status]}}</span>
                                    @endif
                                </td>
                                <td data-payment-status="{{ $booking->payment_status }}">
                                    @if($booking->payment_status=='paye')
                                        <span class="badge badge-success">{{\App\Models\Booking::$paymentStatus[$booking->payment_status]}}</span>
                                    @elseif($booking->payment_status=='impaye')
                                        <span class="badge badge-danger">{{\App\Models\Booking::$paymentStatus[$booking->payment_status]}}</span>
                                    @elseif($booking->payment_status=='partiellement_paye')
                                        <span class="badge badge-warning">{{\App\Models\Booking::$paymentStatus[$booking->payment_status]}}</span>
                                    @endif
                                </td>
                                @if(Gate::check('edit booking') || Gate::check('delete booking') || Gate::check('show booking'))
                                    <td>
                                        <div class="cart-action">
                                            <form action="{{ route('booking.destroy', $booking->id) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            @can('show booking')
                                                <a class="text-warning"
                                                   data-bs-toggle="tooltip"
                                                   data-bs-original-title="{{__('Details')}}"
                                                   href="{{ route('booking.show',\Illuminate\Support\Facades\Crypt::encrypt($booking->id)) }}"
                                                > <i data-feather="eye"></i></a>
                                            @endcan
                                            @can('edit booking')
                                                <a class="text-success" data-bs-toggle="tooltip"
                                                   data-bs-original-title="{{__('Edit')}}"
                                                   href="{{ route('booking.edit',\Illuminate\Support\Facades\Crypt::encrypt($booking->id)) }}">
                                                    <i data-feather="edit"></i></a>
                                            @endcan
                                            @can('delete booking')
                                                <a class="text-danger confirm_dialog" data-bs-toggle="tooltip"
                                                   data-bs-original-title="{{__('Delete')}}" href="#"> 
                                                    <i data-feather="trash-2"></i>
                                                </a>
                                            @endcan
                                            </form>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

{{-- Import Excel Modal --}}
@can('delete booking')
<form id="bulkDeleteForm" action="{{ route('booking.bulk-destroy') }}" method="POST" style="display:none;">
    @csrf
    <div id="bulkDeleteInputs"></div>
</form>
@endcan

@can('create booking')
<div class="modal fade" id="importBookingModal" tabindex="-1" aria-labelledby="importBookingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importBookingModalLabel">{{ __('Import Bookings from Excel') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <form action="{{ route('booking.import') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="modal-body">

                {{-- Error table shown when rows were skipped --}}
                @if(session('import_skipped'))
                <div class="alert alert-warning p-2 mb-3">
                    <strong>{{ count(session('import_skipped')) }} ligne(s) non importée(s) :</strong>
                    <div class="table-responsive mt-2">
                        <table class="table table-sm table-bordered mb-0" style="font-size:0.8em;">
                            <thead class="table-danger">
                                <tr>
                                    <th>#Ligne</th>
                                    <th>NOM & PRENOM</th>
                                    <th>IMMATRICULATION</th>
                                    <th>DATE DEBUT</th>
                                    <th>DATE RETOUR</th>
                                    <th>Erreur(s)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach(session('import_skipped') as $s)
                                <tr>
                                    <td>{{ $s['row'] }}</td>
                                    <td>{{ $s['nom'] }}</td>
                                    <td>{{ $s['plaque'] }}</td>
                                    <td>{{ $s['debut'] }}</td>
                                    <td>{{ $s['retour'] }}</td>
                                    <td class="text-danger">{{ implode(' | ', $s['errors']) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif

                <p class="text-muted small mb-3">
                    {{ __('Upload an .xlsx or .xls file. Download the') }}
                    <a href="{{ route('booking.template') }}" target="_blank">{{ __('template') }}</a>
                    {{ __('to see the required format.') }}
                </p>
                <div class="mb-3">
                    <label for="importFile" class="form-label fw-semibold">{{ __('Excel File') }}</label>
                    <input type="file" class="form-control" id="importFile" name="file" accept=".xlsx,.xls,.csv" required>
                </div>
                <div class="alert alert-info small mb-0">
                    <strong>Format attendu (10 colonnes) :</strong>
                    <code class="d-block mt-1 mb-1" style="font-size:0.78em;">
                        NOM &amp; PRENOM | DATE DEBUT | HEURE | LA MARQUE | IMMATRICULATION | DATE RETOUR | HEURE RETOUR | PERIODE | PRIX | METHOD
                    </code>
                    <ul class="mb-0 ps-3 mt-1">
                        <li>Dates au format <strong>JJ/MM/AAAA</strong> (ex: 01/02/2026).</li>
                        <li>Le PRIX peut être laissé vide.</li>
                        <li>La METHOD peut être laissée vide (ex: espèce, virement, chèque, carte).</li>
                        <li>Conducteurs et véhicules inexistants sont créés automatiquement.</li>
                        <li>Le statut est automatiquement défini à <em>À démarrer</em>.</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-warning btn-sm">
                    <i class="ti-upload mr-5"></i>{{ __('Import') }}
                </button>
            </div>
            </form>
        </div>
    </div>
</div>
@endcan

@push('scripts')
<script>
$(document).ready(function() {
    @if(session('reopen_import_modal'))
    var importModal = new bootstrap.Modal(document.getElementById('importBookingModal'));
    importModal.show();
    @endif

    // Destroy existing DataTable if it exists
    if ($.fn.DataTable.isDataTable('#bookingTable')) {
        $('#bookingTable').DataTable().destroy();
    }

    // Reinitialize
    $('#bookingTable').DataTable({
        columnDefs: [
            // Your column definitions
        ],
        order: [[0, 'desc']]
    });

    // Select all checkbox
    $('#selectAll').on('change', function() {
        $('.booking-checkbox').prop('checked', this.checked);
        updateBulkDeleteBtn();
    });

    // Individual checkbox change
    $(document).on('change', '.booking-checkbox', function() {
        var total = $('.booking-checkbox').length;
        var checked = $('.booking-checkbox:checked').length;
        $('#selectAll').prop('indeterminate', checked > 0 && checked < total);
        $('#selectAll').prop('checked', checked === total);
        updateBulkDeleteBtn();
    });

    function updateBulkDeleteBtn() {
        var checked = $('.booking-checkbox:checked').length;
        if (checked > 0) {
            $('#bulkDeleteBtn').show().text('{{ __('Delete Selected') }} (' + checked + ')');
        } else {
            $('#bulkDeleteBtn').hide();
        }
    }
});

function confirmBulkDelete() {
    var ids = $('.booking-checkbox:checked').map(function() { return $(this).val(); }).get();
    if (ids.length === 0) return;

    if (!confirm('{{ __('Are you sure you want to delete the') }} ' + ids.length + ' {{ __('selected booking(s)?') }}')) return;

    var container = $('#bulkDeleteInputs');
    container.empty();
    $.each(ids, function(i, id) {
        container.append('<input type="hidden" name="ids[]" value="' + id + '">');
    });
    $('#bulkDeleteForm').submit();
}
</script>
@endpush