@extends('layouts.auth')

@section('title', 'Users')

@section('content')
    <h2 class="text-center">Usuarios</h2>

    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>Nombre</th>
                <th>Correo electronico</th>
                @if (auth()->user()->hasRole('root'))
                    <th>Acciones</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($users as $user)
                <tr>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    @if (auth()->user()->hasRole('root'))
                        <td>
                            <a href="{{ route('swift-auth.user.show', $user->id) }}" class="btn btn-warning btn-sm">Editar</a>
                            <form action="{{ route('swift-auth.user.destroy', $user->id) }}" method="POST" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm"
                                    onclick="return confirm('¿Estás seguro?')">
                                    Eliminar
                                </button>
                            </form>
                        </td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
