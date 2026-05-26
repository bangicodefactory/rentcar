@extends('layouts.app')
@section('page-title')
    {{__('Reminder Type')}}
@endsection
@section('breadcrumb')
    <ul class="breadcrumb mb-0">
        <li class="breadcrumb-item">
            <a href="{{route('dashboard')}}"><h1>{{__('Dashboard')}}</h1></a>
        </li>
        <li class="breadcrumb-item active">
            <a href="#">
                {{__('Reminder Type')}}
            </a>
        </li>
    </ul>
@endsection
@section('card-action-btn')
    @if(Gate::check('manage reminder'))
        <a class="btn btn-primary btn-sm ml-20 customModal" href="#" data-size="md"
           data-url="{{ route('reminder-type.create') }}"
           data-title="{{__('Create Type')}}"> <i
                class="ti-plus mr-5"></i>
            {{__('Create Reminder Type')}}
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
                            <th>{{__('Type')}}</th>
                            @if(Gate::check('edit reminder') || Gate::check('delete reminder'))
                                <th>{{__('Action')}}</th>
                            @endif
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($types as $type)
                            <tr>
                                <td>{{ $type->type }} </td>
                                @if(Gate::check('edit reminder') || Gate::check('delete reminder'))
                                    <td>
                                        <div class="cart-action">
                                            <form action="{{ route('reminder-type.destroy', $type->id) }}" method="POST">
                            @csrf
                            @method('DELETE')
                                            @can('edit reminder')
                                                <a class="text-success customModal" data-bs-toggle="tooltip"
                                                   data-bs-original-title="{{__('Edit')}}" href="#" data-size="md"
                                                   data-url="{{ route('reminder-type.edit',$type) }}"
                                                   data-title="{{__('Edit CLient')}}"> <i data-feather="edit"></i></a>
                                            @endcan
                                            @can('delete reminder')
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
