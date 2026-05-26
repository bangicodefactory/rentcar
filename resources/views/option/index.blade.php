@extends('layouts.app')
@section('page-title')
    {{__('Option')}}
@endsection
@section('breadcrumb')
    <ul class="breadcrumb mb-0">
        <li class="breadcrumb-item">
            <a href="{{route('dashboard')}}"><h1>{{__('Dashboard')}}</h1></a>
        </li>
        <li class="breadcrumb-item active">
            <a href="#">
                {{__('Option')}}
            </a>
        </li>
    </ul>
@endsection
@section('card-action-btn')
    @if(Gate::check('manage options'))
        <a class="btn btn-primary btn-sm ml-20 customModal" href="#" data-size="md"
           data-url="{{ route('option.create') }}"
           data-title="{{__('Create Option')}}"> <i
                class="ti-plus mr-5"></i>
            {{__('Create Option')}}
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
                            <th>{{__('Option')}}</th>
                            @if(Gate::check('edit options') || Gate::check('delete options'))
                                <th>{{__('Action')}}</th>
                            @endif
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($options as $option)
                            <tr>
                                <td>{{ $option->name }} </td>
                                @if(Gate::check('edit options') || Gate::check('delete options'))
                                    <td>
                                        <div class="cart-action">
                                            <form action="{{ route('option.destroy', $option->id) }}" method="POST">
                            @csrf
                            @method('DELETE')
                                            @can('edit options')
                                                <a class="text-success customModal" data-bs-toggle="tooltip"
                                                   data-bs-original-title="{{__('Edit')}}" href="#" data-size="md"
                                                   data-url="{{ route('option.edit',$option) }}"
                                                   data-title="{{__('Edit Option')}}"> <i data-feather="edit"></i></a>
                                            @endcan
                                            @can('delete options')
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
