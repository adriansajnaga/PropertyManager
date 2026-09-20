@extends('layouts.app')

@section('title', 'Edycja lokalu')
@section('heading', $unit->description)
@section('subheading', 'Edycja lokalu, najemcy i liczników')

@section('content')
    <flux:card class="max-w-4xl">
        <form method="POST" action="{{ route('units.update', $unit) }}" class="space-y-6">
            @method('PUT')
            @include('units.form')
        </form>
    </flux:card>
@endsection
