@extends('layouts.app')

@section('title', 'Nowy licznik')
@section('heading', 'Nowy licznik')

@section('content')
    <flux:card class="max-w-3xl">
        <form method="POST" action="{{ route('meters.store') }}" class="space-y-6">
            @include('meters.form')
        </form>
    </flux:card>
@endsection
