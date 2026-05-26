@extends('layouts.app')
@section('page-title')
    {{__('Role')}}
@endsection
@section('breadcrumb')
    <ul class="breadcrumb mb-0">
        <li class="breadcrumb-item">
            <a href="{{route('dashboard')}}"><h1>{{__('Dashboard')}}</h1></a>
        </li>
        <li class="breadcrumb-item">
            <a href="{{route('role.index')}}">{{__('Roles')}}</a>
        </li>
        <li class="breadcrumb-item active">
            <a href="#">{{__('Edit')}}</a>
        </li>
    </ul>
@endsection
@section('content')
    @php
        $systemModules=\App\Models\User::$systemModules;
    @endphp
    <div class="row">
        <div class="col-xl-12 col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4>{{__('Edit Role And Permissions')}}</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('role.update', $role->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="form-group">
                        <label for="title" class="form-label">{{ __('Role Title') }}</label>
                        <input type="text" name="title" id="title" class="form-control" placeholder="{{ __('Enter role title') }}" value="{{ old('title', $role->name) }}" {{ in_array($role->name, ['client', 'driver']) ? 'readonly' : '' }}>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-xl-12 col-md-12">
                                @foreach($systemModules as $module)
                                    <div class="row">
                                        @foreach($permissionList as $permission)
                                            @if (str_contains(strtolower($permission->name), strtolower($module)))
                                                <div class="form-check custom-chek form-check-inline col-md-2">
                                                    <input type="checkbox" name="user_permission[]" id="{{ $module.'_permission'.$permission->id }}" class="form-check-input" value="{{ $permission->id }}" {{ in_array($permission->id, $assignPermission) ? 'checked' : '' }}>
                                                    <label for="{{ $module.'_permission'.$permission->id }}" class="form-check-label">{{ ucfirst($permission->name) }}</label>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                    <hr>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="form-group mt-20 text-end">
                        <button type="submit" class="btn btn-primary btn-rounded">{{__('Update')}}</button>
                    </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
