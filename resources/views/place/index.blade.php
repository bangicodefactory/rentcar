@extends('layouts.app')
@section('page-title')
    {{__('Place')}}
@endsection
@section('breadcrumb')
    <ul class="breadcrumb mb-0">
        <li class="breadcrumb-item">
            <a href="{{route('dashboard')}}"><h1>{{__('Dashboard')}}</h1></a>
        </li>
        <li class="breadcrumb-item active">
            <a href="#">
                {{__('Place')}}
            </a>
        </li>
    </ul>
@endsection
@section('card-action-btn')
    @if(Gate::check('manage place'))
        <a class="btn btn-primary btn-sm ml-20 customModal" href="#" data-size="md"
           data-url="{{ route('place.create') }}"
           data-title="{{__('Create Place')}}"> <i
                class="ti-plus mr-5"></i>
            {{__('Create Place')}}
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
                            <th>{{__('Name')}}</th>
                            <th>{{__('City')}}</th>
                            <th>{{__('Island')}}</th>
                            <th>{{__('Price')}}</th>
                            <th>{{__('Depo name')}}</th>
                            <th>{{__('Depo address')}}</th>
                            @if(Gate::check('edit place') || Gate::check('delete place'))
                                <th>{{__('Action')}}</th>
                            @endif
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($places as $place)
                            <tr>
                                <td>{{ $place->name }} </td>
                                <td>{{ $place->city }} </td>
                                <td>{{ $place->island }} </td>
                                <td>{{ priceFormat($place->price) }} </td>
                                <td>{{ !empty($place->depo_name)?$place->depo_name:'-' }} </td>
                                <td>{{ !empty($place->depo_address)?$place->depo_address:'-' }} </td>
                                @if(Gate::check('edit place') || Gate::check('delete place'))
                                    <td>
                                        <div class="cart-action">
                                            <form action="{{ route('place.destroy', $place->id) }}" method="POST">
                            @csrf
                            @method('DELETE')
                                            @can('edit place')
                                                <a class="text-success customModal" data-bs-toggle="tooltip"
                                                   data-bs-original-title="{{__('Edit')}}" href="#" data-size="md"
                                                   data-url="{{ route('place.edit',$place) }}"
                                                   data-title="{{__('Edit Place')}}"> <i data-feather="edit"></i></a>
                                            @endcan
                                            @can('delete place')
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
