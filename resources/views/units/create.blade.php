@extends('layouts.app')

@section('title', 'Nowy lokal')
@section('heading', 'Nowy lokal')

@section('content')
    <flux:card class="max-w-4xl">
        <form method="POST" action="{{ route('units.store') }}" class="space-y-6">
            @include('units.form')
        </form>
    </flux:card>
@endsection
