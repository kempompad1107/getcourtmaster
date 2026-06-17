@extends('layouts.app')
@section('title', 'Edit ' . $branch->name)

@section('content')

<x-page-header :title="'Edit: ' . $branch->name" :back="route('admin.branches.index')"/>

<div class="row justify-content-center">
<div class="col-12 col-lg-9 col-xl-8">

<form method="POST" action="{{ route('admin.branches.update', $branch) }}">
    @csrf @method('PUT')
    @include('admin.branches._form', ['submitLabel' => 'Update Branch'])
</form>

</div>
</div>

@endsection
