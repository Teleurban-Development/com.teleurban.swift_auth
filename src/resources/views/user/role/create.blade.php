@extends('layouts.app')

@section('content')
    <h2>Crear rol</h2>

    <form action="{{ route('swift-auth.user.role.store') }}" method="POST">
        @csrf
        <div class="mb-3">
            <label for="name" class="form-label">Nombre:</label>
            <input type="text" name="name" class="form-control" required>
        </div>
        
        <div class="mb-3">
            <label for="description" class="form-label">Descripción:</label>
            <textarea name="description" class="form-control"></textarea>
        </div>

        <button type="submit" class="btn btn-success">Crear</button>
        <a href="{{ route('swift-auth.user.role.index') }}" class="btn btn-secondary">Cancelar</a>
    </form>
@endsection
