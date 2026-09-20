@extends('layouts.app')

@section('title', 'Nowy koszt utrzymania')
@section('heading', 'Nowy koszt utrzymania')

@section('content')
    <flux:card class="max-w-3xl">
        <form method="POST" action="{{ route('maintenance-costs.store') }}" class="space-y-6">
            @include('maintenance-costs.form')
        </form>
    </flux:card>
@endsection
