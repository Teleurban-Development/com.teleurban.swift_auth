import { ReactNode } from "react";
import Authenticated from "../../Layouts/Authenticated";
import { PageProps } from "@/types";
import { Link, Head } from "@inertiajs/react";

interface User {
    id: number;
    name: string;
    email: string;
}

type Props = {
    users: User[];
}

const Index = ({ users }: PageProps<Props>) => {
    return (
        <>
            <Head title="Users" />

            <div className="max-w-4xl mx-auto mt-10 bg-white p-6 rounded-lg shadow-md">
                <div className="flex justify-between items-center mb-4">
                    <h2 className="text-2xl font-bold text-left mb-4">Usuarios</h2>

                    <Link href={route("swift-auth.user.create")} className="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                        Nuevo usuario
                    </Link>
                </div>
                <table className="w-full border-collapse border border-gray-200">
                    <thead>
                        <tr className="bg-gray-100">
                            <th className="border p-2">Nombre</th>
                            <th className="border p-2">Correo electrónico</th>
                            <th className="border p-2">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        {users.length > 0 ? (
                            users.map((user) => (
                                <tr
                                    key={user.id}
                                    className="text-center hover:bg-gray-50"
                                >
                                    <td className="border p-2">{user.name}</td>
                                    <td className="border p-2">{user.email}</td>
                                    <td className="border p-2">
                                        <div className="flex justify-center space-x-2">
                                        <a href={route("swift-auth.user.edit", user.id)}>
                                            <img src="/icons/edit.svg" className="h-8" alt="" />
                                        </a>
                                        <a href={route("swift-auth.user.destroy", user.id)} >
                                            <img src="/icons/destroy.svg" className="h-8 " alt="" />
                                        </a>

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
                                    No hay usuarios autenticados.
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
