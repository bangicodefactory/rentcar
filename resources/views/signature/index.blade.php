@extends('layouts.app')
@php
    $profile = asset(Storage::url('upload/profile/'));
@endphp
@section('page-title')
    {{ __('Driver') }}
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
                {{ __('Signature') }}
            </a>
        </li>
    </ul>
@endsection
@section('card-action-btn')
    @if (Gate::check('manage driver'))
        <a class="btn btn-primary btn-sm ml-20" href="{{ route('signature.create') }}"> <i class="ti-plus mr-5"></i>
            {{ __('Create Signature') }}
        </a>
    @endif
@endsection
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <table class="display dataTable cell-border datatbl-advance" id="signatureTable">
                        <thead>
                            <tr>
                                <th hidden>ID</th>
                                <th>{{ __('Client') }}</th>
                                <th>{{ __('Signature') }}</th>
                                <th>{{ __('Created At') }}</th>
                                <th>{{ __('Action') }}</th>
                                {{-- <th>{{__('Issue Date')}}</th>
                            <th>{{__('Expiration Date')}}</th> --}}
                                {{-- @if (Gate::check('manage driver') || Gate::check('create driver') || Gate::check('show driver'))
                                <th>{{__('Action')}}</th>
                            @endif --}}
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($signatures as $signature)
                                <tr>
                                    <td hidden>{{ $signature->id }}</td>
                                    <td>{{ $signature->user->name }}</td>
                                    <td>
                                        <img src="{{ Storage::url($signature->signature_path) }}" alt="Signature"
                                            style="max-height: 100px;">
                                    </td>
                                    <td>{{ $signature->created_at->format('Y-m-d H:i') }}</td>
                                    <td>
                                        <div class="cart-action">

                                            @if (Storage::disk('public')->exists($signature->signature_path))
                                                <a href="{{ asset('storage/' . $signature->signature_path) }}"
                                                    class="btn btn-sm btn-info" target="_blank">View Full Size</a>
                                            @endif
                                            @if (Gate::check('delete driver'))
                                                <form action="{{ route('signature.destroy', $signature->id) }}" method="POST">
                                                @csrf
                                                @method('DELETE')
                                                <a class=" text-danger confirm_dialog" data-bs-toggle="tooltip"
                                                    data-bs-original-title="{{ __('Detete') }}" href="#"> <i
                                                        data-feather="trash-2"></i></a>
                                                </form>
                                            @endif
                                            

                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center">No signatures found</td>
                                </tr>
                            @endforelse

                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
<script>
$(document).ready(function() {
    // Destroy existing DataTable if it exists
    if ($.fn.DataTable.isDataTable('#signatureTable')) {
        $('#signatureTable').DataTable().destroy();
    }
    
    // Reinitialize
    $('#signatureTable').DataTable({
        columnDefs: [
            // Your column definitions
        ],
        order: [[0, 'desc']]
    });
});
</script>
@endpush