@extends('layouts.app')

@section('title', 'Edycja kosztu utrzymania')
@section('heading', 'Edycja kosztu utrzymania')
@section('subheading', $cost->description)

@section('content')
    <flux:card class="max-w-3xl">
        <form method="POST" action="{{ route('maintenance-costs.update', $cost) }}" class="space-y-6">
            @method('PUT')
            @include('maintenance-costs.form')
        </form>
    </flux:card>
@endsection
