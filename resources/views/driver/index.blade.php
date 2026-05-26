@extends('layouts.app')
@php
    $profile=asset(Storage::url('upload/profile/'));
@endphp
@section('page-title')
    {{__('Driver')}}

@endsection
@section('breadcrumb')
    <ul class="breadcrumb mb-0">
        <li class="breadcrumb-item">
            <a href="{{route('dashboard')}}"><h1>{{__('Dashboard')}}</h1></a>
        </li>
        <li class="breadcrumb-item active">
            <a href="#">
                {{__('Driver')}}
            </a>
        </li>
    </ul>
@endsection
@section('card-action-btn')
    @if(Gate::check('manage driver'))
        <a class="btn btn-primary btn-sm ml-20 customModal" href="#" data-size="lg"
           data-url="{{ route('driver.create') }}"
           data-title="{{__('Create Driver')}}"> <i
                class="ti-plus mr-5"></i>
            {{__('Create Driver')}}
        </a>
    @endif
@endsection
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <table class="display dataTable cell-border datatbl-advance" id="bookingTable_driver">
                        <thead>
                        <tr>
                            <th hidden>id</th>
                            <th data-sort="desc">{{__('ID')}}</th>
                            <th>{{__('Driver')}}</th>
                            <th>{{__('Email')}}</th>
                            <th>{{__('Phone Number')}}</th>
                            <th>{{__('License Number')}}</th>
                            <th>{{__('Issue Date')}}</th>
                            <th>{{__('Expiration Date')}}</th>
                            @if(Gate::check('manage driver') || Gate::check('create driver') || Gate::check('show driver'))
                                <th>{{__('Action')}}</th>
                            @endif
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($drivers as $driver)
                            <tr>
                                <td hidden>{{ $driver->id }}</td>
                                <td>{{ !empty($driver->drivers)?driverPrefix().$driver->drivers->driver_id:'-' }} </td>
                                <td class="table-user">
                                    <img
                                        src="{{!empty($driver->avatar)?asset(Storage::url('upload/profile')).'/'.$driver->avatar:asset(Storage::url('upload/profile')).'/avatar.png'}}"
                                        alt="" class="mr-2 avatar-sm rounded-circle user-avatar">
                                    <a href="#" class="text-body font-weight-semibold">{{ $driver->name }}</a>
                                </td>
                                <td>{{ !empty($driver->email)?$driver->email:'-' }}</td>
                                <td>{{ !empty($driver->phone_number)?$driver->phone_number:'-' }} </td>
                                <td>{{ !empty($driver->drivers) && !empty($driver->drivers->license_number)?$driver->drivers->license_number:'-' }}  </td>
                                <td>{{ !empty($driver->drivers) && !empty($driver->drivers->issue_date)?dateFormat($driver->drivers->issue_date):'-' }}  </td>
                                <td>{{ !empty($driver->drivers) && !empty($driver->drivers->expiration_date)?dateFormat($driver->drivers->expiration_date):'-' }}  </td>
                                @if(Gate::check('edit driver') || Gate::check('delete driver') || Gate::check('show driver'))
                                    <td>
                                        <div class="cart-action">
                                            <form action="{{ route('driver.destroy', $driver->id) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            @can('show driver')
                                                <a class="text-warning customModal" data-size="lg"
                                                   data-bs-toggle="tooltip"
                                                   data-bs-original-title="{{__('Show')}}" href="#"
                                                   data-url="{{ route('driver.show',$driver->id) }}"
                                                   data-title="{{__('Details')}}"> <i data-feather="eye"></i></a>
                                            @endcan
                                            @can('edit driver')
                                                <a class="text-success customModal" data-bs-toggle="tooltip"
                                                   data-bs-original-title="{{__('Edit')}}" href="#" data-size="lg"
                                                   data-url="{{ route('driver.edit',$driver->id) }}"
                                                   data-title="{{__('Edit Driver')}}"> <i data-feather="edit"></i></a>
                                            @endcan
                                            @can('delete driver')
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
    if ($.fn.DataTable.isDataTable('#bookingTable_driver')) {
        $('#bookingTable_driver').DataTable().destroy();
    }
    
    // Reinitialize
    $('#bookingTable_driver').DataTable({
        columnDefs: [
            // Your column definitions
        ],
        order: [[0, 'desc']]
    });
});
</script>
@endpush