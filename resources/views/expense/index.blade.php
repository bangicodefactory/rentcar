@extends('layouts.app')
@section('page-title')
    {{__('Expense')}}
@endsection
@section('breadcrumb')
    <ul class="breadcrumb mb-0">
        <li class="breadcrumb-item">
            <a href="{{route('dashboard')}}"><h1>{{__('Dashboard')}}</h1></a>
        </li>
        <li class="breadcrumb-item active">
            <a href="#">
                {{__('Expense')}}
            </a>
        </li>
    </ul>
@endsection
@section('card-action-btn')
    @if(Gate::check('manage expense'))
        <a class="btn btn-primary btn-sm ml-20 customModal" href="#" data-size="lg"
           data-url="{{ route('expense.create') }}"
           data-title="{{__('Create Expense')}}"> <i
                class="ti-plus mr-5"></i>
            {{__('Create Expense')}}
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
                            <th>{{__('Date')}}</th>
                            <th>{{__('Title')}}</th>
                            <th>{{__('Type')}}</th>
                            <th>{{__('Vehicle')}}</th>
                            <th>{{__('Amount')}}</th>
                            <th>{{__('Notes')}}</th>
                            @if(Gate::check('edit expense') || Gate::check('delete expense'))
                                <th>{{__('Action')}}</th>
                            @endif
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($expenses as $expense)
                            <tr>
                                <td> {{ dateFormat($expense->date) }} </td>
                                <td>{{ $expense->title }} </td>
                                <td>{{ !empty($expense->types)?$expense->types->title:'-' }} </td>
                                <td>{{ !empty($expense->vehicles)?$expense->vehicles->name:'-' }} </td>
                                <td>{{ priceFormat($expense->amount) }} </td>
                                <td>
                                    {{$expense->notes}}
                                </td>
                                @if(Gate::check('edit expense') || Gate::check('delete expense') )
                                    <td>
                                        <div class="cart-action">
                                            <form action="{{ route('expense.destroy', $expense->id) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            @if(!empty($expense->receipt))
                                                <a  class="text-primary"  href="{{asset('/storage/upload/expense/'.$expense->receipt)}} "
                                                   target="_blank" data-bs-toggle="tooltip"
                                                    data-bs-original-title="{{__('Receipt')}}"> <i data-feather="file"></i> </a>
                                            @endif

                                            @can('edit expense')
                                                <a class="text-success customModal" data-bs-toggle="tooltip"
                                                   data-bs-original-title="{{__('Edit')}}" href="#" data-size="lg"
                                                   data-url="{{ route('expense.edit',$expense->id) }}"
                                                   data-title="{{__('Edit Expense')}}"> <i data-feather="edit"></i></a>
                                            @endcan
                                            @can('delete expense')
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
