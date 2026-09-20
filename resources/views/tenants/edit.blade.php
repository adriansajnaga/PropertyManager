@extends('layouts.app')

@section('title', 'Edycja najemcy')
@section('heading', $tenant->name)
@section('subheading', 'Edycja danych najemcy')

@section('content')
    <flux:card class="max-w-3xl">
        <form method="POST" action="{{ route('tenants.update', $tenant) }}" class="space-y-6">
            @method('PUT')
            @include('tenants.form')
        </form>
    </flux:card>
@endsection
