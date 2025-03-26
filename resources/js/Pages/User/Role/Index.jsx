import { Head } from "@inertiajs/react";
import { ReactNode } from "react";
import Authenticated from "../../../Layouts/Authenticated";

const Index = ({ roles }) => {
    return (
        <>
            <Head title="Roles" />

            <div className="max-w-4xl mx-auto mt-10 bg-white p-6 rounded-lg shadow-md">
                <h2 className="text-2xl font-bold text-center mb-4">Roles</h2>
                <table className="w-full border-collapse border border-gray-200">
                    <thead>
                        <tr className="bg-gray-100">
                            <th className="border p-2">Nombre</th>
                            <th className="border p-2">Descripción</th>
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
                                    <td className="border p-2">{role.description}</td>
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
