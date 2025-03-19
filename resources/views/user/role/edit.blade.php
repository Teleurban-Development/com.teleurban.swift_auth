@extends('layouts.app')

@section('content')
<div class="container">
    <h2>Editar rol</h2>

    <form action="{{ route('swift-auth.user.role.update', $role->id) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label for="name" class="form-label">Nombre:</label>
            <input type="text" name="name" class="form-control" value="{{ $role->name }}" required>
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">Descripción:</label>
            <textarea name="description" class="form-control">{{ $role->description }}</textarea>
        </div>

        <button type="submit" class="btn btn-success">Actualizar</button>
        <a href="{{ route('swift-auth.user.role.index') }}" class="btn btn-secondary">Cancelar</a>
    </form>
</div>
@endsection
