@extends('layouts.app')

@section('title', 'Nowa nieruchomość')
@section('heading', 'Nowa nieruchomość')

@section('content')
    <flux:card class="max-w-3xl">
        <form method="POST" action="{{ route('properties.store') }}" class="space-y-6">
            @include('properties.form')
        </form>
    </flux:card>
@endsection
