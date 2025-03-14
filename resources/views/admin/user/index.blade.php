@extends('admin.layouts.auth')

@section('content')
<h2 class="text-center">Usuarios</h2>

<button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addUserModal">
    Crear
</button>

<table class="table table-bordered table-striped">
    <thead class="table-dark">
        <tr>
            <th>Nombre</th>
            <th>Correo electronico</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($users as $user)
            <tr>
                <td>{{ $user->name }}</td>
                <td>{{ $user->email }}</td>
                <td>
                    <a href="{{ route('admin.user.update', $user->id) }}" class="btn btn-warning btn-sm">Editar</a>
                    <form action="{{ route('admin.user.destroy', $user->id) }}" method="POST" class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('¿Estás seguro?')">
                            Eliminar
                        </button>
                    </form>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addUserModalLabel">Nuevo usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="{{ route('admin.user.store') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Correo electrónico</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contraseña</label>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-success w-100">Guardar</button>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection
