@extends('layouts.app')

@section('title', 'Edycja licznika')
@section('heading', $meter->name)
@section('subheading', 'Edycja licznika')

@section('content')
    <flux:card class="max-w-3xl">
        <form method="POST" action="{{ route('meters.update', $meter) }}" class="space-y-6">
            @method('PUT')
            @include('meters.form')
        </form>
    </flux:card>
@endsection
