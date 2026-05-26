@extends('layouts.app')
@section('page-title')
    {{__('Vehicle')}}
@endsection
@section('breadcrumb')
    <ul class="breadcrumb mb-0">
        <li class="breadcrumb-item">
            <a href="{{route('dashboard')}}"><h1>{{__('Dashboard')}}</h1></a>
        </li>
        <li class="breadcrumb-item active">
            <a href="#">
                {{__('Vehicle')}}
            </a>
        </li>
    </ul>
@endsection
@section('card-action-btn')
    @if(Gate::check('manage vehicle'))
        <a class="btn btn-primary btn-sm ml-20 customModal" href="#" data-size="lg"
           data-url="{{ route('vehicle.create') }}"
           data-title="{{__('Create Vehicle')}}"> <i
                class="ti-plus mr-5"></i>
            {{__('Create Vehicle')}}
        </a>
    @endif
@endsection
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <table class="display dataTable cell-border datatbl-advance" id="vehicleTable">
                        <thead>
                        <tr>
                            <th hidden>id</th>
                            <th>{{__('ID')}}</th>
                            <th>{{__('Name')}}</th>
                            <th>{{__('Type')}}</th>
                            <th>{{__('Model')}}</th>
                            <th>{{__('License Plate')}}</th>
                            <th>{{__('Registration Expiration Date')}}</th>
                            <th>{{__('Engine Type')}}</th>
                            @if(Gate::check('edit vehicle') || Gate::check('delete vehicle') || Gate::check('show vehicle'))
                                <th>{{__('Action')}}</th>
                            @endif
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($vehicles as $vehicle)
                            <tr>
                                <td hidden>{{ $vehicle->id }}</td>
                                <td>{{ vehiclePrefix().$vehicle->vehicle_id }} </td>
                                <td>{{ $vehicle->name }} </td>
                                <td>{{ !empty($vehicle->types)?$vehicle->types->type:'-' }} </td>
                                <td>{{ $vehicle->model }} </td>
                                <td>{{ $vehicle->license_plate }} </td>
                                <td>{{ !empty($vehicle->registration_expiry_date)?dateFormat($vehicle->registration_expiry_date):'-' }} </td>
                                <td>{{ $vehicle->engine_type }} </td>
                                @if(Gate::check('edit vehicle') || Gate::check('delete vehicle') || Gate::check('show vehicle'))
                                    <td>
                                        <div class="cart-action">
                                            <form action="{{ route('vehicle.destroy', $vehicle->id) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            @can('show vehicle')
                                                <a class="text-warning customModal" data-size="lg"
                                                   data-bs-toggle="tooltip"
                                                   data-bs-original-title="{{__('Details')}}" href="#"
                                                   data-url="{{ route('vehicle.show',$vehicle->id) }}"
                                                   data-title="{{__('Vehicle Details')}}"> <i data-feather="eye"></i></a>
                                            @endcan
                                            @can('edit vehicle')
                                                <a class="text-success customModal" data-bs-toggle="tooltip"
                                                   data-bs-original-title="{{__('Edit')}}" href="#" data-size="lg"
                                                   data-url="{{ route('vehicle.edit',$vehicle->id) }}"
                                                   data-title="{{__('Edit Vehicle')}}"> <i data-feather="edit"></i></a>
                                            @endcan
                                            @can('delete vehicle')
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
    if ($.fn.DataTable.isDataTable('#vehicleTable')) {
        $('#vehicleTable').DataTable().destroy();
    }
    
    // Reinitialize
    $('#vehicleTable').DataTable({
        columnDefs: [
            // Your column definitions
        ],
        order: [[0, 'desc']]
    });
});
</script>
@endpush