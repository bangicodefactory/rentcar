@extends('layouts.app')
@section('page-title')
    {{__('Inspection')}}
@endsection
@section('breadcrumb')
    <ul class="breadcrumb mb-0">
        <li class="breadcrumb-item">
            <a href="{{route('dashboard')}}"><h1>{{__('Dashboard')}}</h1></a>
        </li>
        <li class="breadcrumb-item active">
            <a href="#">
                {{__('Inspection')}}
            </a>
        </li>
    </ul>
@endsection
@section('card-action-btn')
    @if(Gate::check('manage vehicle'))
        <a class="btn btn-primary btn-sm ml-20" href="{{ route('inspection.create') }}"> <i
                class="ti-plus mr-5"></i>
            {{__('Create Inspection')}}
        </a>
    @endif
@endsection
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <table class="display dataTable cell-border datatbl-advance">
                        <thead>
                        <tr>
                            <th>{{__('Vehicle')}}</th>
                            <th>{{__('Inspection Date')}}</th>
                            <th>{{__('Inspection By')}}</th>
                            <th>{{__('Inspection Status')}}</th>
                            <th>{{__('Repair Status')}}</th>
                            @if(Gate::check('edit inspection') || Gate::check('delete inspection') || Gate::check('show inspection'))
                                <th>{{__('Action')}}</th>
                            @endif
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($inspections as $inspection)
                            <tr>
                                <td>{{ !empty($inspection->vehicles)?$inspection->vehicles->name:'-' }} </td>
                                <td>{{ dateFormat($inspection->inspection_date) }} </td>
                                <td>{{ $inspection->inspector }} </td>
                                <td>
                                    @if($inspection->status=='pending' || $inspection->status=='on_hold')
                                        <span class="badge badge-warning">{{\App\Models\Inspection::$status[$inspection->status]}}</span>
                                    @elseif($inspection->status=='completed' || $inspection->status=='conditional_pass')
                                        <span class="badge badge-success">{{\App\Models\Inspection::$status[$inspection->status]}}</span>
                                    @elseif($inspection->status=='in_progress')
                                        <span class="badge badge-primary">{{\App\Models\Inspection::$status[$inspection->status]}}</span>
                                    @elseif($inspection->status=='reject')
                                        <span class="badge badge-danger">{{\App\Models\Inspection::$status[$inspection->status]}}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($inspection->repair_status=='pending' || $inspection->repair_status=='on_hold')
                                        <span class="badge badge-warning">{{\App\Models\Inspection::$repairStatus[$inspection->repair_status]}}</span>
                                    @elseif($inspection->repair_status=='completed')
                                        <span class="badge badge-success">{{\App\Models\Inspection::$repairStatus[$inspection->repair_status]}}</span>
                                    @elseif($inspection->repair_status=='in_progress')
                                        <span class="badge badge-primary">{{\App\Models\Inspection::$repairStatus[$inspection->repair_status]}}</span>
                                    @elseif($inspection->repair_status=='needs_repair')
                                        <span class="badge badge-danger">{{\App\Models\Inspection::$repairStatus[$inspection->repair_status]}}</span>
                                    @endif

                                </td>
                                @if(Gate::check('edit inspection') || Gate::check('delete inspection') || Gate::check('show inspection'))
                                    <td>
                                        <div class="cart-action">
                                            <form action="{{ route('inspection.destroy', $inspection->id) }}" method="POST">
                            @csrf
                            @method('DELETE')
                                            @can('show inspection')
                                                <a class="text-warning"
                                                   data-bs-toggle="tooltip"
                                                   data-bs-original-title="{{__('Details')}}" href="{{ route('inspection.show',\Illuminate\Support\Facades\Crypt::encrypt($inspection->id)) }}"
                                                  > <i data-feather="eye"></i></a>
                                            @endcan
                                            @can('edit inspection')
                                                <a class="text-success" data-bs-toggle="tooltip"
                                                   data-bs-original-title="{{__('Edit')}}" href="{{ route('inspection.edit',\Illuminate\Support\Facades\Crypt::encrypt($inspection->id)) }}"> <i data-feather="edit"></i></a>
                                            @endcan
                                            @can('delete inspection')
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
