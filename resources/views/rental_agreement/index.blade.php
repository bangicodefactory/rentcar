@extends('layouts.app')
@section('page-title')
    {{__('Rental Agreement')}}
@endsection
@section('breadcrumb')
    <ul class="breadcrumb mb-0">
        <li class="breadcrumb-item">
            <a href="{{route('dashboard')}}"><h1>{{__('Dashboard')}}</h1></a>
        </li>
        <li class="breadcrumb-item active">
            <a href="#">
                {{__('Rental Agreement')}}
            </a>
        </li>
    </ul>
@endsection
@section('card-action-btn')
    @if(Gate::check('manage rental agreement'))
        <a class="btn btn-primary btn-sm ml-20 customModal" href="#" data-size="lg"
           data-url="{{ route('rental-agreement.create') }}"
           data-title="{{__('Create Rental Agreement')}}"> <i
                class="ti-plus mr-5"></i>
            {{__('Create Agreement')}}
        </a>
    @endif
@endsection
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <table class="display dataTable cell-border datatbl-advance" id="rentalTable_agreement">
                        <thead>
                        <tr>
                            <th hidden>id</th>
                            <th>{{__('ID')}}</th>
                            <th>{{__('Driver')}}</th>
                            <th>{{__('Vehicle')}}</th>
                            <th>{{__('Date')}}</th>
                            <th>{{__('Rental Start Date')}}</th>
                            <th>{{__('Rental End Date')}}</th>
                            <th>{{__('Rental Duration')}}</th>
                            <th>{{__('Status')}}</th>
                            @if(Gate::check('edit rental agreement') || Gate::check('delete rental agreement') || Gate::check('show rental agreement'))
                                <th>{{__('Action')}}</th>
                            @endif
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($agreements as $agreement)
                            <tr>
                                <td hidden>{{ $agreement->id }}</td>
                                <td>{{ rentalAgreementPrefix().$agreement->agreement_id }} </td>
                                <td>{{!empty($agreement->drivers)?$agreement->drivers->name:'-'}}</td>
                                <td>{{!empty($agreement->vehicles)?$agreement->vehicles->name.' - '.$agreement->vehicles->license_plate:'-'}}</td>
                                <td>{{ dateFormat($agreement->date) }} </td>
                                <td>{{ dateFormatHourMinute($agreement->rental_start_date) }} </td>
                                <td>{{ dateFormatHourMinute($agreement->rental_end_date) }} </td>
                                <td>{{ $agreement->rental_duration.' '.__('Days') }} </td>
                                <td>
                                    @if($agreement->status=='draft')
                                        <span class="badge badge-info">{{\App\Models\RentalAgreement::$status[$agreement->status]}}</span>
                                    @elseif($agreement->status=='pending')
                                        <span class="badge badge-warning">{{\App\Models\RentalAgreement::$status[$agreement->status]}}</span>
                                    @elseif($agreement->status=='confirmed' || $agreement->status=='active')
                                        <span class="badge badge-success">{{\App\Models\RentalAgreement::$status[$agreement->status]}}</span>
                                    @else
                                        <span class="badge badge-danger">{{\App\Models\RentalAgreement::$status[$agreement->status]}}</span>
                                    @endif
                                </td>
                                @if(Gate::check('edit rental agreement') || Gate::check('delete rental agreement') || Gate::check('show rental agreement'))
                                    <td>
                                        <div class="cart-action">
                                            <form action="{{ route('rental-agreement.destroy', $agreement->id) }}" method="POST">
                            @csrf
                            @method('DELETE')
                                            @can('show rental agreement')
                                                <a class="text-warning"
                                                   data-bs-toggle="tooltip"
                                                   data-bs-original-title="{{__('Details')}}" href="{{ route('rental-agreement.show',\Illuminate\Support\Facades\Crypt::encrypt($agreement->id)) }}"
                                                  > <i data-feather="eye"></i></a>
                                            @endcan
                                            @can('edit rental agreement')
                                                <a class="text-success customModal" data-bs-toggle="tooltip"
                                                   data-bs-original-title="{{__('Edit')}}" href="#" data-size="lg"
                                                   data-url="{{ route('rental-agreement.edit',$agreement->id) }}"
                                                   data-title="{{__('Edit Rental Agreement')}}"> <i data-feather="edit"></i></a>
                                            @endcan
                                            @can('delete rental agreement')
                                                <a class=" text-danger confirm_dialog" data-bs-toggle="tooltip"
                                                   data-bs-original-title="{{__('Detete')}}" href="#"> <i
                                                        data-feather="trash-2"></i></a>
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
@push('scripts')
<script>
$(document).ready(function() {
    // Destroy existing DataTable if it exists
    if ($.fn.DataTable.isDataTable('#rentalTable_agreement')) {
        $('#rentalTable_agreement').DataTable().destroy();
    }
    
    // Reinitialize
    $('#rentalTable_agreement').DataTable({
        columnDefs: [
            // Your column definitions
        ],
        order: [[0, 'desc']]
    });
     $('#customModal').on('shown.bs.modal', function () {
    // Destroy and re-initialize to avoid double init
    $('.basic-select').each(function() {
        if ($(this).hasClass("select2-hidden-accessible")) {
            $(this).select2('destroy');
        }
        $(this).select2({
            dropdownParent: $('#customModal')
        });
    });
});
});
</script>
@endpush