@extends('layouts.app')
@section('page-title')
    {{ __('Booking') }}
@endsection
@section('breadcrumb')
    <ul class="breadcrumb mb-0">
        <li class="breadcrumb-item">
            <a href="{{ route('dashboard') }}">
                <h1>{{ __('Dashboard') }}</h1>
            </a>
        </li>
        <li class="breadcrumb-item active">
            <a href="#">
                {{ __('Booking') }}
            </a>
        </li>
    </ul>
@endsection
{{-- @section('card-action-btn')
    @if (Gate::check('manage vehicle'))
        <a class="btn btn-primary btn-sm ml-20" href="{{ route('booking.create') }}"> <i
                class="ti-plus mr-5"></i>
            {{__('Create Booking')}}
        </a>
    @endif
@endsection --}}
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <table class="display dataTable cell-border datatbl-advance" id="bookingTable">
                        <thead>
                            <tr>
                                <th hidden>id</th>
                                <th>{{ __('ID') }}</th>
                                <th>{{ __('Driver') }}</th>
                                <th>{{ __('Vehicle') }}</th>
                                <th>{{ __('Duration') }}</th>
                                <th data-column="status">{{ __('Status') }}</th>
                                {{-- <th data-column="payment_status">{{__('Payment Status')}}</th> --}}
                                @if (Gate::check('edit booking') || Gate::check('delete booking') || Gate::check('show booking'))
                                    <th>{{ __('Action') }}</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($bookingRequests as $bookingRequest)
                                <tr>
                                    <td hidden>{{ $bookingRequest->id }}</td>
                                    <td>{{ bookingPrefix() . $bookingRequest->id }}</td>
                                    <td>{{ $bookingRequest->guest->name . ' - ' . $bookingRequest->guest->email }}</td>
                                    <td>{{ !empty($bookingRequest->car) ? $bookingRequest->car->name : '-' }} -
                                        {{ !empty($bookingRequest->car) ? $bookingRequest->car->license_plate : '-' }}</td>
                                    <td>
                                        {{ dateFormat($bookingRequest->start_date) . ' / ' . timeFormat($bookingRequest->start_time) }}
                                        <br>
                                        {{ dateFormat($bookingRequest->end_date) . ' / ' . timeFormat($bookingRequest->end_time) }}
                                    </td>
                                    <td data-status="{{ $bookingRequest->status }}">
                                        @if ($bookingRequest->status == 'pending')
                                            <span class="badge badge-primary">{{ $bookingRequest->status }}</span>
                                        @elseif($bookingRequest->status == 'confirmed')
                                            <span class="badge badge-success">{{ $bookingRequest->status }}</span>
                                        @elseif($bookingRequest->status == 'refused')
                                            <span class="badge badge-warning">{{ $bookingRequest->status }}</span>
                                        @endif
                                    </td>
                                    {{-- <td data-payment-status="{{ $booking->payment_status }}">
                                    @if ($booking->payment_status == 'paye')
                                        <span class="badge badge-success">{{\App\Models\BookingRequest::$paymentStatus[$booking->payment_status]}}</span>
                                    @elseif($booking->payment_status=='impaye')
                                        <span class="badge badge-danger">{{\App\Models\BookingRequest::$paymentStatus[$booking->payment_status]}}</span>
                                    @elseif($booking->payment_status=='partiellement_paye')
                                        <span class="badge badge-warning">{{\App\Models\BookingRequest::$paymentStatus[$booking->payment_status]}}</span>
                                    @endif
                                </td> --}}
                                    @if (Gate::check('edit booking') || Gate::check('delete booking') || Gate::check('show booking'))
                                        <td>
                                            <div class="cart-action">
                                                @can('show booking')
                                                    <a class="text-warning" data-bs-toggle="tooltip"
                                                        data-bs-original-title="{{ __('Details') }}"
                                                        href="{{ route('booking_requests.show', \Illuminate\Support\Facades\Crypt::encrypt($bookingRequest->id)) }}">
                                                        <i data-feather="eye"></i></a>
                                                @endcan
                                                @can('edit booking')
                                                    <button type="button"
                                                        class="btn btn-link p-0 m-0 align-baseline text-success swal-approve"
                                                        data-id="{{ $bookingRequest->id }}"
                                                        data-action="{{ route('booking_requests.approve', $bookingRequest->id) }}"
                                                        data-bs-toggle="tooltip" title="{{ __('Confirm') }}">
                                                        <i data-feather="check"></i>
                                                    </button>
                                                @endcan

                                                @can('delete booking')
                                                    <button type="button"
                                                        class="btn btn-link p-0 m-0 align-baseline text-danger swal-refuse"
                                                        data-id="{{ $bookingRequest->id }}"
                                                        data-action="{{ route('booking_requests.refuse', $bookingRequest->id) }}"
                                                        data-bs-toggle="tooltip" title="{{ __('Refuse') }}">
                                                        <i data-feather="x-circle"></i>
                                                    </button>
                                                @endcan



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

@push('scripts')
    <script>
        $(document).ready(function() {
            // Destroy existing DataTable if it exists
            if ($.fn.DataTable.isDataTable('#bookingTable')) {
                $('#bookingTable').DataTable().destroy();
            }

            // Reinitialize
            $('#bookingTable').DataTable({
                columnDefs: [
                    // Your column definitions
                ],
                order: [
                    [0, 'desc']
                ]
            });
            $('.swal-approve').click(function() {
                let actionUrl = $(this).data('action');
                Swal.fire({
                    title: '{{ __('Are you sure?') }}',
                    text: '{{ __('You are about to confirm this booking.') }}',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: '{{ __('Yes, confirm it!') }}',
                    cancelButtonText: '{{ __('Cancel') }}'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.post(actionUrl, {
                            _token: '{{ csrf_token() }}'
                        }, function() {
                            location.reload();
                        });
                    }
                });
            });

            // Refuse booking
            $('.swal-refuse').click(function() {
                let actionUrl = $(this).data('action');
                Swal.fire({
                    title: '{{ __('Are you sure?') }}',
                    text: '{{ __('You are about to refuse this booking.') }}',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: '{{ __('Yes, refuse it!') }}',
                    cancelButtonText: '{{ __('Cancel') }}'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.post(actionUrl, {
                            _token: '{{ csrf_token() }}'
                        }, function() {
                            location.reload();
                        });
                    }
                });
            });
        });
    </script>
@endpush
