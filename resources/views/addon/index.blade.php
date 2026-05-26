@extends('layouts.app')
@section('page-title')
    {{__('Addon')}}
@endsection
@section('breadcrumb')
    <ul class="breadcrumb mb-0">
        <li class="breadcrumb-item">
            <a href="{{route('dashboard')}}"><h1>{{__('Dashboard')}}</h1></a>
        </li>
        <li class="breadcrumb-item active">
            <a href="#">
                {{__('Addon')}}
            </a>
        </li>
    </ul>
@endsection
@section('card-action-btn')
    @if(Gate::check('manage addon'))
        <a class="btn btn-primary btn-sm ml-20 customModal" href="#" data-size="md"
           data-url="{{ route('addon.create') }}"
           data-title="{{__('Create Addon')}}"> <i
                class="ti-plus mr-5"></i>
            {{__('Create Addon')}}
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
                            <th>{{__('Addon')}}</th>
                            <th>{{__('Price')}}</th>
                            <th>{{__('Billing Type')}}</th>
                            @if(Gate::check('edit addon') || Gate::check('delete addon'))
                                <th>{{__('Action')}}</th>
                            @endif
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($addons as $addon)
                            <tr>
                                <td>{{ $addon->name }} </td>
                                <td>{{ priceFormat($addon->price) }} </td>
                                <td>{{ $addon->billing_type }} </td>
                                @if(Gate::check('edit addon') || Gate::check('delete addon'))
                                    <td>
                                        <div class="cart-action">
                                            <form action="{{ route('addon.destroy', $addon->id) }}" method="POST">
                            @csrf
                            @method('DELETE')
                                            @can('edit addon')
                                                <a class="text-success customModal" data-bs-toggle="tooltip"
                                                   data-bs-original-title="{{__('Edit')}}" href="#" data-size="md"
                                                   data-url="{{ route('addon.edit',$addon) }}"
                                                   data-title="{{__('Edit Addon')}}"> <i data-feather="edit"></i></a>
                                            @endcan
                                            @can('delete addon')
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
