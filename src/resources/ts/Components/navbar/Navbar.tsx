import { Link } from '@inertiajs/react';
import { useState } from 'react';

export function Navbar({ user }: { user?: { name: string } }) {
    const [isOpen, setIsOpen] = useState(false);

    return (
        <nav className="bg-gray-900 text-white">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="flex items-center justify-between py-4">
                    <div className="flex space-x-6">
                        <Link href="/" className="hover:text-gray-300">
                            Inicio
                        </Link>
                        <Link
                            href={route('swift-auth.user.index')}
                            className="hover:text-gray-300"
                        >
                            Usuarios
                        </Link>
                        <Link
                            href={route('swift-auth.user.role.index')}
                            className="hover:text-gray-300"
                        >
                            Roles
                        </Link>
                    </div>

                    {/* Botón menú hamburguesa (móvil) */}
                    {/* <div className="sm:hidden">
                        <button onClick={() => setIsOpen(!isOpen)}>
                            {isOpen ? <X size={24} /> : <Menu size={24} />}
                        </button>
                    </div> */}

                    <div className="hidden space-x-4 sm:flex">
                        {user ? (
                            <>
                                <span className="px-4 py-2">{user.name}</span>
                                <form
                                    method="GET"
                                    action={route('swift-auth.logout')}
                                >
                                    <button
                                        type="submit"
                                        className="rounded bg-red-500 px-4 py-2 hover:bg-red-600"
                                    >
                                        Cerrar sesión
                                    </button>
                                </form>
                            </>
                        ) : (
                            <Link
                                href="/login"
                                className="rounded bg-blue-500 px-4 py-2 hover:bg-blue-600"
                            >
                                Iniciar sesión
                            </Link>
                        )}
                    </div>
                </div>
            </div>

            {isOpen && (
                <div className="bg-gray-800 py-2 sm:hidden">
                    <div className="flex flex-col items-center space-y-4">
                        <Link href="/" className="hover:text-gray-300">
                            Inicio
                        </Link>
                        <Link href="/users" className="hover:text-gray-300">
                            Usuarios
                        </Link>
                        <Link href="/roles" className="hover:text-gray-300">
                            Roles
                        </Link>

                        {user ? (
                            <>
                                <span className="py-2">{user.name}</span>
                                <form method="POST" action="/logout">
                                    <button
                                        type="submit"
                                        className="rounded bg-red-500 px-4 py-2 hover:bg-red-600"
                                    >
                                        Cerrar sesión
                                    </button>
                                </form>
                            </>
                        ) : (
                            <Link
                                href="/login"
                                className="rounded bg-blue-500 px-4 py-2 hover:bg-blue-600"
                            >
                                Iniciar sesión
                            </Link>
                        )}
                    </div>
                </div>
            )}
        </nav>
    );
}
