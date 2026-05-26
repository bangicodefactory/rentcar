@extends('layouts.app')
@section('page-title')
    {{__('SMTP Settings')}}
@endsection
@php
$settings=settings();
@endphp
@section('breadcrumb')
    <ul class="breadcrumb mb-0">
        <li class="breadcrumb-item">
            <a href="{{route('dashboard')}}"><h1>{{__('Dashboard')}}</h1></a>
        </li>
        <li class="breadcrumb-item active">
            <a href="#">{{__('SMTP Settings')}}</a>
        </li>
    </ul>
@endsection
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('setting.smtp') }}" method="POST">
                    @csrf
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label for="sender_name" class="form-label">{{ __('Sender Name') }}</label>
                            <input type="text" name="sender_name" id="sender_name" class="form-control" placeholder="{{ __('Enter sender name') }}" value="{{ old('sender_name', $settings['FROM_NAME']) }}">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="sender_email" class="form-label">{{ __('Sender Email') }}</label>
                            <input type="text" name="sender_email" id="sender_email" class="form-control" placeholder="{{ __('Enter sender email') }}" value="{{ old('sender_email', $settings['FROM_EMAIL']) }}">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="server_driver" class="form-label">{{ __('SMTP Driver') }}</label>
                            <input type="text" name="server_driver" id="server_driver" class="form-control" placeholder="{{ __('Enter smtp driver') }}" value="{{ old('server_driver', $settings['SERVER_DRIVER']) }}">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="server_host" class="form-label">{{ __('SMTP Host') }}</label>
                            <input type="text" name="server_host" id="server_host" class="form-control" placeholder="{{ __('Enter smtp host') }}" value="{{ old('server_host', $settings['SERVER_HOST']) }}">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="server_username" class="form-label">{{ __('SMTP Username') }}</label>
                            <input type="text" name="server_username" id="server_username" class="form-control" placeholder="{{ __('Enter smtp username') }}" value="{{ old('server_username', $settings['SERVER_USERNAME']) }}">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="server_password" class="form-label">{{ __('SMTP Password') }}</label>
                            <input type="text" name="server_password" id="server_password" class="form-control" placeholder="{{ __('Enter smtp password') }}" value="{{ old('server_password', $settings['SERVER_PASSWORD']) }}">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="server_encryption" class="form-label">{{ __('SMTP Encryption') }}</label>
                            <input type="text" name="server_encryption" id="server_encryption" class="form-control" placeholder="{{ __('Enter smtp encryption') }}" value="{{ old('server_encryption', $settings['SERVER_ENCRYPTION']) }}">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="server_port" class="form-label">{{ __('SMTP Port') }}</label>
                            <input type="text" name="server_port" id="server_port" class="form-control" placeholder="{{ __('Enter smtp port') }}" value="{{ old('server_port', $settings['SERVER_PORT']) }}">
                        </div>
                    </div>
                    <div class="text-right">
                        <button type="submit" class="btn btn-primary btn-rounded">{{__('Save')}}</button>
                        <a href="#" data-size="md" data-url="{{ route('setting.smtp.test') }}" data-title="{{__('Add Email')}}"  class='btn btn-primary btn-rounded customModal'> {{ __('Test Mail') }} </a>
                    </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
