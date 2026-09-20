@extends('layouts.app')

@section('title', 'Edycja nieruchomości')
@section('heading', $property->name)
@section('subheading', 'Edycja danych nieruchomości')

@section('content')
    <flux:card class="max-w-3xl">
        <form method="POST" action="{{ route('properties.update', $property) }}" class="space-y-6">
            @method('PUT')
            @include('properties.form')
        </form>
    </flux:card>
@endsection
