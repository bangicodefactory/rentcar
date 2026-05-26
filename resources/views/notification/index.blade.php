@extends('layouts.app')

@section('page-title')
    {{ __('Email Notification Template') }}
@endsection
@section('breadcrumb')
    <ul class="breadcrumb mb-0">
        <li class="breadcrumb-item">
            <a href="{{ route('dashboard') }}">
                <h1>{{ __('Dashboard') }}</h1>
            </a>
        </li>
        <li class="breadcrumb-item active">
            <a href="#">
                {{ __('Email Notification Template') }}
            </a>
        </li>
    </ul>
@endsection


@push('script-page')
@endpush

@push('script-page')
    <script src="{{ asset('assets/js/vendors/ckeditor/ckeditor.js') }}"></script>
    <script>
        setTimeout(() => {
            feather.replace();
        }, 500);
    </script>
@endpush

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <table class="display dataTable cell-border datatbl-advance">
                        <thead>
                            <tr>
                                <th>{{ __('Module') }}</th>
                                <th>{{ __('Subject') }}</th>
                                <th>{{ __('Email Enable') }}</th>
                                @if (Gate::check('edit notification') || Gate::check('delete notification'))
                                    <th>{{ __('Action') }}</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($notifications as $item)
                                <tr>
                                    <td>{{ $item->name }} </td>
                                    <td>{{ $item->subject }}</td>
                                    <td>

                                        @if ($item->enabled_email == 1)
                                            <span class="d-inline badge badge-primary">{{ __('Enable') }}</span>
                                        @else
                                            <span class="d-inline badge badge-danger">{{ __('Disable') }}</span>
                                        @endif

                                    </td>
                                    @if (Gate::check('edit notification') || Gate::check('delete notification'))
                                        <td>
                                            <div class="cart-action">
                                                <form action="{{ route('notification.destroy', $item->id) }}" method="POST">
                                                @csrf
                                                @method('DELETE')
                                                @can('edit notification')
                                                    <a class="text-success customModal" data-bs-toggle="tooltip" data-size="lg"
                                                        data-bs-original-title="{{ __('Edit') }}" href="#"
                                                        data-url="{{ route('notification.edit', $item->id) }}"
                                                        data-title="{{ __('Edit Notification') }}"> <i
                                                            data-feather="edit"></i></a>
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
