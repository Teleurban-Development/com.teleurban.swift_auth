<?php

namespace Teleurban\SwiftAuth\Console\Commands;

use Illuminate\Console\Command;

class InstallSwiftAuth extends Command
{
    protected $signature    = 'swift-auth:install';
    protected $description  = 'Instala SwiftAuth: configura, migra y publica archivos';

    public function handle()
    {
        $this->info('Iniciando instalación de SwiftAuth...');

        $this->call('vendor:publish', ['--provider' => 'Teleurban\SwiftAuth\Providers\SwiftAuthServiceProvider', '--tag' => 'swift-auth:config']);

        $choice = $this->choice(
            '¿Qué frontend deseas utilizar?',
            [
                'React + TypeScript',
                'React + JavaScript',
                'Blade',
            ],
            0
        );

        if ($choice === 'Blade') {
            $this->installBlade();
        } elseif ($choice === 'React + TypeScript') {
            $this->installTypeScript();
        } else {
            $this->installJavaScript();
        }

        $this->info('Importando migraciones...');

        $this->call('vendor:publish', ['--provider' => 'Teleurban\SwiftAuth\Providers\SwiftAuthServiceProvider', '--tag' => 'swift-auth:migrations']);
        $this->call('migrate');

        $this->info('Importando iconos...');
        $this->call('vendor:publish', ['--provider' => 'Teleurban\SwiftAuth\Providers\SwiftAuthServiceProvider', '--tag' => 'swift-auth:icons']);


        $this->info('Importando modelos...');
        $this->call('vendor:publish', ['--provider' => 'Teleurban\SwiftAuth\Providers\SwiftAuthServiceProvider', '--tag' => 'swift-auth:models']);

        $this->info('Instalación completada.');
    }

    protected function installBlade(): void
    {
        $this->info('Instalando vistas Blade...');
        $this->call('vendor:publish', ['--provider' => 'Teleurban\SwiftAuth\Providers\SwiftAuthServiceProvider', '--tag' => 'swift-auth:views']);
    }

    protected function installJavaScript(): void
    {
        $this->info('Instalando vistas en React con JavaScript...');
        $this->call('vendor:publish', ['--provider' => 'Teleurban\SwiftAuth\Providers\SwiftAuthServiceProvider', '--tag' => 'swift-auth:js-react']);

        $this->warn('Recuerda ejecutar: npm install && npm run dev');
    }

    protected function installTypeScript(): void
    {
        $this->info('Instalando vistas en React con TypeScript...');
        $this->call('vendor:publish', ['--provider' => 'Teleurban\SwiftAuth\Providers\SwiftAuthServiceProvider', '--tag' => 'swift-auth:ts-react']);

        $this->warn('Recuerda ejecutar: npm install && npm run dev');
    }
}
