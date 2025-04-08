@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="mb-4">Roles</h2>

    <a href="{{ route('swift-auth.user.role.store') }}" class="btn btn-primary mb-3">Crear rol</a>
    <a href="{{ route('swift-auth.user.role.assignForm') }}" class="btn btn-info mb-3">Asignar rol</a>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Descripción</th>
                @if(auth()->user()->hasRole('root'))
                    <th>Acciones</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($roles as $role)
                <tr>
                    <td>{{ $role->name }}</td>
                    <td>{{ $role->description }}</td>
                    @if(auth()->user()->hasRole('root'))
                        <td>
                            <a href="{{ route('swift-auth.user.role.show', $role->id) }}" class="btn btn-warning btn-sm">Editar</a>

                            <form action="{{ route('swift-auth.user.role.destroy', $role->id) }}" method="POST" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('¿Estas seguro?')">Eliminar</button>
                            </form>
                        </td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
