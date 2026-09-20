@extends('layouts.app')

@section('title', 'Nowy najemca')
@section('heading', 'Nowy najemca')

@section('content')
    <flux:card class="max-w-3xl">
        <form method="POST" action="{{ route('tenants.store') }}" class="space-y-6">
            @include('tenants.form')
        </form>
    </flux:card>
@endsection
