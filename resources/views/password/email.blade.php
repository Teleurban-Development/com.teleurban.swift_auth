@extends('layouts.guest')

@section('title', 'Recover password')

@section('content')
    <h2 class="text-center">Recuperar contraseña</h2>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form action="{{ route('swift-auth.password.email') }}" method="POST">
        @csrf
        <div class="mb-3">
            <label for="email" class="form-label">Correo electrónico</label>
            <input type="email" name="email" class="form-control" required>
        </div>

        <button type="submit" class="btn btn-primary w-100">Enviar</button>
    </form>
@endsection
