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
        <li class="breadcrumb-item">
            <a href="{{ route('booking.index') }}">
                {{ __('Booking') }}
            </a>
        </li>
        <li class="breadcrumb-item active">
            <a href="#">
                {{ bookingPrefix() . $booking->booking_id }} {{ __('Details') }}
            </a>
        </li>
    </ul>
@endsection
@section('card-action-btn')
    {{-- Use correct payment status key (paye) & show payment button only if not fully paid --}}
    @if (Gate::check('create booking payment') && $booking->payment_status != 'paye')
        <a class="btn btn-warning btn-sm ml-5 customModal" href="#" data-size="md"
            data-url="{{ route('booking.payment.create', $booking->id) }}" data-title="{{ __('Create Payment') }}"> <i
                class="ti-credit-card mr-5"></i>
            {{ __('Payment') }}
        </a>
    @endif
    @if (Gate::check('edit booking'))
        <a class="btn btn-primary btn-sm ml-5"
            href="{{ route('booking.edit', \Illuminate\Support\Facades\Crypt::encrypt($booking->id)) }}"> <i
                class="ti-pencil mr-5"></i>
            {{ __('Edit') }}
        </a>
    @endif
    <a class="btn btn-secondary print ml-5" href="javascript:void(0);"><i class="fa fa-print"></i> {{ __('Print') }}</a>
