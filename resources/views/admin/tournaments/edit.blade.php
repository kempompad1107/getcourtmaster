@extends('layouts.app')
@section('title', 'Edit Tournament')

@section('content')

<x-page-header :title="'Edit: ' . $tournament->name" :back="route('admin.tournaments.show', $tournament)"/>

<div class="row justify-content-center">
    <div class="col-12 col-lg-10 col-xl-9">
        <form method="POST" action="{{ route('admin.tournaments.update', $tournament) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('admin.tournaments._form', ['submitLabel' => 'Save Changes'])
        </form>
    </div>
</div>

@endsection
