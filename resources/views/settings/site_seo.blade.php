@extends('layouts.app')
@section('page-title')
    {{__('Site SEO Settings')}}
@endsection
@php
    $company_logo=getSettingsValByName('company_logo');
@endphp
@section('breadcrumb')
    <ul class="breadcrumb mb-0">
        <li class="breadcrumb-item">
            <a href="{{route('dashboard')}}"><h1>{{__('Dashboard')}}</h1></a>
        </li>
        <li class="breadcrumb-item active">
            <a href="#">{{__('Site SEO Settings')}}</a>
        </li>
    </ul>
@endsection
@section('content')
    <form action="{{ route('setting.site.seo') }}" method="POST" enctype="multipart/form-data">
    @csrf
    <div class="row">
        <div class="col-xl-4 col-lg-5">
            <div class="card">
                <div class="card-body">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label for="meta_seo_image" class="form-label">{{ __('Meta Image') }}</label>
                            <input type="file" name="meta_seo_image" id="meta_seo_image" class="form-control">
                        </div>
                    </div>
                    @if(!empty($settings['meta_seo_image']))
                        <div class="col-12 mt-20">
                            <img src="{{asset(Storage::url('upload/seo')).'/'.$settings['meta_seo_image']}}" class="setting-logo" alt="">
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-xl-8 col-lg-7">
            <div class="card">
                <div class="card-body">

                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="meta_seo_title" class="form-label">{{ __('Meta Title') }}</label>
                                <input type="text" name="meta_seo_title" id="meta_seo_title" class="form-control" placeholder="{{ __('Enter meta SEO title') }}" value="{{ old('meta_seo_title', $settings['meta_seo_title']) }}" required>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="meta_seo_keyword" class="form-label">{{ __('Meta Keyword') }}</label>
                                <input type="text" name="meta_seo_keyword" id="meta_seo_keyword" class="form-control" placeholder="{{ __('Enter meta SEO keyword') }}" value="{{ old('meta_seo_keyword', $settings['meta_seo_keyword']) }}" required>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="meta_seo_description" class="form-label">{{ __('Meta Description') }}</label>
                                <textarea name="meta_seo_description" id="meta_seo_description" class="form-control" placeholder="{{ __('Enter meta SEO description') }}" rows="2" required>{{ old('meta_seo_description', $settings['meta_seo_description']) }}</textarea>
                            </div>
                        </div>
                    </div>
                    <div class="text-right">
                        <button type="submit" class="btn btn-primary btn-rounded">{{__('Save')}}</button>
                    </div>

                </div>
            </div>
        </div>
    </div>
    </form>
@endsection