@endsection
@section('content')
    <div id="invoice-print">
        <div class="row">
            <div class="col-lg-12 col-md-12">
                <div class="card">
                    <div class="card-body cdx-invoice">
                        <div id="cdx-invoice">
                            <div class="head-invoice">
                                <div class="codex-brand" style="top: 0; left: 0; max-width: 130px; max-height: 130px;">
                                    <a class="codexbrand-logo" href="Javascript:void(0);">
                                        <img class="img-fluid"
                                            src="{{ asset('storage/upload/logo/' . ($settings['company_logo'] ?? 'logo.png')) }}"
                                            alt="invoice-logo">
                                    </a>
                                    {{-- <a class="codexdark-logo" href="Javascript:void(0);">
                                        <img class="img-fluid"
                                            src="{{ asset(Storage::url('upload/logo/')) . '/' . (isset($settings['company_logo']) && !empty($settings['company_logo']) ? $settings['company_logo'] : 'logo.png') }}"
                                            alt="invoice-logo">
                                    </a> --}}
                                </div>
                                <ul class="contact-list">
                                    <li>
                                        <div class="icon-wrap"><i class="fa fa-user"></i></div>
                                        {{ $settings['company_name'] }}
                                    </li>
                                    <li>
                                        <div class="icon-wrap"><i class="fa fa-phone"></i></div>
                                        {{ $settings['company_phone'] }}
                                    </li>
                                    <li>
                                        <div class="icon-wrap"><i class="fa fa-envelope"></i></div>
                                        {{ $settings['company_email'] }}
                                    </li>


                                </ul>
                                <ul class="contact-list">
                                    {{-- Add IF, RC, Patente, ICE --}}
                                    <li>
                                        <div class="icon-wrap"><i class="fa fa-info-circle"></i></div>IF:
                                        {{ $settings['if'] ?? ' ' }}
                                    </li>
                                    <li>
                                        <div class="icon-wrap"><i class="fa fa-info-circle"></i></div>RC:
                                        {{ $settings['rc'] ?? ' ' }}
                                    </li>
                                    <li>
                                        <div class="icon-wrap"><i class="fa fa-info-circle"></i></div>Patente:
                                        {{ $settings['patente'] ?? ' ' }}
                                    </li>
                                    <li>
                                        <div class="icon-wrap"><i class="fa fa-info-circle"></i></div>ICE:
                                        {{ $settings['ice'] ?? ' ' }}
                                    </li>
                                </ul>
                            </div>
                            <div class="invoice-user">
                                <div class="left-user">
                                    <h5>{{ __('Receipt To') }}:</h5>
                                    <ul class="detail-list">
                                        <li>
                                            <div class="icon-wrap"><i class="fa fa-user"></i></div>
                                            {{ !empty($booking->drivers) ? $booking->drivers->name : '' }}
                                        </li>
                                        <li>
                                            <div class="icon-wrap"><i class="fa fa-phone"></i></div>
                                            {{ !empty($booking->drivers) ? $booking->drivers->phone_number : '' }}
                                        </li>
                                        <li>
                                            <div class="icon-wrap"><i class="fa fa-envelope"></i></div>
                                            {{ !empty($booking->drivers) ? $booking->drivers->email : '' }}
                                        </li>
                                        <li>
                                            <div class="icon-wrap"><i class="fa fa-industry"></i></div>
                                            ICE:{{ optional(\App\Models\Driver::where('user_id', $booking->drivers->id)->first())->ICE_company }}
                                        </li>

                                    </ul>
                                </div>
                                <div class="right-user">
                                    <ul class="detail-list">
                                        <li>{{ __('Booking Date') }}: <span> {{ dateFormat($booking->created_at) }}</span>
                                        </li>
                                        <li>{{ __('Booking ID') }}:
                                            <span>{{ bookingPrefix() . $booking->booking_id }}</span>
                                        </li>
                                        <li>{{ __('Start Date') }}:
                                            <span>{{ dateFormat($booking->start_date) }} -
                                                {{ timeFormat($booking->start_time) }}</span>
                                        </li>
                                        <li>{{ __('End Date') }}:
                                            <span>
                                                {{ dateFormat($booking->end_date) }} -
                                                {{ timeFormat($booking->end_time) }}
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div class="body-invoice">
                                <div class="table-responsive1">
                                    <table class="table ml-1">
                                        <thead>
                                            <tr>
                                                <th>{{ __('Vehicle') }}</th>
                                                <th>{{ !empty($booking->vehicleDetails()) ? $booking->vehicleDetails()->name : '-' }}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php
                                                // Normalize details to an object to avoid property access errors
                                                $detailsRaw = $booking->details;
                                                if (is_string($detailsRaw)) {
                                                    $decoded = json_decode($detailsRaw);
                                                    $detailsRaw = $decoded !== null ? $decoded : (object) [];
                                                }
                                                if (is_array($detailsRaw)) {
                                                    $detailsRaw = (object) $detailsRaw; // cast array to object
                                                }
                                                if (empty($detailsRaw)) {
                                                    $detailsRaw = (object) [];
                                                }
                                                $details = $detailsRaw;
                                            @endphp
                                            <tr>
                                                <td>{{ __('Duration') }}</td>
                                                <td>
                                                    @php
                                                        $parts = [];
                                                        if (
                                                            property_exists($details, 'totalDays') &&
                                                            $details->totalDays > 0
                                                        ) {
                                                            $parts[] = $details->totalDays . ' ' . __('Days');
                                                        }
                                                        if (
                                                            property_exists($details, 'totalHours') &&
                                                            $details->totalHours > 0
                                                        ) {
                                                            $parts[] = $details->totalHours . ' ' . __('Hours');
                                                        }
                                                        if (
                                                            property_exists($details, 'totalMinuts') &&
                                                            $details->totalMinuts > 0
                                                        ) {
                                                            $parts[] = $details->totalMinuts . ' ' . __('Minuts');
                                                        }
                                                    @endphp
                                                    {{ implode(', ', $parts) }}
                                                </td>
                                            </tr>
                                            @if (!empty($booking->addon))
                                                @foreach ($booking->addons() as $addon)
                                                    <tr>
                                                        <td>{{ $addon->name }}</td>
                                                        <td>
                                                            {{ priceFormat($addon->price) }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            @endif
                                            <tr>
                                                <td>{{ __('Pickup Address') }}</td>
                                                <td>
                                                    @if ($booking->pickupAddress)
                                                        {{ $booking->pickupAddress->name }}
                                                        @if (isset($booking->pickupAddress->price))
                                                            ({{ priceFormat($booking->pickupAddress->price) }})
                                                        @endif
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>{{ __('Drop Off Address') }}</td>
                                                <td>
                                                    @if ($booking->dropOffAddress)
                                                        {{ $booking->dropOffAddress->name }}
                                                        @if (isset($booking->dropOffAddress->price))
                                                            ({{ priceFormat($booking->dropOffAddress->price) }})
                                                        @endif
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                            </tr>
                                            {{-- <tr>
                                                <td>{{ __('Status') }}</td>
                                                <td>
                                                    @if ($booking->status == 'yet_to_start')
                                                        <span
                                                            class="badge badge-primary">{{ \App\Models\Booking::$status[$booking->status] }}</span>
                                                    @elseif($booking->status == 'completed')
                                                        <span
                                                            class="badge badge-success">{{ \App\Models\Booking::$status[$booking->status] }}</span>
                                                    @elseif($booking->status == 'on_going')
                                                        <span
                                                            class="badge badge-warning">{{ \App\Models\Booking::$status[$booking->status] }}</span>
                                                    @elseif($booking->status == 'cancelled')
                                                        <span
                                                            class="badge badge-danger">{{ \App\Models\Booking::$status[$booking->status] }}</span>
                                                    @endif
                                                </td>
                                            </tr> --}}
                                            <tr>
                                                <td>{{ __('Payment Status') }}</td>
                                                <td>
                                                    @if ($booking->payment_status == 'paye')
                                                        <span
                                                            class="badge badge-success">{{ \App\Models\Booking::$paymentStatus[$booking->payment_status] }}</span>
                                                    @elseif($booking->payment_status == 'impaye')
                                                        <span
                                                            class="badge badge-danger">{{ \App\Models\Booking::$paymentStatus[$booking->payment_status] }}</span>
                                                    @elseif($booking->payment_status == 'partiellement_paye')
                                                        <span
                                                            class="badge badge-warning">{{ \App\Models\Booking::$paymentStatus[$booking->payment_status] }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                            {{-- <tr>
                                                <td>{{ __('Notes') }}</td>
                                                <td>{{ $booking->notes }} </td>
                                            </tr> --}}

                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div style=" width: 40%; margin-left:auto;">
                                {{-- <div class="footer-invoice"> --}}

                                <table class="table table-bordered">
                                    <tbody>
                                        <tr>
                                            <td>{{ __('Total Amount') }}</td>
                                            <td>{{ priceFormat($booking->getTotalAmount() * 0.8) }}</td>
                                        </tr>
                                        <tr>
                                            <td>TVA:</td>
                                            <td>{{ priceFormat($booking->getTotalAmount() * 0.2) }}</td>
                                        </tr>
                                        <tr>
                                            <td>{{ __('Rest') }}</td>
                                            <td>{{ priceFormat($booking->getTotalDueAmount()) }}</td>
                                        </tr>
                                        <tr>
                                            <td>{{ __('Due Amount') }}</td>
                                            <td>{{ priceFormat($booking->getTotalAmount()) }}</td>
                                        </tr>

                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('Payment History') }}</h5>
                    </div>
                    <div class="card-body">
                        <table class="display dataTable cell-border datatbl-advance1">
                            <thead>
                                <tr>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Payment Method') }}</th>
                                    <th>{{ __('Notes') }}</th>
                                    <th>{{ __('Amount') }}</th>
                                    @can('delete booking payment')
                                        <th class="text-right action">{{ __('Action') }}</th>
                                    @endcan
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($booking->payments as $payment)
                                    <tr role="row">
                                        <td>{{ dateFormat($payment->date) }} </td>
                                        <td>{{ $payment->payment_method }} </td>
                                        <td>{{ $payment->notes }} </td>
                                        <td>{{ priceFormat($payment->amount) }} </td>
                                        @can('delete booking payment')
                                            <td class="text-right action">
                                                <div class="cart-action">
                                                    <form action="{{ route('booking.payment.destroy', [$booking->id, $payment->id]) }}" method="POST">
                                                    @csrf
                                                    @method('DELETE')
                                                    <a class=" text-danger confirm_dialog" data-bs-toggle="tooltip"
                                                        data-bs-original-title="{{ __('Detete') }}" href="#"> <i
                                                            data-feather="trash-2"></i></a>
                                                    </form>
                                                </div>
                                            </td>
                                        @endcan
                                    </tr>
                                @endforeach
                            </tbody>

                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('script-page')
    <script>
        $(document).on('click', '.print', function() {
            $('.action').addClass('d-none');
            var printContents = document.getElementById('invoice-print').innerHTML;
            var originalContents = document.body.innerHTML;

            document.body.innerHTML = printContents;

            window.print();

            document.body.innerHTML = originalContents;
            $('.action').removeClass('d-none');
        });
    </script>
@endpush
