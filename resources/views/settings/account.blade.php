@extends('layouts.app')
@section('page-title')
    {{__('Account Settings')}}
@endsection
@section('breadcrumb')
    <ul class="breadcrumb mb-0">
        <li class="breadcrumb-item">
            <a href="{{route('dashboard')}}"><h1>{{__('Dashboard')}}</h1></a>
        </li>
        <li class="breadcrumb-item active">
            <a href="#">{{__('Account Settings')}}</a>
        </li>
    </ul>
@endsection
@section('content')
    <div class="row">
        <div class="col-xl-12 cdx-xxl-100 cdx-xl-12">
            <div class="card">
                <div class="card-body">
                    <div class="info-group">
                        <form action="{{ route('setting.account') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name" class="form-label">{{ __('Name') }}</label>
                                    <input type="text" name="name" id="name" class="form-control" placeholder="{{ __('Enter your name') }}" value="{{ old('name', $loginUser->name) }}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email" class="form-label">{{ __('Email Address') }}</label>
                                    <input type="text" name="email" id="email" class="form-control" placeholder="{{ __('Enter your email') }}" value="{{ old('email', $loginUser->email) }}" required>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="profile" class="form-label">{{ __('Profile') }}</label>
                                    <input type="file" name="profile" id="profile" class="form-control">
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
