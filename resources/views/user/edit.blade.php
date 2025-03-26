@extends('layouts.app')

@section('content')
<div class="container">
    <h2>Editar rol</h2>

    <form action="{{ route('swift-auth.user.update', $role->id) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label for="name" class="form-label">Nombre:</label>
            <input type="text" name="name" class="form-control" value="{{ $role->name }}" required>
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Correo electronico:</label>
            <input type="email" name="email" class="form-control" value="{{ $role->email }}" required>
        </div>

        <button type="submit" class="btn btn-success">Actualizar</button>
        <a href="{{ route('swift-auth.user.index') }}" class="btn btn-secondary">Cancelar</a>
    </form>
</div>
@endsection
