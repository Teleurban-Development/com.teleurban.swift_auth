@extends('layouts.app')

@section('content')
    <div class="container">
        <h2>Asignar role a usuario</h2>

        <form method="POST" action="{{ route('swift-auth.user.role.assign') }}">
            @csrf

            <div class="mb-3">
                <label for="user_id" class="form-label">Usuario:</label>
                <select name="user_id" class="form-control" required>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                    @endforeach
                </select>
            </div>

            <div class="mb-3">
                <label for="role_id" class="form-label">Rol:</label>
                <select name="role_id" class="form-control" required>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}">{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="btn btn-primary">Asignar</button>
            <a href="{{ route('swift-auth.user.role.index') }}" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
@endsection
