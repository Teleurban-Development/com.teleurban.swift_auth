import { FormEvent, ReactNode } from "react";
import Authenticated from "../../../Layouts/Authenticated";
import { PageProps } from "@/types";
import { Link, Head } from "@inertiajs/react";
import { router } from "@inertiajs/react";

type Role = {
    id: number;
    name: string;
    description: string;
};

type Props = {
    roles: Role[];
};

const Index = ({ roles }: PageProps<Props>) => {
    const onDelete = (role: Role) => {
        const confirmDelete = window.confirm(
            `¿Estás seguro de que quieres eliminar el rol ${role.name}?`
        );
        if (confirmDelete) {
            router.delete(route("swift-auth.role.destroy", role.id));
        }
    };

    return (
        <>
            <Head title="Roles" />
            <div className="max-w-4xl mx-auto mt-10 bg-white p-6 rounded-lg shadow-md">
                <div className="flex justify-between items-center mb-4">
                    <h2 className="text-2xl font-bold text-left mb-4">Roles</h2>

                    <Link
                        href={route("swift-auth.role.create")}
                        className="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
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
                                                    "swift-auth.role.edit",
                                                    role.id
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
                                                    className="h-8 "
                                                />
                                            </button>

                                            <a
                                                href={route(
                                                    "swift-auth.role.destroy",
                                                    role.id
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

Index.layout = (page: ReactNode) => <Authenticated>{page}</Authenticated>;

export default Index;
