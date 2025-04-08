import { PageProps } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ReactNode } from 'react';
import Authenticated from '../../../Layouts/Authenticated';

const Index = ({ roles }) => {
    const onDelete = (role) => {
        const confirmDelete = window.confirm(
            `¿Estás seguro de que quieres eliminar el rol ${role.name}?`,
        );
        if (confirmDelete) {
            router.delete(route('swift-auth.user.role.destroy', role.id));
        }
    };

    return (
        <>
            <Head title="Roles" />
            <div className="mx-auto mt-10 max-w-4xl rounded-lg bg-white p-6 shadow-md">
                <div className="mb-4 flex items-center justify-between">
                    <h2 className="mb-4 text-left text-2xl font-bold">Roles</h2>

                    <Link
                        href={route('swift-auth.user.role.create')}
                        className="rounded bg-gray-500 px-4 py-2 font-bold text-white hover:bg-gray-700"
                    >
                        Nuevo rol
                    </Link>
                </div>
                <table className="w-full border-collapse border border-gray-200">
                    <thead>
                        <tr className="bg-gray-100">
                            <th className="border p-2">Nombre</th>
                            <th className="border p-2">Descripción</th>
                            <th className="border p-2">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        {roles.length > 0 ? (
                            roles.map((role) => (
                                <tr
                                    key={role.id}
                                    className="text-center hover:bg-gray-50"
                                >
                                    <td className="border p-2">{role.name}</td>
                                    <td className="border p-2">
                                        {role.description}
                                    </td>
                                    <td className="border p-2">
                                        <div className="flex justify-center space-x-2">
                                            <a
                                                href={route(
                                                    'swift-auth.role.edit',
                                                    role.id,
                                                )}
                                            >
                                                <img
                                                    src="/icons/edit.svg"
                                                    className="h-8"
                                                    alt=""
                                                />
                                            </a>
                                            <button
                                                onClick={() => onDelete(role)}
                                                className="cursor-pointer"
                                            >
                                                <img
                                                    src="/icons/destroy.svg"
                                                    className="h-8"
                                                />
                                            </button>

                                            <a
                                                href={route(
                                                    'swift-auth.user.role.destroy',
                                                    role.id,
                                                )}
                                            ></a>
                                        </div>
                                    </td>
                                </tr>
                            ))
                        ) : (
                            <tr>
                                <td
                                    colSpan={2}
                                    className="border p-4 text-center text-gray-500"
                                >
                                    No hay roles registrados.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </>
    );
};

Index.layout = (page) => <Authenticated>{page}</Authenticated>;

export default Index;
