import { Link } from "@inertiajs/react";
import { useState } from "react";
// import { Menu, X } from "lucide-react";

export function Navbar({ user }: { user?: { name: string } }) {
    const [isOpen, setIsOpen] = useState(false);

    return (
        <nav className="bg-gray-900 text-white">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="flex justify-between items-center py-4">
                    <div className="flex space-x-6">
                        <Link href="/" className="hover:text-gray-300">
                            Inicio
                        </Link>
                        <Link href="/users" className="hover:text-gray-300">
                            Usuarios
                        </Link>
                        <Link href="/roles" className="hover:text-gray-300">
                            Roles
                        </Link>
                    </div>

                    {/* Botón menú hamburguesa (móvil) */}
                    {/* <div className="sm:hidden">
                        <button onClick={() => setIsOpen(!isOpen)}>
                            {isOpen ? <X size={24} /> : <Menu size={24} />}
                        </button>
                    </div> */}

                    <div className="hidden sm:flex space-x-4">
                        {user ? (
                            <>
                                <span className="px-4 py-2">{user.name}</span>
                                <form method="POST" action="/logout">
                                    <button
                                        type="submit"
                                        className="bg-red-500 px-4 py-2 rounded hover:bg-red-600"
                                    >
                                        Cerrar sesión
                                    </button>
                                </form>
                            </>
                        ) : (
                            <Link
                                href="/login"
                                className="bg-blue-500 px-4 py-2 rounded hover:bg-blue-600"
                            >
                                Iniciar sesión
                            </Link>
                        )}
                    </div>
                </div>
            </div>

            {isOpen && (
                <div className="sm:hidden bg-gray-800 py-2">
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
                                        className="bg-red-500 px-4 py-2 rounded hover:bg-red-600"
                                    >
                                        Cerrar sesión
                                    </button>
                                </form>
                            </>
                        ) : (
                            <Link
                                href="/login"
                                className="bg-blue-500 px-4 py-2 rounded hover:bg-blue-600"
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
