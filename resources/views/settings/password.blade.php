@extends('layouts.app')
@section('page-title')
    {{__('Password Settings')}}
@endsection
@section('breadcrumb')
    <ul class="breadcrumb mb-0">
        <li class="breadcrumb-item">
            <a href="{{route('dashboard')}}"><h1>{{__('Dashboard')}}</h1></a>
        </li>
        <li class="breadcrumb-item active">
            <a href="#">{{__('Password Settings')}}</a>
        </li>
    </ul>
@endsection
@section('content')
    <div class="row">
        <div class="col-xl-12 cdx-xxl-100 cdx-xl-12">
            <div class="card">
                <div class="card-body">
                    <div class="info-group">
                        <form action="{{ route('setting.password') }}" method="POST">
                        @csrf
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="current_password" class="form-label">{{ __('Current Password') }}</label>
                                    <input type="password" name="current_password" id="current_password" class="form-control" placeholder="{{ __('Enter your current password') }}" required>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="new_password" class="form-label">{{ __('New Password') }}</label>
                                    <input type="password" name="new_password" id="new_password" class="form-control" placeholder="{{ __('Enter your new password') }}" required>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="confirm_password" class="form-label">{{ __('Confirm New Password') }}</label>
                                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="{{ __('Enter your confirm new password') }}" required>
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <button type="submit" class="btn btn-primary btn-rounded">{{__('Save')}}</button>
                        </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
