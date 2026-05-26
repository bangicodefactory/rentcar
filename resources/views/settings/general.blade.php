@extends('layouts.app')
@section('page-title')
    {{__('General Settings')}}
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
            <a href="#">{{__('General Settings')}}</a>
        </li>
    </ul>
@endsection
@section('content')
    <div class="row">
        <div class="col-xl-12 col-lg-12">
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('setting.general') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="application_name" class="form-label">{{ __('Application Name') }}</label>
                                <input type="text" name="application_name" id="application_name" class="form-control" placeholder="{{ __('Enter your application name') }}" value="{{ old('application_name', !empty($settings['app_name']) ? $settings['app_name'] : env('APP_NAME')) }}" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="logo" class="form-label">{{ __('Logo') }}</label>
                                <input type="file" name="logo" id="logo" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="favicon" class="form-label">{{ __('Favicon') }}</label>
                                <input type="file" name="favicon" id="favicon" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="image_home_1" class="form-label">{{ __('Première image de page d acceuil') }}</label>
                                <input type="file" name="image_home_1" id="image_home_1" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="image_home_2" class="form-label">{{ __('Deuxième image de page d acceuil') }}</label>
                                <input type="file" name="image_home_2" id="image_home_2" class="form-control">
                            </div>
                        </div>

                        @if(\Auth::user()->type=='super admin')
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="landing_logo" class="form-label">{{ __('Landing Page Logo') }}</label>
                                    <input type="file" name="landing_logo" id="landing_logo" class="form-control">
                                </div>
                            </div>
                        @endif

                    </div>
                    <div class="text-right">
                        <button type="submit" class="btn btn-primary btn-rounded">{{__('Save')}}</button>
                    </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- Signature Section -->
    <div class="row mt-4">
        <div class="col-xl-12 col-lg-12">
            <div class="card">
                <div class="card-body">
                    <h4>{{ __('Admin Signature') }}</h4>

                    @if(!empty($settings['admin_signature']))
                        <div class="mb-4">
                            <p>{{ __('Current Signature:') }}</p>
                            <img src="{{ asset('storage/' . $settings['admin_signature']) }}"
                                 alt="Signature"
                                 style="max-height: 100px;"
                                 onerror="this.onerror=null; this.src=''; this.alt='Signature not found - Path: {{ $settings['admin_signature'] }}'; this.style.border='1px dashed red'; this.style.padding='10px';">
                            <small class="text-muted d-block mt-1">Path: {{ $settings['admin_signature'] }}</small>
                        </div>
                    @endif

                    <div class="signature-container">
                        <div class="border rounded p-3 bg-white">
                            <canvas id="signatureCanvas" style="border: 1px solid #dee2e6; width: 100%; height: 200px;"></canvas>
                        </div>

                        <div class="mt-3">
                            <button type="button" id="clearButton" class="btn btn-danger">
                                {{ __('Clear Signature') }}
                            </button>
                            <button type="button" id="saveButton" class="btn btn-success">
                                {{ __('Save Signature') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('signatureCanvas');
    const ctx = canvas.getContext('2d');
    let drawing = false;
    let lastX = 0;
    let lastY = 0;

    function resizeCanvas() {
        const container = canvas.parentElement;
        canvas.width = container.clientWidth;
        canvas.height = 200;
    }

    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    ctx.strokeStyle = '#000';
    ctx.lineWidth = 2;
    ctx.lineJoin = 'round';
    ctx.lineCap = 'round';

    function getPosition(e) {
        const rect = canvas.getBoundingClientRect();
        const clientX = e.clientX || (e.touches ? e.touches[0].clientX : 0);
        const clientY = e.clientY || (e.touches ? e.touches[0].clientY : 0);

        return {
            x: clientX - rect.left,
            y: clientY - rect.top
        };
    }

    function startDrawing(e) {
        drawing = true;
        const pos = getPosition(e);
        [lastX, lastY] = [pos.x, pos.y];
    }

    function draw(e) {
        if (!drawing) return;

        const pos = getPosition(e);

        ctx.beginPath();
        ctx.moveTo(lastX, lastY);
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();

        [lastX, lastY] = [pos.x, pos.y];
    }

    function stopDrawing() {
        drawing = false;
    }

    canvas.addEventListener('mousedown', startDrawing);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDrawing);
    canvas.addEventListener('mouseout', stopDrawing);

    canvas.addEventListener('touchstart', function(e) {
        e.preventDefault();
        startDrawing(e.touches[0]);
    });

    canvas.addEventListener('touchmove', function(e) {
        e.preventDefault();
        draw(e.touches[0]);
    });

    canvas.addEventListener('touchend', stopDrawing);

    document.getElementById('clearButton').addEventListener('click', function() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
    });

    document.getElementById('saveButton').addEventListener('click', function() {
        canvas.toBlob(function(blob) {
            if (!blob) {
                alert('Please draw a signature first');
                return;
            }

            const formData = new FormData();
            formData.append('signature', blob, 'signature.png');
            formData.append('_token', '{{ csrf_token() }}');

            fetch("{{ route('AdminSignature.store') }}", {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
        }, 'image/png');
    });
});
</script>
@endpush
