@extends('layouts.guest')

@section('content')
    <h2 class="text-center">Iniciar sesión</h2>

    <form method="POST" action="{{ route('swift-auth.login.submit') }}">
        @csrf
        <div class="mb-3">
            <label for="email" class="form-label">Correo electrónico:</label>
            <input type="email" class="form-control" name="email" required>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Contraseña:</label>
            <input type="password" class="form-control" name="password" required>
        </div>

        <button type="submit" class="btn btn-primary w-100">Enviar</button>

        <div class="text-center mt-3">
            <a href="{{ route('swift-auth.register') }}" class="text-decoration-none">¿No tienes cuenta? Regístrate</a>
            <br>
            <a href="{{ route('swift-auth.password.request') }}" class="text-decoration-none">¿Olvidaste tu contraseña?</a>
        </div>
    </form>
@endsection
